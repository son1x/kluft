<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ShopBestellungMail;
use App\Models\Page;
use App\Models\Shop\Artikel;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Runde;
use App\Models\Shop\Set;
use App\Models\Shop\Variante;
use App\Models\User;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use App\Support\SpamGuard;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * Öffentliche Seite der Sammelbestellung (/bestellen): Shop der freigegebenen
 * Artikel, Bestellformular für Mitglieder bzw. Erziehungsberechtigte,
 * Bestätigung per Mail-Link, Statusseite mit Storno solange offen.
 */
class ShopController extends Controller
{
    public function index(): View
    {
        abort_unless(Sammelbestellung::sichtbar(auth()->user()), 404);

        return view('shop.index', [
            'testbetrieb' => Sammelbestellung::testbetrieb(),
            'page' => Page::query()->published()->where('slug', 'bestellen')->first(),
            'runde' => Runde::offene(),
            'bestellbar' => Sammelbestellung::bestellbar(auth()->user()),
            'fehlt' => auth()->user()?->canAccessPanel(Filament::getPanel('admin')) ? Sammelbestellung::fehlt() : [],
            'sets' => Set::query()->freigegeben()->with(['teile.artikel.varianten' => fn ($q) => $q->where('aktiv', true), 'bildArtikel.bild'])->orderBy('sort')->orderBy('name')->get()
                ->filter(fn (Set $s) => $s->bestellbar())->values(),
            'artikel' => Artikel::query()->freigegeben()->with(['varianten' => fn ($q) => $q->where('aktiv', true), 'bild'])->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    /** Freier Artikel: Name, Preis und Größen zur Artikelnummer (Rüsthaus, gecacht). */
    public function nachschlagen(Request $request): JsonResponse
    {
        abort_unless(Sammelbestellung::bestellbar(auth()->user()), 404);
        $nummer = trim((string) $request->query('nummer'));
        abort_unless($nummer !== '' && mb_strlen($nummer) <= 300, 422);

        return response()->json(Sammelbestellung::nachschlagen($nummer) ?? ['gefunden' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Sammelbestellung::bestellbar(auth()->user()), 404);
        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? redirect()->route('shop.danke') : back()->withInput()->withErrors(['name' => 'Das Formular war zu lange offen. Bitte noch einmal absenden.']);
        }
        $runde = Runde::offene();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'telefon' => ['nullable', 'string', 'max:40'],
            'rolle' => ['required', 'in:'.implode(',', array_keys(Bestellung::ROLLEN))],
            'abholung' => ['nullable', 'string', 'max:120'],
            'bemerkung' => ['nullable', 'string', 'max:2000'],
            'einwilligung' => ['accepted'],
            'pos' => ['required', 'array', 'min:1', 'max:30'],
            'pos.*.mitglied' => ['nullable', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'pos.*.stufe' => ['nullable', 'in:'.implode(',', array_keys((array) config('dpsg.stages')))],
            'pos.*.menge' => ['required', 'integer', 'min:1', 'max:10'],
            'pos.*.variante' => ['nullable', 'integer'],
            'pos.*.set' => ['nullable', 'integer'],
            'pos.*.teile' => ['nullable', 'array', 'max:20'],
            'pos.*.teile.*.variante' => ['nullable', 'integer'],
            'pos.*.teile.*.dabei' => ['nullable', 'boolean'],
            'pos.*.frei_nummer' => ['nullable', 'string', 'max:300'],
            'pos.*.frei_text' => ['nullable', 'string', 'max:160'],
        ], [
            'pos.required' => 'Bitte mindestens einen Artikel eintragen.',
            'einwilligung.accepted' => 'Bitte bestätige die Einwilligung.',
        ]);

        $positionen = [];
        foreach ($data['pos'] as $i => $pos) {
            // Ohne Angabe ist die bestellende Person selbst gemeint.
            $basis = ['mitglied' => trim((string) ($pos['mitglied'] ?? '')) ?: trim($data['name']), 'stufe' => $pos['stufe'] ?? null, 'menge' => (int) $pos['menge']];
            if (filled($pos['set'] ?? null)) {
                $set = Set::query()->whereKey($pos['set'])->with('teile.artikel.varianten')->first();
                try {
                    array_push($positionen, ...Sammelbestellung::setPositionen($set ?? new Set, (int) $pos['menge'], (array) ($pos['teile'] ?? []), Arr::except($basis, 'menge')));
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(["pos.{$i}.set" => $e->getMessage()]);
                }

                continue;
            }
            if (filled($pos['variante'] ?? null)) {
                $variante = Variante::query()->whereKey($pos['variante'])->where('aktiv', true)
                    ->whereHas('artikel', fn ($q) => $q->where('freigegeben', true))->with('artikel')->first();
                if (! $variante) {
                    throw ValidationException::withMessages(["pos.{$i}.variante" => 'Diesen Artikel gibt es gerade nicht.']);
                }
                $positionen[] = $basis + ['artikel_id' => $variante->artikel_id, 'nummer' => $variante->nummer, 'bezeichnung' => $variante->artikel->name,
                    'groesse' => $variante->bezeichnung, 'preis_cent' => $variante->preis_cent, 'frei' => false, 'bestaetigt' => true];

                continue;
            }
            $nummer = trim((string) ($pos['frei_nummer'] ?? ''));
            if ($nummer === '') {
                throw ValidationException::withMessages(["pos.{$i}.variante" => 'Bitte einen Artikel wählen oder eine Rüsthaus-Artikelnummer eintragen.']);
            }
            $info = Sammelbestellung::nachschlagen($nummer);
            if ($info['gesperrt'] ?? false) {
                throw ValidationException::withMessages(["pos.{$i}.frei_nummer" => 'Dieser Artikel kann über die Sammelbestellung nicht bestellt werden. Sprich bitte deine Leitung an.']);
            }
            // Die eingegebene Nummer kann eine bestimmte Größe sein.
            $passend = collect($info['varianten'] ?? [])->firstWhere('nummer', $nummer);
            $positionen[] = $basis + [
                'artikel_id' => null,
                'nummer' => Str::limit($passend['nummer'] ?? ($info['nummer'] ?? (Str::startsWith($nummer, 'http') ? null : $nummer)), 40, ''),
                'bezeichnung' => mb_substr($info['name'] ?? (trim((string) ($pos['frei_text'] ?? '')) ?: 'Artikel '.$nummer), 0, 160),
                'groesse' => $passend['bezeichnung'] ?? (filled($pos['frei_text'] ?? null) && isset($info['name']) ? mb_substr((string) $pos['frei_text'], 0, 120) : null),
                'preis_cent' => $passend['preis_cent'] ?? ($info['preis_cent'] ?? null),
                'frei' => true, 'bestaetigt' => false,
            ];
        }

        $token = Str::random(40);
        $bestellung = DB::transaction(function () use ($data, $positionen, $runde, $token) {
            $bestellung = Bestellung::query()->create([
                'runde_id' => $runde->id, 'token' => hash('sha256', $token), 'name' => trim($data['name']), 'email' => $data['email'],
                'telefon' => $data['telefon'] ?? null, 'rolle' => $data['rolle'], 'abholung' => $data['abholung'] ?? null, 'bemerkung' => $data['bemerkung'] ?? null,
                'test' => Sammelbestellung::testbetrieb(),
            ]);
            $bestellung->positionen()->createMany($positionen);

            return $bestellung;
        });

        Mail::to($bestellung->email, $bestellung->name)->send(new ShopBestellungMail($bestellung, 'doi', $token));

        return redirect()->route('shop.danke');
    }

    public function danke(): View
    {
        abort_unless(Sammelbestellung::aktiv(), 404);

        return view('shop.danke');
    }

    public function show(string $token): View
    {
        $bestellung = $this->bestellung($token);

        return view('shop.show', ['bestellung' => $bestellung->load('positionen.zahlung', 'runde', 'zahlungen'), 'token' => $token]);
    }

    public function bestaetigen(string $token): RedirectResponse
    {
        $bestellung = $this->bestellung($token);
        if (! $bestellung->bestaetigt_am && ! $bestellung->storniert_am) {
            abort_unless($bestellung->runde->istOffen(), 410);
            $bestellung->update(['bestaetigt_am' => now()]);
            Sammelbestellung::zahlungAnfordern($bestellung);
            try {
                SammelbestellungAblauf::mitgliedschaftPruefen($bestellung);
            } catch (Throwable $e) {
                report($e); // Die Prüfung lässt sich im Backend wiederholen.
            }
            Mail::to($bestellung->email, $bestellung->name)->send(new ShopBestellungMail($bestellung->fresh(), 'bestaetigt', $token));
            if ($bestellung->positionen()->where('frei', true)->exists()) {
                $empfaenger = User::query()->where('status', 'active')
                    ->whereHas('roleAssignments', fn ($q) => $q->where('role', 'sammelbestellung'))->get();
                foreach ($empfaenger as $user) {
                    Mail::to($user->email, $user->name)->send(new ShopBestellungMail($bestellung->fresh(), 'pruefen'));
                }
            }
        }

        return redirect()->route('shop.show', $token)->with('shop_status', 'bestaetigt');
    }

    /** Storno durch die Person: nur solange die Runde offen ist und nichts bezahlt wurde. */
    public function stornieren(string $token): RedirectResponse
    {
        $bestellung = $this->bestellung($token);
        if (! $bestellung->storniert_am) {
            abort_unless($bestellung->runde->istOffen() && $bestellung->zahlungen()->where('bezahlt_cent', '>', 0)->doesntExist(), 409);
            Sammelbestellung::stornieren($bestellung);
        }

        return redirect()->route('shop.show', $token)->with('shop_status', 'storniert');
    }

    private function bestellung(string $token): Bestellung
    {
        abort_unless(Sammelbestellung::aktiv() && preg_match('/^[A-Za-z0-9]{40}$/', $token), 404);

        return Bestellung::query()->where('token', hash('sha256', $token))->firstOrFail();
    }
}
