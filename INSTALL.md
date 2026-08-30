# Installation

Procédure complète pour déployer EV Recharges sur une nouvelle machine.

## Prérequis

- Docker et Docker Compose
- Un accès réseau sortant (récupération des prix des carburants sur
  `data.economie.gouv.fr` et `donnees.roulez-eco.fr`)

Rien d'autre n'est nécessaire sur l'hôte : ni PHP, ni Composer, ni Node. Tout
passe par les conteneurs.

## 1. Récupérer le code

```bash
git clone <url-du-depot> ev
cd ev
```

## 2. Configurer l'environnement

```bash
cp .env.example .env
```

Éditer `.env` et renseigner au minimum :

| Variable | Rôle |
| --- | --- |
| `DB_PASSWORD` | Mot de passe de l'utilisateur applicatif MariaDB |
| `DB_ROOT_PASSWORD` | Mot de passe root MariaDB (utilisé à la création du conteneur uniquement) |
| `APP_URL` | URL publique de l'application |

Générer deux mots de passe distincts, par exemple avec `openssl rand -hex 16`.

Points d'attention :

- `DB_HOST` doit rester `mariadb` : c'est le nom du service dans
  `docker-compose.yml`, pas `127.0.0.1`.
- `DB_PASSWORD` et `DB_ROOT_PASSWORD` ne sont lus qu'**à la première création**
  du volume `dbdata`. Les modifier ensuite ne change pas les mots de passe déjà
  enregistrés dans la base.
- `APP_KEY` reste vide à ce stade, il est généré à l'étape 4.

## 3. Démarrer les conteneurs

```bash
docker-compose up -d
```

Trois conteneurs démarrent :

| Conteneur | Rôle |
| --- | --- |
| `ev-app` | PHP-FPM 8.4 (l'application) |
| `ev-nginx` | Serveur web, expose l'application sur le réseau `ev-net` |
| `ev-mariadb` | Base de données MariaDB 11 |

Attendre que MariaDB soit prêt (quelques secondes au premier lancement, le temps
qu'il initialise le volume) avant de passer à la suite.

## 4. Initialiser l'application

```bash
docker exec ev-app php artisan key:generate
docker exec ev-app php artisan migrate --force
```

## 5. Construire les assets front

Le conteneur applicatif n'embarque pas npm : on passe par un conteneur Node
jetable, depuis la racine du projet.

```bash
docker run --rm -v "$(pwd)":/app -w /app node:20-alpine npm install
docker run --rm -v "$(pwd)":/app -w /app node:20-alpine npm run build
```

Cette étape est **obligatoire** : `public/build` est dans `.gitignore`, donc les
assets compilés ne sont pas dans le dépôt. Sans elle, les pages échouent au
rendu sur la directive `@vite(...)`.

## 6. Exposer l'application

`ev-nginx` ne publie pas de port sur l'hôte : il est joignable sur le réseau
Docker `ev-net`. C'est volontaire, l'application étant destinée à être placée
derrière un reverse proxy (avec authentification) plutôt qu'exposée directement.

Pour un accès direct, ajouter un mappage de port au service `nginx` dans
`docker-compose.yml` :

```yaml
    ports:
      - "8096:80"
```

puis recréer le conteneur :

```bash
docker-compose rm -sf nginx && docker-compose up -d --no-deps nginx
```

Pour vérifier sans publier de port :

```bash
docker run --rm --network ev-net curlimages/curl:latest -s -o /dev/null -w '%{http_code}\n' http://ev-nginx/
```

## 7. Renseigner les données de référence

À la première connexion, aller dans **Administration** et créer :

1. Au moins un **véhicule**, avec ses consommations : `kWh/100 km`,
   `L/100 km essence` et `L/100 km diesel`. Sans ces valeurs, les recharges
   sont exclues du calcul d'équivalence carburant (c'est volontaire : aucune
   consommation n'est supposée par défaut).
2. Les **localisations**, **fournisseurs** et **puissances de borne** utiles —
   ou les créer à la volée depuis le formulaire de saisie via « Autre… ».

## 8. Importer l'historique des prix des carburants (optionnel)

Le prix du jour est récupéré automatiquement. Pour disposer aussi de
l'historique et calculer l'équivalence carburant sur des recharges passées :

```bash
docker exec ev-app php artisan fuel-prices:backfill
```

La commande reconstruit les prix quotidiens SP95 / Gazole depuis l'archive
annuelle officielle, du 1er janvier à hier. Sans elle, les dates sans relevé
retombent sur un prix de repli (1,95 €), et les totaux concernés sont signalés
comme estimés dans l'interface.

## 9. Activer la télémétrie du véhicule (optionnel)

Permet de récupérer automatiquement le niveau de charge de la voiture.

1. Sur [abetterrouteplanner.com](https://abetterrouteplanner.com/home/app/api-keys/telemetry),
   générer une clé API « Telemetry-Only » (gratuite) et la placer dans le `.env` :

   ```
   ABRP_API_KEY=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
   ```

   Recréer ensuite le conteneur applicatif pour que la variable soit prise en compte :

   ```bash
   docker-compose rm -sf app && docker-compose up -d --no-deps app
   ```

2. Dans l'application ABRP : Settings &rarr; Car model &rarr; le véhicule &rarr;
   **Live data**. Y activer **Enode** (nécessite un abonnement ABRP Premium ; c'est
   lui qui alimente réellement la donnée), puis relever le token affiché dans
   l'option **Generic**.

3. Coller ce token dans `/admin/vehicules`, colonne « Token ABRP ».

4. Vérifier :

   ```bash
   docker exec ev-app php artisan telemetry:poll
   ```

5. Installer le scheduler sur l'hôte pour que la récupération soit périodique :

   ```
   * * * * * sudo /usr/bin/docker exec ev-app php artisan schedule:run >/dev/null 2>&1
   ```

## 10. Importer la base des bornes de recharge (planificateur)

La page **Planificateur** a besoin de la base nationale IRVE. L'import télécharge
un fichier d'environ 150 Mo, l'agrège en stations et le range en base :

```bash
docker exec ev-app php artisan irve:import
```

Compter trois à quatre minutes pour environ 57 000 stations. Une tâche planifiée
le rejoue chaque lundi à 4 h 30 ; il n'y a rien d'autre à configurer, aucune clé
n'est nécessaire.

## 11. Ouvrir l'application à plusieurs utilisateurs

L'identité est fournie par la passerelle passkey. Dans sa configuration nginx
(`/var/docker/ev-gate/nginx/gate.conf`), le `location /` doit transmettre l'email
de la session à l'application :

```nginx
auth_request_set $sso_email $upstream_http_x_sso_email;
proxy_set_header X-SSO-Email $sso_email;
```

`proxy_set_header` écrase systématiquement ce qu'un client aurait pu envoyer :
l'en-tête n'est pas falsifiable depuis l'extérieur. Sans lui, l'application répond
403 sur toutes ses pages.

Pour restreindre l'accès à un seul compte, ajouter `?required_email=...` à
l'`proxy_pass` du `location = /_sso_check` ; sans ce paramètre, tous les passkeys
enregistrés sont acceptés et chacun obtient son propre espace.

Le `.env` porte `EV_OWNER_EMAIL`, le compte auquel la migration rattache les
données existantes lors du passage au multi-comptes.

## Mise à jour

```bash
git pull
docker run --rm -v "$(pwd)":/app -w /app node:20-alpine npm run build
docker exec ev-app php artisan migrate --force
docker exec ev-app php artisan view:clear
```

Le code source est monté en bind dans le conteneur : un `git pull` suffit pour
le PHP et les vues, sans reconstruire l'image. Ne reconstruire l'image
(`docker-compose build app`) que si les dépendances Composer ou le Dockerfile
ont changé.

## Dépannage

**`docker-compose up -d` échoue avec `KeyError: 'ContainerConfig'`**
Bug de docker-compose 1.29.2 lors de la recréation d'un conteneur existant.
Contourner en supprimant le conteneur d'abord :

```bash
docker-compose rm -sf <service> && docker-compose up -d --no-deps <service>
```

**Erreur `Unable to locate file in Vite manifest`**
Les assets n'ont pas été construits (étape 5), ou un nouveau point d'entrée JS a
été ajouté sans être déclaré dans `input:` de `vite.config.js`.

**`SQLSTATE[HY000] [2002] Connection refused`**
MariaDB n'a pas fini de démarrer, ou `DB_HOST` a été mis à `127.0.0.1` au lieu
de `mariadb`.

**Une modification de vue n'apparaît pas**
Vider le cache des vues :

```bash
docker exec ev-app php artisan view:clear
```
