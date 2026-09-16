# CLAUDE.md

Notes de travail pour les agents. Complète le [README](README.md), qui décrit
le projet ; ce fichier décrit **comment y travailler** et les pièges rencontrés.

## Déploiement : pas de build d'image à chaque modification

L'app tourne sur le **VPS Hostinger** (depuis le 2026-09-10 ; auparavant sur
hostingtools) dans `/var/docker/ev`, et l'arbre source est
**monté en bind** dans le conteneur (`app/`, `config/`, `database/`,
`resources/`, `routes/`, `bootstrap/app.php`, `public/build`). Modifier un
fichier PHP ou Blade est donc pris en compte immédiatement, sans reconstruire
l'image ni redémarrer le conteneur.

Après une modification de vue, vider le cache :

```bash
sudo docker exec ev-app php artisan view:clear
```

Le code n'est pas édité depuis un poste distant puis déployé : on travaille
directement dans `/var/docker/ev` sur le serveur.

### Assets front

Le conteneur `ev-app` n'a **pas** npm. Le build passe par un conteneur jetable :

```bash
cd /var/docker/ev && sudo docker run --rm -v "$(pwd)":/app -w /app \
  -u "$(id -u):$(id -g)" -e HOME=/tmp -e npm_config_cache=/tmp/.npm \
  node:20-alpine npm run build
```

**Toujours avec `-u "$(id -u):$(id -g)"`.** Sans lui, le conteneur écrit
`public/build` en root — constaté le 2026-09-13 après un rebuild manuel sans
ce flag : les fichiers restent lisibles (le site continue de fonctionner) mais
`server-update.py` (voir plus bas), qui tourne sans privilèges, ne peut plus
les effacer pour reconstruire à son tour. L'échec ne dit pas pourquoi — il
faut aller vérifier `ls -la public/build`. `HOME` et `npm_config_cache` sont
nécessaires pour la même raison : l'uid emprunté n'a pas de répertoire
personnel dans l'image, npm y écrirait sinon son cache dans `/` et échouerait.

`public/build` est dans `.gitignore` : les assets compilés ne sont pas
versionnés, il faut donc rebuilder après un clone.

Tout nouveau point d'entrée JS doit être ajouté à `input:` dans
`vite.config.js`, sinon `@vite(...)` échoue au rendu.

### Dépendances PHP : `composer update` est à chaud

`vendor/`, `composer.json` et `composer.lock` sont **montés en bind** eux aussi
(depuis le 2026-09-06). Une mise à jour Composer ne demande donc plus de
reconstruire l'image ni de recréer le conteneur :

```bash
sudo docker exec -e COMPOSER_ALLOW_SUPERUSER=1 ev-app \
  composer update --no-dev --optimize-autoloader <paquets nommés>
sudo docker exec ev-app sh -c 'php artisan config:clear && php artisan view:clear && php artisan route:clear'
```

**Nommer les paquets un à un.** `composer.json` ne contraint que
`laravel/framework`, `laravel/tinker` et `php` : tout le reste est transitif, et
un `composer update` global emporte les changements de majeure sans prévenir.

Retour arrière : restaurer `composer.lock` puis `composer install`.

**Avant de monter ou de faire confiance à un `vendor/` d'hôte, vérifier qu'il
correspond au lock** (`vendor/composer/installed.json`). Un rollback de mise à
jour restaure `composer.lock` mais **pas** `vendor/` : le nôtre a porté 32
paquets divergents pendant une semaine sans que rien ne le signale.

### docker-compose : utiliser `docker compose`, jamais `docker-compose`

La v1.29.2 est toujours installée et plante avec `KeyError: 'ContainerConfig'`
sur un `up -d`/`restart` d'un conteneur existant — elle **renomme le conteneur
et le laisse arrêté**, ce qui a coupé le site quatre minutes le 2026-08-30.

Le **plugin v2** est installé depuis le 2026-09-06
(`/usr/libexec/docker/cli-plugins/docker-compose`) : utiliser `sudo docker
compose` (sans tiret). Après toute recréation du conteneur `app`, **redémarrer
nginx** — il garde en cache l'adresse IP de l'upstream et renverrait 502 :

```bash
sudo docker compose up -d app && sudo docker restart ev-nginx
```

### Mise à jour depuis `/admin/serveur`

Page réservée admin qui montre l'inventaire des dépendances (composer/npm/apt,
relevé par `scripts/server-inventory.py`) et permet de déclencher une montée
npm ou apt directement depuis le navigateur. Composer en est exclu : la montée
suppose de reconstruire l'image, et l'outillage de cet hôte ne sait pas le
faire sans couper le site (voir plus haut) — ces montées-là restent manuelles.

Le conteneur ne peut rien exécuter sur l'hôte : il dépose un fichier témoin
dans `storage/app/system/` (`server-update.request`), qu'une tâche cron de
l'hôte (`server-update.py`, chaque minute) ramasse et applique, en écrivant
son résultat dans `server-update.log`. Ce découplage a un prix : **tout ce que
le script touche dans `storage/app/system/` (le journal, `backup/`,
`public/build/`) doit appartenir à l'utilisateur qui exécute le cron
(`claudecode`), pas à un autre.** Constaté le 2026-09-14 : trois de ces
fichiers appartenaient encore à un autre uid (résidu d'avant la migration du
2026-09-10, plus `public/build` cassé par un rebuild manuel sans `-u`, voir
plus haut) — la montée de react restait indéfiniment "en attente" sans qu'
aucune erreur ne remonte nulle part, le script échouant en silence à chaque
tentative avant même d'avoir pu écrire pourquoi dans son propre journal.

Diagnostic si une demande reste bloquée :

```bash
# Rien ne doit tourner : sinon la mise a jour est reellement en cours, pas bloquee.
sudo docker ps | grep node

# Fichiers qui n'appartiennent pas au bon utilisateur : cause la plus probable.
find /var/docker/ev/storage/app/system -not -user claudecode

# Le verrou se considere perime au bout de 10 minutes (STEP_TIMEOUT) : au-dela,
# sans processus reellement en cours, il est sans risque de le supprimer a la main.
rm /var/docker/ev/storage/app/system/server-update.lock
```

## Tester une page sans passer par le passkey

**Depuis le 2026-09-10 (VPS Hostinger)**, `ev-nginx` publie directement sur
`127.0.0.1:8098` (port choisi côté hôte car 8095-8097 étaient déjà pris par
d'autres projets du VPS — voir `docker-compose.yml`) : plus besoin de rejoindre
un réseau Docker, un `curl` local suffit. La ligne ci-dessous décrivait
l'ancien hébergement hostingtools, où rien n'était publié et où il fallait
taper `ev-nginx` dans le réseau `ev-net` — méthode encore possible aujourd'hui
(`ev-net` existe toujours sur le VPS) mais inutilement détournée quand le port
est directement accessible.

L'application exige l'en-tête posé par la passerelle : sans lui, `IdentifyUser`
répond 403. Pour obtenir une page rendue comme pour un utilisateur :

```bash
curl -s -H "Host: ev.lolinux.org" -H "X-SSO-Email: atran@lolinux.org" \
  http://127.0.0.1:8098/ma-page
```

`Host:` est nécessaire même en local : `trustProxies` et les URL générées par
Laravel (`route()`, `asset()`...) en dépendent, et un `Host` absent ou faux
produirait des liens cassés dans la page rendue — piège rencontré en vérifiant
le point de données `?flux=batterie` de `/infoCar` (2026-09-13).

### Vue dans un vrai navigateur

Pour une page protégée par la passkey (contrairement à `/infoCar`, seule
adresse qui s'en passe déjà et se visite directement dans
`https://ev.lolinux.org/infoCar`) : un conteneur nginx jetable qui pose
l'en-tête à la place de la passerelle. **Éviter le port 8099** sur ce VPS,
utilisé par le projet `patrimoine` (`127.0.0.1:8099`) — un choix de port en
conflit ferait simplement échouer le démarrage du conteneur jetable, sans rien
casser côté patrimoine, mais autant en changer plutôt que de tomber dessus par
surprise. `proxy_set_header Host $http_host` est indispensable, sinon les
redirections cassent. **Ce conteneur contourne l'authentification du site : le
supprimer dès la vérification finie**, dans le même enchaînement de commandes.

```bash
sudo docker rm -f ev-devproxy && sudo rm -rf /var/docker/ev-devproxy
```

## CHANGELOG.md est genere

`App\Support\Changelog` reste la **seule source** du journal des nouveautes —
tenu a la main, redige pour qui se sert de l'application. `CHANGELOG.md` en est
une vue, regeneree apres tout ajout :

```bash
sudo docker run --rm -v /var/docker/ev:/app -w /app -u 0:0 ev-app \
    php artisan changelog:export
```

**Pas via `docker exec ev-app`** (le conteneur qui tourne) : la racine du depot
n'y est pas montee, le fichier serait ecrit dans la couche du conteneur. La
commande ci-dessus lance un conteneur jetable a partir de la meme image
(`ev-app`, confondre les deux noms est facile), avec la racine du depot montee
en `/app` — c'est ce qui fait la difference. `changelog:export --check` signale
que le fichier est perime sans le reecrire.

Ne pas editer `CHANGELOG.md` a la main : la prochaine regeneration l'ecraserait.

## Git

Le remote est un GitLab auto-hébergé sur la même machine :
`ssh://git@gitlab-local:2222/root/ev.git`. L'alias `gitlab-local` est défini
dans le `~/.ssh/config` de l'utilisateur `claudecode`, donc **ne jamais préfixer
les commandes git par `sudo`** — sudo perd cette configuration SSH et le push
échoue.

L'authentification passe par une **clé de déploiement dédiée**,
`~/.ssh/id_ev_deploy`, déclarée en écriture sur le seul projet `root/ev`. Elle
n'ouvre rien d'autre : un `git ls-remote` avec cette clé échoue sur les autres
dépôts du GitLab. Le `ssh gitlab-local` répond « Welcome to GitLab, @root! » —
c'est le propriétaire de la clé, **pas** son périmètre, ne pas s'en alarmer.

**Pousser fait partie du travail, ce n'est pas une formalité de fin de session.**
La sauvegarde quotidienne de ce projet ne couvre **pas** le code source
(`ev.conf` n'archive que la base, `.env`, `docker-compose.yml`, `public/build`
et `storage/app/system`), et il n'existe aucune copie locale sur le Mac. Un
commit non poussé n'a donc **qu'un seul exemplaire** ; poussé, il entre dans la
sauvegarde de GitLab, elle-même rapatriée hors du VPS chaque nuit.

## Pièges Blade / Bulma rencontrés

- **`@if` collé à un mot n'est pas interprété.** `utiles@if (...)` produit une
  erreur `unexpected token "endif"` : Blade exige que la directive ne soit pas
  précédée d'un caractère de mot. Toujours laisser une espace ou un retour à la
  ligne avant `@if`.
- **En-tête de tableau figé** : la classe `.table-scroll` (dans
  `resources/css/app.css`) combine `max-height` + `overflow-y` sur le conteneur
  et `position: sticky` sur les `th`. Le `background-color` opaque sur les `th`
  est obligatoire, sinon les lignes défilent visiblement sous l'en-tête.
- **nginx `if` + `auth_request`** ne font pas bon ménage dans les gates passkey :
  utiliser le motif `error_page 418 = @named_location` plutôt qu'un `if`
  englobant un `proxy_pass`.

## Équivalence carburant : deux « valeurs par défaut » à ne pas confondre

`FuelPriceService` calcule le coût équivalent essence/diesel par recharge.
Deux mécanismes distincts s'y ressemblent mais n'ont pas le même statut :

- **Consommations du véhicule** (`kwh_per_100km`, `essence_l_per_100km`,
  `diesel_l_per_100km` sur `vehicles`) : **aucune valeur par défaut**. Une
  recharge dont le véhicule n'est pas renseigné est simplement exclue du calcul,
  et comptée à part (`configured_sessions`). Ne pas réintroduire de constantes
  de repli ici : c'est un choix explicite, une estimation inventée fausserait
  silencieusement les totaux.
- **Prix du carburant** : `DEFAULT_ESSENCE_PRICE` / `DEFAULT_DIESEL_PRICE`
  (1,95 €) servent légitimement de repli quand la table `fuel_prices` n'a pas de
  relevé pour la date de la recharge. Les sessions concernées sont signalées via
  `known_price_sessions` / `estimated`.

## Données de référence externes

Les courbes de recharge (`resources/data/charging-curves/*.json`) viennent
d'evkx.net. Ce sont des données de référence figées, pas des données
utilisateur : un fichier JSON par modèle, pas de table en base.

Vérifier systématiquement si la page source porte la mention **« Données
estimées »** : certaines variantes (le G6 MY2025 par exemple) publient des
courbes modélisées très éloignées du réel (425 kW annoncés, soit 5,26 C). Le
champ `estimated` du JSON déclenche un encart d'avertissement sur la page.

Les capacités brute/nette proviennent de la fiche `specifications/` du même
site, pas de la page de courbe qui n'affiche que la brute.

## Télémétrie : le boîtier OBD publie en MQTT

**A Better Routeplanner a été retiré le 2026-09-02.** Il n'a jamais été une
source mais un relais : tous les relevés reçus portaient `telemetry_type =
obdble`, c'est-à-dire le boîtier, dont les données faisaient un détour
appauvrissant par un cloud tiers. `AbrpClient`, la commande `telemetry:poll` et
la colonne `vehicles.abrp_token` n'existent plus. Les relevés historiques sont
conservés et gardent leur source d'origine.

La chaîne actuelle :

```
voiture → dongle OBD → XPCarData (Android) → broker MQTT de la maison
        → conteneur mqtt-ingest → telemetry:ingest-mqtt → vehicle_telemetries
```

**Pas de client MQTT en PHP, et c'est structurel** : ajouter une dépendance
Composer est certes redevenu simple, mais un client MQTT devrait tourner en
permanence, ce qu'un PHP-FPM ne fait pas. Un conteneur `mosquitto_sub` dépose
les messages dans `storage/app/system/mqtt-inbox.jsonl`, et la commande les relit
**à partir d'un décalage en octets** conservé dans `mqtt-inbox-state.json`.

Points à connaître avant de toucher à l'ingestion :

- **Les champs tournent.** Le boîtier interroge les PID à tour de rôle : un
  message ne porte qu'une poignée de valeurs (sur douze messages, l'odomètre
  n'apparaissait qu'une fois). Les relevés sont recomposés à partir des
  dernières valeurs connues, **aucune de plus de cinq minutes** — un report
  illimité figerait l'odomètre et ferait croire à une voiture à l'arrêt.
- **Le fichier de décalage est écrit par deux identités** : root via le
  planificateur (`docker exec`), l'uid 82 via le bouton « Mettre à jour les
  informations » (`Artisan::call`). Il est donc remis en `0664` après chaque
  écriture, et un échec dégrade en avertissement au lieu de lever une exception —
  sinon la requête web répond 500.
- **Tout champ venant d'un PID peut sortir n'importe quoi** : `cumulativeCharge`
  a annoncé 17 124 kWh pour 1,2 point de batterie. L'énergie mesurée est
  confrontée à ce que le gain de niveau permet, et laissée vide au-delà.
- `vehicles.mqtt_client_id` rattache un topic à une voiture
  (`vehicles/{id}/data`, `/charging`, `/status`).

**Le boîtier publie aussi des recharges toutes faites** sur `vehicles/{id}/charging` :
énergie **mesurée** au compteur du BMS, courbe complète, position. Elle prime sur
l'estimation par SoC × capacité, qui s'est révélée 29 % sous le compte.

## API Xpeng (`/ma-voiture/dataapixpeng`) — donnée constructeur, pas de télémétrie

Depuis le 2026-09-16, Xpeng expose une API officielle
(`App\Services\XpengClient`, un seul endpoint `/oauth2/queryData`) —
**totalement distincte** du boîtier OBD/MQTT ci-dessus. À ne pas confondre :

- **Ce n'est pas une API de télémétrie en direct.** Un premier appel dépose
  une tâche d'export chez Xpeng et répond `"DataFileExporting"` ; les appels
  suivants (identiques côté appelant) interrogent son état via un `recordNo`
  mis en cache **côté Xpeng**, jamais exposé ici, jusqu'à une URL de
  téléchargement valable **~30 secondes seulement**.
- **Quota strict : 5 soumissions/24h** par couple utilisateur-entreprise.
  `App\Console\Commands\SyncXpengData` (`xpeng:sync`) ne se déclenche donc
  qu'une fois par jour (`routes/console.php`, 06h15) — prudent tant que le
  comportement réel d'une resoumission pendant un export « en cours » n'est
  pas vérifié en conditions réelles (le guide suggère qu'elle est gratuite,
  sans le garantir explicitement).
- **La commande patiente sur place** (jusqu'à 10 relances, 8 s d'écart,
  recommandation du guide d'intégration) plutôt que d'attendre le lendemain :
  la fenêtre de 30 s pour télécharger le fichier ne survivrait pas à un
  report au jour suivant.
- **Piège de signature** : seules deux clés participent à la chaîne signée —
  `body` (le JSON du corps **tel qu'envoyé**, pas re-sérialisé) et `nonce` —
  pas chaque champ du corps un par un, contrairement à ce qu'un résumé rapide
  de la doc pourrait laisser croire. `XpengClient::queryData()` construit la
  chaîne de signature à partir des mêmes octets que ceux réellement postés,
  jamais d'un objet ré-encodé séparément.
- **Le format du fichier n'est pas documenté par Xpeng, mais un export manuel
  du portail (2026-09-16, avant l'obtention d'`appId`/`appSecret`) a permis de
  le reverse-engineer** : un zip de 3 CSV par véhicule
  (`..._veh_driving_operation_di`, `..._power_energy_di`, `..._status_di`),
  chacun scindé en plusieurs fichiers `_partN` **au-delà d'un nombre de
  lignes fixe** (constaté : 1 000 000, pas un découpage par date — un même
  jour peut chevaucher le fichier principal et sa suite), à la **seconde**
  (colonne `timer`, epoch Unix). `App\Services\XpengExportParser` lit ce
  format ; `App\Console\Commands\ImportXpengExport` (`xpeng:import
  <chemin_zip>`) permet de rejouer un export à la main.
- **Plusieurs champs bruts portent une valeur-sentinelle** (« signal absent »)
  plutôt que de rester vides — `255` pour les champs codés sur un octet
  (`esp_vehspd`, `ldcu_bms_soc_disp`), des valeurs proches de `1638`/`1677`
  ou `215` pour d'autres (`ldcu_chrgpwr`, `ldcu_dstbatdisp_dynamic`,
  `bms_batttempmax_gb`…). Repérées à l'œil sur l'export du 2026-09-16, pas
  documentées : `XpengExportParser::VALEURS_VALIDES` n'est qu'une
  approximation prudente, à corriger si un futur export révèle d'autres
  anomalies. `bms_battvolt`/`bms_battcurr` (tension/courant batterie) sont
  restés **hors du schéma retenu** : leurs valeurs ne correspondaient à rien
  de physiquement plausible (jusqu'à 1023 V) sans qu'un filtrage évident ne
  se dégage.
- **Stocké agrégé à la minute** (`xpeng_telemetries`), jamais à la seconde :
  un mois d'export fait ~1 million de lignes par jeu de données, ce qui
  romprait la règle « jamais purger » déjà en place pour `vehicle_telemetries`
  (pensée pour un volume négligeable, une ligne/minute au plus). Les fichiers
  bruts, eux, restent archivés tels quels (`storage/app/xpeng/`) : la finesse
  seconde par seconde n'est jamais perdue, seulement pas mise en base.
  Décision prise avec l'utilisateur le 2026-09-16.
- **La page (`/ma-voiture/dataapixpeng`) réutilise le module de graphiques de
  Statistiques OBD** (`resources/js/obd-stats.js`, `canvas.obd-chart` +
  `data-labels`/`-values`/`-unit`) plutôt que d'en écrire un second :
  générique, déjà éprouvé, rien à dupliquer. Fenêtre affichée limitée à 30
  jours (`XpengDataController::JOURS_AFFICHES`) — la table grossit chaque
  jour, contrairement à Statistiques OBD qui navigue par mois/jour.
- **`appId`/`appSecret` restent à renseigner** (`XPENG_APP_ID`/`XPENG_APP_SECRET`
  dans `.env`, vides au 2026-09-16) — obtenus par inscription développeur
  auprès de `glo.open@xpeng.com`, distincts des quatre champs d'autorisation
  utilisateur (`openId`, `accessToken`, `enterpriseName`, `scopeCode`) reçus
  par email et déjà en place. Sans eux, `xpeng:sync` échoue proprement au
  démarrage plutôt que de tenter un appel non signé.
- **Piège déjà connu, retombé ici** : modifier `.env` ne suffit pas, il
  injecte les variables via `env_file` au démarrage du conteneur — recréer
  `ev-app` (`docker compose up -d --force-recreate app`) puis redémarrer
  `ev-nginx`, sinon `config('services.xpeng.*')` reste sur les anciennes
  valeurs (vues : `null` partout) malgré un `config:cache` refait.

## Page publique `/infoCar`

Seule adresse servie **sans passkey**, pour le navigateur embarqué de la
voiture. **L'exemption se déclare à deux endroits, et les deux sont
nécessaires** :

1. Le vhost du **nginx de l'hôte du VPS Hostinger**,
   `/etc/nginx/sites-available/ev.lolinux.org` — hors de ce dépôt, convention
   du VPS (plusieurs projets y partagent le même nginx, aucun n'y versionne son
   vhost). **Depuis la migration du 2026-09-10** : sur l'ancien hébergement
   hostingtools, cette exemption vivait dans un conteneur dédié `ev-gate`,
   décommissionné avec le reste de l'ancienne pile — `docker/gate/`, qui en
   gardait une copie de référence, est retiré du dépôt le 2026-09-13 (plus
   rien à quoi la comparer) ; l'historique complet reste dans `git log` ;
2. `App\Http\Middleware\IdentifyUser::PUBLIC_PATHS` — sinon ce middleware,
   appliqué à tout le groupe `web`, répond 403.

**La passerelle ne protège pas en filtrant, elle protège en écrasant** :
`proxy_set_header X-SSO-Email $sso_email`. Toute nouvelle `location` doit poser
cet en-tête elle aussi — **à vide si elle est publique** — faute de quoi nginx
transmet celui du client et n'importe qui se déclare propriétaire d'un compte.
Vérifier toute exemption en forgeant l'en-tête depuis l'extérieur.

La page est **entièrement autonome** : aucune feuille de style ni script
externe, les assets étant eux aussi derrière la passerelle. Elle doit tenir dans
un écran qu'on ne peut ni défiler ni dézoomer. Sans build ni transpilation, le
JS embarqué est volontairement **ES5** (`var`, pas d'arrow functions ni de
`forEach` sur les NodeList) : le navigateur de la voiture est de provenance
inconnue, mieux vaut ne rien supposer de récent.

Elle expose délibérément plus que la seule batterie — position, communes
traversées — comme le détaille le docblock d'`InfoCarController`. Protégée en
plus par un code à 6 chiffres (`config('services.info_car.pin')`), un filtre
contre qui tomberait sur l'adresse sans la connaître, pas une vraie identité.

### Poser un point de données sans casser l'exemption

**L'exemption nginx ne matche que le chemin exact `^/infocar/?$`, jamais un
préfixe.** Une sous-route (`/infoCar/quelquechose`) retomberait derrière la
passkey — la requête n'atteindrait même pas Laravel avec l'en-tête d'identité
vidé, elle serait redirigée vers `pk.lolinux.org`.

Le graphique de batterie du jour (2026-09-13) a donc besoin de données fraîches
sans en ajouter : il réutilise le **même chemin** `GET /infoCar`, différencié
par une chaîne de requête (`?flux=batterie`) plutôt qu'une route à part — le
chemin ne change pas, donc l'exemption s'applique toujours, et
`IdentifyUser::PUBLIC_PATHS` compare `$request->path()`, qui ignore lui aussi
la chaîne de requête. **Vérifié sur le domaine public**, pas seulement en
interne : `403 {"erreur":"verrouille"}` sans le cookie du code, `200` en JSON
avec — jamais de redirection vers la passerelle passkey dans un cas comme dans
l'autre.

Le contrôle du code à 6 chiffres est extrait dans une méthode dédiée
(`deverrouille()`), réutilisée par la vue HTML et par ce point de données :
sans ce doublon, le second aurait été une porte laissée grande ouverte à côté
de la première.

## Télémétrie : aucune rétention, jamais

Les relevés de `vehicle_telemetries` sont conservés **indéfiniment**, y compris la
colonne `raw` qui archive le relevé complet — hier la réponse d'ABRP, aujourd'hui
le message du boîtier. C'est une demande explicite de l'utilisateur : ne rien purger.

**Ne pas ajouter de purge, de `prune`, ni de tâche de nettoyage** sur cette table.

Les `subDays()` que l'on trouve dans `MyVehicleController`, `PendingTelemetryCharges`
et `ChargingCurveController::state()` sont des **fenêtres de lecture** pour l'affichage
et la détection — elles ne suppriment rien. Le volume reste négligeable : une ligne par
relevé, quelques centaines d'octets, et la voiture ne remonte un point qu'à la minute
au maximum, uniquement en charge.

## Scheduler

Scheduler Laravel (`routes/console.php`), déclenché par une entrée cron sur l'hôte :

```
* * * * * sudo /usr/bin/docker exec ev-app php artisan schedule:run >/dev/null 2>&1
```

**L'ordonnancement sous la minute fonctionne avec ce cron** : quand une tâche
sub-minute est déclarée, `schedule:run` boucle jusqu'à la fin de la minute
courante (vérifié, le processus reste ~57 s). Les filtres `when`/`skip` ne sont
évalués qu'aux instants dus, pas à chaque tick.

La cadence de la collecte suit l'état du véhicule, **et la page « Ma voiture »
suit la même table** (`VehicleState::REFRESH_SECONDS`) : 5 s en charge, 20 s en
roulage, 60 s à l'arrêt. Rafraîchir l'écran plus vite que la collecte ne
montrerait rien de neuf — les trois doivent bouger ensemble.

**Augmenter la fréquence d'échantillonnage réveille des faux positifs.** En
passant à 15 s, `TelemetrySessionDetector` a pris chaque scintillement de
`isCharging` en roulage pour une recharge d'une minute à zéro kWh. D'où le seuil
`PendingTelemetryCharges::MIN_KWH`. Vérifier les détecteurs après toute
accélération.

## Planificateur : pourquoi pas l'API Iternio

L'API de planification d'Iternio (`https://api.iternio.com/2/plan`, celle qui fait
tourner ABRP) est **commerciale** : frais de mise en service puis facturation au
plan délivré. La clé « Telemetry-Only » utilisée pour la télémétrie n'y donne
aucun droit — testé, elle répond `403 {"message":"Feature plan is not available"}`,
et le reste de l'API v2 répond `403 missing feature`. Inutile de réessayer sans
contrat.

Le planificateur est donc calculé localement, à partir de sources gratuites :

- **OSRM** pour l'itinéraire (`services.osrm.base_url`, serveur de démonstration
  public par défaut) ;
- **base nationale IRVE** de data.gouv.fr pour les bornes, importée en base par
  `php artisan irve:import` dans `charging_stations` (une ligne par station, pas
  par point de charge) ;
- **Base Adresse Nationale** puis Nominatim pour le géocodage ;
- la **courbe de recharge** du véhicule pour les temps de charge.

Piège rencontré : OSRM, data.gouv et Nominatim répondent **403 à un client sans
User-Agent**, ce que Guzzle est par défaut. D'où `App\Services\HttpUserAgent`, par
lequel tous ces appels passent.

Le modèle de bridage de puissance vit dans `App\Services\ChargeCurveSimulator`,
partagé avec `ChargingCurveController` : deux implémentations de la même formule
finiraient par diverger.

## Libelles IRVE : c'est la position qui fait foi

Le fichier consolide contient des lignes dont les coordonnees ne correspondent pas
a l'adresse declaree — « IONITY Tavel Nord », annonce sur l'A9 dans le Gard, porte
des coordonnees sur l'A6 dans le Rhone. Le champ `consolidated_is_lon_lat_correct`
existe mais vaut `False` sur un quart du fichier : filtrer dessus amputerait la
base bien au-dela des vrais defauts.

Le parti pris est donc d'afficher la borne la ou ses coordonnees la placent, et de
donner partout des liens Google Maps et Waze pointant sur ces coordonnees. Les
pages le disent : le libelle vient tel quel de la base, c'est la position qui fait
foi.

## Cloisonnement par utilisateur

Depuis l'ouverture a plusieurs passkeys, chaque compte ne voit que ses donnees.
Trois pieces :

- `App\Http\Middleware\IdentifyUser` lit l'en-tete `X-SSO-Email` pose par la
  passerelle, cree le compte a la premiere visite et refuse la requete (403) si
  l'en-tete manque. La passerelle ecrase cet en-tete avec `proxy_set_header` :
  un client ne peut pas se l'inventer.
- `App\Support\CurrentUser` porte l'utilisateur de la requete.
- `App\Models\Concerns\BelongsToUser` ajoute un **scope global** et remplit
  `user_id` a la creation. Scope global et non `where` disperses : on ne peut pas
  l'oublier, et une fuite entre comptes demanderait de le retirer explicitement.

**`user_id` n'est jamais `fillable`** : le proprietaire d'une ligne ne doit pas
pouvoir venir d'un champ de formulaire. La copie de trajet, qui cree
deliberement une ligne pour quelqu'un d'autre, l'affecte directement sur le
modele — le hook `creating` utilise `??=` et respecte donc une valeur posee.

**Hors requete web, `CurrentUser::id()` vaut null et le scope ne s'applique pas.**
C'est voulu : `telemetry:poll` doit voir les vehicules de tous les comptes. Le
corollaire est qu'une commande artisan travaille sur toute la base — y penser
avant d'ecrire une commande qui modifie des donnees.

### Se deconnecter, c'est deconnecter la passerelle

L'application **n'a pas de session a elle** : l'identite arrive dans l'en-tete a
chaque requete. Il n'y a donc rien a detruire ici. `/deconnexion`
(`LogoutController`) redirige vers `pk.lolinux.org/logout.php`, qui detruit la
session de la passerelle — donc **deconnecte de tous les services qu'elle
protege**, pas seulement de celui-ci. Le menu le dit au survol, « Mon compte »
en toutes lettres. Ne pas essayer d'ajouter une deconnexion « locale » : elle
n'aurait aucun effet, la page suivante reposerait l'identite.

L'adresse de la passerelle est dans `services.passkey.url`
(`PASSKEY_GATEWAY_URL`), pas recopiee dans les vues.

`deconnexion` figure dans `PUBLIC_PATHS` a cote de `infocar`. **Ce n'est pas un
trou** : la passerelle exige toujours un passkey pour atteindre l'adresse, c'est
l'identite *applicative* qui n'y est pas requise. Sans cela, un compte en
attente d'autorisation recevrait un 403 sans aucun moyen d'en changer.

Tables restees communes a dessein : `charging_stations` (IRVE) et `fuel_prices`,
donnees publiques importees. `vehicle_telemetries` et `charge_alerts` sont
cloisonnees par ricochet via leur vehicule.

**L'autorisation d'acces est propre a l'application** (`users.approved_at`). Le SSO
passkey est partage avec tevflix, frigate, emby, cuisine : en retirer quelqu'un
couperait tout. `IdentifyUser` cree bien le compte a la premiere visite — pour que
l'administrateur voie la demande — mais repond 403 tant qu'il n'est pas autorise.
`users.is_admin` reserve `/admin/utilisateurs`.

`RequiresTelemetry` masque et ferme "Ma voiture" et "Deplacements" pour les comptes
sans vehicule relie au boitier. Le critere est la presence d'un `mqtt_client_id`,
pas une liste d'emails : les pages reapparaissent seules le jour ou quelqu'un
renseigne le sien.

**Les relations `chargingSessions`, `vehicles` et `favoriteRoutes` sur `User`
retirent le scope global** (`withoutGlobalScope('user')`) : elles comptent ce que
possede *un* compte donne, pas celui de la requete. Sans cela la page de gestion
afficherait zero partout sauf pour soi.

Les libelles ne sont plus uniques dans l'absolu mais **par compte** (index
`(user_id, name)`), d'ou `uniqueForUser()` dans `ReferenceDataController` :
`unique:locations,name` aurait interdit a un second utilisateur d'avoir sa propre
"Maison".

## Listes de suggestions

`resources/js/suggest.js` porte le mecanisme commun : debounce, navigation
clavier, fermeture au blur, rendu delegue. Trois usages s'appuient dessus —
adresses du planificateur et des favoris (`address-autocomplete.js`), bornes et
fournisseurs du formulaire de recharge (`app.js`).

Toujours une liste maison, **jamais un `<datalist>`** : le navigateur refiltre
lui-meme les options d'un datalist en tenant compte des accents, et masque le
resultat que l'API vient de renvoyer.

La recherche de bornes (`ChargerLookupController`) decoupe la saisie en mots et
exige que **chacun** se retrouve dans une des colonnes, pas forcement la meme :
sans cela "tesla villabe" ne donnait rien, aucune colonne ne contenant les deux.
Le tri met en tete les communes qui commencent par le premier mot.

## Alertes

Pas de SMTP configure pour ce projet (`MAIL_MAILER` absent du `.env`, la
valeur par defaut de Laravel etant `log`) -- vrai sur hostingtools, toujours
vrai depuis la migration sur le VPS, bien qu'un Postfix fonctionnel y tourne
deja pour d'autres projets du meme serveur : ce serait a configurer pour
ev si on voulait vraiment l'utiliser. Pour notifier
l'utilisateur, utiliser l'API SMS Free Mobile **en GET** (le POST renvoie 400
malgré la documentation).

**Les identifiants Free Mobile ne sont pas dans le `.env`** : ils vivent sur la
ligne `users` de chacun (`free_mobile_user`, `free_mobile_password`, cette
dernière chiffrée par le cast `encrypted`), et se renseignent dans `/mon-compte`.
Une clé d'API doit pouvoir être relue pour appeler Free, d'où `encrypted` et non
`hashed`. Corollaire : elle est illisible si `APP_KEY` change.

Il n'y a **aucun repli sur un compte commun** — sans identifiants, pas de SMS.
Un repli ferait sonner le téléphone du propriétaire du `.env` à chaque recharge
de n'importe quel utilisateur.
