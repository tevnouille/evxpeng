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


    'donate' => [
        // Lien PayPal (ex. https://paypal.me/votrenom) du bouton « Offrez-moi une
        // biere » en bas de page. Vide : aucun bouton.
        'url' => env('DONATE_URL'),
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

    'info_car' => [
        // Code a 6 chiffres pour deverrouiller /infoCar, retenu un an par
        // cookie chiffre (App\Http\Controllers\InfoCarController::unlock()).
        // Aucune valeur par defaut : sans PIN configure, la page reste
        // ouverte comme avant plutot que de reposer sur un code invente qui
        // donnerait une fausse impression de protection.
        'pin' => env('INFO_CAR_PIN'),
    ],

    'position_share' => [
        // Domaine dedie (optionnel) des liens de partage de position. Vide : le
        // lien est servi sur le domaine principal.
        'domain' => env('POSITION_SHARE_DOMAIN'),
    ],

    'xpeng' => [
        // API Open Platform du constructeur (App\Services\XpengClient) :
        // pas de telemetrie en direct, un fichier d'export recupere par la
        // commande planifiee `xpeng:sync`. app_id/app_secret viennent de
        // l'inscription developpeur (email a glo.open@xpeng.com) ; les
        // quatre autres champs, de l'email d'autorisation envoye a
        // l'utilisateur -- tous distincts, ne pas les confondre.
        'base_url' => env('XPENG_BASE_URL', 'https://open.xpeng.com/open/oauth2/queryData'),
        'app_id' => env('XPENG_APP_ID'),
        'app_secret' => env('XPENG_APP_SECRET'),
        'open_id' => env('XPENG_OPEN_ID'),
        'access_token' => env('XPENG_ACCESS_TOKEN'),
        'enterprise_name' => env('XPENG_ENTERPRISE_NAME'),
        'scope_code' => env('XPENG_SCOPE_CODE'),
    ],

    'meross' => [
        // Identifiants du compte Meross (App\Services\MerossClient) : les
        // memes que l'application mobile, Meross n'ayant pas d'API publique
        // avec cle dediee. L'appel reel part du service interne "meross"
        // (docker/meross/), jamais de ev-app directement (ni acces MQTT ni
        // acces au socket Docker de l'hote).
        'email' => env('MEROSS_EMAIL'),
        'password' => env('MEROSS_PASSWORD'),
        // UUID stables, decouverts une fois via l'API Meross -- pas des
        // secrets, mais autant les laisser en config que les coder en dur
        // dans le service.
        'devices' => [
            'garage' => env('MEROSS_GARAGE_UUID', '1911082967173290804648e1e9112e49'),
            'portail' => env('MEROSS_PORTAIL_UUID', '1911082759724990804648e1e9111938'),
        ],
    ],

    // Domicile (App\Console\Commands\AutoOuvrirPortail) : geocode une seule
    // fois le 30/09/2026 via App\Services\Geocoder (62 rue Henri Berreau,
    // 91100 Corbeil-Essonnes) -- coordonnees stables, meme raison de les
    // garder en config plutot qu'en recalcul a chaque execution que les UUID
    // Meross ci-dessus.
    'domicile' => [
        'lat' => (float) env('DOMICILE_LAT', 48.595264),
        'lon' => (float) env('DOMICILE_LON', 2.476097),
    ],

];
