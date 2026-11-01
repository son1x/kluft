<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\DatenmeldungMail;
use App\Models\Datenmeldung;
use App\Support\Datenabgleich;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * „Meine Daten aktualisieren“ (NaMi-Datenabgleich, ND-02): Mitglieder bzw.
 * Erziehungsberechtigte tragen ihre aktuellen Daten ein und bestätigen per
 * Mail-Link. Die Verwaltung vergleicht sie danach mit NaMi.
 */
class DatenmeldungController extends Controller
{
    public function create(): View
    {
        abort_unless(Datenabgleich::aktiv(), 404);

        return view('datenmeldung.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Datenabgleich::aktiv(), 404);
        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? redirect()->route('datenmeldung.danke') : back()->withInput()->withErrors(['vorname' => 'Das Formular war zu lange offen. Bitte noch einmal absenden.']);
        }
        $data = $request->validate([
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'geburtsdatum' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:'.now()->subYears(100)->toDateString()],
            'geschlecht' => ['nullable', 'in:w,m,d'],
            'strasse' => ['required', 'string', 'max:150'],
            'plz' => ['required', 'string', 'max:10', 'regex:/^([A-Za-z]{1,2}[- ]?)?[0-9]{4,5}$/'],
            'ort' => ['required', 'string', 'max:150'],
            'telefon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\/-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'email_eltern' => ['nullable', 'email:rfc', 'max:254'],
            'rolle' => ['required', 'in:mitglied,erziehungsberechtigt'],
            'einwilligung' => ['accepted'],
        ], [
            'required' => 'Bitte fülle dieses Feld aus.',
            'einwilligung.accepted' => 'Bitte bestätige die Einwilligung.',
            'email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'plz.regex' => 'Bitte gib eine gültige Postleitzahl an.',
            'telefon.regex' => 'Bitte nur Ziffern, Leerzeichen und + ( ) / - verwenden.',
        ]);
        // Bestätigt wird über die Adresse der Person, die das Formular ausfüllt.
        $an = $data['rolle'] === 'erziehungsberechtigt' ? ($data['email_eltern'] ?? null) : ($data['email'] ?? null);
        if (blank($an)) {
            return back()->withInput()->withErrors([$data['rolle'] === 'erziehungsberechtigt' ? 'email_eltern' : 'email' => 'Bitte gib deine E-Mail-Adresse an – dorthin schicken wir den Bestätigungslink.']);
        }

        $token = Str::random(40);
        Datenmeldung::query()->create([
            'daten' => collect($data)->except('einwilligung')->map(fn ($v) => is_string($v) ? trim($v) : $v)->all() + ['einwilligung_am' => now()->toIso8601String()],
            'email_hash' => Datenmeldung::hash($data['email'] ?? null),
            'email_eltern_hash' => Datenmeldung::hash($data['email_eltern'] ?? null),
            'token_hash' => hash('sha256', $token),
        ]);
        Mail::to($an)->send(new DatenmeldungMail(trim($data['vorname'].' '.$data['nachname']), route('datenmeldung.bestaetigen', $token)));

        return redirect()->route('datenmeldung.danke');
    }

    public function danke(): View
    {
        abort_unless(Datenabgleich::aktiv(), 404);

        return view('datenmeldung.danke', ['bestaetigt' => false]);
    }

    public function bestaetigen(string $token): View
    {
        abort_unless(Datenabgleich::aktiv() && preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);
        $meldung = Datenmeldung::query()->where('token_hash', hash('sha256', $token))->first();
        abort_unless($meldung !== null, 404);
        if (! $meldung->bestaetigt_am) {
            // Ältere Meldungen derselben Person ersetzt die neue.
            $daten = (array) $meldung->daten;
            Datenmeldung::query()->whereKeyNot($meldung->id)->whereNotNull('bestaetigt_am')->get()
                ->filter(fn (Datenmeldung $m) => mb_strtolower(($m->daten['vorname'] ?? '').($m->daten['nachname'] ?? '').($m->daten['geburtsdatum'] ?? ''))
                    === mb_strtolower(($daten['vorname'] ?? '').($daten['nachname'] ?? '').($daten['geburtsdatum'] ?? '')))
                ->each->delete();
            $meldung->update(['bestaetigt_am' => now(), 'token_hash' => null]);
        }

        return view('datenmeldung.danke', ['bestaetigt' => true]);
    }
}
