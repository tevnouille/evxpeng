<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reserve la gestion des comptes a l'administrateur. */
class RequiresAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CurrentUser::get()?->is_admin, 404);

        return $next($request);
    }
}
