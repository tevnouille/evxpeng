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
        // App uniquement joignable via ev-gate + HAProxy qui terminent le TLS
        // et transmettent X-Forwarded-Proto ; sans ca, isSecure() renvoie false
        // et les assets/redirections sont generes en http:// (mixed content).
        //
        // On ne truste QUE les reseaux Docker internes, pas `*` ni tout le
        // prive. Subtilite mesuree en production : le trafic externe est NATe
        // par la Freebox, si bien que HAProxy ne voit jamais l'IP publique du
        // client mais l'IP LAN de la box (192.168.1.254). Cette IP est privee :
        // truster tout le prive revenait a la depasser et a retomber sur
        // l'entree que le client force a gauche du X-Forwarded-For — le throttle
        // de /infoCar restait contournable (une IP par requete = une limite
        // neuve). En ne trustant que les /20 Docker (ev-net, ev_default, et le
        // bridge d'acces HAProxy), Symfony s'arrete sur 192.168.1.254 : tout le
        // trafic entrant partage alors une seule limite de 60/min, ce qui est
        // exactement le but pour une appli mono-utilisateur exposee.
        //
        // Note : REMOTE_ADDR (ev-gate, 192.168.64.x) reste truste, donc
        // X-Forwarded-Proto est honore et isSecure() fonctionne.
        $middleware->trustProxies(at: [
            '192.168.48.0/20',   // ev_default
            '192.168.64.0/20',   // ev-net
            '192.168.80.0/20',   // bridge d'acces HAProxy -> ev-gate
            '172.16.0.0/12',     // pool Docker alternatif
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
