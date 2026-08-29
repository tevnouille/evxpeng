# CLAUDE.md

Notes de travail pour les agents. Complète le [README](README.md), qui décrit
le projet ; ce fichier décrit **comment y travailler** et les pièges rencontrés.

## Déploiement : pas de build d'image à chaque modification

L'app tourne sur hostingtools dans `/var/docker/ev`, et l'arbre source est
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
cd /var/docker/ev && sudo docker run --rm -v "$(pwd)":/app -w /app node:20-alpine npm run build
```

`public/build` est dans `.gitignore` : les assets compilés ne sont pas
versionnés, il faut donc rebuilder après un clone.

Tout nouveau point d'entrée JS doit être ajouté à `input:` dans
`vite.config.js`, sinon `@vite(...)` échoue au rendu.

### docker-compose 1.29.2

La version installée plante avec `KeyError: 'ContainerConfig'` sur un
`up -d`/`restart` d'un conteneur existant. Contournement systématique :

```bash
sudo docker-compose rm -sf <service> && sudo docker-compose up -d --no-deps <service>
```

Cette version ignore aussi certaines clés récentes (`cgroup:` par exemple).

## Tester une page sans passer par le passkey

`ev-gate` (la passerelle passkey) écoute sur `127.0.0.1:8096` et redirige en 302
tout ce qui n'est pas authentifié — donc un `curl` direct ne teste rien d'utile.
Pour obtenir le HTML réellement rendu, taper le nginx applicatif dans le réseau
`ev-net` :

```bash
sudo docker run --rm --network ev-net curlimages/curl:latest -s http://ev-nginx/ma-page > /tmp/page.html
```

Attention : `curl -o fichier` écrirait **dans le conteneur jetable**. Toujours
utiliser une redirection shell (`> /tmp/...`) pour récupérer le fichier sur
l'hôte.

## Git

Le remote est un GitLab auto-hébergé sur la même machine :
`ssh://git@gitlab-local:2222/root/ev.git`. L'alias `gitlab-local` est défini
dans le `~/.ssh/config` de l'utilisateur `claudecode`, donc **ne jamais préfixer
les commandes git par `sudo`** — sudo perd cette configuration SSH et le push
échoue.

Si le push échoue avec `Connection refused` sur le port 2222, GitLab est
simplement éteint (l'utilisateur l'arrête quand il ne s'en sert pas, ce n'est
pas un incident) :

```bash
cd /var/docker/gitlab && sudo docker-compose up -d
```

Compter 2-3 minutes avant que `gitlab-ctl status` affiche tous les services en
`run:`.

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

## Télémétrie ABRP : deux secrets à ne pas confondre

La récupération du niveau de charge passe par l'API Iternio (A Better Routeplanner),
et elle demande **deux valeurs distinctes** que la documentation d'ABRP nomme mal :

| Valeur | Où on la trouve | Où elle est stockée |
|---|---|---|
| **API key** (« Telemetry-Only », gratuite) | abetterrouteplanner.com &rarr; Manage your telemetry API keys | `ABRP_API_KEY` dans le `.env` |
| **User token** (un par véhicule) | ABRP &rarr; Settings &rarr; Car model &rarr; le véhicule &rarr; **Live data** &rarr; **Generic** | colonne `vehicles.abrp_token` |

Les messages d'erreur permettent de savoir laquelle est en cause : `401 Unauthorized Key`
désigne la clé, `401 Unauthorized Token` désigne le token. Un `200` sur
`/tlm/get_carmodels_list` (qui ne demande pas de token) confirme que la clé seule est bonne.

Autres pièges relevés :

- L'API répond **HTTP 200 même en erreur applicative** : c'est le champ `status` du JSON
  qui fait foi, jamais le code HTTP seul.
- `get_latest_telemetry` **n'existe pas** (404) ; l'endpoint est `get_telemetry`.
- La doc lisible n'est pas la page web mais le JSON de la collection Postman :
  `https://documenter.gw.postman.com/api/collections/7396339/SWTK5a8w`
- `env_file` dans docker-compose injecte les variables **à la création du conteneur** :
  après ajout d'une clé dans `.env`, un `config:clear` ne suffit pas, il faut recréer
  `ev-app` (`docker-compose rm -sf app && docker-compose up -d --no-deps app`).

## Télémétrie : aucune rétention, jamais

Les relevés de `vehicle_telemetries` sont conservés **indéfiniment**, y compris la
colonne `raw` qui archive la réponse complète d'ABRP. C'est une demande explicite de
l'utilisateur : ne rien purger.

**Ne pas ajouter de purge, de `prune`, ni de tâche de nettoyage** sur cette table.

Les `subDays()` que l'on trouve dans `MyVehicleController`, `PendingTelemetryCharges`
et `ChargingCurveController::state()` sont des **fenêtres de lecture** pour l'affichage
et la détection — elles ne suppriment rien. Le volume reste négligeable : une ligne par
relevé, quelques centaines d'octets, et la voiture ne remonte un point qu'à la minute
au maximum, uniquement en charge.

## Scheduler

Depuis l'ajout de la télémétrie, l'application a un vrai scheduler Laravel
(`routes/console.php`), déclenché par une entrée cron sur l'hôte :

```
* * * * * sudo /usr/bin/docker exec ev-app php artisan schedule:run >/dev/null 2>&1
```

C'est l'endroit où brancher les prochaines tâches périodiques.

## Alertes

Pas de SMTP fonctionnel sur hostingtools (`MAIL_MAILER=log`). Pour notifier
l'utilisateur, utiliser l'API SMS Free Mobile **en GET** (le POST renvoie 400
malgré la documentation).
