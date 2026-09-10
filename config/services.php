<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'passkey' => [
        // Passerelle qui authentifie et pose l'en-tete d'identite. L'application
        // n'est joignable que par elle ; cette adresse ne sert qu'a construire
        // le lien de deconnexion, la passerelle etant seule a tenir une session.
        'url' => env('PASSKEY_GATEWAY_URL', 'https://pk.lolinux.org'),
    ],

    'osrm' => [
        // Serveur de calcul d'itineraire. La demo publique suffit au volume d'une
        // application personnelle ; l'URL reste configurable pour pouvoir
        // basculer sur une instance privee si elle se met a refuser les appels.
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
    ],

    'irve' => [
        // Fichier consolide de la base nationale des bornes de recharge.
        // L'identifiant de ressource est stable, c'est son contenu qui change.
        'url' => env('IRVE_CSV_URL', 'https://www.data.gouv.fr/api/1/datasets/r/eb76d20a-8501-400e-b336-d85724de5435'),
    ],

    'charge_alerts' => [
        // Paliers de niveau de charge donnant lieu a un SMS, au franchissement.
        'thresholds' => [79, 89, 95, 96, 97, 98, 99, 100],
    ],

];
