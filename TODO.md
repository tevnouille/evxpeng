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

## Alerte SMS sur écart de recharge significatif

`ChargingCurveController::compare()` calcule déjà l'écart entre puissance
mesurée et courbe de référence par session, et le marque `significant` (ligne
~181, seuils `SIGNIFICANT_GAP_KW` / `SIGNIFICANT_GAP_RATIO`). Rien n'alerte
aujourd'hui : il faut visiter `/courbe-de-recharge/comparaison` pour le voir.

- Détecter la fin d'une `TelemetryChargingSession`, calculer l'écart comme le
  fait déjà `comparison()`, et envoyer un SMS via `FreeMobileSms` (même
  mécanisme que `ChargeAlert`) si `significant`.
- Question : où brancher la détection de fin de charge ? Probablement dans le
  même passage que `TelemetrySessionDetector` / `PendingTelemetryCharges`,
  pas un nouveau poll.
- Attention à ne pas alerter sur une charge AC volontairement bridée à faible
  puissance — `comparison()` gère déjà ce cas en calant la référence sur
  `max_power_kw` de la session, à réutiliser tel quel.

## Cumul "économisé depuis le début" (équivalent carburant)

`FuelPriceService` calcule déjà l'équivalent essence/diesel par recharge
(section CLAUDE.md « Équivalence carburant »), affiché recharge par recharge
dans l'historique mensuel. Rien n'additionne sur la durée de vie du compte.

- Ajouter un total cumulé (kWh, coût réel, coût essence/diesel équivalent,
  gain) sur le dashboard ou une nouvelle carte « Depuis le début ».
- Respecter la règle déjà en place : les recharges sans véhicule configuré
  (`configured_sessions`) restent exclues du calcul, pas de valeur inventée.

## Alerte si le prix du carburant dépasse un seuil

La table `fuel_prices` (import régulier, cf. CLAUDE.md) existe et sert
uniquement au calcul de l'équivalent par recharge. Jamais utilisée pour
notifier.

- SMS (ou juste un encart sur le dashboard) quand le prix essence/diesel
  dépasse un seuil réglable, à la manière des paliers de charge
  (`config('services.charge_alerts.thresholds')`).
- Question : seuil global ou par utilisateur (comme les identifiants Free
  Mobile, propres à chaque compte) ?

## Coût réel au km glissant

Le coût réel par recharge existe (`ChargingSession`), mais rien ne le
rapporte aux kilomètres parcourus sur une fenêtre récente (30/90 jours) pour
donner un chiffre "coût au km" directement lisible.

- Croiser `ChargingSession` (coût réel) et l'odomètre (`VehicleTelemetry`,
  déjà utilisé par `DailyVehicleActivity` et `TripMapController::odometerDistance()`)
  sur une fenêtre glissante.
- Question : inclure un abonnement domicile/box (pas dans le modèle
  actuellement) ou rester sur le seul coût des recharges saisies ?

## Comparaison trajet planifié vs trajet réellement effectué

`RoutePlannerController` estime temps et arrêts de recharge via
`ChargeCurveSimulator` ; `TripMapController` reconstitue après coup ce qui
s'est vraiment passé (vitesse, arrêts, distance). Les deux ne se recoupent
jamais aujourd'hui.

- Après un trajet effectué qui ressemble à un itinéraire planifié récent
  (ou à un trajet favori), proposer une comparaison temps estimé/réel,
  arrêts prévus/réels.
- Question, non triviale : comment rapprocher automatiquement un trajet
  réel d'un plan ou d'un favori (mêmes points de départ/arrivée à quelques
  centaines de mètres près, sur une fenêtre de temps raisonnable) ? À
  concevoir avant de coder — risque de faux rapprochements.

## Notes personnelles sur les bornes

`ChargingStation` (base IRVE, données publiques importées, cf. CLAUDE.md
« Libellés IRVE ») n'a aucun champ pour une appréciation personnelle. Le
planificateur et les favoris (`FavoriteRouteController`) ne s'appuient que
sur la donnée brute.

- Nouveau modèle (ou champ) rattaché à l'utilisateur (`BelongsToUser`) :
  note libre sur une borne (fiable, HS, accès compliqué), affichée dans le
  planificateur et les favoris à côté de la borne concernée.
- Question : une note par (utilisateur, borne), ou par (utilisateur, borne,
  visite) si on veut dater chaque observation ?

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
