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

    'info_car' => [
        // Code a 6 chiffres pour deverrouiller /infoCar, retenu un an par
        // cookie chiffre (App\Http\Controllers\InfoCarController::unlock()).
        // Aucune valeur par defaut : sans PIN configure, la page reste
        // ouverte comme avant plutot que de reposer sur un code invente qui
        // donnerait une fausse impression de protection.
        'pin' => env('INFO_CAR_PIN'),
    ],

    'position_share' => [
        // Domaine dedie des liens de partage de position, distinct du domaine
        // principal de l'appli : un vhost a part, entierement public, qui ne
        // passe pas par la passerelle passkey (docker/share/README.md).
        'domain' => env('POSITION_SHARE_DOMAIN', 's.lolinux.fr'),
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

    'ford' => [
        // API FordConnect (App\Services\FordClient) : client_id/client_secret
        // viennent de l'inscription sur developer.ford.com. Distinct de
        // l'autorisation par vehicule (access_token/refresh_token), qui elle
        // vit en base (App\Models\FordOAuthToken) et non ici : le
        // refresh_token tourne a chaque utilisation cote Ford.
        // "FordConnect Query" (v1/garage, v1/telemetry) : le programme actuel,
        // distinct de l'ancien api.mps.ford.com/api/fordconnect/vehicles/v3
        // (toujours documente ca et la, mais pas ce que expose ce compte).
        'base_url' => env('FORD_BASE_URL', 'https://api.vehicle.ford.com/fcon-query'),
        // Domaine + tenant + politique propres a FordConnect Query (verifies
        // le 22/09/2026 via le client officiel d'evcc, apres un premier essai
        // rate sur le tenant/politique B2C generique -- kid introuvable pour
        // le JWE, la politique ne correspondait pas a celle qui a emis le code).
        'token_url' => env('FORD_TOKEN_URL', 'https://api.vehicle.ford.com/dah2vb2cprod.onmicrosoft.com/oauth2/v2.0/token?p=B2C_1A_FCON_AUTHORIZE'),
        // Point d'entree reel de l'autorisation FordConnect, confirme le
        // 22/09/2026 -- absent de developer.ford.com, gere lui-meme la
        // redirection vers la connexion Ford/FordPass puis vers redirect_uri.
        'authorize_url' => env('FORD_AUTHORIZE_URL', 'https://api.vehicle.ford.com/fcon-public/v1/auth/init'),
        // Constante publique du programme FordConnect, exigee en en-tete de
        // chaque appel a l'API (vue dans plusieurs implementations tierces
        // publiques) -- ne s'obtient pas depuis developer.ford.com.
        'application_id' => env('FORD_APPLICATION_ID', 'AFDC085B-377A-4351-B23E-5E1D35FB3700'),
        'client_id' => env('FORD_CLIENT_ID'),
        'client_secret' => env('FORD_CLIENT_SECRET'),
        'vin' => env('FORD_VIN'),
        'redirect_uri' => env('FORD_REDIRECT_URI'),
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
        // dans le service, meme convention que Ford application_id.
        'devices' => [
            'garage' => env('MEROSS_GARAGE_UUID', '1911082967173290804648e1e9112e49'),
            'portail' => env('MEROSS_PORTAIL_UUID', '1911082759724990804648e1e9111938'),
        ],
    ],

];
