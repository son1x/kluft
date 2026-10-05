<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Teilnahmebescheinigung;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Echtheitsprüfung einer Teilnahmebescheinigung: Prüfcode und Name rein,
 * Ergebnis raus. Speichert nichts und verrät ohne passenden Namen nichts.
 */
class CertificateController extends Controller
{
    public function show(Request $request): View
    {
        return view('certificates.verify', [
            'code' => mb_substr((string) $request->query('code', ''), 0, 40),
            'name' => '',
            'result' => null,
        ]);
    }

    public function check(Request $request, Teilnahmebescheinigung $bescheinigung): View
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:200'],
        ], [
            'code.required' => 'Bitte gib den Prüfcode von der Bescheinigung ein.',
            'name.required' => 'Bitte gib den Namen so ein, wie er auf der Bescheinigung steht.',
        ]);

        $ergebnis = $bescheinigung->verify($data['code'], $data['name']);

        return view('certificates.verify', [
            'code' => $data['code'],
            'name' => $data['name'],
            'result' => match (true) {
                $ergebnis instanceof Event => 'echt',
                $ergebnis === 'tippfehler' => 'tippfehler',
                default => 'unbekannt',
            },
            'event' => $ergebnis instanceof Event ? $ergebnis : null,
        ]);
    }
}
