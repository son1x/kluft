<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Support\VorOrt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Vor Ort (docs/vor-ort.md): Seite für Tablet und Handy und ihre
 * JSON-Schnittstelle. Nur angemeldet, nur mit eingeschalteter Funktion.
 */
class VorOrtController extends Controller
{
    public function seite(Request $request): View
    {
        $this->nutzer($request);

        return view('vor-ort.app');
    }

    public function veranstaltungen(Request $request): JsonResponse
    {
        $user = $this->nutzer($request);

        return response()->json([
            'person' => $user->name,
            'veranstaltungen' => VorOrt::veranstaltungen($user)->map(fn (Event $e) => VorOrt::uebersicht($e))->values(),
        ]);
    }

    public function veranstaltung(Request $request, Event $event): JsonResponse
    {
        abort_unless($this->nutzer($request)->can('manageParticipants', $event), 403);

        return response()->json(VorOrt::daten($event));
    }

    public function ereignisse(Request $request): JsonResponse
    {
        $user = $this->nutzer($request);
        $request->validate([
            'ereignisse' => ['required', 'array', 'max:200'],
            'ereignisse.*.id' => ['required', 'uuid'],
            'ereignisse.*.typ' => ['required', 'string', 'max:30'],
            // Nummer der Anmeldung, oder „neu:<Kennung>“ für eine Person, die in derselben Warteschlange nachgemeldet wird.
            'ereignisse.*.anmeldung' => ['nullable', 'regex:/^(\d+|neu:[0-9a-fA-F-]{36})$/'],
            'ereignisse.*.event' => ['nullable', 'integer'],
            'ereignisse.*.daten' => ['nullable', 'array'],
            'ereignisse.*.zeit' => ['nullable', 'date'],
        ]);

        return response()->json(['ergebnisse' => VorOrt::verarbeiten($user, $request->input('ereignisse'))]);
    }

    /** Web-App-Manifest: „Vor Ort“ als App installierbar (Stufe 3). */
    public function manifest(): JsonResponse
    {
        abort_unless(VorOrt::aktiv(), 404);

        return response()->json([
            'name' => 'Vor Ort – '.config('dpsg.site.name'),
            'short_name' => 'Vor Ort',
            'description' => 'Begleiter für Veranstaltungen: Anwesenheit, Zahlungen, Unterschriften – auch ohne Netz.',
            'lang' => 'de',
            'start_url' => '/vor-ort',
            'scope' => '/vor-ort/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f6f2ea',
            'theme_color' => '#003056',
            'icons' => [
                ['src' => route('vor-ort.symbol', '192', false), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('vor-ort.symbol', '512', false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Service Worker: nur Programmdateien zwischenspeichern, nie Daten (docs/vor-ort.md, Stufe 3). */
    public function serviceWorker(): Response
    {
        abort_unless(VorOrt::aktiv(), 404);

        return response(file_get_contents(resource_path('js/vor-ort-sw.js')), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/vor-ort/',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /** Symbol der App: Logo der Gliederung mittig auf weißer Fläche. */
    public function symbol(string $groesse): Response
    {
        abort_unless(VorOrt::aktiv(), 404);
        $png = VorOrt::symbol((int) $groesse);
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    private function nutzer(Request $request): User
    {
        abort_unless(VorOrt::aktiv(), 404);
        $user = $request->user();
        abort_unless($user instanceof User && $user->isActive(), 403);

        return $user;
    }
}
