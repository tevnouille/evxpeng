<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reserve « Donnees Ford » a l'administrateur et au proprietaire du vehicule (User::hasFordData). */
class RequiresFordData
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CurrentUser::get()?->hasFordData(), 404);

        return $next($request);
    }
}
