<?php

namespace App\Services;

use App\Models\VehicleTelemetry;
use Illuminate\Support\Collection;

/**
 * Ce que chaque source de telemetrie remonte reellement.
 *
 * ABRP ne renvoie pas un schema fixe : `get_telemetry` rend « un sous-ensemble
 * des proprietes de l'objet telemetrie », et ce sous-ensemble depend de qui a
 * pousse la donnee. Le cloud constructeur, atteint par Enode, se limite au
 * niveau de charge et a la position ; un dongle OBD y ajoute le compteur, la
 * sante de la batterie, la puissance et les temperatures.
 *
 * D'ou un releve construit sur les cles effectivement observees plutot que sur
 * une liste ecrite en dur : une source qui se mettrait a fournir un champ de
 * plus apparait ici sans modification de code.
 */
class TelemetrySources
{
    /**
     * Au-dela, la source n'alimente plus en direct — voir VehicleState::link().
     */
    private const LIVE_MINUTES = 15;

    /**
     * Noms lisibles des codes connus. Un code inconnu s'affiche tel quel : mieux
     * vaut un intitule brut qu'une etiquette inventee.
     */
    private const LABELS = [
        'obdble' => 'Dongle OBD Bluetooth',
        'xpcardata' => 'Boîtier OBD via XPCarData (MQTT)',
        'api' => 'Cloud constructeur (Enode)',
        'enode' => 'Cloud constructeur (Enode)',
        'car' => 'Cloud constructeur',
    ];

    /**
     * Cles de l'enveloppe : elles decrivent l'appel, pas la voiture.
     */
    private const ENVELOPE = ['timestamp', 'typecode', 'name', 'vehicle_id', 'user_id', 'telemetry_type', 'is_connected'];

    /**
     * @param  Collection<int, VehicleTelemetry>  $history  Tries par recorded_at croissant.
     * @return array<int, array<string, mixed>>  La source la plus recente d'abord.
     */
    public function summarize(Collection $history): array
    {
        $sources = [];

        foreach ($history as $row) {
            $type = $row->telemetry_type ?? 'inconnue';
            $telemetry = ($row->raw['telemetry'] ?? null);

            if (! isset($sources[$type])) {
                $sources[$type] = [
                    'type' => $type,
                    'label' => self::LABELS[$type] ?? $type,
                    'count' => 0,
                    'first_at' => $row->recorded_at,
                    'last_at' => $row->recorded_at,
                    'keys' => [],
                    'latest' => [],
                ];
            }

            $sources[$type]['count']++;
            $sources[$type]['last_at'] = $row->recorded_at;

            if (! is_array($telemetry)) {
                continue;
            }

            foreach ($telemetry as $key => $value) {
                if (in_array($key, self::ENVELOPE, true)) {
                    continue;
                }

                // Une cle toujours nulle n'est pas un champ fourni : ABRP en
                // renvoie plusieurs par politesse, sans valeur derriere.
                if ($value === null) {
                    continue;
                }

                $sources[$type]['keys'][$key] = true;
                $sources[$type]['latest'][$key] = $value;
            }
        }

        $summaries = array_values(array_map(function (array $source) {
            $source['keys'] = array_keys($source['keys']);
            sort($source['keys']);
            ksort($source['latest']);
            $source['minutes'] = (int) $source['last_at']->diffInMinutes(now());
            $source['live'] = $source['minutes'] <= self::LIVE_MINUTES;

            return $source;
        }, $sources));

        usort($summaries, fn ($a, $b) => $b['last_at'] <=> $a['last_at']);

        return $summaries;
    }
}
