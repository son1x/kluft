<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Wartung;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wartungsmodus von Hand (App\Support\Wartung): Besucher*innen bekommen die
 * Wartungsseite (503), Angemeldete sehen die Website. Mit „nur Admins“
 * kommen andere Angemeldete auch nicht ins Backend.
 */
class Wartungsmodus
{
    /** Immer erreichbar: Anmeldung, Systemstatus, Livewire/Filament für das Login. */
    private const FREI = [
        'admin/login', 'admin/login/*', 'admin/logout', 'livewire/*', 'livewire-*/*', 'filament/*',
        'auth/*', 'redaktion/anmelden', 'redaktion/anmelden/*', 'notfall-login',
        'status', 'api/status', 'up', 'einspielen/stand/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $stand = Wartung::stand();
        if (! $stand['aktiv']) {
            return $next($request);
        }

        $user = $request->user();
        $backend = $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*', 'filament/*');

        // Redaktionsstopp: Angemeldete ohne Admin-Rolle bleiben draußen (außer Abmelden).
        if ($stand['nur_admins'] && $user instanceof User && ! $user->isAdmin() && ! $request->is('admin/logout')) {
            return $this->seite($stand, 'Das Backend ist gerade gesperrt. Bitte schau etwas später noch einmal vorbei.');
        }

        // Bestätigen/Stornieren per Link aus der Mail bei „Vorab extern freigeben“
        // (TestsystemSchutz prüft das Token): Auf Test bleibt das auch beim Umzug
        // möglich, docs/livegang.md „Anmeldungen vor dem Livegang“.
        if ($user === null && $request->attributes->get('testsystem_extern') && $request->is('anmeldung/bestaetigen/*', 'anmeldung/stornieren/*')) {
            return $next($request);
        }

        if ($user !== null || $backend || $request->is(...self::FREI)) {
            return $next($request);
        }

        return $this->seite($stand, $stand['text']);
    }

    /** @param  array{bis: ?Carbon}  $stand */
    private function seite(array $stand, string $text): Response
    {
        $antwort = response()->view('errors.503', ['wartungText' => $text, 'wartungBis' => $stand['bis']], 503);
        $antwort->headers->set('Cache-Control', 'no-store');
        $antwort->headers->set('X-Robots-Tag', 'noindex');
        if ($stand['bis']?->isFuture()) {
            $antwort->headers->set('Retry-After', (string) max(60, (int) now()->diffInSeconds($stand['bis'])));
        }

        return $antwort;
    }
}
