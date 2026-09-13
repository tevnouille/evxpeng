# Passerelle passkey (`ev-gate`) — HISTORIQUE, ne décrit plus rien de servi

**Ce répertoire ne protège plus aucun trafic depuis la migration du projet sur
le VPS Hostinger, le 2026-09-10.** Le conteneur `ev-gate` a été décommissionné
avec le reste de l'ancienne pile hostingtools ; il n'existe plus nulle part.

`gate.conf` reste ici tel quel, comme trace de ce qu'était le mécanisme sur
l'ancien hébergement — copie de référence d'un fichier qui n'est plus monté en
bind par rien, plus jamais synchronisé. Les commandes qu'il documentait
(`docker exec ev-gate ...`) échoueraient aujourd'hui : ce conteneur n'existe
pas.

## Où vit vraiment l'exemption aujourd'hui

Sur le VPS, c'est le **nginx de l'hôte** qui termine le TLS et pose l'exemption,
directement dans son vhost — `/etc/nginx/sites-available/ev.lolinux.org`, hors
de ce dépôt (convention du VPS : plusieurs projets y partagent le même nginx,
rien n'y est versionné par projet). Voir la section « Page publique `/infoCar` »
de `CLAUDE.md` à la racine du dépôt pour le détail à jour.

## ce que garantissait la passerelle (toujours vrai côté nginx de l'hôte)

`proxy_set_header X-SSO-Email $sso_email` **écrase** systématiquement l'en-tête
envoyé par le client : l'identité ne peut pas être forgée depuis l'extérieur.
Toute nouvelle `location` doit poser cet en-tête elle aussi — à vide si elle est
publique — faute de quoi nginx transmettrait celui du client et n'importe qui
pourrait se déclarer propriétaire d'un compte. Ce principe n'a pas changé avec
la migration, seul son emplacement a bougé.
