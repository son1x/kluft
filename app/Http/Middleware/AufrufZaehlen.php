<?php

namespace App\Http\Middleware;

use App\Support\Aufrufe;
use App\Support\Features;
use App\Support\ParticipantLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Seitenaufrufe zählen ohne Personenbezug (docs/aufrufe.md): je Pfad und
 * Tag eine Zahl, sonst nichts. Der User-Agent wird nur auf Bots geprüft und
 * nie gespeichert. Geschrieben wird erst nach der Antwort (defer), Fehler
 * beim Zählen machen keine Seite kaputt.
 */
class AufrufZaehlen
{
    /** Erste Pfad-Teile, die nicht zur öffentlichen Website gehören. */
    private const NICHT_OEFFENTLICH = [
        'admin', 'filament', 'api', 'build', 'storage', 'uploads',
        // Anmeldung der Redaktion ist kein Inhalt.
        'auth', 'redaktion', 'notfall-login',
    ];

    private const BOTS = '/bot|crawl|spider|slurp|preview|monitor|uptime|curl|wget|python|httpclient|headless|lighthouse|facebookexternalhit|whatsapp/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->zaehlt($request, $response)) {
                $pfad = Aufrufe::pfad($request->path());
                $datum = now()->toDateString();
                defer(function () use ($pfad, $datum) {
                    try {
                        Aufrufe::erhoehen($pfad, $datum);
                    } catch (Throwable $e) {
                        // Ohne Personendaten: nur Art des Fehlers.
                        Log::warning('Seitenaufruf nicht gezählt: '.$e::class);
                    }
                });
            }
        } catch (Throwable $e) {
            Log::warning('Seitenaufruf nicht gezählt: '.$e::class);
        }

        return $response;
    }

    private function zaehlt(Request $request, Response $response): bool
    {
        if ($request->getMethod() !== 'GET' || $response->getStatusCode() !== 200) {
            return false;
        }
        if (! str_starts_with(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) {
            return false;
        }
        if (! $this->oeffentlich($request->path())) {
            return false;
        }

        // Vorabladen, „Do Not Track“, „Global Privacy Control“.
        $purpose = strtolower($request->headers->get('Sec-Purpose', '').' '.$request->headers->get('Purpose', '').' '.$request->headers->get('X-Moz', ''));
        if (str_contains($purpose, 'prefetch') || $request->headers->get('DNT') === '1' || $request->headers->get('Sec-GPC') === '1') {
            return false;
        }

        $agent = (string) $request->userAgent();
        if (trim($agent) === '' || preg_match(self::BOTS, $agent)) {
            return false;
        }

        // Angemeldete Redaktion und Teilnehmende („Meine Anmeldungen“) zählen nicht.
        if ($request->user() !== null) {
            return false;
        }
        if ($request->hasSession() && app(ParticipantLogin::class)->email($request) !== null) {
            return false;
        }

        return Features::enabled(Features::AUFRUFE);
    }

    private function oeffentlich(string $pfad): bool
    {
        $pfad = trim($pfad, '/');
        $erster = strtolower(strtok($pfad, '/') ?: '');
        if (in_array($erster, self::NICHT_OEFFENTLICH, true) || str_starts_with($erster, 'livewire')) {
            return false;
        }

        // Dateien (.pdf, .xml, .ics, .json, .txt, .webmanifest …) zählen nicht.
        return ! preg_match('/\.[a-z0-9]{1,12}$/i', $pfad);
    }
}
