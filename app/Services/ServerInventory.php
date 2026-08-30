<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Lecture du releve depose par scripts/server-inventory.py.
 *
 * L'application ne collecte rien elle-meme : depuis le conteneur elle ne voit
 * ni les paquets du systeme, ni node_modules, et npm n'y est pas installe. Un
 * script tourne donc sur l'hote et depose son resultat dans storage/app/system,
 * seul repertoire ecrit des deux cotes.
 */
class ServerInventory
{
    /** Au-dela, le releve est presente comme date. */
    private const STALE_HOURS = 26;

    private const FILE = 'system/server-inventory.json';
    private const REQUEST_FILE = 'system/server-inventory.request';
    private const UPDATE_REQUEST_FILE = 'system/server-update.request';
    private const UPDATE_LOG_FILE = 'system/server-update.log';

    /**
     * Ecosystemes dont la mise a jour se declenche depuis la page.
     *
     * Composer en est retire apres essai : le monter suppose de reconstruire
     * l'image et de recreer le conteneur, or docker-compose 1.29 de cet hote
     * ne sait plus le faire avec la version de Docker installee, et recreer le
     * conteneur change son adresse — que nginx garde en cache. L'essai a
     * laisse le site indisponible quelques minutes. Ces montees restent
     * manuelles tant que l'outillage de deploiement n'est pas repare.
     */
    public const UPDATABLE = ['npm', 'apt'];

    /** Ceux dont la mise a jour interromprait le service. Aucun pour l'instant. */
    public const REDEPLOYS = [];

    /** Verdicts de mise a jour, du plus urgent au plus anodin. */
    public const VERDICTS = [
        'securite' => ['label' => 'Sécurité', 'class' => 'is-danger', 'help' => 'Correctif de sécurité publié : à appliquer sans attendre.'],
        'abandonne' => ['label' => 'Abandonné', 'class' => 'is-danger', 'help' => "Le paquet n'est plus maintenu : prévoir un remplacement."],
        'recommandee' => ['label' => 'Recommandée', 'class' => 'is-warning', 'help' => 'Montée de version compatible, sans rupture annoncée.'],
        'a-evaluer' => ['label' => 'À évaluer', 'class' => 'is-info', 'help' => "Changement de version majeure : rupture possible, à lire avant d'y aller."],
        'indirecte' => ['label' => 'Via son parent', 'class' => 'is-light', 'help' => "Dépendance tirée par une autre : elle se met à jour avec celle qui l'amène, pas toute seule."],
        'inconnu' => ['label' => 'Non vérifié', 'class' => 'is-light', 'help' => "Paquet de développement, absent du conteneur de production : sa version disponible n'a pas pu être interrogée."],
        'a-jour' => ['label' => 'À jour', 'class' => 'is-success is-light', 'help' => 'Rien à faire.'],
    ];

    /** Verdicts qui n'appellent aucune action. */
    public const IDLE_VERDICTS = ['a-jour', 'indirecte', 'inconnu'];

    private ?array $data = null;

    public function exists(): bool
    {
        return $this->load() !== null;
    }

    public function generatedAt(): ?Carbon
    {
        $raw = $this->load()['generated_at'] ?? null;

        return $raw === null ? null : Carbon::parse($raw);
    }

    public function isStale(): bool
    {
        $at = $this->generatedAt();

        return $at === null || $at->diffInHours(now()) > self::STALE_HOURS;
    }

    /** @return array<string, mixed> */
    public function host(): array
    {
        return $this->load()['host'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function runtimes(): array
    {
        return $this->load()['runtimes'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function containers(): array
    {
        return $this->load()['containers'] ?? [];
    }

    /**
     * Les trois inventaires, chacun deja trie : ce qui demande une action
     * d'abord, le reste ensuite par ordre alphabetique.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        return [
            [
                'key' => 'composer',
                'label' => 'Dépendances PHP (Composer)',
                'note' => "Le projet lui-même. Les paquets marqués « développement » ne sont pas installés en production. Ces montées se font à la main : elles supposent de reconstruire l'image et de recréer le conteneur, ce que l'outillage de cette machine ne sait pas faire sans interrompre le service.",
                'packages' => $this->composer(),
            ],
            [
                'key' => 'npm',
                'label' => 'Dépendances JavaScript (npm)',
                'note' => "Servent à construire les fichiers du navigateur. Une dépendance indirecte suit celle qui l'amène.",
                'packages' => $this->npm(),
            ],
            [
                'key' => 'apt',
                'label' => 'Paquets du système (Ubuntu)',
                'note' => "Mis à jour par le système, indépendamment du projet.",
                'packages' => $this->apt(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function composer(): array
    {
        $rows = array_map(function (array $p): array {
            $verdict = match (true) {
                $p['abandoned'] => 'abandonne',
                $p['status'] === 'semver-safe-update' => 'recommandee',
                $p['status'] === 'update-possible' => 'a-evaluer',
                // composer outdated ne connait que ce qui est installe : les
                // paquets de dev ne le sont pas ici, on ne pretend donc pas
                // savoir s'ils sont a jour.
                $p['status'] === 'not-installed' => 'inconnu',
                default => 'a-jour',
            };

            return [
                'name' => $p['name'],
                'ecosystem' => 'composer',
                'updatable' => false,
                'version' => $p['version'],
                'available' => $verdict === 'inconnu' ? null : $p['available'],
                'license' => $p['license'],
                'verdict' => $verdict,
                'tags' => array_values(array_filter([
                    $p['dev'] ? 'développement' : null,
                    $p['direct'] ? 'directe' : 'indirecte',
                ])),
                'note' => $p['description'],
            ];
        }, $this->load()['composer'] ?? []);

        return $this->sorted($rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function npm(): array
    {
        $rows = array_map(function (array $p): array {
            // wanted = ce que la contrainte du projet autorise deja, donc une
            // montee sans rupture ; au-dela c'est un changement de branche.
            $verdict = match (true) {
                ! $p['direct'] => $p['version'] === $p['available'] ? 'a-jour' : 'indirecte',
                $p['wanted'] !== $p['version'] => 'recommandee',
                $p['available'] !== $p['version'] => 'a-evaluer',
                default => 'a-jour',
            };

            return [
                'name' => $p['name'],
                'ecosystem' => 'npm',
                'updatable' => $verdict === 'recommandee',
                'version' => $p['version'],
                'available' => $p['available'],
                'license' => $p['license'],
                'verdict' => $verdict,
                'tags' => array_values(array_filter([
                    $p['dev'] ? 'développement' : null,
                    $p['direct'] ? 'directe' : 'indirecte',
                ])),
                'note' => null,
            ];
        }, $this->load()['npm'] ?? []);

        return $this->sorted($rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function apt(): array
    {
        $rows = array_map(fn (array $p): array => [
            'name' => $p['name'],
            'ecosystem' => 'apt',
            'updatable' => $p['outdated'],
            'version' => $p['version'],
            'available' => $p['available'],
            'license' => $p['license'],
            'verdict' => match (true) {
                $p['security'] => 'securite',
                $p['outdated'] => 'recommandee',
                default => 'a-jour',
            },
            'tags' => array_values(array_filter([$p['section']])),
            'note' => null,
        ], $this->load()['apt'] ?? []);

        return $this->sorted($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sorted(array $rows): array
    {
        $order = array_flip(array_keys(self::VERDICTS));

        usort($rows, fn (array $a, array $b) => [$order[$a['verdict']], $a['name']] <=> [$order[$b['verdict']], $b['name']]);

        return $rows;
    }

    /**
     * Nombre de paquets par verdict, tous inventaires confondus.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::VERDICTS), 0);

        foreach ($this->groups() as $group) {
            foreach ($group['packages'] as $package) {
                $counts[$package['verdict']]++;
            }
        }

        return $counts;
    }

    /** Nombre de paquets qui appellent une decision. */
    public function actionable(): int
    {
        $counts = $this->counts();

        return $counts['securite'] + $counts['abandonne'] + $counts['recommandee'] + $counts['a-evaluer'];
    }


    /**
     * Demande la mise a jour d'un paquet.
     *
     * Comme pour le releve, le conteneur ne fait que deposer une demande :
     * c'est l'hote qui l'execute, apres avoir lui-meme reverifie que la montee
     * est compatible.
     */
    public function requestUpdate(string $ecosystem, string $package): bool
    {
        if (! in_array($ecosystem, self::UPDATABLE, true)) {
            return false;
        }

        return $this->writeRequest([
            'ecosystem' => $ecosystem,
            'package' => $package,
        ]);
    }

    /**
     * Demande la mise a jour de tout ce qui est compatible.
     *
     * L'hote regroupe par ecosysteme : une commande chacun, donc une seule
     * reconstruction d'image au lieu d'une par dependance PHP.
     */
    public function requestUpdateAll(): bool
    {
        return $this->writeRequest(['mode' => 'all']);
    }

    /** @param array<string, mixed> $payload */
    private function writeRequest(array $payload): bool
    {
        $payload['requested_at'] = now()->toIso8601String();

        return @file_put_contents(
            storage_path('app/'.self::UPDATE_REQUEST_FILE),
            json_encode($payload),
        ) !== false;
    }

    /**
     * Paquets qu'un traitement groupe monterait, par ecosysteme.
     *
     * @return array<string, int>
     */
    public function updatableCounts(): array
    {
        $counts = [];

        foreach ($this->groups() as $group) {
            $n = count(array_filter($group['packages'], fn (array $p) => $p['updatable']));
            if ($n > 0) {
                $counts[$group['key']] = $n;
            }
        }

        return $counts;
    }

    public function updatePending(): bool
    {
        return file_exists(storage_path('app/'.self::UPDATE_REQUEST_FILE));
    }

    /**
     * Journal des mises a jour tentees, la plus recente d'abord.
     *
     * @return array<int, array<string, mixed>>
     */
    public function updateLog(): array
    {
        $path = storage_path('app/'.self::UPDATE_LOG_FILE);

        if (! is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Demande un nouveau releve.
     *
     * Le conteneur ne peut pas lancer de commande sur l'hote : il depose un
     * fichier temoin, qu'une tache de l'hote ramasse dans la minute.
     */
    public function requestRefresh(): bool
    {
        $path = storage_path('app/'.self::REQUEST_FILE);

        return @file_put_contents($path, now()->toIso8601String()."\n") !== false;
    }

    public function refreshPending(): bool
    {
        return file_exists(storage_path('app/'.self::REQUEST_FILE));
    }

    /** @return array<string, mixed>|null */
    private function load(): ?array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = storage_path('app/'.self::FILE);

        if (! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->data = is_array($decoded) ? $decoded : null;
    }
}
