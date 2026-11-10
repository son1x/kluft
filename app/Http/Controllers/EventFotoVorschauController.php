<?php

namespace App\Http\Controllers;

use App\Models\EventFoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vorschau noch nicht freigegebener Veranstaltungsfotos, nur für Personen,
 * die die Teilnehmenden der Veranstaltung verwalten dürfen.
 */
class EventFotoVorschauController extends Controller
{
    public function __invoke(Request $request, EventFoto $foto): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('manageParticipants', $foto->event) && $foto->path, 404);

        return Storage::disk(EventFoto::DISK)->response($foto->path, null, [
            'Cache-Control' => 'private, max-age=600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
