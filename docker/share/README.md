# Domaine des liens de partage (`s.lolinux.fr`)

Copie de référence de la configuration réellement servie, montée en bind depuis
`/etc/nginx/sites-available/s.lolinux.fr` — **hors du dépôt**, donc jamais
versionnée jusqu'ici. Ce fichier n'est pas lu par nginx : il existe pour qu'une
modification du vhost laisse une trace dans l'historique, à côté du code qu'il
sert. Même principe que `docker/gate/` pour `ev.lolinux.org`.

Après toute modification du fichier réel :

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo cat /etc/nginx/sites-available/s.lolinux.fr > /var/docker/ev/docker/share/s.lolinux.fr.conf
```

## Pourquoi un domaine à part

Le lien de partage de position (`/partager-ma-position`, `App\Models\PositionShare`)
doit être ouvrable par un destinataire qui n'a ni compte ni passkey sur cette
application — contrairement à `/infoCar`, qui reste sur `ev.lolinux.org` et
s'appuie sur une exemption *dans* la passerelle passkey. Un domaine séparé,
entièrement public, évite d'avoir à faire cette même exemption pour chaque
nouvelle adresse publique et rend le périmètre plus lisible : tout ce qui
répond sur `s.lolinux.fr` est public par construction.

## Ce que ce vhost garantit

`proxy_set_header X-SSO-Email ""` **écrase** systématiquement l'en-tête envoyé
par le client, à vide — c'est le point critique. Sans cette ligne, nginx
transmettrait l'en-tête tel que le client l'a forgé, et une requête vers une
adresse de l'application normale (`/recharges`, `/admin`, ...) tapée sous ce
domaine se ferait passer pour n'importe quel compte. Vérifié en forgeant
l'en-tête depuis l'extérieur : `curl -H "X-SSO-Email: quelqu'un@…"
https://s.lolinux.fr/recharges` répond bien 403.

`App\Http\Middleware\IdentifyUser::PUBLIC_ROUTES` contient `position-shares.show`
et laisse passer cette route par son **nom**, pas par son chemin : le chemin
est un token généré (`/{token}`), qui ne peut pas figurer dans une liste
statique comme `PUBLIC_PATHS`. Le token — 40 caractères aléatoires, jamais
deviné — reste la seule protection réelle du lien, exactement comme un lien de
partage classique. Toute autre route de l'application reste jointe sous ce
domaine (`routes/web.php` ne restreint que `position-shares.show` par
`Route::domain()`), mais y répond 403 faute d'identité, l'en-tête étant
toujours vide ici.

## DNS et certificat

`s.lolinux.fr` pointe en A vers l'IP du VPS (vérifié le 2026-09-10). Certificat
Let's Encrypt obtenu via `certbot --nginx -d s.lolinux.fr`, renouvellement
automatique comme les autres domaines du serveur.
