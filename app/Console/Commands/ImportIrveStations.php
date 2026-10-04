<?php

namespace App\Console\Commands;

use App\Models\ChargingStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Import de la base nationale des IRVE (bornes de recharge) publiee sur
 * data.gouv.fr.
 *
 * Le fichier consolide fait ~160 Mo et decrit un point de charge par ligne. On
 * l'agrege en stations, seule maille utile au planificateur, ce qui divise le
 * volume par deux et rend les requetes de corridor instantanees.
 *
 * L'import est fait en local plutot qu'interroge a chaud : l'API Opendatasoft
 * publique est limitee en debit, et un plan long enchaine des dizaines de
 * requetes geographiques.
 */
class ImportIrveStations extends Command
{
    protected $signature = 'irve:import
        {--file= : Utiliser un CSV deja telecharge au lieu de le retelecharger}
        {--keep : Conserver le CSV telecharge}';

    protected $description = 'Importe la base nationale des bornes de recharge (IRVE, data.gouv.fr)';

    /** Lignes envoyees par requete : ~17 colonnes, on reste loin de la limite de placeholders de MariaDB. */
    private const CHUNK = 500;

    public function handle(): int
    {
        // Le fichier tient en memoire une fois agrege (~130 000 stations), mais
        // pas avec les 128 Mo par defaut du conteneur.
        ini_set('memory_limit', '768M');

        $path = $this->option('file') ?: $this->download();

        if ($path === null) {
            return self::FAILURE;
        }

        $this->info('Lecture de '.$path.'...');

        $stations = $this->aggregate($path);

        if ($stations === []) {
            $this->error('Aucune station lue : le format du fichier a peut-etre change.');

            return self::FAILURE;
        }

        $this->info(number_format(count($stations), 0, ',', ' ').' stations agregees, ecriture en base...');

        $this->store($stations);

        if (! $this->option('file') && ! $this->option('keep')) {
            @unlink($path);
        }

        $this->info('Termine : '.number_format(ChargingStation::count(), 0, ',', ' ').' stations en base.');

        return self::SUCCESS;
    }

    private function download(): ?string
    {
        $url = config('services.irve.url');
        $path = storage_path('app/irve-'.now()->format('Ymd').'.csv');

        $this->info('Telechargement du fichier consolide IRVE...');

        try {
            $response = Http::timeout(600)
                // data.gouv sert une 403 aux clients sans User-Agent.
                ->withHeaders(['User-Agent' => config('app.url').' (ev)'])
                ->sink($path)
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Telechargement impossible : '.$e->getMessage());

            return null;
        }

        if (! $response->successful() || ! is_file($path) || filesize($path) < 1_000_000) {
            $this->error('Telechargement incomplet (HTTP '.$response->status().').');

            return null;
        }

        $this->line(number_format(filesize($path) / 1_048_576, 1, ',', ' ').' Mo telecharges.');

        return $path;
    }

    /**
     * Agrege les points de charge en stations.
     *
     * @return array<string, array<string, mixed>>
     */
    private function aggregate(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, 0, ',', '"', '');

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $columns = array_flip($header);
        $stations = [];
        $lines = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $lines++;

            $value = fn (string $name) => isset($columns[$name], $row[$columns[$name]])
                ? trim((string) $row[$columns[$name]])
                : '';

            $lat = $this->number($value('consolidated_latitude'));
            $lon = $this->number($value('consolidated_longitude'));

            // Sans position exploitable la station est inutilisable pour un
            // corridor ; le fichier en contient quelques milliers.
            if ($lat === null || $lon === null || abs($lat) < 0.01 || abs($lon) < 0.01) {
                continue;
            }

            $key = $this->stationKey($value('id_station_itinerance'), $value('nom_station'), $lat, $lon);
            $power = $this->power($value('puissance_nominale'));

            if (! isset($stations[$key])) {
                $stations[$key] = [
                    'external_id' => $key,
                    'name' => $this->truncate($value('nom_station') ?: $value('nom_enseigne') ?: 'Station', 190),
                    'network' => $this->truncate($this->cleanNetwork($value('nom_enseigne')), 120) ?: null,
                    'operator' => $this->truncate($this->cleanNetwork($value('nom_operateur')), 120) ?: null,
                    'address' => $this->truncate($value('adresse_station'), 190) ?: null,
                    'city' => $this->truncate($value('consolidated_commune'), 120) ?: null,
                    'lat' => round($lat, 7),
                    'lon' => round($lon, 7),
                    'max_power_kw' => $power,
                    'points_count' => 0,
                    'has_ccs' => false,
                    'has_type2' => false,
                    'has_chademo' => false,
                    'is_free' => false,
                    'is_public' => false,
                ];
            }

            $station = &$stations[$key];
            $station['max_power_kw'] = max($station['max_power_kw'], $power);
            $station['points_count']++;
            $station['has_ccs'] = $station['has_ccs'] || $this->flag($value('prise_type_combo_ccs'));
            $station['has_type2'] = $station['has_type2'] || $this->flag($value('prise_type_2'));
            $station['has_chademo'] = $station['has_chademo'] || $this->flag($value('prise_type_chademo'));
            $station['is_free'] = $station['is_free'] || $this->flag($value('gratuit'));
            // Une station compte comme publique des qu'un seul de ses points
            // l'est : le doute profite a la station.
            $station['is_public'] = $station['is_public'] || ! $this->isRestricted($value('condition_acces'));
            unset($station);

            if ($lines % 50_000 === 0) {
                $this->line('  '.number_format($lines, 0, ',', ' ').' points de charge lus...');
            }
        }

        fclose($handle);

        $this->line('  '.number_format($lines, 0, ',', ' ').' points de charge lus.');

        return $stations;
    }

    /**
     * @param  array<string, array<string, mixed>>  $stations
     */
    private function store(array $stations): void
    {
        // Seconde pleine : la colonne datetime n'a pas de fraction, et c'est sur
        // cet horodatage que se reperent ensuite les stations disparues.
        $now = now()->startOfSecond();

        foreach (array_chunk($stations, self::CHUNK) as $chunk) {
            $rows = array_map(fn (array $station) => $station + [
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            ChargingStation::upsert($rows, ['external_id'], [
                'name', 'network', 'operator', 'address', 'city', 'lat', 'lon',
                'max_power_kw', 'points_count', 'has_ccs', 'has_type2',
                'has_chademo', 'is_free', 'is_public', 'updated_at',
            ]);
        }

        // Stations absentes du fichier (fermees, fusionnees) : les laisser
        // reviendrait a router vers une borne qui n'existe plus.
        $removed = ChargingStation::where('updated_at', '<', $now)->delete();

        if ($removed > 0) {
            $this->line('  '.number_format($removed, 0, ',', ' ').' stations retirees (absentes du fichier).');
        }
    }

    private function stationKey(string $itinerance, string $name, float $lat, float $lon): string
    {
        // "Non concerne" est la valeur que posent les operateurs sans identifiant
        // d'itinerance ; elle est partagee par des milliers de lignes.
        $itinerance = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $itinerance) ?? '');

        if ($itinerance !== '' && ! str_starts_with($itinerance, 'NONCONCERNE') && strlen($itinerance) >= 6) {
            return substr($itinerance, 0, 190);
        }

        return substr('geo:'.round($lat, 5).','.round($lon, 5).':'.md5($name), 0, 190);
    }

    /** La puissance est tantot en kW, tantot en W selon les producteurs de donnees. */
    private function power(string $raw): float
    {
        $value = $this->number($raw);

        if ($value === null || $value <= 0) {
            return 0.0;
        }

        if ($value > 1000) {
            $value /= 1000;
        }

        return round(min($value, 400.0), 1);
    }

    private function number(string $raw): ?float
    {
        $raw = str_replace([' ', ','], ['', '.'], $raw);

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * Acces reserve (flotte, copropriete, hotel).
     *
     * Le champ ne prend que deux valeurs, "Acces libre" et "Acces reserve",
     * mais le fichier consolide contient des lignes dont l'encodage a ete abime
     * en amont ("AccĂ¨s libre"). On ne garde donc que les lettres, ce qui laisse
     * "accslibre" et "accsrserv" : le test porte sur ces radicaux, et tout ce
     * qui n'est ni l'un ni l'autre est considere accessible.
     */
    private function isRestricted(string $raw): bool
    {
        $folded = strtolower(preg_replace('/[^A-Za-z]/', '', $raw) ?? '');

        if ($folded === '' || str_contains($folded, 'libre')) {
            return false;
        }

        return str_contains($folded, 'serv');
    }

    private function flag(string $raw): bool
    {
        return in_array(strtolower($raw), ['true', '1', 'oui', 'yes'], true);
    }

    /** Les operateurs suffixent souvent leur nom d'un identifiant ("Freshmile | FR*FR1"). */
    private function cleanNetwork(string $raw): string
    {
        $raw = trim(explode('|', $raw)[0]);

        return preg_replace('/\s+/', ' ', $raw) ?? $raw;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_substr(trim($value), 0, $length);
    }
}
