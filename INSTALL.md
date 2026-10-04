# Installation

Procédure pour déployer EV Recharges sur une machine neuve.

## Où l'installer

**De préférence sur une machine virtuelle (ou un petit VPS) dédiée**, plutôt que
sur votre poste ou un serveur qui héberge déjà autre chose&nbsp;:

- l'application tourne dans Docker et a besoin d'une tâche planifiée chaque minute
  (cron) ainsi que de quelques ports&nbsp;; une VM isole tout cela proprement et se
  jette ou se restaure d'un clic&nbsp;;
- elle garde des données personnelles (positions, trajets, recharges) et, si vous
  activez la télémétrie, un broker MQTT&nbsp;: autant ne pas les mélanger avec
  d'autres services&nbsp;;
- rien n'est installé sur l'hôte hors Docker, donc la VM reste propre.

Configuration suffisante&nbsp;: Debian 12+ ou Ubuntu 22.04+, 1 vCPU, 2 Go de RAM,
10 Go de disque.

## Prérequis

- **Docker** et le plugin **Docker Compose v2** (commande `docker compose`, avec
  une espace — l'ancien `docker-compose` 1.x n'est pas supporté)
- **Python 3** (déjà présent sur Debian/Ubuntu) et **git**, pour le script d'installation
- Un accès réseau sortant (téléchargement des images Docker, des dépendances, des
  prix des carburants sur `data.economie.gouv.fr` et `donnees.roulez-eco.fr`)

Rien d'autre sur l'hôte : ni PHP, ni Composer, ni Node.

Installer Docker sur une machine neuve (Debian/Ubuntu)&nbsp;:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"   # puis se reconnecter
```

## Installation rapide (recommandée)

```bash
git clone https://github.com/tevnouille/evxpeng.git ev
cd ev
python3 scripts/install.py
```

Le script pose quelques questions (URL, email et mot de passe du premier
administrateur, broker MQTT, import des bornes) puis fait tout&nbsp;: génération du
`.env` avec des secrets aléatoires, dépendances PHP et assets front dans des
conteneurs jetables, démarrage des conteneurs, migration de la base, création du
premier administrateur. Il peut être relancé sans risque (il ne régénère ni le
`.env`, ni les mots de passe existants).

Options utiles&nbsp;:

| Option | Effet |
| --- | --- |
| `--yes` | Non interactif, valeurs par défaut |
| `--app-url https://ev.exemple.fr` | URL publique (`APP_URL`) |
| `--admin-email … --admin-password …` | Premier administrateur |
| `--public` | Publie le port web sur toutes les interfaces (sinon `127.0.0.1` seulement) |
| `--with-mqtt` | Installe aussi le broker MQTT (télémétrie OBD, voir « Télémétrie du véhicule ») |
| `--import-data` | Importe les bornes IRVE et l'historique des prix carburants |
| `--install-cron` | Installe la tâche planifiée (root) |
| `--rebuild` | Refait `vendor/` et les assets même s'ils existent |

À la fin, il affiche l'adresse (`http://…:8098/login`) et la ligne cron à ajouter.

**HTTP seulement&nbsp;:** l'application écoute en clair sur le port `8098`. Pour un
accès depuis Internet, placez un reverse proxy TLS (nginx, Caddy…) devant, ne
publiez pas ce port tel quel.

La suite de ce document décrit les mêmes étapes à la main.

## Installation manuelle

### 1. Récupérer le code

```bash
git clone https://github.com/tevnouille/evxpeng.git ev
cd ev
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Éditer `.env` et renseigner au minimum :

| Variable | Rôle |
| --- | --- |
| `APP_KEY` | Clé de chiffrement. Générer : `echo "base64:$(openssl rand -base64 32)"` |
| `DB_PASSWORD` | Mot de passe de l'utilisateur applicatif MariaDB |
| `DB_ROOT_PASSWORD` | Mot de passe root MariaDB (utilisé à la création du conteneur uniquement) |
| `APP_URL` | URL publique de l'application |

Générer les mots de passe avec `openssl rand -hex 16`. Points d'attention&nbsp;:

- `DB_HOST` doit rester `mariadb` : c'est le nom du service dans
  `docker-compose.yml`, pas `127.0.0.1`.
- `DB_PASSWORD` et `DB_ROOT_PASSWORD` ne sont lus qu'**à la première création**
  du volume `dbdata`. Les modifier ensuite ne change pas les mots de passe déjà
  enregistrés dans la base.
- `HTTP_BIND` / `HTTP_PORT` règlent la publication du port web (par défaut
  `127.0.0.1:8098`, accessible depuis la machine seulement).

### 3. Installer les dépendances (conteneurs jetables)

`vendor/` et `public/build` ne sont pas dans le dépôt et **sont indispensables** :
`vendor/` est monté dans le conteneur applicatif, et sans les assets compilés les
pages échouent au rendu sur la directive `@vite(...)`.

```bash
docker run --rm -v "$(pwd)":/app -w /app composer:2 install \
    --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs
docker run --rm -v "$(pwd)":/app -w /app node:22-alpine npm install
docker run --rm -v "$(pwd)":/app -w /app node:22-alpine npm run build
chmod -R a+rwX storage
```

### 4. Démarrer les conteneurs

```bash
docker compose up -d --build
```

Trois conteneurs démarrent (la première construction d'image prend quelques minutes)&nbsp;:

| Conteneur | Rôle |
| --- | --- |
| `ev-app` | PHP-FPM 8.4 (l'application) |
| `ev-nginx` | Serveur web, publié sur `HTTP_BIND:HTTP_PORT` |
| `ev-mariadb` | Base de données MariaDB 11 |

Attendre que MariaDB soit prêt (quelques secondes au premier lancement).

### 5. Initialiser la base

```bash
docker exec ev-app php artisan migrate --force
```

### 6. Vérifier

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8098/login   # 200
```

Pour y accéder depuis une autre machine, mettre `HTTP_BIND=0.0.0.0` dans `.env`
puis `docker compose up -d --no-deps nginx`, ou — mieux — placer un reverse proxy
TLS devant.

### 7. Suite

Créer le premier administrateur et installer la tâche planifiée : voir les
sections « Premier administrateur » et « Tâche planifiée » ci-dessous.

## Données de référence

À la première connexion, aller dans **Administration** et créer :

1. Au moins un **véhicule**, avec ses consommations : `kWh/100 km`,
   `L/100 km essence` et `L/100 km diesel`. Sans ces valeurs, les recharges
   sont exclues du calcul d'équivalence carburant (c'est volontaire : aucune
   consommation n'est supposée par défaut).
2. Les **localisations**, **fournisseurs** et **puissances de borne** utiles —
   ou les créer à la volée depuis le formulaire de saisie via « Autre… ».

## Historique des prix des carburants (optionnel)

Le prix du jour est récupéré automatiquement. Pour disposer aussi de
l'historique et calculer l'équivalence carburant sur des recharges passées :

```bash
docker exec ev-app php artisan fuel-prices:backfill
```

La commande reconstruit les prix quotidiens SP95 / Gazole depuis l'archive
annuelle officielle, du 1er janvier à hier. Sans elle, les dates sans relevé
retombent sur un prix de repli (1,95 €), et les totaux concernés sont signalés
comme estimés dans l'interface.

## Télémétrie du véhicule (optionnel)

La télémétrie vient d'un dongle OBD-II lu par l'application Android
[XPCarData](https://github.com/stevelea/xpcardata), qui publie en MQTT. Il faut donc
un **serveur MQTT** : le `docker-compose.yml` en fournit un (Mosquitto).

1. Choisir un compte MQTT et l'inscrire dans le `.env` :

   ```
   MQTT_USER=ev
   MQTT_PASSWORD=un-mot-de-passe-solide
   ```

2. Créer le fichier de mots de passe du broker (non versionné) :

   ```bash
   set -a; . ./.env; set +a
   docker run --rm -v "$(pwd)/docker/mosquitto:/mosquitto/config" eclipse-mosquitto:2 \
       mosquitto_passwd -b -c /mosquitto/config/passwd "$MQTT_USER" "$MQTT_PASSWORD"
   ```

3. Démarrer le broker et le service d'ingestion :

   ```bash
   docker compose --profile mqtt up -d
   ```

   Le port 1883 est **en clair** : sur Internet, passer par un VPN ou un tunnel TLS
   (par exemple un `stream` nginx avec certificat) plutôt que d'ouvrir le port tel quel.

4. Dans XPCarData, renseigner l'adresse du broker, le compte ci-dessus et un
   identifiant de véhicule (topics `vehicles/<identifiant>/data`, `/charging`,
   `/status`).

5. Saisir ce même identifiant dans `/admin/vehicules`, champ « Identifiant MQTT ».

6. Vérifier que les messages arrivent, puis qu'ils sont ingérés :

   ```bash
   docker compose logs --tail 20 mqtt-ingest
   docker exec ev-app php artisan telemetry:ingest-mqtt
   ```

## Base des bornes de recharge (planificateur)

La page **Planificateur** a besoin de la base nationale IRVE. L'import télécharge
un fichier d'environ 150 Mo, l'agrège en stations et le range en base :

```bash
docker exec ev-app php artisan irve:import
```

Compter trois à quatre minutes pour environ 57 000 stations. Une tâche planifiée
le rejoue chaque lundi à 4 h 30 ; il n'y a rien d'autre à configurer, aucune clé
n'est nécessaire.

## Premier administrateur

L'application n'a pas d'inscription : l'accès se fait par email et mot de passe,
et les comptes sont gérés par les administrateurs. Créer le premier en ligne de
commande (12 caractères minimum pour le mot de passe)&nbsp;:

```bash
docker exec -it ev-app php artisan user:create admin@exemple.fr --admin
```

Se connecter ensuite sur `/login` ; les autres comptes se créent depuis
**Administration &rarr; Utilisateurs**.

Le `.env` porte `EV_OWNER_EMAIL`, le compte auquel la migration rattache les
données existantes lors d'une mise à niveau depuis une base mono-compte (sans
effet sur une installation neuve).

## Tâche planifiée

L'application a besoin que son planificateur tourne **chaque minute** (ingestion de
la télémétrie, synchronisation Xpeng, prix des carburants, import hebdomadaire des
bornes…). `python3 scripts/install.py --install-cron` l'installe en root&nbsp;; à la
main, ajouter à la crontab (`crontab -e`)&nbsp;:

```
* * * * * cd /chemin/vers/ev && docker compose exec -T app php artisan schedule:run >/dev/null 2>&1
```

## Mise à jour

```bash
git pull
docker run --rm -v "$(pwd)":/app -w /app composer:2 install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs
docker run --rm -v "$(pwd)":/app -w /app node:22-alpine npm install
docker run --rm -v "$(pwd)":/app -w /app node:22-alpine npm run build
docker exec ev-app php artisan migrate --force
docker exec ev-app php artisan view:clear
```

Le code source est monté en bind dans le conteneur : un `git pull` suffit pour
le PHP et les vues, sans reconstruire l'image. Ne reconstruire l'image
(`docker compose build app`) que si les dépendances Composer ou le Dockerfile
ont changé.

## Dépannage

**Erreur `Unable to locate file in Vite manifest`**
Les assets n'ont pas été construits (étape 3), ou un nouveau point d'entrée JS a
été ajouté sans être déclaré dans `input:` de `vite.config.js`.

**`SQLSTATE[HY000] [2002] Connection refused`**
MariaDB n'a pas fini de démarrer, ou `DB_HOST` a été mis à `127.0.0.1` au lieu
de `mariadb`.

**Une modification de vue n'apparaît pas**
Vider le cache des vues :

```bash
docker exec ev-app php artisan view:clear
```
