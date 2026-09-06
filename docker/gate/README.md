# Passerelle passkey (`ev-gate`)

Copie de référence de la configuration réellement servie, montée en bind depuis
`/var/docker/ev-gate/nginx/gate.conf` — **hors du dépôt**, donc jamais versionnée
jusqu'ici. Ce fichier n'est pas lu par le conteneur : il existe pour qu'une
modification de la passerelle laisse une trace dans l'historique, à côté du code
qu'elle protège.

Après toute modification du fichier réel :

```bash
docker exec ev-gate nginx -t && docker exec ev-gate nginx -s reload
cp /var/docker/ev-gate/nginx/gate.conf /var/docker/ev/docker/gate/gate.conf
```

## Ce que la passerelle garantit

`proxy_set_header X-SSO-Email $sso_email` **écrase** systématiquement l'en-tête
envoyé par le client : l'identité ne peut pas être forgée depuis l'extérieur.
Toute nouvelle `location` doit poser cet en-tête elle aussi — à vide si elle est
publique — faute de quoi nginx transmettrait celui du client et n'importe qui
pourrait se déclarer propriétaire d'un compte.

## Adresses publiques

`/infoCar` (casse indifférente) est la seule adresse servie sans passkey : l'état
de la batterie pour le navigateur embarqué de la voiture. Elle n'expose ni
position, ni historique, ni coûts. L'exemption est déclarée à deux endroits, et
les deux sont nécessaires : ici pour la passerelle, et dans
`App\Http\Middleware\IdentifyUser::PUBLIC_PATHS` pour l'application.
