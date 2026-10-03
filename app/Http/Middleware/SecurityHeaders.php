<?php

namespace App\Http\Middleware;

use App\Support\Embeds;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security-Header nach Pflichtenheft 10.1. Die öffentliche Website kommt
 * ohne Inline-Skripte aus; das Backend (Filament/Livewire) braucht sie.
 * Alpine.js braucht 'unsafe-eval'. Alle Quellen sind lokal – externe
 * Dienste würden hier ohnehin blockiert (F-CON-02/06).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isBackend = $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*', 'filament/*');

        $scriptSrc = ["'self'", "'unsafe-eval'"];
        if ($isBackend) {
            $scriptSrc[] = "'unsafe-inline'";
        }

        $connectSrc = ["'self'"];

        // Lokale Entwicklung mit `npm run dev`: Vite-Dev-Server zulassen.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $scriptSrc[] = $hot;
            $connectSrc[] = $hot;
            $connectSrc[] = preg_replace('#^http#', 'ws', $hot);
        }

        $csp = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSrc,
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => $connectSrc,
            'worker-src' => ["'self'", 'blob:'],
            'child-src' => ["'self'", 'blob:'],
            // Zwei-Klick-Einbettungen (Videos, Karten) – erst nach Klick geladen.
            'frame-src' => ["'self'", ...Embeds::frameSources()],
            'frame-ancestors' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            // Abmelden leitet über /admin/login weiter zur Microsoft-Anmeldung.
            'form-action' => ["'self'", 'https://login.microsoftonline.com'],
        ];

        $headers = [
            'Content-Security-Policy' => collect($csp)->map(fn (array $v, string $k) => $k.' '.implode(' ', array_unique($v)))->implode('; '),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure() || str_starts_with((string) config('app.url'), 'https://')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
