<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\EventRegistration;
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

        $externFrei = ! $request->user() && self::externFrei($request);
        // Für Außenstehende ohne gelbes Band (Layout), Suchmaschinen bleiben ausgesperrt.
        $request->attributes->set('testsystem_extern', $externFrei);
        if (Testsystem::geschlossen() && ! $request->user() && ! $request->is(...self::FREI) && ! $externFrei) {
            return redirect()->guest(url('/admin/login'));
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * Veranstaltungen mit „Vorab extern freigeben“ sind auch auf dem
     * geschlossenen Testsystem öffentlich: Seite, Einbett-Ansicht, Anmeldung,
     * Bestätigen und Stornieren per Link, dazu Datenschutz und Impressum.
     */
    private static function externFrei(Request $request): bool
    {
        $pfad = $request->path();
        if (in_array($pfad, ['datenschutz', 'impressum'], true)) {
            return Event::query()->where('extern_freigegeben', true)->exists();
        }
        if (preg_match('#^veranstaltungen/([^/]+)(/einbetten|/anmeldung)?$#', $pfad, $t)) {
            return Event::query()->where('extern_freigegeben', true)->where('slug', $t[1])->exists();
        }
        if (preg_match('#^anmeldung/(bestaetigen|stornieren)/([^/]+)$#', $pfad, $t)) {
            $spalte = $t[1] === 'bestaetigen' ? 'confirm_token_hash' : 'cancel_token_hash';

            return EventRegistration::query()->where($spalte, EventRegistration::hashToken($t[2]))
                ->whereHas('event', fn ($q) => $q->where('extern_freigegeben', true))->exists();
        }

        return false;
    }
}
