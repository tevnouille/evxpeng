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
| `php artisan view:clear` | Vide le cache des vues après modification d'un Blade |

## Notes pour les agents

Voir [CLAUDE.md](CLAUDE.md) : déploiement par bind-mount, build des assets,
contournements docker-compose, test des pages derrière le passkey et pièges
rencontrés.
