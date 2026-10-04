<?php

namespace App\Services;

use App\Models\FuelPrice;
use App\Models\User;

/**
 * Previent par SMS quand le prix moyen national du carburant franchit un
 * seuil choisi par l'utilisateur.
 *
 * Seuil propre a chaque compte, et non global (config('services.charge_alerts'))
 * comme les paliers de charge : le prix du carburant est une donnee partagee,
 * mais ce qui interesse en depend. Meme logique que les identifiants Free
 * Mobile, deja propres a chaque compte.
 */
class FuelPriceAlertNotifier
{
    public function __construct(private readonly FreeMobileSms $sms)
    {
    }

    /**
     * @return array<int, string>  Emails effectivement notifies.
     */
    public function notify(FuelPrice $today, ?FuelPrice $previous): array
    {
        $notified = [];

        $users = User::whereNotNull('approved_at')
            ->where(function ($query) {
                $query->whereNotNull('fuel_alert_essence_price')
                    ->orWhereNotNull('fuel_alert_diesel_price');
            })
            ->get();

        foreach ($users as $user) {
            $crossed = $this->crossings($user, $today, $previous);

            if ($crossed === []) {
                continue;
            }

            $sms = $this->sms->forUser($user);

            if (! $sms->configured()) {
                continue;
            }

            if ($sms->send($this->message($crossed))) {
                $notified[] = $user->email;
            }
        }

        return $notified;
    }

    /**
     * @return array<int, array{fuel: string, price: float, threshold: float}>
     */
    private function crossings(User $user, FuelPrice $today, ?FuelPrice $previous): array
    {
        $crossed = [];

        if ($user->fuel_alert_essence_price !== null
            && $this->hasCrossed((float) $today->essence_price, $previous?->essence_price, (float) $user->fuel_alert_essence_price)) {
            $crossed[] = ['fuel' => 'essence', 'price' => (float) $today->essence_price, 'threshold' => (float) $user->fuel_alert_essence_price];
        }

        if ($user->fuel_alert_diesel_price !== null
            && $this->hasCrossed((float) $today->diesel_price, $previous?->diesel_price, (float) $user->fuel_alert_diesel_price)) {
            $crossed[] = ['fuel' => 'diesel', 'price' => (float) $today->diesel_price, 'threshold' => (float) $user->fuel_alert_diesel_price];
        }

        return $crossed;
    }

    /**
     * On notifie sur le franchissement, pas tant que le prix reste au-dessus :
     * sans cette regle, une SMS partirait chaque jour tant que l'essence reste
     * chere. Sans relevé de la veille (premier jour de collecte, ou trou), une
     * valeur deja au-dessus du seuil est traitee comme un franchissement — mieux
     * vaut le dire une fois de trop que jamais.
     */
    private function hasCrossed(float $price, mixed $previousPrice, float $threshold): bool
    {
        if ($price < $threshold) {
            return false;
        }

        return $previousPrice === null || (float) $previousPrice < $threshold;
    }

    /**
     * @param  array<int, array{fuel: string, price: float, threshold: float}>  $crossed
     */
    private function message(array $crossed): string
    {
        $parts = array_map(fn (array $c) => sprintf(
            '%s : %s €/L (seuil %s)',
            $c['fuel'] === 'essence' ? 'Essence' : 'Diesel',
            str_replace('.', ',', (string) round($c['price'], 3)),
            str_replace('.', ',', (string) round($c['threshold'], 3))
        ), $crossed);

        return 'Prix carburant, moyenne nationale du jour : '.implode(' — ', $parts).'.';
    }
}
