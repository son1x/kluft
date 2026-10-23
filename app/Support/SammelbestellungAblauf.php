<?php

namespace App\Support;

use App\Mail\ShopBestellungMail;
use App\Mail\ShopRundeMail;
use App\Models\BeitragsMitglied;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Position;
use App\Models\Shop\Runde;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\Mitglieder\Mitglied;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Sammelbestellung Phase 2: Ablauf einer Runde (Erinnerung, Schließen mit
 * Storno, bestellt, geliefert, erledigt), Rüsthaus-Rechnung, Ausgabe,
 * Mitgliedsprüfung und Löschfristen (docs/pflichtenheft-sammelbestellung.md, Kapitel 4).
 */
class SammelbestellungAblauf
{
    /** Löschfrist nach Abschluss einer Runde (SB-71). */
    public static function loeschfristMonate(): int
    {
        return max(1, (int) config('dpsg.sammelbestellung_loeschfrist_monate', 6));
    }

    /** Bestätigte, nicht stornierte Bestellungen einer Runde. @return Collection<int, Bestellung> */
    private static function bestellungen(Runde $runde): Collection
    {
        return $runde->bestellungen()->whereNotNull('bestaetigt_am')->whereNull('storniert_am')->with('positionen.zahlung', 'runde')->get();
    }

    /**
     * Erinnerung einen Tag vor dem Bestelldatum an alle mit offenem Betrag
     * (SB-56), je Bestellung einmal. Liefert die Zahl der Erinnerungen.
     */
    public static function erinnern(Runde $runde): int
    {
        if (! $runde->istOffen() || Zahlungen::konto() === null) {
            return 0;
        }
        $n = 0;
        foreach (self::bestellungen($runde)->whereNull('erinnert_am') as $bestellung) {
            $offen = $bestellung->zahlungen()->whereIn('status', ['offen', 'teilweise'])->get();
            foreach ($offen as $zahlung) {
                Zahlungen::auffordern($zahlung, $bestellung->email, $bestellung->name,
                    'Erinnerung: Am '.$runde->bestelldatum->format('d.m.Y').' bestellen wir beim Rüsthaus. Was bis dahin nicht bezahlt ist, wird storniert.');
            }
            if ($offen->isNotEmpty()) {
                $bestellung->update(['erinnert_am' => now()]);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Runde schließen (Kapitel 4, Schritt 6): Unbezahltes wird storniert, außer
     * bei gehaltenen Bestellungen. Ist ein Teil bezahlt, entfallen nur die
     * unbezahlten Positionen. Liefert die Zahl der (teilweise) stornierten Bestellungen.
     */
    public static function schliessen(Runde $runde, ?int $userId = null): int
    {
        $runde->update(['status' => 'geschlossen', 'geschlossen_am' => now()]);
        $n = 0;
        foreach (self::bestellungen($runde)->where('gehalten', false) as $bestellung) {
            $unbezahlt = $bestellung->positionen->filter(fn (Position $p) => ! $p->bezahlt());
            if ($unbezahlt->isEmpty()) {
                continue;
            }
            $n++;
            if ($unbezahlt->count() === $bestellung->positionen->count()) {
                Sammelbestellung::stornieren($bestellung, $userId);
                Mail::to($bestellung->email, $bestellung->name)->send(new ShopBestellungMail($bestellung->fresh(), 'storno', null,
                    'Sie war bis zum Bestelldatum nicht bezahlt.'));

                continue;
            }
            DB::transaction(function () use ($unbezahlt, $userId) {
                Zahlung::query()->whereIn('id', $unbezahlt->pluck('zahlung_id')->filter()->unique())->where('status', 'offen')->get()
                    ->each(fn (Zahlung $z) => $z->stornieren($userId, 'Sammelbestellung: unbezahlt beim Schließen'));
                Position::query()->whereIn('id', $unbezahlt->pluck('id'))->delete();
            });
            $hinweis = 'Nicht bezahlt und daher nicht bestellt: '.$unbezahlt->map(fn (Position $p) => $p->menge.' × '.$p->bezeichnung.($p->groesse ? ' ('.$p->groesse.')' : '').' für '.$p->mitglied)->implode(', ').'. Der Rest ist bestellt.';
            Mail::to($bestellung->email, $bestellung->name)->send(new ShopBestellungMail($bestellung->fresh(), 'storno', null, $hinweis));
        }

        return $n;
    }

    public static function bestellt(Runde $runde): void
    {
        $runde->update(['status' => 'bestellt', 'bestellt_am' => now()]);
    }

    /** Ware ist da: Mail an alle mit bezahlten Positionen (Kapitel 4, Schritt 8). */
    public static function geliefert(Runde $runde, ?string $abholung = null): int
    {
        $runde->update(['status' => 'geliefert', 'geliefert_am' => now()]);
        $n = 0;
        foreach (self::bestellungen($runde) as $bestellung) {
            if ($bestellung->positionen->contains(fn (Position $p) => $p->bezahlt())) {
                Mail::to($bestellung->email, $bestellung->name)->send(new ShopBestellungMail($bestellung, 'abholbereit', null, $abholung));
                $n++;
            }
        }

        return $n;
    }

    public static function erledigt(Runde $runde): void
    {
        $runde->update(['status' => 'erledigt', 'erledigt_am' => now()]);
    }

    /**
     * Abrechnung der Runde (SB-45): eingegangene Zahlungen abzüglich
     * Erstattungen, Rüsthaus-Rechnung, Differenz (Provision abzüglich Versand).
     *
     * @return array{eingang: int, rechnung: ?int, versand: ?int, differenz: ?int}
     */
    public static function abrechnung(Runde $runde): array
    {
        $ids = $runde->bestellungen()->pluck('id');
        $zahlungen = Zahlung::query()->where('zahlbar_type', (new Bestellung)->getMorphClass())->whereIn('zahlbar_id', $ids)->get();
        $eingang = (int) $zahlungen->sum(fn (Zahlung $z) => (int) $z->bezahlt_cent - (int) $z->erstattet_cent);
        $rechnung = $runde->rechnung_cent;

        return [
            'eingang' => $eingang,
            'rechnung' => $rechnung,
            'versand' => $runde->versand_cent,
            'differenz' => $rechnung === null ? null : $eingang - $rechnung - (int) $runde->versand_cent,
        ];
    }

    /** Ausgabe abhaken bzw. zurücknehmen (SB-43). */
    public static function ausgeben(Position $position, ?User $user, ?string $vermerk = null, bool $ausgegeben = true): void
    {
        $position->update($ausgegeben
            ? ['ausgegeben_am' => now(), 'ausgegeben_von' => $user?->id, 'ausgabe_vermerk' => $vermerk]
            : ['ausgegeben_am' => null, 'ausgegeben_von' => null, 'ausgabe_vermerk' => null]);
    }

    public static function darfAusgeben(?User $user): bool
    {
        return Sammelbestellung::aktiv() && $user !== null && ($user->isSammelbestellung() || $user->isVerwaltung());
    }

    /**
     * Ausgabeliste: bezahlte Positionen einer Runde je Stufe, sortiert nach
     * Mitglied; Teile eines Sets stehen zusammen (SB-42).
     *
     * @return Collection<string, Collection<int, Position>>
     */
    public static function ausgabeliste(Runde $runde, ?Bestellung $nur = null): Collection
    {
        return Position::query()
            ->whereHas('bestellung', fn ($q) => $q->where('runde_id', $runde->id)->whereNull('storniert_am')->whereNotNull('bestaetigt_am')
                ->when($nur, fn ($q) => $q->whereKey($nur->id)))
            ->whereHas('zahlung', fn ($q) => $q->where('status', 'bezahlt'))
            ->with('bestellung', 'set')
            ->get()
            ->sortBy(fn (Position $p) => mb_strtolower($p->mitglied).'|'.($p->set_gruppe ?? 'zz').'|'.$p->id)
            ->groupBy(fn (Position $p) => $p->stufe ? (config('dpsg.stages.'.$p->stufe.'.label') ?? $p->stufe) : 'ohne Stufe');
    }

    /**
     * Mitgliedschaft prüfen (SB-37): je Mitglied der Bestellung Vor- und
     * Nachname gegen die Mitgliederliste der Kasse (NaMi-Abruf), sonst gegen
     * den Mitgliederabgleich. Gespeichert wird nur das Ergebnis.
     *
     * @return 'ja'|'nein'|'unklar'|null null = keine Liste zum Prüfen
     */
    public static function mitgliedschaftPruefen(Bestellung $bestellung): ?string
    {
        if ($bestellung->mitgliedschaft === 'bestaetigt') {
            return 'bestaetigt';
        }
        $namen = $bestellung->positionen()->pluck('mitglied')->map(fn ($n) => trim((string) $n))->filter()->unique()->values();
        $lokal = BeitragsMitglied::query()->where('aktiv', true)->exists();
        if ($namen->isEmpty() || (! $lokal && ! MitgliederAbgleich::eingerichtet())) {
            return null;
        }

        $ergebnisse = $namen->map(function (string $name) use ($lokal) {
            $teile = preg_split('/\s+/u', $name) ?: [];
            if (count($teile) < 2) {
                return 'unklar';
            }
            $vorname = MitgliederAbgleich::norm($teile[0]);
            $nachname = MitgliederAbgleich::norm((string) end($teile));
            if ($lokal) {
                $treffer = BeitragsMitglied::query()->where('aktiv', true)->get(['vorname', 'nachname'])
                    ->filter(fn (BeitragsMitglied $m) => MitgliederAbgleich::norm((string) $m->nachname) === $nachname
                        && MitgliederAbgleich::norm((string) (preg_split('/[\s-]+/u', trim((string) $m->vorname))[0] ?? '')) === $vorname);

                return $treffer->isNotEmpty() ? 'ja' : 'nein';
            }
            try {
                $gefunden = collect(app(Mitgliederverzeichnis::class)->suche($teile[0], (string) end($teile)))
                    ->filter(fn (Mitglied $m) => $m->aktiv && MitgliederAbgleich::norm($m->nachname) === $nachname);

                return $gefunden->isNotEmpty() ? 'ja' : 'nein';
            } catch (MitgliederverzeichnisException) {
                return 'unklar';
            }
        });

        $status = match (true) {
            $ergebnisse->every(fn ($e) => $e === 'ja') => 'ja',
            $ergebnisse->contains('nein') => 'nein',
            default => 'unklar',
        };
        $bestellung->update(['mitgliedschaft' => $status, 'mitgliedschaft_am' => now()]);

        return $status;
    }

    /**
     * Löschfristen (SB-32, SB-71): unbestätigte Bestellungen nach 48 Stunden
     * löschen; Runden nach Abschluss plus Frist anonymisieren. Zahlungen und
     * Summen bleiben für die Kasse, ohne Namen.
     *
     * @return array{unbestaetigt: int, anonymisiert: int}
     */
    public static function aufraeumen(): array
    {
        $unbestaetigt = Bestellung::query()->whereNull('bestaetigt_am')->where('created_at', '<', now()->subHours(48))->get();
        $unbestaetigt->each(function (Bestellung $b) {
            Zahlung::query()->where('zahlbar_type', $b->getMorphClass())->where('zahlbar_id', $b->id)->where('status', 'offen')->delete();
            $b->delete();
        });

        $runden = Runde::query()->where('status', 'erledigt')->where('erledigt_am', '<', now()->subMonths(self::loeschfristMonate()))->pluck('id');
        $anonym = 0;
        Bestellung::query()->whereIn('runde_id', $runden)->whereNull('anonymisiert_am')->get()
            ->each(function (Bestellung $b) use (&$anonym) {
                self::anonymisieren($b);
                $anonym++;
            });

        return ['unbestaetigt' => $unbestaetigt->count(), 'anonymisiert' => $anonym];
    }

    /** Personendaten einer Bestellung entfernen; Positionen, Summen und Zahlungen bleiben. */
    public static function anonymisieren(Bestellung $bestellung): void
    {
        DB::transaction(function () use ($bestellung) {
            $bestellung->positionen()->update(['mitglied' => 'gelöscht', 'ausgabe_vermerk' => null]);
            $bestellung->update([
                'name' => 'gelöscht', 'email' => 'geloescht@invalid', 'telefon' => null, 'bemerkung' => null, 'abholung' => null,
                'token' => hash('sha256', 'anonym-'.$bestellung->id.'-'.random_bytes(8)), 'anonymisiert_am' => now(),
            ]);
        });
    }

    /** Am Bestelldatum: Hinweis an die Sammelbestellung, falls die Runde noch offen ist (SB-22). */
    public static function faelligMelden(Runde $runde): bool
    {
        if (! $runde->istOffen() || $runde->erinnert_am || $runde->bestelldatum->isFuture()) {
            return false;
        }
        $empfaenger = User::query()->where('status', 'active')
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', 'sammelbestellung'))->get();
        foreach ($empfaenger as $user) {
            Mail::to($user->email, $user->name)->send(new ShopRundeMail($runde));
        }
        $runde->update(['erinnert_am' => now()]);

        return $empfaenger->isNotEmpty();
    }
}
