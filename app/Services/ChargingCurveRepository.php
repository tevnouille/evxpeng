<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Acces aux courbes de recharge, stockees en JSON dans resources/data/charging-curves.
 *
 * Ce sont des donnees de reference externes (evkx.net), figees et non modifiables
 * par l'utilisateur : un fichier par modele suffit, pas de table en base. Seule
 * l'association vehicule -> courbe est persistee (colonne vehicles.charging_curve).
 */
class ChargingCurveRepository
{
    private const CURVE_DIR = 'data/charging-curves';

    private ?Collection $cache = null;

    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $dir = resource_path(self::CURVE_DIR);

        if (! File::isDirectory($dir)) {
            return $this->cache = collect();
        }

        return $this->cache = collect(File::files($dir))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->map(fn ($file) => json_decode(File::get($file->getPathname()), true))
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /** @return array<string, mixed>|null */
    public function find(?string $slug): ?array
    {
        if ($slug === null) {
            return null;
        }

        return $this->all()->firstWhere('slug', $slug);
    }

    /**
     * Slugs valides, pour la validation du formulaire vehicule.
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return $this->all()->pluck('slug')->all();
    }
}
