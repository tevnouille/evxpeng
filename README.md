# EV Recharges

Application personnelle de suivi des recharges de véhicule électrique.
Elle enregistre chaque session de charge, calcule les coûts, les compare à
l'équivalent essence/diesel au prix réel du jour, et présente l'historique et
les courbes de charge du véhicule.

En production : <https://ev.lolinux.org> (accès protégé par passkey, compte
unique).

## Fonctionnalités

### Saisie des recharges

- Formulaire de saisie : véhicule, date, localisation, fournisseur de borne,
  puissance de borne, quantité (kWh), durée, coût unitaire et coût total.
- Le coût total est calculé automatiquement (quantité × coût unitaire) tant
  qu'il n'a pas été saisi manuellement.
- **Ajouter et dupliquer** : reprend localisation, fournisseur, puissance et
  coût unitaire de la recharge précédente, pour enchaîner les saisies répétitives.
- **Géolocalisation** : un bouton récupère la position via le navigateur et
  remplit la localisation par reverse-geocoding (Nominatim / OpenStreetMap).
- Les listes déroulantes sont filtrables au clavier, et chacune propose
  « Autre… » pour créer une nouvelle valeur à la volée.

### Historique

- Vue annuelle : une tuile par mois avec nombre de recharges, kWh et coût.
- Vue mensuelle : détail des sessions, plus deux graphiques journaliers
  (kWh et coût) du 1er au dernier jour du mois.

### Dashboard

Graphiques d'évolution filtrables par année (l'année en cours par défaut),
répartition par fournisseur, et totaux.

### Équivalence carburant

Pour chaque recharge, l'app calcule ce qu'aurait coûté le même trajet en
essence ou en diesel, à partir :

- du **prix réel du carburant à la date de la recharge**, relevé sur les données
  ouvertes du gouvernement (`data.economie.gouv.fr`, avec historique récupérable
  via `php artisan fuel-prices:backfill`) ;
- des **consommations propres au véhicule** (kWh/100 km, L/100 km essence et
  diesel), saisies dans l'administration.

Une recharge dont le véhicule n'a pas ses consommations renseignées est exclue
du calcul plutôt qu'estimée : les totaux affichent alors combien de sessions
ont réellement été prises en compte.

### Courbe de recharge

Page de référence par modèle de véhicule :

- puissance de charge en fonction du niveau de batterie (graphique) ;
- tableau détaillé de 0 à 100 % : puissance, capacité brute, capacité nette,
  temps cumulé, énergie chargée ;
- temps de recharge restant depuis chaque niveau jusqu'à 80, 90 ou 100 %
  (graphique et tableau).

Les données proviennent d'[evkx.net](https://evkx.net) et sont stockées dans
`resources/data/charging-curves/`, un fichier JSON par modèle. Le sélecteur de
modèle apparaît automatiquement dès qu'il y a plus d'une courbe. Lorsque la
source indique que la courbe est estimée et non mesurée, la page l'affiche
explicitement.

Modèle actuellement présent : Xpeng G6 AWD Performance MY2023/MY2024.

### Ma voiture

Page dédiée à l'état du véhicule : niveau de charge, énergie disponible, autonomie estimée
(à partir de la consommation saisie sur la fiche), position, courbe du niveau de charge sur
7 à 90 jours, et **recharges détectées**.

ABRP n'expose aucune notion de session : elles sont reconstituées à partir des transitions du
booléen « en charge » entre deux relevés. L'énergie affichée est celle *entrée dans la batterie*
(écart de niveau × capacité utile), donc inférieure à celle *facturée à la borne*. D'où un bouton
« Pré-remplir » qui amène vers le formulaire de saisie avec la date, la durée et l'énergie estimée,
plutôt qu'un enregistrement automatique.

La carte OpenStreetMap n'est chargée qu'au clic, pour ne pas transmettre la position du véhicule
à chaque affichage de la page.

### Puissance de borne sur la courbe de recharge

Un sélecteur (7,4 / 11 / 50 / 150 / 300 kW) permet de voir la courbe telle qu'elle serait sur une
borne bridée. À énergie constante, une puissance divisée par deux double la durée du segment :
chaque intervalle est donc étiré du rapport entre sa puissance d'origine et la puissance bridée.
Sans limite sélectionnée le facteur vaut 1, et la page retrouve exactement les durées d'evkx
(vérifié : 21 m 1 s sur 10 → 80 %, 49 m 54 s sur 0 → 100 %).

Les indicateurs de tête, les deux tableaux, les graphiques et le temps restant depuis le niveau
réel suivent tous la borne choisie.

### Télémétrie du véhicule (ABRP)

Le niveau de charge réel de la voiture est récupéré automatiquement via l'API
d'A Better Routeplanner, qui s'alimente elle-même auprès du cloud du constructeur
(Enode). Aucun boîtier OBD n'est nécessaire.

- La page **Courbe de recharge** affiche le niveau actuel, l'énergie disponible,
  le temps restant jusqu'à 80 / 90 / 100 % et surligne la ligne correspondante
  dans les deux tableaux.
- Chaque mesure est historisée dans `vehicle_telemetries` (SoC, en charge ou non,
  position, horodatage constructeur).
- La récupération tourne toutes les 15 minutes via le scheduler.

À savoir : la donnée est rafraîchie **environ une fois par heure véhicule à l'arrêt**,
plus souvent en charge, et le backend d'ABRP applique 60 s de traitement par lots.
Ce n'est donc pas du temps réel.

Configuration : `ABRP_API_KEY` dans le `.env` (clé « Telemetry-Only », gratuite) et
un token par véhicule dans `/admin/vehicules`.

### Planificateur d'itinéraire

La page **Planificateur** calcule un trajet avec ses arrêts de recharge : on saisit
un départ, une arrivée, une puissance minimale de borne et éventuellement des
réseaux préférés, et l'application propose les arrêts, le niveau de charge à
l'arrivée et au départ de chacun, la durée de chaque recharge et le total.

Elle **n'utilise pas l'API de planification d'Iternio** : celle-ci est commerciale
(frais de mise en service puis facturation au plan) et répond `403 Feature plan is
not available` à la clé « Telemetry-Only » gratuite. Le plan est donc calculé
localement, à partir de trois sources gratuites :

| Brique | Source |
| --- | --- |
| Itinéraire routier | OSRM (serveur de démonstration public) |
| Bornes de recharge | Base nationale IRVE (data.gouv.fr), importée en base |
| Temps de charge | La courbe de recharge du véhicule, déjà utilisée par `/courbe-de-recharge` |
| Adresses | Base Adresse Nationale, puis Nominatim hors de France |

Les réseaux sélectionnés sont par défaut une **préférence** (ils pèsent dans le
choix de la borne) et non un filtre : une case à cocher permet de les imposer.

Le modèle est volontairement simple — consommation constante, ni relief, ni météo,
ni trafic, borne supposée libre et à sa puissance nominale. Il donne un ordre de
grandeur et une liste d'arrêts crédibles, pas une prévision au kilomètre près.

### Trajets favoris

La page **Favoris** répond à un besoin différent du planificateur&nbsp;: pas de plan
optimisé, mais la carte de **toutes** les bornes du parcours, à la manière de
Chargemap, et le choix manuel de celles qu'on veut retenir.

On nomme le trajet, on saisit départ et arrivée, on filtre par puissance minimale,
réseaux et détour maximal, puis on clique les bornes sur la carte. Le récapitulatif
liste les bornes retenues avec, pour chacune, un lien **Google Maps** et un lien
**Waze**, plus un lien vers l'itinéraire complet dans Google Maps (bornes en étapes).

Une borne retenue est **recopiée** dans le trajet, pas seulement référencée&nbsp;:
elle reste affichée même si l'import IRVE suivant la fait disparaître.

## Stack

- **Laravel 13** / PHP 8.4, **MariaDB 11**
- **Bulma** pour la mise en page, **Chart.js** pour les graphiques
- **React 19** uniquement sur le dashboard ; les autres pages utilisent du
  JavaScript standard
- **Vite** pour le build des assets
- Déploiement Docker (`ev-app` PHP-FPM, `ev-nginx`, `ev-mariadb`), derrière une
  passerelle passkey et HAProxy

## Installation

Voir [INSTALL.md](INSTALL.md) pour la procédure complète : configuration,
démarrage des conteneurs, build des assets, données de référence à saisir et
import de l'historique des prix des carburants.

Seuls Docker et Docker Compose sont nécessaires sur la machine hôte.

## Commandes utiles

| Commande | Rôle |
| --- | --- |
| `php artisan fuel-prices:backfill` | Importe l'historique annuel des prix des carburants |
| `php artisan telemetry:poll` | Récupère le niveau de charge des véhicules auprès d'ABRP |
| `php artisan irve:import` | Importe la base nationale des bornes de recharge (planificateur) |
| `php artisan view:clear` | Vide le cache des vues après modification d'un Blade |

## Notes pour les agents

Voir [CLAUDE.md](CLAUDE.md) : déploiement par bind-mount, build des assets,
contournements docker-compose, test des pages derrière le passkey et pièges
rencontrés.
