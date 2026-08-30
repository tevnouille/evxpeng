<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reserve les pages de telemetrie aux comptes qui en ont une.
 *
 * "Ma voiture" et "Deplacements" n'affichent que des donnees remontees par
 * A Better Routeplanner : sans token ABRP sur un vehicule — ce qui suppose un
 * abonnement ABRP Premium a soi — elles n'ont rien a montrer. Plutot que de les
 * proposer vides, on les retire de la navigation et on repond 404.
 *
 * Le critere est la presence d'un token, pas une liste d'emails : le jour ou un
 * autre utilisateur prend un abonnement et renseigne le sien, les pages
 * apparaissent d'elles-memes.
 */
class RequiresTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CurrentUser::get()?->hasTelemetry(), 404);

        return $next($request);
    }
}
