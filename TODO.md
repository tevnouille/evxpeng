# TODO

Idées de fonctionnalités à trier, notées le 2026-09-10 pour ne pas les perdre.
Chaque entrée pointe vers le code existant à réutiliser et les questions à
trancher avant de s'y mettre.

« Santé batterie (SoH) dans le temps » est livrée sous une forme différente de
la note initiale : le SoH lui-même reste figé à 99 % sur toute la période
collectée (vérifié), donc c'est l'écart entre cellules — déjà le signal
avant-coureur retenu sur `/infoCar` — qui est tracé jour par jour, dans un
nouveau graphique « Équilibre des cellules » sur « Ma voiture ». Voir
`App\Services\BatteryHealth` et le CHANGELOG.md du 2026-09-10.

« Alerte SMS sur écart de recharge significatif » est livrée : le calcul est
extrait dans `App\Services\ChargeCurveComparison` (une seule implémentation
pour la page et l'alerte), et `App\Services\ChargeGapNotifier` envoie le SMS,
appelé depuis `IngestMqttTelemetry::handleCharging()` au moment où le boîtier
publie la session terminée — pas de sondage séparé. Dédoublonnage par la
colonne `gap_alert_delivered` sur `telemetry_charging_sessions`, sur le même
principe que `delivered` dans `ChargeAlert`. Voir le CHANGELOG.md du 2026-09-10.

« Cumul "économisé depuis le début" » est livré, sous forme plus simple que la
note initiale : le calcul cumulé existait déjà en fait (sélectionner « Toutes
les années » sur le dashboard le donnait), il manquait une carte toujours
visible sans avoir à changer de filtre. `DashboardController::lifetime()`
réutilise `FuelPriceService::equivalentTotals()` sur l'ensemble des recharges
du compte. Voir le CHANGELOG.md du 2026-09-10.

« Alerte si le prix du carburant dépasse un seuil » est livrée. La question
ouverte est tranchée en faveur du seuil par utilisateur (`users.fuel_alert_essence_price`
/ `fuel_alert_diesel_price`, réglables dans /mon-compte) plutôt que global :
même logique que les identifiants Free Mobile, déjà propres à chaque compte,
et aucun prix « raisonnable » à deviner à la place de qui que ce soit.
`App\Services\FuelPriceAlertNotifier` notifie sur le franchissement (comparaison
au relevé le plus récent avant celui du jour, pas seulement la veille au cas où
un jour aurait manqué), appelé par la nouvelle commande planifiée
`fuel-prices:check` (7h30 chaque matin) — jusqu'ici le prix n'était rafraîchi
que par une visite de page. Voir le CHANGELOG.md du 2026-09-10.

« Coût réel au km glissant » est livré, sur « Ma voiture »
(`MyVehicleController::costPerKm()`), même fenêtre glissante que le reste de
la page. Question tranchée : pas d'abonnement domicile/box dans le calcul —
ce concept n'existe nulle part ailleurs dans le modèle de données, l'ajouter
serait une fonctionnalité à part entière, pas une hypothèse à glisser ici.
Piège rencontré et corrigé en route : le coût doit être borné à la période
*réellement couverte par l'odomètre*, pas aux jours demandés — sinon une
fenêtre large compte des recharges d'avant l'installation du boîtier,
faussant le ratio sans que rien ne le signale. Voir le CHANGELOG.md du
2026-09-10.

« Comparaison trajet planifié vs trajet réellement effectué » est livrée, sur
« Déplacements » (`TripMapController::comparison()`). La question ouverte
(risque de faux rapprochements) est tranchée en évitant complètement la
détection automatique : deux menus déroulants laissent choisir soi-même le
déplacement du jour et le trajet favori à confronter. Seuls les trajets
favoris sont comparables (pas de plan éphémère du planificateur : rien n'y
est persisté tant qu'il n'est pas enregistré en favori), et seule la durée
routière (OSRM, sans arrêt imposé) est estimée — le planificateur ne prévoit
pas de pauses, donc l'écart observé inclut toute recharge ou pause réelle.
Voir le CHANGELOG.md du 2026-09-10.

« Notes personnelles sur les bornes » est livrée, page `/mes-bornes`
(`App\Models\ChargingStationNote`). Question tranchée : une note par
(utilisateur, borne), pas par visite — la réécrire remplace l'observation
précédente. `App\Services\RouteCorridor::stations()` centralise l'affichage :
notes ajoutées après coup sur les résultats, une seule requête, donc
planificateur et favoris les récupèrent sans rien changer côté contrôleur.
Recherche de borne : `ChargerLookupController::stations()` expose désormais
`id` (utilisé nulle part ailleurs avant), sur le même mécanisme que
« Rechercher bornes » côté saisie de recharge. Voir le CHANGELOG.md du
2026-09-10.

## Estimation "prochaine recharge nécessaire"

Rien aujourd'hui ne projette l'autonomie actuelle sur les habitudes de
déplacement pour dire "il faudra recharger d'ici tel jour".

- Combiner l'autonomie courante (déjà calculée dans `MyVehicleController` /
  `InfoCarController`) et une fréquence de trajets habituelle (`DailyVehicleActivity`
  ou un nouveau calcul sur `TripMapController`) pour une estimation grossière.
- Question : vaut vraiment le coup pour un usage single-utilisateur avec
  recharge à domicile ? Peut-être la fonctionnalité la moins prioritaire de
  cette liste — à confirmer avant de s'y lancer.

## État de charge en cours sur la page de partage de position

`PositionShare` (livré le 2026-09-10, voir CHANGELOG.md et
`app/Http/Controllers/PublicPositionShareController.php`) affiche batterie,
autonomie et adresse par point, mais pas si la voiture est en train de
charger au moment où le destinataire consulte le lien.

- Ajouter l'état de charge en cours (puissance, temps restant estimé) sur la
  page publique, utile si on partage sa position pendant une charge sur
  autoroute. Réutiliser `VehicleState` et `ChargeCurveSimulator`, déjà
  partagés avec `InfoCarController`.
