<?php

namespace App\Http\Middleware;

use App\Support\Testsystem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testsystem (docs/livegang.md): Suchmaschinen aussperren und, im Modus
 * „geschlossen“, die Website nur Angemeldeten zeigen. Anmeldung, Backend,
 * Systemstatus und Livewire bleiben erreichbar.
 */
class TestsystemSchutz
{
    private const FREI = [
        'admin', 'admin/*', 'livewire/*', 'livewire-*/*', 'filament/*',
        'auth/*', 'redaktion/anmelden', 'redaktion/anmelden/*', 'notfall-login',
        'status', 'api/status', 'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Testsystem::aktiv()) {
            return $next($request);
        }

        if (Testsystem::geschlossen() && ! $request->user() && ! $request->is(...self::FREI)) {
            return redirect()->guest(url('/admin/login'));
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
