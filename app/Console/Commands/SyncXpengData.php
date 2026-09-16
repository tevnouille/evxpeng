<?php

namespace App\Console\Commands;

use App\Models\XpengDataExport;
use App\Services\XpengClient;
use App\Services\XpengExportParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Recupere l'export de donnees Xpeng, du depot de la tache a la
 * recuperation du fichier.
 *
 * Une seule commande fait les deux, parce que l'API ne les distingue pas non
 * plus : le premier appel depose la tache, les suivants -- identiques cote
 * appelant -- interrogent son etat jusqu'a l'URL de telechargement, valable
 * ~30 secondes. Attendre le lendemain pour la suite romprait cette fenetre :
 * on reste donc sur l'appel en attendant, par petites relances, comme le
 * recommande le guide d'integration (section 12, 5 a 10 s entre deux appels).
 *
 * Planifiee une fois par jour (routes/console.php) : le quota Xpeng est de 5
 * soumissions/24h par couple utilisateur-entreprise, et resoumettre pendant
 * qu'une tache est deja "en export" ne semble pas en consommer une nouvelle
 * (le guide parle d'un recordNo mis en cache) -- mais ca reste a verifier en
 * conditions reelles, d'ou une cadence quotidienne prudente pour commencer.
 */
class SyncXpengData extends Command
{
    protected $signature = 'xpeng:sync';

    protected $description = "Soumet ou relance l'export Xpeng et telecharge le fichier des qu'il est pret.";

    private const TENTATIVES_MAX = 10;

    private const DELAI_ENTRE_TENTATIVES = 8;

    public function handle(XpengClient $client, XpengExportParser $parser): int
    {
        if (! config('services.xpeng.app_id') || ! config('services.xpeng.app_secret')) {
            $this->error("Xpeng : appId/appSecret non configures (voir .env), rien a faire.");

            return self::FAILURE;
        }

        $requestedAt = now();
        $export = XpengDataExport::create([
            'requested_at' => $requestedAt,
            'statut' => 'en_cours',
        ]);

        for ($tentative = 1; $tentative <= self::TENTATIVES_MAX; $tentative++) {
            $resultat = $client->queryData();

            if ($resultat['code'] !== 0) {
                $this->echoue($export, $resultat, $resultat['desc'] ?? $resultat['msg'] ?? ('code '.$resultat['code']));

                return self::FAILURE;
            }

            $donnee = $resultat['data'];

            if ($donnee === 'DataFileExportFailed') {
                $this->echoue($export, $resultat, "Xpeng a signale l'echec de la generation du fichier.");

                return self::FAILURE;
            }

            if ($donnee === 'DataFileExporting') {
                $this->info("Tentative $tentative/".self::TENTATIVES_MAX.' : export en cours...');

                if ($tentative < self::TENTATIVES_MAX) {
                    sleep(self::DELAI_ENTRE_TENTATIVES);
                }

                continue;
            }

            // $donnee est alors l'URL de telechargement : il faut la saisir
            // tout de suite, elle n'est valable qu'une trentaine de secondes.
            $chemin = $this->telecharger($donnee, $requestedAt);

            $export->update([
                'statut' => $chemin ? 'ok' : 'echec',
                'reponse_brute' => json_encode($resultat, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'chemin_fichier' => $chemin,
                'erreur' => $chemin ? null : 'Le telechargement du fichier a echoue (lien peut-etre expire).',
            ]);

            if ($chemin) {
                $this->info("Fichier Xpeng recupere : $chemin");

                // L'archivage du fichier brut ne doit jamais echouer a cause
                // d'un parsing qui casse -- le format n'est pas documente par
                // Xpeng, une variation future (nouvelle colonne, structure
                // differente) ne doit pas empecher de garder le fichier.
                try {
                    $nombre = $parser->importZip(storage_path('app/'.$chemin));
                    $this->info("$nombre minute(s) agregee(s) dans xpeng_telemetries.");
                } catch (\Throwable $e) {
                    $this->warn("Fichier recupere, mais l'agregation a echoue : {$e->getMessage()}");
                    report($e);
                }

                return self::SUCCESS;
            }

            $this->error('Xpeng : telechargement du fichier echoue.');

            return self::FAILURE;
        }

        $export->update([
            'statut' => 'echec',
            'erreur' => 'Toujours "en export" apres '.(self::TENTATIVES_MAX * self::DELAI_ENTRE_TENTATIVES).' s, abandon pour cette fois.',
        ]);
        $this->warn('Xpeng : export toujours en cours, on reessaiera a la prochaine execution planifiee.');

        return self::FAILURE;
    }

    private function echoue(XpengDataExport $export, array $resultat, string $erreur): void
    {
        $export->update([
            'statut' => 'echec',
            'reponse_brute' => json_encode($resultat, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'erreur' => $erreur,
        ]);
        $this->error("Xpeng : $erreur");
    }

    /**
     * Enregistre le fichier tel quel : le format reel n'est pas documente
     * cote Xpeng, mieux vaut le garder brut et l'inspecter que le parser a
     * l'aveugle et perdre des donnees non reconnues.
     */
    private function telecharger(string $url, Carbon $requestedAt): ?string
    {
        $reponse = Http::timeout(20)->get($url);

        if (! $reponse->successful() || $reponse->body() === '') {
            return null;
        }

        $dossier = storage_path('app/xpeng');

        if (! is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        $typeContenu = $reponse->header('Content-Type') ?? '';
        $extension = match (true) {
            str_contains($typeContenu, 'zip') => 'zip',
            str_contains($typeContenu, 'json') => 'json',
            str_contains($typeContenu, 'csv') => 'csv',
            default => 'dat',
        };

        $nom = $requestedAt->format('Y-m-d_His').'.'.$extension;
        file_put_contents($dossier.'/'.$nom, $reponse->body());

        return 'xpeng/'.$nom;
    }
}
