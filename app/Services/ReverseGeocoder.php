<?php

namespace App\Services;

use App\Models\GeocodedPlace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adresses postales des positions relevees, par la Base Adresse Nationale.
 *
 * Deux precautions face a un service public gratuit :
 *
 *  - **un appel groupe, pas un par point.** La BAN expose un point d'entree
 *    CSV qui traite tout un fichier d'un coup ; une journee de roulage compte
 *    des centaines de releves, et les interroger un a un serait indefendable ;
 *  - **un cache definitif.** Une adresse ne change pas : la position arrondie a
 *    la dizaine de metres n'est resolue qu'une fois, jamais redemandee.
 *
 * L'echec est mis en cache lui aussi, libelle vide : une position sans adresse
 * connue — pleine campagne, aire d'autoroute — serait sinon redemandee a chaque
 * affichage de la journee.
 */
class ReverseGeocoder
{
    private const ENDPOINT = 'https://api-adresse.data.gouv.fr/reverse/csv/';

    /** Quatre decimales, soit environ onze metres. */
    private const PRECISION = 4;

    /**
     * Plafond par appel : au-dela, la journee est resolue en plusieurs fois, un
     * lot a chaque affichage. Mieux vaut une page complete au second passage
     * qu'un envoi de plusieurs megaoctets sur le dos de la BAN.
     */
    private const BATCH = 400;

    private const AGENT = 'ev.lolinux.org (suivi de vehicule, usage personnel)';

    /** Un lot de plusieurs centaines de points prend le temps qu'il faut. */
    private const DELAI_LOT = 60;

    /**
     * Un point isole, lui, est demande depuis une page qui s'affiche : mieux
     * vaut une tuile vide qu'un ecran qui reste blanc parce que la BAN tarde.
     */
    private const DELAI_UNITAIRE = 5;

    public function key(float $lat, float $lon): string
    {
        return number_format($lat, self::PRECISION, '.', '')
            .','.number_format($lon, self::PRECISION, '.', '');
    }

    /**
     * Adresses connues pour ces positions, sans aucun appel reseau.
     *
     * @param  Collection<int, object>  $points
     * @return array<string, GeocodedPlace>
     */
    public function known(Collection $points): array
    {
        $cles = $points
            ->filter(fn ($p) => $p->lat !== null && $p->lon !== null)
            ->map(fn ($p) => $this->key((float) $p->lat, (float) $p->lon))
            ->unique();

        if ($cles->isEmpty()) {
            return [];
        }

        // Filtre sur la latitude — colonne de tete de l'index unique — puis
        // appariement exact en memoire. `all()` chargeait toute la table a
        // chaque affichage : elle grossit a chaque trajet, et cette methode
        // sert desormais une page rafraichie toutes les vingt secondes.
        $latitudes = $cles->map(fn (string $cle) => explode(',', $cle)[0])->unique()->all();

        return GeocodedPlace::whereIn('lat', $latitudes)->get()
            ->filter(fn (GeocodedPlace $place) => $cles->contains($this->key($place->lat, $place->lon)))
            ->keyBy(fn (GeocodedPlace $place) => $this->key($place->lat, $place->lon))
            ->all();
    }

    /**
     * Resout ce qui manque, en un seul appel groupe.
     *
     * @param  Collection<int, object>  $points
     * @return int  Nombre de positions nouvellement resolues.
     */
    public function resolveMissing(Collection $points, ?int $delai = null): int
    {
        $connues = $this->known($points);

        $manquantes = $points
            ->filter(fn ($p) => $p->lat !== null && $p->lon !== null)
            ->map(fn ($p) => $this->key((float) $p->lat, (float) $p->lon))
            ->unique()
            ->reject(fn (string $cle) => isset($connues[$cle]))
            ->take(self::BATCH)
            ->values();

        if ($manquantes->isEmpty()) {
            return 0;
        }

        // Les cles sont deja au format « lat,lon » attendu par la BAN.
        $csv = "lat,lon\n".implode("\n", $manquantes->all())."\n";

        try {
            $reponse = Http::withHeaders(['User-Agent' => self::AGENT])
                ->timeout($delai ?? self::DELAI_LOT)
                ->attach('data', $csv, 'positions.csv')
                ->post(self::ENDPOINT, ['lat' => 'lat', 'lon' => 'lon']);
        } catch (\Throwable $e) {
            Log::warning('Geocodage inverse impossible', ['message' => $e->getMessage()]);

            return 0;
        }

        if (! $reponse->successful()) {
            Log::warning('Geocodage inverse : reponse inattendue', ['status' => $reponse->status()]);

            return 0;
        }

        return $this->store($reponse->body());
    }

    /**
     * Commune d'une position, resolue si elle ne l'est pas encore.
     *
     * Un seul point, donc un seul appel, et le cache fait le reste : une
     * voiture a l'arret ne redemande rien, une voiture qui roule ne demande
     * qu'une fois par tranche de onze metres.
     */
    public function city(float $lat, float $lon): ?string
    {
        $point = collect([(object) ['lat' => $lat, 'lon' => $lon]]);
        $cle = $this->key($lat, $lon);
        $connues = $this->known($point);

        if (! isset($connues[$cle])) {
            $this->resolveMissing($point, self::DELAI_UNITAIRE);
            $connues = $this->known($point);
        }

        // Chaine vide = la BAN a repondu qu'elle ne connaissait pas : on ne la
        // redemande pas, et la tuile ne s'affiche pas.
        return ($connues[$cle]->city ?? null) ?: null;
    }

    /**
     * Enregistre les lignes du CSV renvoye par la BAN.
     */
    private function store(string $csv): int
    {
        $lignes = preg_split("/\r\n|\n|\r/", trim($csv));
        $entete = str_getcsv(array_shift($lignes) ?? '', ',', '"', '');
        $index = array_flip($entete);

        if (! isset($index['lat'], $index['lon'])) {
            Log::warning('Geocodage inverse : entete CSV inattendue', ['entete' => $entete]);

            return 0;
        }

        $enregistrees = 0;

        foreach ($lignes as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $champs = str_getcsv($ligne, ',', '"', '');
            $valeur = fn (string $nom) => isset($index[$nom]) ? ($champs[$index[$nom]] ?? null) : null;

            $lat = $valeur('lat');
            $lon = $valeur('lon');

            if ($lat === null || $lon === null || $lat === '' || $lon === '') {
                continue;
            }

            GeocodedPlace::updateOrCreate(
                [
                    'lat' => round((float) $lat, self::PRECISION),
                    'lon' => round((float) $lon, self::PRECISION),
                ],
                [
                    // Chaine vide et non null quand la BAN ne trouve rien : la
                    // ligne existe, ce qui evite de redemander indefiniment.
                    'label' => $valeur('result_label') ?: '',
                    'city' => $valeur('result_city') ?: null,
                    'postcode' => $valeur('result_postcode') ?: null,
                    'distance_m' => is_numeric($valeur('result_distance')) ? (int) $valeur('result_distance') : null,
                ]
            );

            $enregistrees++;
        }

        return $enregistrees;
    }
}
