<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Umfrage;
use App\Models\UmfrageAntwort;
use App\Models\UmfrageEinladung;
use App\Models\UmfrageGewinnspiel;
use App\Services\Umfragen\Einladungen;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\SpamGuard;
use App\Support\Umfragen;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Teilnahme an einer Umfrage (docs/umfragen.md): öffentlicher Link, auf
 * Wunsch mit Zugangscode, oder persönlicher Link aus der Einladung an die
 * Teilnehmenden einer Veranstaltung. Danach optional das Gewinnspiel,
 * getrennt von den Antworten.
 */
class UmfrageController extends Controller
{
    public function __construct(private Einladungen $einladungen) {}

    public function zeigen(Request $request, string $code): View
    {
        $umfrage = $this->umfrage($code);
        $einladung = $this->einladungen->finden($umfrage, $request->query('t', $request->input('t')));
        if ($sperre = $this->sperre($request, $umfrage, $einladung)) {
            return $sperre;
        }

        return view('umfragen.zeigen', ['umfrage' => $umfrage->load('fragen'), 'token' => $einladung ? (string) $request->query('t') : null, 'vorschau' => false]);
    }

    /** Vorschau für das Backend: auch als Entwurf, ohne Absenden. */
    public function vorschau(Umfrage $umfrage): View
    {
        abort_unless(Umfragen::zugriff(Filament::auth()->user()), 403);

        return view('umfragen.zeigen', ['umfrage' => $umfrage->load('fragen'), 'token' => null, 'vorschau' => true]);
    }

    public function code(Request $request, string $code): RedirectResponse
    {
        $umfrage = $this->umfrage($code);
        $request->validate(['zugangscode' => ['required', 'string', 'max:50']]);
        if (! hash_equals(mb_strtolower(trim((string) $umfrage->zugangscode)), mb_strtolower(trim((string) $request->input('zugangscode'))))) {
            return back()->withErrors(['zugangscode' => 'Der Zugangscode stimmt nicht.']);
        }
        $request->session()->put('umfrage.'.$umfrage->id.'.code', true);

        return redirect()->route('umfragen.zeigen', $umfrage->code);
    }

    public function absenden(Request $request, string $code): RedirectResponse|View
    {
        $umfrage = $this->umfrage($code);
        $token = $request->input('t');
        $einladung = $this->einladungen->finden($umfrage, $token);
        if ($sperre = $this->sperre($request, $umfrage, $einladung)) {
            return $sperre;
        }
        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? redirect()->route('umfragen.danke', $umfrage->code) : back()->withInput()->with('umfrage_status', 'abgelaufen');
        }

        $fragen = $umfrage->fragen()->get();
        [$daten, $fehler] = Umfragen::pruefen($fragen, $request->only(['a', 'kommentar', 'sonstiges']));
        if ($fehler !== []) {
            return back()->withInput()->withErrors($fehler)->with('umfrage_status', 'fehler');
        }

        $registration = $einladung?->registration;
        UmfrageAntwort::query()->create([
            'umfrage_id' => $umfrage->id,
            'eingegangen_am' => now()->toDateString(),
            'event_registration_id' => $umfrage->anonym ? null : $registration?->id,
            'name' => $umfrage->anonym || ! $registration ? null : $registration->fullName(),
            'email' => $umfrage->anonym || ! $registration ? null : $registration->email,
            'daten' => $daten,
        ]);
        $einladung?->update(['teilgenommen_am' => now()->toDateString()]);
        AuditLogger::log('umfrage.antwort', request: $request);

        $request->session()->put('umfrage.'.$umfrage->id.'.gewinnspiel', $umfrage->gewinnspiel);
        $antwort = redirect()->route('umfragen.danke', $umfrage->code);
        if (! $umfrage->mehrfach && ! $einladung) {
            $antwort->withCookie(Cookie::make('umfrage_'.$umfrage->id, '1', 60 * 24 * 365));
        }

        return $antwort;
    }

    public function danke(Request $request, string $code): View
    {
        $umfrage = $this->umfrage($code);

        return view('umfragen.danke', [
            'umfrage' => $umfrage,
            'gewinnspiel' => $umfrage->gewinnspiel && $request->session()->get('umfrage.'.$umfrage->id.'.gewinnspiel'),
        ]);
    }

    public function gewinnspiel(Request $request, string $code): RedirectResponse
    {
        $umfrage = $this->umfrage($code);
        abort_unless($umfrage->gewinnspiel && $request->session()->get('umfrage.'.$umfrage->id.'.gewinnspiel'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'einwilligung' => ['accepted'],
        ], [
            'name.required' => 'Bitte gib deinen Namen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'einwilligung.accepted' => 'Bitte bestätige den Hinweis zum Gewinnspiel.',
        ]);
        UmfrageGewinnspiel::query()->create([
            'umfrage_id' => $umfrage->id, 'name' => trim($data['name']), 'email' => mb_strtolower(trim($data['email'])), 'eingegangen_am' => now()->toDateString(),
        ]);
        $request->session()->forget('umfrage.'.$umfrage->id.'.gewinnspiel');

        return redirect()->route('umfragen.danke', $umfrage->code)->with('gewinnspiel_status', 'eingetragen');
    }

    private function umfrage(string $code): Umfrage
    {
        abort_unless(Features::enabled(Features::UMFRAGEN), 404);

        return Umfrage::query()->umfragen()->where('code', $code)->firstOrFail();
    }

    /** Hinweis statt Formular, wenn die Teilnahme gerade nicht geht. */
    private function sperre(Request $request, Umfrage $umfrage, ?UmfrageEinladung $einladung): ?View
    {
        $hinweis = fn (string $art) => view('umfragen.hinweis', ['umfrage' => $umfrage, 'art' => $art]);

        return match (true) {
            $umfrage->status === 'entwurf' || $umfrage->status === 'archiviert' => abort(404),
            $umfrage->nurEingeladene() && ! $einladung => $hinweis('nur_eingeladene'),
            ! $umfrage->istOffen() => $hinweis($umfrage->beginnt_am?->isFuture() ? 'noch_nicht' : 'geschlossen'),
            $einladung !== null && $einladung->teilgenommen_am !== null && ! $umfrage->mehrfach => $hinweis('schon_teilgenommen'),
            ! $einladung && ! $umfrage->mehrfach && $request->cookie('umfrage_'.$umfrage->id) => $hinweis('schon_teilgenommen'),
            ! $einladung && filled($umfrage->zugangscode) && ! $request->session()->get('umfrage.'.$umfrage->id.'.code') => $hinweis('code'),
            default => null,
        };
    }
}
