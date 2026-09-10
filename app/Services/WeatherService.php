<?php

namespace App\Services;

use App\Models\DailyWeather;
use Carbon\CarbonImmutable;

/**
 * Temperature moyenne du jour, via l'API historique gratuite d'Open-Meteo
 * (pas de cle).
 *
 * Necessaire car le boitier OBD n'a jamais remonte de temperature exterieure
 * malgre la colonne `ext_temp` prevue pour — verifie sur 4079 releves, aucun
 * ne porte le champ, XPCarData ne le publie simplement pas.
 *
 * Une position par vehicule pour toute la plage demandee, pas une par jour :
 * une voiture rechargee a la maison reste l'essentiel du temps dans la meme
 * region, et une position par jour aurait impose un appel par jour manquant
 * plutot qu'un seul par plage. Indicatif, comme les autres approximations
 * geographiques de l'application (somme GPS, libelles IRVE).
 */
class WeatherService
{
    private const API_URL = 'https://archive-api.open-meteo.com/v1/archive';

    /**
     * L'API archive publie avec un leger retard : au-dela, pas de donnee
     * "definitive" a aller chercher — inutile de retenter chaque jour.
     */
    private const MIN_LAG_DAYS = 2;

    public function __construct(private readonly HttpUserAgent $http)
    {
    }

    /**
     * @return array<string, array{min: ?float, max: ?float, mean: ?float}>  Cle Y-m-d.
     */
    public function forRange(CarbonImmutable $start, CarbonImmutable $end, float $lat, float $lon): array
    {
        $cutoff = CarbonImmutable::now()->subDays(self::MIN_LAG_DAYS)->startOfDay();
        $end = $end->greaterThan($cutoff) ? $cutoff : $end;

        if ($end->lessThan($start)) {
            return [];
        }

        $missing = $this->missingDates($start, $end);

        if ($missing !== []) {
            $this->fetch($missing[0], end($missing), $lat, $lon);
        }

        return DailyWeather::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'))
            ->map(fn (DailyWeather $row) => [
                'min' => $row->temp_min_c !== null ? (float) $row->temp_min_c : null,
                'max' => $row->temp_max_c !== null ? (float) $row->temp_max_c : null,
                'mean' => $row->temp_mean_c !== null ? (float) $row->temp_mean_c : null,
            ])
            ->all();
    }

    /**
     * @return array<int, string>  Dates (Y-m-d) sans releve en cache.
     */
    private function missingDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $known = DailyWeather::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->flip();

        $missing = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            if (! $known->has($day->format('Y-m-d'))) {
                $missing[] = $day->format('Y-m-d');
            }
        }

        return $missing;
    }

    private function fetch(string $startDate, string $endDate, float $lat, float $lon): void
    {
        $payload = $this->http->get(self::API_URL, [
            'latitude' => $lat,
            'longitude' => $lon,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'daily' => 'temperature_2m_mean,temperature_2m_min,temperature_2m_max',
            'timezone' => 'auto',
        ]);

        $daily = $payload['daily'] ?? null;

        if (! is_array($daily) || ! isset($daily['time']) || ! is_array($daily['time'])) {
            return;
        }

        foreach ($daily['time'] as $index => $date) {
            DailyWeather::updateOrCreate(
                ['date' => $date],
                [
                    'lat' => $lat,
                    'lon' => $lon,
                    'temp_mean_c' => $daily['temperature_2m_mean'][$index] ?? null,
                    'temp_min_c' => $daily['temperature_2m_min'][$index] ?? null,
                    'temp_max_c' => $daily['temperature_2m_max'][$index] ?? null,
                ]
            );
        }
    }
}
