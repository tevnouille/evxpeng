# Journal des nouveautés

**Fichier généré — ne pas modifier à la main.** Il est écrit depuis
`app/Support/Changelog.php`, qui reste la seule source. Après y avoir ajouté
une entrée :

```bash
php artisan changelog:export
```

Le journal est rédigé pour qui se sert de l'application, pas depuis l'historique
Git : les messages de commit parlent de code, et une bonne moitié d'entre eux ne
change rien de visible. La même liste s'affiche sur `/changelog`.

## 8 septembre 2026

- **Nouveauté** — Le tableau « Par fournisseur » de l'historique gagne une colonne « Coût réel » : ce que la recharge aurait coûté au tarif affiché, frais annexes compris, sans les remises. En regard de la colonne « Coût », qui porte le montant réellement débité, l'écart entre les deux se lit directement — c'est le gain apporté par le réseau, abonnement ou opération commerciale confondus. La remise n'est pas réintégrée : le coût réel la porte déjà, l'ajouter reviendrait à la compter deux fois.
- **Nouveauté** — Le tableau de bord se termine par un récapitulatif chiffré par fournisseur : nombre de recharges, kWh, coût, prix moyen du kWh, part du coût total et gain. Les camemberts au-dessus donnent les proportions, pas les montants ; les pastilles de couleur reprennent celles des graphiques pour retrouver d'un coup d'œil la part qui correspond à chaque ligne.
- **Amélioration** — Le tableau des recharges de l'historique se trie par colonne, dans les deux sens, et se filtre au fil de la frappe. Le tri porte sur la valeur et non sur le texte affiché : les dates se classent chronologiquement, les durées en minutes, et une durée absente reste en fin de liste au lieu de se glisser entre deux valeurs. Le filtre ignore accents et majuscules — « locheres » trouve « Lochères » — et le compteur indique le nombre de recharges retenues.
- **Nouveauté** — La page « Déplacements » affiche sous la carte le détail des relevés de la journée : horodatage, latitude, longitude et adresse postale, triable et filtrable comme le tableau des recharges. Les adresses viennent de la Base Adresse Nationale, interrogée en un seul appel groupé pour la journée entière et mise en cache définitivement — un lieu déjà résolu ne recoûte rien. La distance entre le point relevé et l'adresse trouvée n'apparaît qu'au-delà d'une soixantaine de mètres : c'est elle qui dit si le libellé désigne l'endroit où vous étiez ou la maison la plus proche. Cette page transmet donc vos positions à la Base Adresse Nationale, en plus des fonds de carte déjà demandés à OpenStreetMap.

## 6 septembre 2026

- **Nouveauté** — Nouvelle page « Recharges face à la courbe », dans le menu « Courbe de recharge » : chaque recharge d'au moins 5 kWh relevée par le boîtier est superposée à la courbe du véhicule. C'est ce que la courbe seule ne montre pas — une charge qui n'atteint pas ce que la batterie devrait accepter, borne bridée, batterie froide ou cellule faible, se voit d'un coup d'œil, et l'écart le plus marqué est chiffré. Pour une charge en courant alternatif, la référence est ramenée à la puissance de la borne : sa puissance est imposée par le chargeur, pas par la batterie, et la comparer aux 300 kW de la courbe n'aurait aucun sens.
- **Nouveauté** — Le champ « Coût total facturé » gagne un bouton « Recalculer », à côté de « Gratuit ». Après un « Gratuit » ou une saisie manuelle, reprendre le calcul demandait jusqu'ici de retoucher la remise ou les frais annexes, ce que rien n'indiquait.
- **Amélioration** — « Ma voiture » se recharge maintenant au rythme de ce que fait la voiture : toutes les 5 secondes en charge, 20 secondes en route, une minute à l'arrêt. La collecte des relevés suit la même règle — rafraîchir l'écran plus vite que la donnée n'arrive ne montrerait rien de neuf.
- **Correction** — Une recharge a été enregistrée à 17 124 kWh pour 1,2 % de batterie gagné : le compteur d'énergie de la batterie renvoie parfois une valeur absurde. L'énergie mesurée est désormais confrontée à ce que le gain de niveau permet, et laissée vide si elle est invraisemblable — mieux vaut un champ à compléter qu'un chiffre faux dans les statistiques. La recharge déjà enregistrée a été reprise.

## 4 septembre 2026

- **Correction** — Le bouton « Mettre à jour les informations » de « Ma voiture » renvoyait par moments une erreur 500. La collecte automatique et ce bouton lancent le même traitement, mais sous deux identités différentes : le planificateur écrit son fichier de position de lecture en tant qu'administrateur, le bouton en tant que serveur web. Le premier créait le fichier sans laisser au second le droit de l'écrire. Les droits sont désormais posés à chaque écriture, et si le fichier reste inaccessible le traitement se contente d'un avertissement au lieu d'échouer — aucun relevé n'était perdu dans l'affaire.

## 2 septembre 2026

- **Nouveauté** — Nouvelle page « Statistiques OBD », dans le menu « Ma voiture ». Un calendrier mensuel colore les journées qui portent des relevés — plus la teinte est soutenue, plus ils sont nombreux — et une case vide reste une information : le boîtier n'émet que téléphone présent dans la voiture. En choisissant une journée, chaque indicateur remonté par le boîtier s'affiche : une courbe pour ce qui se mesure, une liste de changements pour ce qui se lit, comme l'état de charge. Rien n'est écrit en dur — un capteur que le boîtier se mettrait à remonter apparaîtrait de lui-même.
- **Amélioration** — A Better Routeplanner est retiré du projet. Il n'a jamais été une source de données mais un relais : tous les relevés reçus venaient déjà du boîtier OBD, au prix d'un détour par un cloud tiers qui les appauvrissait — ni tension de batterie, ni batterie 12 V, ni températures moteur, ni compteurs d'énergie — et les traitait par lots. Le boîtier publie désormais en direct. Le champ « Token ABRP » disparaît de la fiche véhicule au profit de l'« Identifiant MQTT ». Les relevés déjà collectés sont conservés et continuent d'alimenter les graphiques.
- **Amélioration** — La télémétrie est ingérée toutes les 15 secondes au lieu d'une fois par minute, et un relevé est enregistré toutes les 15 secondes quand la voiture roule ou charge, une fois par minute à l'arrêt. Plus rien ne bride la cadence : le broker est à la maison, il n'y a ni quota d'API ni traitement différé chez un tiers.
- **Correction** — Les recharges détectées de moins de 5 kWh ne sont plus proposées à la saisie. En passant à un relevé toutes les 15 secondes, le détecteur voyait chaque bref passage en charge pendant la conduite — de la récupération au freinage — et en faisait des recharges d'une minute à zéro kWh, parfois avec un niveau qui baissait. Un lien « Tout afficher » les fait revenir : rien n'est supprimé.
- **Amélioration** — L'état de la liaison ne mentionne plus ce qu'annonçait A Better Routeplanner, et la source « Dongle OBD Bluetooth » disparaît du bloc « Sources de données » : elle passait par ce relais, plus rien ne l'alimente.

## 1 septembre 2026

- **Nouveauté** — La courbe de recharge se remplace peu à peu par celle relevée sur votre propre voiture. Les courbes de référence viennent d'evkx.net : un exemplaire, un jour, sur une borne donnée. Le boîtier OBD, lui, relève la puissance seconde par seconde à chaque charge rapide. Chaque palier de niveau couvert par vos mesures remplace le palier théorique, la référence restant affichée en pointillé sur le graphique et rappelée au survol dans le tableau. Deux garde-fous : les charges en courant alternatif sont ignorées — leur puissance est imposée par la borne, pas par la batterie — et il faut au moins dix paliers couverts avant toute substitution, sinon des mesures éparses créeraient des ruptures dans le calcul des durées.
- **Correction** — « Ma voiture » restait bloquée sur « en route » des heures après l'arrêt. A Better Routeplanner réémet la dernière mesure connue sous un horodatage neuf : on a vu 24,6 km/h rejoués trois heures après le stationnement, avec un kilométrage inchangé. La vitesse ne sert donc plus que lorsque la voiture ne remonte aucun compteur kilométrique — dès qu'il répond, c'est lui seul qui tranche, puisqu'il est le seul à prouver un déplacement.
- **Nouveauté** — Les recharges relevées par le boîtier OBD arrivent désormais directement dans l'application, sans passer par A Better Routeplanner. Elles apparaissent marquées « mesurée » : leur énergie vient du compteur de la batterie, pas d'un calcul. L'écart n'est pas anecdotique — sur une même recharge, 3,831 kWh mesurés là où l'estimation par niveau de charge donnait 2,98, soit 29 % de moins. Le type de prise (AC ou DC) et la puissance maximale atteinte sont repris au passage, et une recharge vue des deux côtés n'est plus proposée deux fois.
- **Nouveauté** — La télémétrie du boîtier alimente aussi « Ma voiture » avec ce qu'A Better Routeplanner ne transmettait pas : tension de la batterie, batterie 12 V, températures moteur et refroidissement, compteurs d'énergie depuis la mise en service, altitude. Une nouvelle carte apparaît d'elle-même dans « Sources de données ».
- **Amélioration** — Le boîtier n'envoie pas tous les capteurs à chaque fois : il les interroge à tour de rôle, si bien qu'un message isolé ne porte qu'une poignée de valeurs. L'application recompose donc chaque relevé à partir des dernières valeurs connues, en n'en reprenant aucune de plus de cinq minutes — au-delà, un kilométrage figé laisserait croire que la voiture est à l'arrêt.
- **Amélioration** — Administration → Véhicules : nouveau champ « Identifiant MQTT », celui que le boîtier utilise pour publier. Sans lui, ses messages ne peuvent être rattachés à aucune voiture.

## 31 août 2026

- **Nouveauté** — Nouveau champ « Remise (€) » après le coût réel, pour les remises plafonnées — une heure de recharge remisée, le reste au tarif plein. Elle est déduite du total facturé et non retranchée du coût réel : la recharge vaut toujours son prix plein, c'est le montant débité qui baisse, et l'écart apparaît donc en gain dans l'historique. Une remise supérieure au coût ne rend pas le débit négatif.
- **Nouveauté** — Nouveau bloc « Sources de données » sur « Ma voiture » : une carte par source — cloud du constructeur via Enode, dongle OBD — avec le nombre de relevés, la date du dernier, et les champs que cette source remonte réellement avec leur dernière valeur. ABRP ne renvoie pas un jeu de champs fixe : il rend ce que la source lui a poussé, ce qui explique qu'un champ apparaisse puis disparaisse. La liste est construite sur ce qui a été observé, donc une source qui se met à fournir un champ de plus l'affiche sans que rien ne soit à modifier.
- **Amélioration** — Administration → Véhicules : la page n'occupait que dix douzièmes de la largeur et ses sept champs se repliaient de travers, sans étiquette en modification. Chaque véhicule a maintenant sa propre fiche, en pleine largeur, tous les champs nommés.
- **Correction** — « Ma voiture » annonçait une liaison « connectée » alors que le dongle OBD était débranché : le drapeau d'ABRP signale qu'une source est déclarée pour le véhicule, pas qu'elle émet. L'état se lit désormais sur la fraîcheur des relevés — « connectée », « au ralenti » ou « silencieuse » — et nomme la source par laquelle la donnée arrive quand elle est fraîche : « connectée via obdble », les deux si les deux alimentent. Passé un quart d'heure, plus aucune source n'est nommée au présent — un relevé d'il y a trente minutes ne prouve pas qu'un dongle est branché maintenant : la dernière source connue passe alors dans le texte en dessous, au passé, avec ce que déclare ABRP.
- **Nouveauté** — Bouton « Écarter » sur les recharges détectées non enregistrées : une recharge saisie à la main ne peut pas être reconnue par le rapprochement automatique, elle restait donc proposée sans fin. Elle reste consultable sur « Ma voiture », marquée « écartée », d'où elle se rétablit d'un clic.
- **Correction** — Modifier le coût réel d'une recharge enregistrée reporte désormais l'écart sur le total facturé, tant que celui-ci n'a pas été fixé à la main. Au passage, une recharge marquée « Gratuit » ne perd plus son zéro quand on la rouvre pour corriger autre chose.
- **Nouveauté** — Le « Coût additionnel » — stationnement, frais de connexion, pénalité — est maintenant enregistré au lieu de servir uniquement au calcul du total. En rouvrant une recharge, on retrouve donc ce qui séparait le coût réel du montant débité. Les recharges déjà saisies gardent une valeur vide : ce qui a pu y être ajouté n'est plus discernable, et un zéro prétendrait qu'il n'y en avait pas.
- **Nouveauté** — Les recharges dont aucun relevé n'a été témoin sont désormais détectées : réseau coupé, dongle OBD débranché, la charge se lit à un niveau de batterie qui a monté alors que le compteur kilométrique n'avait pas bougé. Elles apparaissent marquées « déduite » dans les recharges détectées. Leur durée reste inconnue et n'est pas pré-remplie — seules la date, l'énergie et la position le sont.
- **Amélioration** — La télémétrie est relevée toutes les 15 secondes quand la voiture roule ou charge, et une fois par minute à l'arrêt. Auparavant, le roulage restait à la cadence lente alors que c'est là que la donnée bouge le plus.
- **Nouveauté** — « Ma voiture » se rafraîchit toute seule toutes les 30 secondes. Le compte à rebours se met en pause si l'onglet passe en arrière-plan, si vous êtes en train de saisir quelque chose, ou si la carte est ouverte — elle disparaîtrait au rechargement.
- **Nouveauté** — Bouton « Mettre à jour les informations » sur « Ma voiture » : il interroge ABRP immédiatement, sans attendre la collecte automatique.
- **Correction** — « Ma voiture » affichait « stationné » même en plein trajet : la seule information dont disposait la page était « en charge / pas en charge », et le champ que fournirait ABRP pour distinguer l'arrêt du roulage n'est jamais renseigné. L'état est désormais reconstruit à partir du compteur kilométrique, qui seul prouve que la voiture a bougé. Trois états s'ajoutent à « en charge » : « en route », « stationné » et « sans relevé récent », ce dernier quand la voiture n'a rien remonté depuis trois quarts d'heure.

## 30 août 2026

- **Nouveauté** — Administration → Information serveurs : caractéristiques de la machine, versions des briques logicielles, et l'inventaire complet des paquets système, Composer et npm — version installée, version disponible, licence, et si la mise à jour est compatible ou représente un changement de version majeure. Un bouton « Vérifier les mises à jour » relance le relevé, et chaque paquet npm ou système dont la montée est compatible peut être mis à jour d'un clic — avec retour arrière automatique si la construction échoue.
- **Nouveauté** — Saisie d'une recharge : champ « Coût additionnel » pour le stationnement, les frais de connexion ou une pénalité. Il s'ajoute au coût réel pour donner le total facturé, et n'est pas enregistré.
- **Nouveauté** — Mon compte : une case permet de masquer le bloc « Équivalent carburant » dans l'historique.
- **Amélioration** — Localisation et fournisseur : « Autre / nouvelle » est passé en tête de liste, un bouton « Nouvelle localisation » y mène directement, et la liste s'efface pendant la saisie du nouveau nom.
- **Amélioration** — Le calendrier s'ouvre en cliquant n'importe où dans le champ Date, et l'horloge dans le champ Durée.
- **Amélioration** — Modifier ou supprimer une recharge depuis un mois de l'historique y ramène, au lieu de renvoyer sur la liste des recharges.
- **Amélioration** — Dupliquer une recharge reprend aussi son commentaire.
- **Nouveauté** — Recharges : nouveau champ « Coût réel » — ce que la recharge vaut (bouton « Recalculer » : quantité × coût unitaire) — à côté du coût facturé, qu'un bouton « Gratuit » met à zéro pour une recharge non débitée. L'historique et le dashboard affichent le total réel et le gain (facturé − réel). Les recharges existantes partent du coût facturé, à ajuster à la main si besoin.
- **Nouveauté** — Saisie d'une recharge : un bouton « Rechercher bornes » propose les bornes autour de vous et remplit d'un clic le lieu, le fournisseur, la puissance et la position exacte.
- **Nouveauté** — Administration → Données récupérées : l'état des trois sources extérieures (bornes, prix des carburants, télémétrie), leur date de dernière mise à jour, et un bouton pour relancer chacune.
- **Nouveauté** — Ma voiture : tableau des kilomètres parcourus et de l'énergie rechargée jour par jour, avec un filtre par mois.
- **Nouveauté** — Le SMS de test peut être envoyé directement depuis le journal des envois.
- **Correction** — Le bouton « SMS de test » affichait une erreur alors que le message était bien parti, et l'envoi n'apparaissait pas dans le journal.
- **Correction** — Sélecteur de réseaux : les réseaux retenus disparaissaient dès qu'on tapait dans le filtre.
- **Correction** — Mon compte : le texte du SMS de test débordait de son cadre et poussait le bouton dehors.
- **Nouveauté** — L'application accepte plusieurs comptes. Chacun a ses propres recharges, véhicules, localisations et trajets favoris ; personne ne voit ceux des autres.
- **Nouveauté** — Un trajet favori peut être copié vers d'autres comptes.
- **Nouveauté** — Les identifiants Free Mobile des alertes SMS sont propres à chaque compte, renseignés depuis « Mon compte ».
- **Amélioration** — Saisie d'une recharge : en choisissant « Autre… », les bornes de la base nationale sont proposées, et en retenir une renseigne aussi le fournisseur et la puissance.
- **Nouveauté** — Trajets favoris : enregistrez un itinéraire, voyez toutes les bornes sur le parcours, retenez les vôtres et retrouvez-les avec un lien Google Maps ou Waze.
- **Nouveauté** — Planificateur d'itinéraire avec arrêts de recharge, filtrable par puissance et par réseau.
- **Nouveauté** — Carte des déplacements, reconstituée depuis la télémétrie.
- **Nouveauté** — Page « Ma voiture » : niveau de charge, autonomie estimée, recharges détectées et tout ce que remonte la voiture.
- **Nouveauté** — Alertes SMS au franchissement des paliers de charge, avec journal des envois.
- **Amélioration** — Une recharge retient désormais la position de la borne : deux bornes d'une même ville ne se confondent plus.

## 29 août 2026

- **Nouveauté** — Page « Courbe de recharge » : durée et énergie entre deux niveaux, avec la courbe propre au véhicule.
- **Nouveauté** — Historique mensuel : graphiques kWh et coût, statistiques et répartition par fournisseur.
- **Amélioration** — Estimation des kilomètres parcourus à partir de la consommation du véhicule.

## 14 août 2026

- **Nouveauté** — Bouton « Utiliser ma position » pour renseigner le lieu d'une recharge.
- **Amélioration** — Graphiques journaliers dans l'historique du mois.

## 9 août 2026

- **Amélioration** — Sélecteur d'année dans le dashboard, et graphiques kWh et coût par mois dans l'historique.

## 8 août 2026

- **Nouveauté** — Première version : saisie des recharges, historique, dashboard, consommation par véhicule et comparaison au prix des carburants.
