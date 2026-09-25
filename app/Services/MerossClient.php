<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Pilote le garage/portail Meross (portes MSG100) via le service interne
 * docker/meross/ -- ev-app ne peut parler ni MQTT (contrainte deja connue de
 * ce projet, voir CLAUDE.md) ni au socket Docker de l'hote pour lancer un
 * conteneur a la demande. Ce client se contente d'un appel HTTP interne,
 * jamais expose au navigateur ni hors du reseau Docker du projet.
 */
class MerossClient
{
    /**
     * Le service reconnecte entierement a Meross a chaque appel (~3-5 s),
     * puis relit l'etat par petits pas jusqu'a confirmation ou 30 s au
     * maximum pour open/close (voir DELAI_ENTRE_RELECTURES_S/RELECTURES_MAX
     * dans docker/meross/app.py -- corrige le 25/09/2026, une seule
     * relecture trop rapide renvoyait encore "ouvert" juste apres une
     * fermeture pourtant reussie, la porte n'ayant pas fini sa course).
     * Marge large plutot qu'un timeout qui coupe une action reellement en
     * cours sur une porte physique.
     */
    private const TIMEOUT_SECONDES = 45;

    /**
     * @return array{ok: bool, open: ?bool, error: ?string}
     */
    public function operer(string $uuid, string $action): array
    {
        try {
            $reponse = Http::timeout(self::TIMEOUT_SECONDES)
                ->post('http://meross:8000/control', [
                    'uuid' => $uuid,
                    'action' => $action,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'open' => null, 'error' => $e->getMessage()];
        }

        $corps = $reponse->json();

        if (! is_array($corps)) {
            return ['ok' => false, 'open' => null, 'error' => 'reponse inattendue du service Meross'];
        }

        return [
            'ok' => (bool) ($corps['ok'] ?? false),
            'open' => $corps['open'] ?? null,
            'error' => $corps['error'] ?? null,
        ];
    }
}
