<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Journal des nouveautes, redige pour ceux qui utilisent l'application.
 *
 * Volontairement tenu a la main plutot que derive de l'historique Git : les
 * messages de commit parlent de code, pas d'usage, et une bonne moitie d'entre
 * eux n'a aucun effet visible. Une entree n'a sa place ici que si elle change
 * quelque chose pour qui se sert de l'outil.
 *
 * A completer a chaque livraison qui se voit.
 */
class Changelog
{
    /**
     * Les plus recentes d'abord.
     *
     * @return array<int, array{date: Carbon, entries: array<int, array{type: string, text: string}>}>
     */
    public static function releases(): array
    {
        return array_map(
            fn (array $release) => [
                'date' => Carbon::parse($release['date']),
                'entries' => $release['entries'],
            ],
            self::DATA
        );
    }

    /** Trois natures de changement, pour distinguer d'un coup d'oeil. */
    public const TYPES = [
        'nouveaute' => ['label' => 'Nouveauté', 'class' => 'is-success'],
        'amelioration' => ['label' => 'Amélioration', 'class' => 'is-info'],
        'correction' => ['label' => 'Correction', 'class' => 'is-warning'],
    ];

    private const DATA = [
        [
            'date' => '2026-09-10',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Un lien « Déconnexion » apparaît dans le menu, à droite, et un bouton sur « Mon compte ». Il n'y en avait aucun : une fois le passkey présenté, il fallait fermer le navigateur ou vider ses cookies pour repartir — impossible de passer d'un compte à l'autre sur la même machine. À noter, et c'est écrit sur « Mon compte » : l'application n'a pas de session à elle, votre identité lui est transmise à chaque page par la passerelle passkey. Se déconnecter ferme la session de cette passerelle, donc vous déconnecte de tous les services qu'elle protège, pas seulement de celui-ci. Pour revenir, il suffit de représenter son passkey."],
                ['type' => 'amelioration', 'text' => "Sur « Déplacements », les relevés d'une journée sont désormais regroupés par déplacement : chaque trajet distinct — séparé du suivant par un écart de plus de quinze minutes entre deux relevés, signe que le boîtier ne remontait plus rien moteur coupé — a sa propre couleur sur la carte. Un sélecteur, visible dès qu'une journée compte plusieurs trajets, permet d'isoler l'un d'eux : la carte se recadre dessus et le tableau des relevés en dessous ne montre plus que ses lignes."],
            ],
        ],
        [
            'date' => '2026-09-09',
            'entries' => [
                ['type' => 'correction', 'text' => "Les erreurs 500 intermittentes sont corrigees a la racine. L'application rangeait son cache dans des fichiers, et le compteur du limiteur de debit s'y ecrivait a chaque visite de la page publique. Le cache fichier n'est pas atomique : deux requetes qui se croisent — la voiture toutes les dix secondes, la collecte, un chargement manuel — pouvaient lire un fichier que l'autre venait de remplacer, d'ou une 500 sans cause apparente. Le cache passe en base de donnees, ou chaque ecriture est atomique."],
                ['type' => 'correction', 'text' => "« Info voiture » renvoyait par moments une erreur 429, page blanche à l'écran de la voiture. La cause était dans le rechargement automatique livré la veille : l'échéance était vérifiée cinq fois par seconde, et une fois passée, chaque vérification relançait le chargement de la page. Sur un lien mobile lent, la page mettait plus d'une seconde à arriver — le temps que trente à cinquante requêtes soient lancées, chacune annulant la précédente, jusqu'à ce que le serveur oppose sa limite de débit. Le rechargement n'est désormais déclenché qu'une fois, et le battement s'arrête avec lui. La voiture passe de trente à cinquante requêtes par minute à six."],
                ['type' => 'amelioration', 'text' => "Le rythme de rafraîchissement passe à dix secondes en charge comme en roulage, au lieu de cinq et vingt. C'est la cadence réellement mesurée du boîtier : médiane de 10,0 secondes sur les 5 527 relevés reçus du 1er au 8 septembre, identique dans les deux cas. Les cinq secondes de la charge demandaient une page sur deux pour rien ; les vingt secondes du roulage affichaient une donnée deux fois plus vieille que nécessaire."],
                ['type' => 'amelioration', 'text' => "Les pages d'erreur 429 et 503 se rechargent d'elles-mêmes au bout de trente secondes. Sur l'écran de la voiture, une page d'erreur restait affichée jusqu'à ce qu'on la recharge à la main — ce qui ne se fait pas en conduisant. Le serveur a redémarré ce soir à 19 h 02 : les quelques secondes où il n'a pas répondu auraient figé l'écran jusqu'à l'arrêt du véhicule."],
            ],
        ],
        [
            'date' => '2026-09-08',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Deux mesures de plus sur « Info voiture », dans une case qui alterne comme la première. L'« écart entre cellules » — 26 mV — est le signe avant-coureur que la santé batterie ne donne pas : celle-ci reste à 99 % pendant des années, alors qu'un déséquilibre qui se creuse se lit tout de suite ici. C'est la médiane des dernières vingt-quatre heures et non la dernière valeur, parce que le boîtier interroge les capteurs à tour de rôle : les deux tensions d'un même relevé ne datent pas du même instant, et un relevé sur cinquante donne même un écart négatif, physiquement impossible. La « limite de charge » — 80 % — dit ce qui est réglé dans la voiture, ce qu'on oublie d'avoir changé avant un long trajet ; le boîtier renvoyant par moments des valeurs absurdes (5 940 relevé cette semaine), c'est le dernier relevé plausible qui est retenu."],
                ['type' => 'amelioration', 'text' => "« Température batterie » et « Puissance » partagent désormais une case, elles aussi. Les trois cases sont décalées d'un tiers de période : une seule change à la fois, moins de deux secondes après sa voisine, au lieu de tout l'écran d'un coup. Chacune garde bien ses cinq secondes par face."],
                ['type' => 'amelioration', 'text' => "Sur « Info voiture », la case « Autonomie estimée » alterne toutes les cinq secondes avec « Santé batterie ». Celle-ci n'a donc plus de case à elle : la même valeur affichée à deux endroits, dont un clignotant, se serait lue comme un défaut. Le rythme suit l'horloge et non un compteur reparti à chaque affichage — en charge, la page se renouvelle toutes les cinq secondes, et la seconde face n'aurait jamais eu le temps de paraître."],
                ['type' => 'amelioration', 'text' => "Sur « Info voiture », la tuile « Compteur » cède la place à la commune où se trouve la voiture. Le kilométrage est déjà sous les yeux du conducteur, sur le tableau de bord ; la commune, elle, ne l'est nulle part ailleurs. Elle vient du même cache d'adresses que « Déplacements » — une voiture à l'arrêt ne redemande donc rien à la Base Adresse Nationale, et une voiture qui roule au plus une fois par tranche de onze mètres. À noter : cette page étant publique, la commune l'est aussi, comme la position déjà accessible par son onglet."],
                ['type' => 'correction', 'text' => "La page « Info voiture » restait parfois figée sur d'anciens relevés. Deux causes distinctes. Le rechargement était programmé une fois pour toutes à l'ouverture : le navigateur de la voiture endort ses minuteries dès que l'écran s'éteint, et ce délai pouvait alors ne jamais arriver à terme. Il se décide désormais sur l'heure, relue en continu — après une mise en veille, la page se renouvelle dès la reprise. Ensuite, afficher la carte suspend le rechargement, pour ne pas la reconstruire et rappeler OpenStreetMap toutes les minutes ; mais rien ne le remettait en marche, si bien qu'un simple passage par l'onglet « Position » figeait la page pour de bon. Revenir sur « Info » ou « Recharge » relance maintenant le compte à rebours."],
                ['type' => 'nouveaute', 'text' => "Une fine barre grise sous la date du dernier relevé se remplit à mesure qu'approche le prochain rafraîchissement de « Info voiture ». Sur un écran où rien ne bouge entre deux relevés, elle dit d'un coup d'œil dans combien de temps les chiffres seront renouvelés — et, si elle reste immobile, que le navigateur a suspendu la page. Elle s'efface le temps de la carte, où le rechargement est volontairement à l'arrêt."],
                ['type' => 'nouveaute', 'text' => "Le tableau « Par fournisseur » de l'historique gagne une colonne « Coût réel » : ce que la recharge aurait coûté au tarif affiché, frais annexes compris, sans les remises. En regard de la colonne « Coût », qui porte le montant réellement débité, l'écart entre les deux se lit directement — c'est le gain apporté par le réseau, abonnement ou opération commerciale confondus. La remise n'est pas réintégrée : le coût réel la porte déjà, l'ajouter reviendrait à la compter deux fois."],
                ['type' => 'nouveaute', 'text' => "Le tableau de bord se termine par un récapitulatif chiffré par fournisseur : nombre de recharges, kWh, coût, prix moyen du kWh, part du coût total et gain. Les camemberts au-dessus donnent les proportions, pas les montants ; les pastilles de couleur reprennent celles des graphiques pour retrouver d'un coup d'œil la part qui correspond à chaque ligne."],
                ['type' => 'amelioration', 'text' => "Le tableau des recharges de l'historique se trie par colonne, dans les deux sens, et se filtre au fil de la frappe. Le tri porte sur la valeur et non sur le texte affiché : les dates se classent chronologiquement, les durées en minutes, et une durée absente reste en fin de liste au lieu de se glisser entre deux valeurs. Le filtre ignore accents et majuscules — « locheres » trouve « Lochères » — et le compteur indique le nombre de recharges retenues."],
                ['type' => 'nouveaute', 'text' => "La page « Déplacements » affiche sous la carte le détail des relevés de la journée : horodatage, latitude, longitude et adresse postale, triable et filtrable comme le tableau des recharges. Les adresses viennent de la Base Adresse Nationale, interrogée en un seul appel groupé pour la journée entière et mise en cache définitivement — un lieu déjà résolu ne recoûte rien. La distance entre le point relevé et l'adresse trouvée n'apparaît qu'au-delà d'une soixantaine de mètres : c'est elle qui dit si le libellé désigne l'endroit où vous étiez ou la maison la plus proche. Cette page transmet donc vos positions à la Base Adresse Nationale, en plus des fonds de carte déjà demandés à OpenStreetMap."],
            ],
        ],
        [
            'date' => '2026-09-06',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Nouvelle page « Recharges face à la courbe », dans le menu « Courbe de recharge » : chaque recharge d'au moins 5 kWh relevée par le boîtier est superposée à la courbe du véhicule. C'est ce que la courbe seule ne montre pas — une charge qui n'atteint pas ce que la batterie devrait accepter, borne bridée, batterie froide ou cellule faible, se voit d'un coup d'œil, et l'écart le plus marqué est chiffré. Pour une charge en courant alternatif, la référence est ramenée à la puissance de la borne : sa puissance est imposée par le chargeur, pas par la batterie, et la comparer aux 300 kW de la courbe n'aurait aucun sens."],
                ['type' => 'nouveaute', 'text' => "Le champ « Coût total facturé » gagne un bouton « Recalculer », à côté de « Gratuit ». Après un « Gratuit » ou une saisie manuelle, reprendre le calcul demandait jusqu'ici de retoucher la remise ou les frais annexes, ce que rien n'indiquait."],
                ['type' => 'amelioration', 'text' => "« Ma voiture » se recharge maintenant au rythme de ce que fait la voiture : toutes les 5 secondes en charge, 20 secondes en route, une minute à l'arrêt. La collecte des relevés suit la même règle — rafraîchir l'écran plus vite que la donnée n'arrive ne montrerait rien de neuf."],
                ['type' => 'correction', 'text' => "Une recharge a été enregistrée à 17 124 kWh pour 1,2 % de batterie gagné : le compteur d'énergie de la batterie renvoie parfois une valeur absurde. L'énergie mesurée est désormais confrontée à ce que le gain de niveau permet, et laissée vide si elle est invraisemblable — mieux vaut un champ à compléter qu'un chiffre faux dans les statistiques. La recharge déjà enregistrée a été reprise."],
            ],
        ],
        [
            'date' => '2026-09-04',
            'entries' => [
                ['type' => 'correction', 'text' => "Le bouton « Mettre à jour les informations » de « Ma voiture » renvoyait par moments une erreur 500. La collecte automatique et ce bouton lancent le même traitement, mais sous deux identités différentes : le planificateur écrit son fichier de position de lecture en tant qu'administrateur, le bouton en tant que serveur web. Le premier créait le fichier sans laisser au second le droit de l'écrire. Les droits sont désormais posés à chaque écriture, et si le fichier reste inaccessible le traitement se contente d'un avertissement au lieu d'échouer — aucun relevé n'était perdu dans l'affaire."],
            ],
        ],
        [
            'date' => '2026-09-02',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Nouvelle page « Statistiques OBD », dans le menu « Ma voiture ». Un calendrier mensuel colore les journées qui portent des relevés — plus la teinte est soutenue, plus ils sont nombreux — et une case vide reste une information : le boîtier n'émet que téléphone présent dans la voiture. En choisissant une journée, chaque indicateur remonté par le boîtier s'affiche : une courbe pour ce qui se mesure, une liste de changements pour ce qui se lit, comme l'état de charge. Rien n'est écrit en dur — un capteur que le boîtier se mettrait à remonter apparaîtrait de lui-même."],
                ['type' => 'amelioration', 'text' => "A Better Routeplanner est retiré du projet. Il n'a jamais été une source de données mais un relais : tous les relevés reçus venaient déjà du boîtier OBD, au prix d'un détour par un cloud tiers qui les appauvrissait — ni tension de batterie, ni batterie 12 V, ni températures moteur, ni compteurs d'énergie — et les traitait par lots. Le boîtier publie désormais en direct. Le champ « Token ABRP » disparaît de la fiche véhicule au profit de l'« Identifiant MQTT ». Les relevés déjà collectés sont conservés et continuent d'alimenter les graphiques."],
                ['type' => 'amelioration', 'text' => "La télémétrie est ingérée toutes les 15 secondes au lieu d'une fois par minute, et un relevé est enregistré toutes les 15 secondes quand la voiture roule ou charge, une fois par minute à l'arrêt. Plus rien ne bride la cadence : le broker est à la maison, il n'y a ni quota d'API ni traitement différé chez un tiers."],
                ['type' => 'correction', 'text' => "Les recharges détectées de moins de 5 kWh ne sont plus proposées à la saisie. En passant à un relevé toutes les 15 secondes, le détecteur voyait chaque bref passage en charge pendant la conduite — de la récupération au freinage — et en faisait des recharges d'une minute à zéro kWh, parfois avec un niveau qui baissait. Un lien « Tout afficher » les fait revenir : rien n'est supprimé."],
                ['type' => 'amelioration', 'text' => "L'état de la liaison ne mentionne plus ce qu'annonçait A Better Routeplanner, et la source « Dongle OBD Bluetooth » disparaît du bloc « Sources de données » : elle passait par ce relais, plus rien ne l'alimente."],
            ],
        ],
        [
            'date' => '2026-09-01',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "La courbe de recharge se remplace peu à peu par celle relevée sur votre propre voiture. Les courbes de référence viennent d'evkx.net : un exemplaire, un jour, sur une borne donnée. Le boîtier OBD, lui, relève la puissance seconde par seconde à chaque charge rapide. Chaque palier de niveau couvert par vos mesures remplace le palier théorique, la référence restant affichée en pointillé sur le graphique et rappelée au survol dans le tableau. Deux garde-fous : les charges en courant alternatif sont ignorées — leur puissance est imposée par la borne, pas par la batterie — et il faut au moins dix paliers couverts avant toute substitution, sinon des mesures éparses créeraient des ruptures dans le calcul des durées."],
                ['type' => 'correction', 'text' => "« Ma voiture » restait bloquée sur « en route » des heures après l'arrêt. A Better Routeplanner réémet la dernière mesure connue sous un horodatage neuf : on a vu 24,6 km/h rejoués trois heures après le stationnement, avec un kilométrage inchangé. La vitesse ne sert donc plus que lorsque la voiture ne remonte aucun compteur kilométrique — dès qu'il répond, c'est lui seul qui tranche, puisqu'il est le seul à prouver un déplacement."],
                ['type' => 'nouveaute', 'text' => "Les recharges relevées par le boîtier OBD arrivent désormais directement dans l'application, sans passer par A Better Routeplanner. Elles apparaissent marquées « mesurée » : leur énergie vient du compteur de la batterie, pas d'un calcul. L'écart n'est pas anecdotique — sur une même recharge, 3,831 kWh mesurés là où l'estimation par niveau de charge donnait 2,98, soit 29 % de moins. Le type de prise (AC ou DC) et la puissance maximale atteinte sont repris au passage, et une recharge vue des deux côtés n'est plus proposée deux fois."],
                ['type' => 'nouveaute', 'text' => "La télémétrie du boîtier alimente aussi « Ma voiture » avec ce qu'A Better Routeplanner ne transmettait pas : tension de la batterie, batterie 12 V, températures moteur et refroidissement, compteurs d'énergie depuis la mise en service, altitude. Une nouvelle carte apparaît d'elle-même dans « Sources de données »."],
                ['type' => 'amelioration', 'text' => "Le boîtier n'envoie pas tous les capteurs à chaque fois : il les interroge à tour de rôle, si bien qu'un message isolé ne porte qu'une poignée de valeurs. L'application recompose donc chaque relevé à partir des dernières valeurs connues, en n'en reprenant aucune de plus de cinq minutes — au-delà, un kilométrage figé laisserait croire que la voiture est à l'arrêt."],
                ['type' => 'amelioration', 'text' => "Administration → Véhicules : nouveau champ « Identifiant MQTT », celui que le boîtier utilise pour publier. Sans lui, ses messages ne peuvent être rattachés à aucune voiture."],
            ],
        ],
        [
            'date' => '2026-08-31',
            'entries' => [
                                ['type' => 'nouveaute', 'text' => "Nouveau champ « Remise (€) » après le coût réel, pour les remises plafonnées — une heure de recharge remisée, le reste au tarif plein. Elle est déduite du total facturé et non retranchée du coût réel : la recharge vaut toujours son prix plein, c'est le montant débité qui baisse, et l'écart apparaît donc en gain dans l'historique. Une remise supérieure au coût ne rend pas le débit négatif."],
                ['type' => 'nouveaute', 'text' => "Nouveau bloc « Sources de données » sur « Ma voiture » : une carte par source — cloud du constructeur via Enode, dongle OBD — avec le nombre de relevés, la date du dernier, et les champs que cette source remonte réellement avec leur dernière valeur. ABRP ne renvoie pas un jeu de champs fixe : il rend ce que la source lui a poussé, ce qui explique qu'un champ apparaisse puis disparaisse. La liste est construite sur ce qui a été observé, donc une source qui se met à fournir un champ de plus l'affiche sans que rien ne soit à modifier."],
                ['type' => 'amelioration', 'text' => "Administration → Véhicules : la page n'occupait que dix douzièmes de la largeur et ses sept champs se repliaient de travers, sans étiquette en modification. Chaque véhicule a maintenant sa propre fiche, en pleine largeur, tous les champs nommés."],
                ['type' => 'correction', 'text' => "« Ma voiture » annonçait une liaison « connectée » alors que le dongle OBD était débranché : le drapeau d'ABRP signale qu'une source est déclarée pour le véhicule, pas qu'elle émet. L'état se lit désormais sur la fraîcheur des relevés — « connectée », « au ralenti » ou « silencieuse » — et nomme la source par laquelle la donnée arrive quand elle est fraîche : « connectée via obdble », les deux si les deux alimentent. Passé un quart d'heure, plus aucune source n'est nommée au présent — un relevé d'il y a trente minutes ne prouve pas qu'un dongle est branché maintenant : la dernière source connue passe alors dans le texte en dessous, au passé, avec ce que déclare ABRP."],
                ['type' => 'nouveaute', 'text' => "Bouton « Écarter » sur les recharges détectées non enregistrées : une recharge saisie à la main ne peut pas être reconnue par le rapprochement automatique, elle restait donc proposée sans fin. Elle reste consultable sur « Ma voiture », marquée « écartée », d'où elle se rétablit d'un clic."],
                ['type' => 'correction', 'text' => "Modifier le coût réel d'une recharge enregistrée reporte désormais l'écart sur le total facturé, tant que celui-ci n'a pas été fixé à la main. Au passage, une recharge marquée « Gratuit » ne perd plus son zéro quand on la rouvre pour corriger autre chose."],
                                ['type' => 'nouveaute', 'text' => "Le « Coût additionnel » — stationnement, frais de connexion, pénalité — est maintenant enregistré au lieu de servir uniquement au calcul du total. En rouvrant une recharge, on retrouve donc ce qui séparait le coût réel du montant débité. Les recharges déjà saisies gardent une valeur vide : ce qui a pu y être ajouté n'est plus discernable, et un zéro prétendrait qu'il n'y en avait pas."],
                                ['type' => 'nouveaute', 'text' => "Les recharges dont aucun relevé n'a été témoin sont désormais détectées : réseau coupé, dongle OBD débranché, la charge se lit à un niveau de batterie qui a monté alors que le compteur kilométrique n'avait pas bougé. Elles apparaissent marquées « déduite » dans les recharges détectées. Leur durée reste inconnue et n'est pas pré-remplie — seules la date, l'énergie et la position le sont."],
                ['type' => 'amelioration', 'text' => "La télémétrie est relevée toutes les 15 secondes quand la voiture roule ou charge, et une fois par minute à l'arrêt. Auparavant, le roulage restait à la cadence lente alors que c'est là que la donnée bouge le plus."],
                ['type' => 'nouveaute', 'text' => "« Ma voiture » se rafraîchit toute seule toutes les 30 secondes. Le compte à rebours se met en pause si l'onglet passe en arrière-plan, si vous êtes en train de saisir quelque chose, ou si la carte est ouverte — elle disparaîtrait au rechargement."],
                ['type' => 'nouveaute', 'text' => "Bouton « Mettre à jour les informations » sur « Ma voiture » : il interroge ABRP immédiatement, sans attendre la collecte automatique."],
                ['type' => 'correction', 'text' => "« Ma voiture » affichait « stationné » même en plein trajet : la seule information dont disposait la page était « en charge / pas en charge », et le champ que fournirait ABRP pour distinguer l'arrêt du roulage n'est jamais renseigné. L'état est désormais reconstruit à partir du compteur kilométrique, qui seul prouve que la voiture a bougé. Trois états s'ajoutent à « en charge » : « en route », « stationné » et « sans relevé récent », ce dernier quand la voiture n'a rien remonté depuis trois quarts d'heure."],
            ],
        ],
        [
            'date' => '2026-08-30',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Administration → Information serveurs : caractéristiques de la machine, versions des briques logicielles, et l'inventaire complet des paquets système, Composer et npm — version installée, version disponible, licence, et si la mise à jour est compatible ou représente un changement de version majeure. Un bouton « Vérifier les mises à jour » relance le relevé, et chaque paquet npm ou système dont la montée est compatible peut être mis à jour d'un clic — avec retour arrière automatique si la construction échoue."],
                ['type' => 'nouveaute', 'text' => "Saisie d'une recharge : champ « Coût additionnel » pour le stationnement, les frais de connexion ou une pénalité. Il s'ajoute au coût réel pour donner le total facturé, et n'est pas enregistré."],
                ['type' => 'nouveaute', 'text' => "Mon compte : une case permet de masquer le bloc « Équivalent carburant » dans l'historique."],
                ['type' => 'amelioration', 'text' => "Localisation et fournisseur : « Autre / nouvelle » est passé en tête de liste, un bouton « Nouvelle localisation » y mène directement, et la liste s'efface pendant la saisie du nouveau nom."],
                ['type' => 'amelioration', 'text' => "Le calendrier s'ouvre en cliquant n'importe où dans le champ Date, et l'horloge dans le champ Durée."],
                ['type' => 'amelioration', 'text' => "Modifier ou supprimer une recharge depuis un mois de l'historique y ramène, au lieu de renvoyer sur la liste des recharges."],
                ['type' => 'amelioration', 'text' => "Dupliquer une recharge reprend aussi son commentaire."],
                ['type' => 'nouveaute', 'text' => "Recharges : nouveau champ « Coût réel » — ce que la recharge vaut (bouton « Recalculer » : quantité × coût unitaire) — à côté du coût facturé, qu'un bouton « Gratuit » met à zéro pour une recharge non débitée. L'historique et le dashboard affichent le total réel et le gain (facturé − réel). Les recharges existantes partent du coût facturé, à ajuster à la main si besoin."],
                ['type' => 'nouveaute', 'text' => "Saisie d'une recharge : un bouton « Rechercher bornes » propose les bornes autour de vous et remplit d'un clic le lieu, le fournisseur, la puissance et la position exacte."],
                ['type' => 'nouveaute', 'text' => "Administration → Données récupérées : l'état des trois sources extérieures (bornes, prix des carburants, télémétrie), leur date de dernière mise à jour, et un bouton pour relancer chacune."],
                ['type' => 'nouveaute', 'text' => "Ma voiture : tableau des kilomètres parcourus et de l'énergie rechargée jour par jour, avec un filtre par mois."],
                ['type' => 'nouveaute', 'text' => "Le SMS de test peut être envoyé directement depuis le journal des envois."],
                ['type' => 'correction', 'text' => "Le bouton « SMS de test » affichait une erreur alors que le message était bien parti, et l'envoi n'apparaissait pas dans le journal."],
                ['type' => 'correction', 'text' => "Sélecteur de réseaux : les réseaux retenus disparaissaient dès qu'on tapait dans le filtre."],
                ['type' => 'correction', 'text' => "Mon compte : le texte du SMS de test débordait de son cadre et poussait le bouton dehors."],

                ['type' => 'nouveaute', 'text' => "L'application accepte plusieurs comptes. Chacun a ses propres recharges, véhicules, localisations et trajets favoris ; personne ne voit ceux des autres."],
                ['type' => 'nouveaute', 'text' => "Un trajet favori peut être copié vers d'autres comptes."],
                ['type' => 'nouveaute', 'text' => "Les identifiants Free Mobile des alertes SMS sont propres à chaque compte, renseignés depuis « Mon compte »."],
                ['type' => 'amelioration', 'text' => "Saisie d'une recharge : en choisissant « Autre… », les bornes de la base nationale sont proposées, et en retenir une renseigne aussi le fournisseur et la puissance."],

                ['type' => 'nouveaute', 'text' => "Trajets favoris : enregistrez un itinéraire, voyez toutes les bornes sur le parcours, retenez les vôtres et retrouvez-les avec un lien Google Maps ou Waze."],
                ['type' => 'nouveaute', 'text' => "Planificateur d'itinéraire avec arrêts de recharge, filtrable par puissance et par réseau."],
                ['type' => 'nouveaute', 'text' => "Carte des déplacements, reconstituée depuis la télémétrie."],
                ['type' => 'nouveaute', 'text' => "Page « Ma voiture » : niveau de charge, autonomie estimée, recharges détectées et tout ce que remonte la voiture."],
                ['type' => 'nouveaute', 'text' => "Alertes SMS au franchissement des paliers de charge, avec journal des envois."],
                ['type' => 'amelioration', 'text' => "Une recharge retient désormais la position de la borne : deux bornes d'une même ville ne se confondent plus."],
            ],
        ],
        [
            'date' => '2026-08-29',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Page « Courbe de recharge » : durée et énergie entre deux niveaux, avec la courbe propre au véhicule."],
                ['type' => 'nouveaute', 'text' => "Historique mensuel : graphiques kWh et coût, statistiques et répartition par fournisseur."],
                ['type' => 'amelioration', 'text' => "Estimation des kilomètres parcourus à partir de la consommation du véhicule."],
            ],
        ],
        [
            'date' => '2026-08-14',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Bouton « Utiliser ma position » pour renseigner le lieu d'une recharge."],
                ['type' => 'amelioration', 'text' => "Graphiques journaliers dans l'historique du mois."],
            ],
        ],
        [
            'date' => '2026-08-09',
            'entries' => [
                ['type' => 'amelioration', 'text' => "Sélecteur d'année dans le dashboard, et graphiques kWh et coût par mois dans l'historique."],
            ],
        ],
        [
            'date' => '2026-08-08',
            'entries' => [
                ['type' => 'nouveaute', 'text' => "Première version : saisie des recharges, historique, dashboard, consommation par véhicule et comparaison au prix des carburants."],
            ],
        ],
    ];
}
