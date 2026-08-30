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
