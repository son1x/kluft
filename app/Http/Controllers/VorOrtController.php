<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Support\VorOrt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'ereignisse.*.anmeldung' => ['required', 'integer'],
            'ereignisse.*.daten' => ['nullable', 'array'],
            'ereignisse.*.zeit' => ['nullable', 'date'],
        ]);

        return response()->json(['ergebnisse' => VorOrt::verarbeiten($user, $request->input('ereignisse'))]);
    }

    private function nutzer(Request $request): User
    {
        abort_unless(VorOrt::aktiv(), 404);
        $user = $request->user();
        abort_unless($user instanceof User && $user->isActive(), 403);

        return $user;
    }
}
