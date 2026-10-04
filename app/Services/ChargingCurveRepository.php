<?php

namespace App\Services;

use App\Models\Vehicle;
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

    public function __construct(private readonly MeasuredChargingCurve $measured)
    {
    }

    /**
     * Courbe du vehicule, paliers mesures substitues quand la couverture le
     * permet.
     *
     * C'est ce point d'entree, et non find(), que doivent utiliser la page et le
     * simulateur : la courbe de reference decrit un exemplaire du modele, celle
     * du vehicule decrit la voiture qui est dans le garage.
     *
     * @return array<string, mixed>|null
     */
    public function forVehicle(?Vehicle $vehicle): ?array
    {
        $curve = $this->find($vehicle?->charging_curve);

        if ($curve === null || $vehicle === null) {
            return $curve;
        }

        return $this->measured->apply(
            $curve,
            $this->measured->forVehicle($vehicle, $curve['max_power_kw'] ?? null)
        );
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
