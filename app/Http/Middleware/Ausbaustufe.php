<?php

namespace App\Http\Middleware;

use App\Support\Ausbau;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bereich erst ab einer Ausbaustufe erreichbar, sonst „nicht gefunden“
 * (z. B. ->middleware(Ausbaustufe::class.':3') für Termine).
 */
class Ausbaustufe
{
    public function handle(Request $request, Closure $next, string $mindestens): Response
    {
        abort_unless(Ausbau::reicht((int) $mindestens), 404);

        return $next($request);
    }
}
