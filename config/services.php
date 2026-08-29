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

    'abrp' => [
        // Cle API "Telemetry-Only" (gratuite) generee sur abetterrouteplanner.com.
        // Le token utilisateur, lui, identifie un vehicule precis : il est stocke
        // sur la fiche du vehicule, pas ici.
        'key' => env('ABRP_API_KEY'),
        'base_url' => env('ABRP_BASE_URL', 'https://api.iternio.com/1'),
    ],

    'free_mobile' => [
        // API SMS Free Mobile : a activer depuis l'espace abonne. Seul le GET est
        // accepte, et la reponse se limite a un code HTTP.
        'user' => env('FREE_MOBILE_USER'),
        'password' => env('FREE_MOBILE_PASS'),
    ],

    'charge_alerts' => [
        // Paliers de niveau de charge donnant lieu a un SMS, au franchissement.
        'thresholds' => [79, 89, 95, 96, 97, 98, 99, 100],
    ],

];
