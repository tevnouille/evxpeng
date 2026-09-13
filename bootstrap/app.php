<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App uniquement joignable via le nginx qui termine le TLS (l'hote du VPS
        // Hostinger depuis le 2026-09-10 ; HAProxy + ev-gate sur l'ancien
        // hebergement hostingtools, decommissionne) ; sans ca, isSecure()
        // renvoie false et les assets/redirections sont generes en http://
        // (mixed content).
        //
        // On ne truste QUE les reseaux Docker internes, pas `*` ni tout le
        // prive. Subtilite mesuree sur l'ancien hebergement, TOUJOURS VALABLE
        // sur le VPS : le trafic entrant est NATe une fois de plus par la
        // regle DOCKER-USER qui isole les conteneurs, si bien que le reverse
        // proxy ne voit jamais directement l'IP publique du visiteur au niveau
        // ou Symfony la lirait sans ce reglage. Truster tout le prive
        // reviendrait a retomber sur l'entree que le client force a gauche du
        // X-Forwarded-For — le throttle de /infoCar redeviendrait contournable
        // (une IP forgee par requete = une limite neuve). En ne trustant que
        // les reseaux Docker, Symfony s'arrete au bon hop : tout le trafic
        // entrant partage alors une seule limite de 60/min, exactement le but
        // pour une appli mono-utilisateur exposee.
        //
        // '172.16.0.0/12' couvre le reseau Docker `ev_default` du VPS, verifie
        // a '172.16.4.0/24' (sudo docker network inspect). Les trois /20 de
        // l'ancien hebergement hostingtools (ev_default, ev-net, bridge
        // HAProxy -> ev-gate) sont retires le 2026-09-13 : ce sont les memes
        // reseaux que ceux decommissionnes avec ev-gate, plus aucune
        // correspondance possible sur le VPS.
        $middleware->trustProxies(at: [
            '172.16.0.0/12',     // pool Docker : couvre ev_default sur le VPS (172.16.4.0/24)
            '10.0.0.0/8',
        ]);

        // Identite fournie par la passerelle passkey : sans elle, aucune page
        // n'est servie, et c'est elle qui cloisonne les donnees par compte.
        $middleware->web(append: [\App\Http\Middleware\IdentifyUser::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
