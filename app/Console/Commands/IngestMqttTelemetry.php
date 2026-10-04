<?php

namespace App\Console\Commands;

use App\Models\TelemetryChargingSession;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use App\Services\ChargeGapNotifier;
use App\Services\ChargeThresholdNotifier;
use App\Services\ChargingCurveRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Consomme ce que le boitier OBD publie sur MQTT, via un fichier tampon.
 *
 * Pourquoi un fichier et non un client MQTT en PHP : `vendor/` n'est pas monte
 * en bind dans le conteneur, une dependance Composer imposerait donc de
 * reconstruire l'image — l'operation qui a coupe le site le 2026-08-30. Un
 * conteneur mosquitto_sub ecrit les messages dans storage/app/system, le meme
 * canal hote/conteneur que le releve serveur, et cette commande les relit.
 *
 * La lecture se fait a partir d'un decalage en octets, conserve d'un passage a
 * l'autre : le producteur ecrit en continu, le consommateur ne relit jamais ce
 * qu'il a deja traite et ne tronque rien sous les pieds de l'ecrivain.
 */
class IngestMqttTelemetry extends Command
{
    protected $signature = 'telemetry:ingest-mqtt
        {--reset : Repartir du debut du fichier}
        {--dry-run : Analyser sans rien ecrire}';

    protected $description = 'Ingere la telemetrie et les recharges publiees par le boitier OBD sur MQTT';

    /**
     * Chemins construits avec storage_path() et non le disque « local » :
     * depuis Laravel 11 celui-ci pointe sur storage/app/private, alors que le
     * canal partage avec l'hote est storage/app/system — c'est deja ainsi que
     * le releve serveur y accede.
     */
    private const INBOX = 'app/system/mqtt-inbox.jsonl';

    private const STATE = 'app/system/mqtt-inbox-state.json';

    /**
     * Duree pendant laquelle une valeur absente du message courant reste
     * reputee valable.
     *
     * XPCarData interroge les PID par rotation : un message ne porte que ce qui
     * vient d'etre rafraichi, et sur douze messages l'odometre n'apparaissait
     * qu'une fois. Sans report, chaque ligne serait a moitie vide ; avec un
     * report illimite, un odometre fige ferait croire a une voiture a l'arret
     * — exactement le bug que VehicleState vient de corriger. D'ou une fenetre
     * bornee, un peu plus large que le cycle lent de l'application (~1 min).
     */
    private const CARRY_SECONDS = 300;

    /**
     * Pas d'ecriture en roulage.
     *
     * Le broker est a la maison : plus aucun quota ne bride la collecte, seule
     * compte la taille de la table. A ce rythme un trajet d'une heure coute
     * 240 lignes, ce qui reste sans commune mesure avec ce qu'apporte la
     * finesse sur un trajet ou une charge.
     */
    private const DRIVING_ROW_INTERVAL = 15;

    /**
     * En charge, la puissance evolue vite et c'est precisement ce qu'on
     * regarde : le pas suit celui du rechargement de la page.
     */
    private const CHARGING_ROW_INTERVAL = 5;

    /**
     * Pas d'ecriture a l'arret. Une voiture immobile ne raconte rien de plus
     * en quinze secondes qu'en une minute, et la table servira des annees.
     */
    private const IDLE_ROW_INTERVAL = 60;

    /** Vitesse a partir de laquelle on considere que la voiture roule, en km/h. */
    private const MOVING_KMH = 3.0;

    /**
     * Taille au-dela de laquelle le tampon est vide, une fois entierement lu.
     * A dix a vingt messages la minute, il grossit de plusieurs megaoctets par
     * jour et personne ne le relira jamais.
     */
    private const TRUNCATE_BYTES = 33554432;

    /** Correspondance directe entre champs XPCarData et colonnes. */
    /**
     * Marge acceptee entre l'energie annoncee par le compteur du BMS et celle
     * que le gain de niveau permet.
     *
     * Le facteur est genereux — le compteur voit ce que le calcul par SoC
     * ignore, et un petit gain de niveau se mesure mal — mais il ecarte les
     * lectures franchement fausses : une session a 17 124 kWh pour 1,2 point de
     * batterie a ete observee, le PID `cumulativeCharge` ayant renvoye une
     * valeur absurde.
     */
    private const ENERGY_PLAUSIBILITY_FACTOR = 2.0;

    /** Tolerance absolue, pour les gains de niveau trop faibles pour borner. */
    private const ENERGY_PLAUSIBILITY_SLACK = 3.0;

    private ?ChargingCurveRepository $curves = null;

    private const FIELDS = [
        'stateOfCharge' => 'soc',
        'stateOfHealth' => 'soh',
        'batteryTemperature' => 'batt_temp',
        'speed' => 'speed',
        'odometer' => 'odometer',
        'latitude' => 'lat',
        'longitude' => 'lon',
    ];

    public function handle(ChargeThresholdNotifier $notifier, ChargingCurveRepository $curves, ChargeGapNotifier $gapNotifier): int
    {
        $this->curves = $curves;

        $path = storage_path(self::INBOX);

        if (! is_readable($path)) {
            $this->warn('Aucun tampon MQTT : le conteneur mqtt-ingest tourne-t-il ?');

            return self::SUCCESS;
        }

        $state = $this->option('reset') ? [] : $this->readState();
        clearstatcache(true, $path);
        $size = filesize($path);
        $offset = (int) ($state['offset'] ?? 0);

        // Fichier reduit : il a ete tourne ou vide, tout relire depuis le debut
        // vaut mieux que de lire au milieu d'une ligne.
        if ($size < $offset) {
            $this->line('Tampon reinitialise : relecture depuis le debut.');
            $offset = 0;
        }

        if ($size === $offset) {
            $this->info('Rien de nouveau.');

            return self::SUCCESS;
        }

        [$lines, $offset] = $this->readFrom($path, $offset, $size);

        $vehicles = $this->vehiclesByClientId();
        $counts = ['data' => 0, 'charging' => 0, 'ignores' => 0];
        $carried = $state['vehicles'] ?? [];

        foreach ($lines as $line) {
            [$topic, $payload] = $this->split($line);

            if ($payload === null) {
                $counts['ignores']++;

                continue;
            }

            $parts = explode('/', $topic);
            $clientId = $parts[1] ?? null;
            $kind = $parts[2] ?? null;
            $vehicle = $vehicles[$clientId] ?? null;

            if ($vehicle === null) {
                $counts['ignores']++;

                continue;
            }

            if ($kind === 'data') {
                $carried[$clientId] = $this->handleData($vehicle, $payload, $carried[$clientId] ?? [], $counts, $notifier);
            } elseif ($kind === 'charging') {
                $this->handleCharging($vehicle, $payload, $counts, $gapNotifier);
            }
        }

        if (! $this->option('dry-run')) {
            $offset = $this->truncateIfConsumed($path, $offset);
            $this->writeState(['offset' => $offset, 'vehicles' => $carried]);
        }

        $this->info(sprintf(
            '%d releve(s) ecrit(s), %d recharge(s) mesuree(s), %d message(s) ignore(s).',
            $counts['data'],
            $counts['charging'],
            $counts['ignores']
        ));

        return self::SUCCESS;
    }

    /**
     * Fusionne le message avec les valeurs encore fraiches, puis enregistre.
     *
     * @param  array<string, array{value: mixed, at: string}>  $carried
     * @return array<string, array{value: mixed, at: string}>
     */
    private function handleData(
        Vehicle $vehicle,
        array $payload,
        array $carried,
        array &$counts,
        ChargeThresholdNotifier $notifier
    ): array {
        $recordedAt = $this->timestamp($payload['timestamp'] ?? null);

        if ($recordedAt === null) {
            $counts['ignores']++;

            return $carried;
        }

        // `stateOfCharge` est souvent nul alors que le PID brut du calculateur,
        // lui, repond : on preferera toujours la valeur officielle, mais on ne
        // se prive pas de la seconde quand elle est seule.
        if (($payload['stateOfCharge'] ?? null) === null && isset($payload['VCU_SOC'])) {
            $payload['stateOfCharge'] = $payload['VCU_SOC'];
        }

        foreach ($payload as $key => $value) {
            if ($value === null || $key === 'rawBytes') {
                continue;
            }

            $carried[$key] = ['value' => $value, 'at' => $recordedAt->toIso8601String()];
        }

        $fresh = $this->fresh($carried, $recordedAt);

        $last = $this->lastRow($vehicle);
        $chargingChanged = $last !== null
            && (bool) $last->is_charging !== (bool) ($payload['isCharging'] ?? false);

        // Le pas depend de ce que fait la voiture, et la bascule charge/pas
        // charge passe toujours : c'est elle qui borne une session, la manquer
        // decalerait le debut.
        if ($payload['isCharging'] ?? false) {
            $interval = self::CHARGING_ROW_INTERVAL;
        } elseif ($this->isActive($payload, $fresh, $last)) {
            $interval = self::DRIVING_ROW_INTERVAL;
        } else {
            $interval = self::IDLE_ROW_INTERVAL;
        }

        if (! $chargingChanged
            && $last !== null
            && $last->recorded_at->diffInSeconds($recordedAt) < $interval) {
            return $carried;
        }

        if ($this->option('dry-run')) {
            $counts['data']++;

            return $carried;
        }

        $attributes = [
            'is_charging' => (bool) ($payload['isCharging'] ?? false),
            'is_connected' => true,
            'telemetry_type' => 'xpcardata',
            'power_kw' => $this->power($fresh),
            // Tout ce qui n'a pas de colonne dediee reste accessible : c'est ce
            // qui alimente « Relevé brut » et « Sources de données » sans qu'une
            // migration soit necessaire a chaque champ nouveau.
            'raw' => [
                'telemetry' => $fresh,
                'telemetry_type' => 'xpcardata',
                'timestamp' => $recordedAt->toIso8601String(),
                'is_connected' => true,
                'source' => 'mqtt',
            ],
        ];

        foreach (self::FIELDS as $key => $column) {
            $attributes[$column] = $fresh[$key] ?? null;
        }

        $row = VehicleTelemetry::updateOrCreate(
            ['vehicle_id' => $vehicle->id, 'recorded_at' => $recordedAt],
            $attributes
        );

        // Les alertes de seuil vivaient dans la commande de collecte ABRP :
        // sans ce rappel, retirer ABRP les aurait supprimees en silence. Le
        // notifieur compare les paliers dus a ceux delivres, il supporte donc
        // d'etre appele sur un releve deja connu.
        foreach ($notifier->notify($vehicle, $row) as $threshold) {
            $this->info("SMS envoye : {$threshold} % atteint.");
        }

        $counts['data']++;

        return $carried;
    }

    /**
     * La voiture est-elle en train de charger ou de rouler ?
     *
     * L'odometre compare au dernier releve est le seul signal certain d'un
     * deplacement ; la vitesse ne sert qu'a reagir des le premier metre, avant
     * que le kilometrage n'ait eu le temps de changer. Se tromper ici ne coute
     * qu'une ligne de plus ou de moins, jamais une donnee fausse.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fresh
     */
    private function isActive(array $payload, array $fresh, ?VehicleTelemetry $last): bool
    {
        if ($payload['isCharging'] ?? false) {
            return true;
        }

        $speed = $fresh['speed'] ?? null;

        if ($speed !== null && (float) $speed >= self::MOVING_KMH) {
            return true;
        }

        $odometer = $fresh['odometer'] ?? null;

        return $odometer !== null
            && $last?->odometer !== null
            && (float) $odometer !== (float) $last->odometer;
    }

    /**
     * Puissance, convention retenue : negatif = energie entrante.
     *
     * XPCarData publie une magnitude, sans signe exploitable au repos. Le sens
     * vient donc de l'etat de charge, faute de quoi une recharge s'afficherait
     * comme une consommation sur la fiche du vehicule. La convention est celle
     * des releves historiques, conservee pour que l'affichage reste homogene.
     *
     * @param  array<string, mixed>  $fresh
     */
    private function power(array $fresh): ?float
    {
        $power = $fresh['power'] ?? null;

        if ($power === null) {
            return null;
        }

        $magnitude = abs((float) $power);

        return ($fresh['isCharging'] ?? false) ? -$magnitude : $magnitude;
    }

    /**
     * Valeurs encore dans la fenetre de report, a l'instant du message.
     *
     * @param  array<string, array{value: mixed, at: string}>  $carried
     * @return array<string, mixed>
     */
    private function fresh(array $carried, Carbon $at): array
    {
        $fresh = [];

        foreach ($carried as $key => $entry) {
            if (! isset($entry['at'], $entry['value'])) {
                continue;
            }

            if (Carbon::parse($entry['at'])->diffInSeconds($at) <= self::CARRY_SECONDS) {
                $fresh[$key] = $entry['value'];
            }
        }

        return $fresh;
    }

    private function handleCharging(Vehicle $vehicle, array $payload, array &$counts, ChargeGapNotifier $gapNotifier): void
    {
        $externalId = $payload['id'] ?? null;
        $startedAt = $this->timestamp($payload['startTime'] ?? null);

        if ($externalId === null || $startedAt === null) {
            $counts['ignores']++;

            return;
        }

        // Une session encore en cours n'a ni energie ni duree definitives : on
        // attend qu'elle se termine plutot que de proposer un chiffre provisoire.
        if (($payload['isActive'] ?? false) === true) {
            return;
        }

        if ($this->option('dry-run')) {
            $counts['charging']++;

            return;
        }

        $session = TelemetryChargingSession::updateOrCreate(
            ['vehicle_id' => $vehicle->id, 'external_id' => $externalId],
            [
                'started_at' => $startedAt,
                'ended_at' => $this->timestamp($payload['endTime'] ?? null),
                'duration_seconds' => $payload['durationSeconds'] ?? null,
                'soc_start' => $payload['startSoc'] ?? null,
                'soc_end' => $payload['endSoc'] ?? null,
                'energy_kwh' => $this->plausibleEnergy($vehicle, $payload),
                'energy_ah' => $payload['energyAddedAh'] ?? null,
                'odometer_start' => $payload['startOdometer'] ?? null,
                'odometer_end' => $payload['endOdometer'] ?? null,
                'charging_type' => $payload['chargingType'] ?? null,
                'max_power_kw' => $payload['maxPowerKw'] ?? null,
                'lat' => $payload['latitude'] ?? null,
                'lon' => $payload['longitude'] ?? null,
                'curve' => $payload['chargingCurve'] ?? null,
                'raw' => array_diff_key($payload, ['chargingCurve' => null]),
            ]
        );

        // Meme jugement que la page « Recharges face à la courbe » : la
        // session vient d'etre finalisee, c'est le seul moment ou il n'y a
        // rien a gagner a attendre. ChargeGapNotifier retente au prochain
        // passage si l'envoi echoue (identifiants absents, Free indisponible).
        if ($gapNotifier->notify($vehicle, $session)) {
            $this->info("SMS envoye : ecart de charge significatif sur la recharge du {$startedAt->format('d/m H:i')}.");
        }

        $counts['charging']++;
    }

    /**
     * Energie de la session, ecartee si le compteur a deraille.
     *
     * On ne corrige pas la valeur — on ne saurait pas vers quoi — on la laisse
     * vide : mieux vaut une recharge sans energie, que l'utilisateur completera,
     * qu'un chiffre faux qui partirait dans les statistiques et sur la courbe.
     * La charge brute reste dans `raw` pour qui voudrait enqueter.
     *
     * @param  array<string, mixed>  $payload
     */
    private function plausibleEnergy(Vehicle $vehicle, array $payload): ?float
    {
        $energy = $payload['energyAddedKwh'] ?? null;

        if ($energy === null) {
            return null;
        }

        $energy = (float) $energy;
        $gained = (float) ($payload['socGained'] ?? 0);
        $curve = $this->curves?->find($vehicle->charging_curve);
        $capacity = $curve['battery_net_kwh'] ?? null;

        if ($capacity === null || $gained <= 0) {
            return $energy;
        }

        $ceiling = $gained / 100 * (float) $capacity * self::ENERGY_PLAUSIBILITY_FACTOR
            + self::ENERGY_PLAUSIBILITY_SLACK;

        if ($energy > $ceiling) {
            $this->warn(sprintf(
                'Energie ecartee : %s kWh annonces pour %s point(s) de batterie.',
                round($energy, 1),
                round($gained, 1)
            ));

            return null;
        }

        return $energy;
    }

    /**
     * @return array<string, Vehicle>
     */
    private function vehiclesByClientId(): array
    {
        return Vehicle::whereNotNull('mqtt_client_id')
            ->get()
            ->keyBy('mqtt_client_id')
            ->all();
    }

    private function lastRow(Vehicle $vehicle): ?VehicleTelemetry
    {
        return VehicleTelemetry::where('vehicle_id', $vehicle->id)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    private function split(string $line): array
    {
        $space = strpos($line, ' ');

        if ($space === false) {
            return ['', null];
        }

        $decoded = json_decode(substr($line, $space + 1), true);

        return [substr($line, 0, $space), is_array($decoded) ? $decoded : null];
    }

    /**
     * Lit les lignes completes a partir du decalage, et rend le decalage
     * atteint. Une derniere ligne tronquee — le producteur ecrivait pendant la
     * lecture — est laissee pour le passage suivant.
     *
     * @return array{0: array<int, string>, 1: int}
     */
    private function readFrom(string $path, int $offset, int $size): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [[], $offset];
        }

        fseek($handle, $offset);
        $chunk = (string) fread($handle, $size - $offset);
        fclose($handle);

        $lastBreak = strrpos($chunk, "\n");

        if ($lastBreak === false) {
            return [[], $offset];
        }

        $complete = substr($chunk, 0, $lastBreak);
        $lines = array_values(array_filter(explode("\n", $complete), fn ($l) => trim($l) !== ''));

        return [$lines, $offset + $lastBreak + 1];
    }

    /**
     * Vide le tampon quand il est entierement lu et devenu gros.
     *
     * Le producteur ecrit en mode append : apres troncature ses ecritures
     * repartent de zero, sans rien corrompre. On revalide la taille juste avant
     * de tronquer — dans le pire des cas on perd les quelques messages arrives
     * dans l'intervalle, jamais une ligne coupee en deux.
     */
    private function truncateIfConsumed(string $path, int $offset): int
    {
        clearstatcache(true, $path);

        if ($offset < self::TRUNCATE_BYTES || filesize($path) !== $offset) {
            return $offset;
        }

        // @ : un echec d'ouverture est ici une possibilite normale — le fichier
        // appartient a qui l'a cree — et Laravel transforme l'avertissement en
        // exception, donc en erreur 500 si l'appel vient du web.
        $handle = @fopen($path, 'r+b');

        if ($handle === false) {
            $this->warn('Tampon non vide : '.$path.' n\'est pas accessible en ecriture.');

            return $offset;
        }

        ftruncate($handle, 0);
        fclose($handle);

        $this->line('Tampon vide apres lecture complete.');

        return 0;
    }

    private function timestamp(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        try {
            // XPCarData date en heure locale du telephone, sans fuseau : la lire
            // dans celui de l'application evite un decalage de deux heures.
            return Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $path = storage_path(self::STATE);

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(array $state): void
    {
        $path = storage_path(self::STATE);

        // Deux utilisateurs ecrivent ce fichier : root quand le planificateur
        // passe par `docker exec`, l'uid 82 de php-fpm quand l'utilisateur
        // clique « Mettre à jour les informations ». Celui qui cree le fichier
        // le laisse en 0644, et l'autre se heurte alors a une permission
        // refusee — cote web, cela donnait une erreur 500.
        if (@file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES)) === false) {
            // Ne pas interrompre : les releves de ce passage sont deja
            // enregistres. Seul le decalage n'avance pas, et le passage suivant
            // relira ce qui l'a deja ete — les ecritures sont idempotentes.
            $this->warn('Decalage non conserve : '.$path.' n\'est pas accessible en ecriture.');

            return;
        }

        // Le groupe est celui du repertoire (setgid) : lui ouvrir l'ecriture
        // suffit pour que les deux utilisateurs se relaient.
        @chmod($path, 0664);
    }
}
