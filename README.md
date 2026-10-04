# EV Recharges

[![Offrez-moi une bière](https://img.shields.io/badge/PayPal-Offrez--moi%20une%20bi%C3%A8re-0070ba?logo=paypal&logoColor=white)](https://paypal.me/tevnouille)

Application personnelle de suivi des recharges de véhicule électrique.
Elle enregistre chaque session de charge, calcule les coûts, les compare à
l'équivalent essence/diesel au prix réel du jour, et présente l'historique et
les courbes de charge du véhicule.

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

#### Saisie assistée par la base des bornes

Sur le formulaire de recharge, choisir **Autre…** en localisation ouvre un champ
qui propose les bornes de la base nationale IRVE dès trois lettres. Chaque mot
saisi est cherché séparément, si bien que « tesla villabé » croise l'enseigne et
la commune. Retenir une borne remplit la localisation avec sa commune, et
complète le fournisseur et la puissance **s'ils sont encore vides** — un choix
déjà fait n'est jamais écrasé. Le fournisseur est repris de vos listes quand le
nom correspond, sinon proposé en « Autre… ».

Le champ « Autre… » des fournisseurs propose de la même façon les enseignes et
opérateurs de la base, ce qui évite de retaper « TotalEnergies Charging Services ».

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

Elles sont reconstituées à partir des transitions du booléen « en charge » entre deux
relevés (ou lues directement quand le boîtier publie la recharge mesurée). L'énergie affichée est celle *entrée dans la batterie*
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

### Télémétrie du véhicule (dongle OBD, XPCarData et MQTT)

Le niveau de charge, la position, l'odomètre, la tension de batterie et les
compteurs d'énergie viennent d'un **dongle OBD-II** branché dans la voiture, lu
par l'application Android
[**XPCarData**](https://github.com/stevelea/xpcardata) (projet de stevelea, conçu
pour les Xpeng) qui les publie en MQTT&nbsp;:

```
voiture → dongle OBD → XPCarData (Android) → broker MQTT (Mosquitto)
        → service mqtt-ingest → telemetry:ingest-mqtt → base
```

- **Un serveur MQTT est fourni** : le `docker-compose.yml` lance un broker
  Mosquitto (`mosquitto`, authentification obligatoire) et un service
  `mqtt-ingest` qui s'y abonne et dépose les messages dans un fichier tampon, que
  `telemetry:ingest-mqtt` relit toutes les 5 à 15 secondes via le scheduler.
  La création du broker et de son compte est détaillée dans
  [INSTALL.md](INSTALL.md), section « Télémétrie du véhicule ».
- Dans XPCarData, renseigner l'adresse du broker, le compte MQTT et un
  identifiant de véhicule (`vehicles/<identifiant>/data`, `/charging`,
  `/status`). Ce même identifiant se saisit dans `/admin/vehicules`, champ
  « Identifiant MQTT » : c'est lui qui rattache la télémétrie au véhicule.
- La page **Courbe de recharge** affiche le niveau actuel, l'énergie disponible,
  le temps restant jusqu'à 80 / 90 / 100 % et surligne la ligne correspondante
  dans les deux tableaux.
- Chaque mesure est historisée dans `vehicle_telemetries` (SoC, en charge ou non,
  position, horodatage). Les recharges mesurées par le BMS publiées par le boîtier
  sont enregistrées avec leur courbe complète.

Les valeurs sont celles que le boîtier interroge à tour de rôle : ce n'est pas du
temps réel strict, et un champ peut manquer d'un relevé à l'autre.

*A Better Routeplanner (ABRP), utilisé au départ comme relais, a été retiré : les
données arrivaient déjà du boîtier, au prix d'un détour par un cloud tiers qui les
appauvrissait.*

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

### Plusieurs utilisateurs, données cloisonnées

L'accès se fait par **email et mot de passe**. Il n'y a pas d'inscription : les
comptes sont créés par un administrateur, depuis **Administration &rarr;
Utilisateurs** (créer un compte, passer administrateur, changer un mot de passe,
désactiver ou supprimer un compte avec tout ce qu'il contient). Le premier
administrateur se crée en ligne de commande&nbsp;:

```bash
docker exec -it ev-app php artisan user:create admin@exemple.fr --admin
```

Chacun change son mot de passe dans **Mon compte**.

**Chaque compte ne voit que ses propres données** : recharges, véhicules,
localisations, fournisseurs, puissances, trajets favoris et journal SMS. Rien n'est
partagé, à deux exceptions assumées&nbsp;: la base nationale des bornes et
l'historique des prix des carburants, qui sont des données publiques importées.

Un compte créé démarre avec les puissances de borne usuelles pré-remplies.

Les pages **Ma voiture** et **Déplacements** n'apparaissent que pour les comptes
disposant d'un véhicule relié au boîtier OBD. Le critère est la présence d'un
identifiant MQTT, pas une liste d'emails :
elles apparaissent d'elles-mêmes le jour où quelqu'un renseigne le sien.

Le cloisonnement est un *scope global* Eloquent (`App\Models\Concerns\BelongsToUser`)
et non un `where` à répéter dans chaque contrôleur&nbsp;: on ne peut pas l'oublier.
Il ne s'applique pas en console, où `telemetry:poll` doit interroger les véhicules
de tout le monde.

**Alertes SMS** : chacun renseigne son compte Free Mobile dans **Mon compte**&nbsp;;
les identifiants sont stockés sur son compte, pas dans la configuration du serveur,
et la clé est chiffrée en base. Sans identifiants, pas d'alerte — il n'y a
délibérément pas de repli sur un compte commun, une recharge ne doit pas faire
sonner le téléphone d'un autre.

**Copie de trajet** : depuis la fiche d'un trajet favori, on le copie vers d'autres
comptes, bornes retenues comprises. C'est une copie et non un partage — le
destinataire reçoit un trajet à lui, qu'il peut modifier ou supprimer sans toucher
à l'original.

## Stack

- **Laravel 13** / PHP 8.4, **MariaDB 11**
- **Bulma** pour la mise en page, **Chart.js** pour les graphiques
- **React 19** uniquement sur le dashboard ; les autres pages utilisent du
  JavaScript standard
- **Vite** pour le build des assets
- Déploiement Docker (`ev-app` PHP-FPM, `ev-nginx`, `ev-mariadb`)

## Installation

Sur une machine neuve (de préférence une VM dédiée), tout se fait avec un script :

```bash
git clone https://github.com/tevnouille/evxpeng.git ev && cd ev
python3 scripts/install.py
```

Voir [INSTALL.md](INSTALL.md) pour le détail, les options et l'installation
manuelle. Seuls Docker (avec Compose v2), Python 3 et git sont nécessaires sur
l'hôte.

## Commandes utiles

| Commande | Rôle |
| --- | --- |
| `php artisan fuel-prices:backfill` | Importe l'historique annuel des prix des carburants |
| `php artisan telemetry:ingest-mqtt` | Ingère les messages du boîtier OBD reçus sur le broker MQTT |
| `php artisan irve:import` | Importe la base nationale des bornes de recharge (planificateur) |
| `php artisan user:create <email> [--admin]` | Crée un compte (le premier administrateur) |
| `php artisan view:clear` | Vide le cache des vues après modification d'un Blade |

## Notes pour les agents

Voir [CLAUDE.md](CLAUDE.md) : déploiement par bind-mount, build des assets,
contournements docker-compose, test des pages derrière la connexion et pièges
rencontrés.
