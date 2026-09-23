<?php

namespace App\Http\Middleware;

use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reserve « Donnees Xpeng » a l'administrateur et aux comptes autorises (User::hasXpengData). */
class RequiresXpengData
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(CurrentUser::get()?->hasXpengData(), 404);

        return $next($request);
    }
}
