<?php

namespace App\Console\Commands;

use App\Support\Changelog;
use Illuminate\Console\Command;

/**
 * Ecrit CHANGELOG.md a partir du journal affiche dans l'application.
 *
 * Le fichier Markdown est une *vue* de App\Support\Changelog, jamais une
 * seconde source : deux journaux tenus a la main en parallele divergent des la
 * premiere semaine, et on ne sait plus lequel ment. Tout ajout se fait donc
 * dans la classe, et ce fichier se regenere.
 *
 * A lancer avec l'arbre entier monte, et non dans `ev-app` : la racine du depot
 * n'y est pas montee, le fichier serait ecrit dans la couche du conteneur et
 * n'apparaitrait jamais cote hote.
 *
 *     docker run --rm -v /var/docker/ev:/app -w /app -u 0:0 ev_app \
 *         php artisan changelog:export
 */
class ExportChangelog extends Command
{
    protected $signature = 'changelog:export {--check : Signaler que le fichier est perime, sans le reecrire}';

    protected $description = 'Regenere CHANGELOG.md depuis App\Support\Changelog';

    public function handle(): int
    {
        $chemin = base_path('CHANGELOG.md');
        $attendu = $this->markdown();

        if ($this->option('check')) {
            $actuel = is_readable($chemin) ? file_get_contents($chemin) : null;

            if ($actuel === $attendu) {
                $this->info('CHANGELOG.md est a jour.');

                return self::SUCCESS;
            }

            $this->error('CHANGELOG.md est perime : lancer `php artisan changelog:export`.');

            return self::FAILURE;
        }

        file_put_contents($chemin, $attendu);
        $this->info('CHANGELOG.md regenere ('.count(Changelog::releases()).' dates).');

        return self::SUCCESS;
    }

    private function markdown(): string
    {
        $lignes = [
            '# Journal des nouveautés',
            '',
            '**Fichier généré — ne pas modifier à la main.** Il est écrit depuis',
            '`app/Support/Changelog.php`, qui reste la seule source. Après y avoir ajouté',
            'une entrée :',
            '',
            '```bash',
            'php artisan changelog:export',
            '```',
            '',
            'Le journal est rédigé pour qui se sert de l\'application, pas depuis l\'historique',
            'Git : les messages de commit parlent de code, et une bonne moitié d\'entre eux ne',
            'change rien de visible. La même liste s\'affiche sur `/changelog`.',
            '',
        ];

        foreach (Changelog::releases() as $release) {
            $lignes[] = '## '.$release['date']->translatedFormat('j F Y');
            $lignes[] = '';

            foreach ($release['entries'] as $entree) {
                $libelle = Changelog::TYPES[$entree['type']]['label'] ?? $entree['type'];
                $lignes[] = '- **'.$libelle.'** — '.$entree['text'];
            }

            $lignes[] = '';
        }

        return implode("\n", $lignes);
    }
}
