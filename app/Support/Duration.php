<?php

namespace App\Support;

/**
 * Mise en forme d'une duree exprimee en minutes.
 *
 * Le planificateur manipule des minutes entieres partout ; les afficher telles
 * quelles donne des "192 min" illisibles sur un trajet long.
 */
class Duration
{
    public static function human(int|float|null $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $minutes = (int) round($minutes);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $rest = $minutes % 60;

        return $rest === 0
            ? intdiv($minutes, 60).' h'
            : sprintf('%d h %02d', intdiv($minutes, 60), $rest);
    }
}
