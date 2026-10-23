<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Shop\Artikel;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Position;
use App\Models\Shop\Runde;
use App\Models\Shop\Sperre;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\Sammelbestellung\Ruesthaus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sammelbestellung beim Rüsthaus, Phase 1 (docs/pflichtenheft-sammelbestellung.md):
 * Artikel freigeben, Bestellungen mit Zahlungsaufforderung über den Baustein
 * „Zahlungen“, freie Artikel bestätigen, Sammelliste der bezahlten Positionen.
 */
class Sammelbestellung
{
    public const PRAEFIX = 'RH';

    /** Funktion an und „Zahlungen“ an (bezahlt wird vorab). */
    public static function aktiv(): bool
    {
        return Features::enabled(Features::SAMMELBESTELLUNG) && Features::enabled(Features::ZAHLUNGEN);
    }

    /** Testbetrieb: Bestellseite nur für Angemeldete mit Backend-Zugang. */
    public static function testbetrieb(): bool
    {
        return ! Features::enabled(Features::SAMMELBESTELLUNG_FUER_ALLE);
    }

    /** Die Bestellseite ist für diese Person sichtbar. */
    public static function sichtbar(?User $user): bool
    {
        return self::aktiv() && (! self::testbetrieb() || ($user !== null && $user->isActive() && $user->roleAssignments->isNotEmpty()));
    }

    /** Bestellen geht nur mit offener Runde und Bankverbindung. */
    public static function bestellbar(?User $user = null): bool
    {
        return self::sichtbar($user) && Runde::offene() !== null && Zahlungen::konto() !== null;
    }

    /** Bestellungen aus dem Testbetrieb samt ihren Zahlungen löschen. */
    public static function testsLoeschen(): int
    {
        return DB::transaction(function () {
            $ids = Bestellung::query()->where('test', true)->pluck('id');
            Zahlung::query()->where('zahlbar_type', (new Bestellung)->getMorphClass())->whereIn('zahlbar_id', $ids)->delete();

            return Bestellung::query()->whereIn('id', $ids)->delete();
        });
    }

    public static function zugriff(?User $user): bool
    {
        return self::aktiv() && $user !== null && $user->isSammelbestellung();
    }

    /**
     * Artikel per Rüsthaus-Nummer oder -Link holen und freigeben (Bild,
     * Beschreibung, Größen werden lokal gespeichert). Bestehende werden aktualisiert.
     *
     * @throws InvalidArgumentException nicht gefunden oder gesperrt
     */
    public static function freigeben(string $eingabe, bool $freigeben = true): Artikel
    {
        $daten = app(Ruesthaus::class)->produkt($eingabe);
        if ($daten === null) {
            throw new InvalidArgumentException('Beim Rüsthaus nicht gefunden. Bitte Artikelnummer oder Link prüfen.');
        }
        if (Sperre::greift($daten['nummer'], $daten['kategorie'])) {
            throw new InvalidArgumentException('Dieser Artikel steht auf der Sperrliste.');
        }

        return DB::transaction(function () use ($daten, $freigeben) {
            $artikel = Artikel::query()->firstOrNew(['nummer' => $daten['nummer']]);
            $artikel->fill([
                'name' => mb_substr($daten['name'], 0, 160),
                'beschreibung' => $daten['beschreibung'],
                'url' => $daten['url'],
                'rh_kategorie' => $daten['kategorie'],
                'preis_cent' => $daten['preis_cent'],
                'abgerufen_am' => now(),
            ]);
            if (! $artikel->exists) {
                $artikel->freigegeben = $freigeben;
                $artikel->sort = (int) Artikel::query()->max('sort') + 10;
            }
            if ($daten['bild'] && ! $artikel->bild_media_id) {
                $artikel->bild_media_id = self::bild($daten['bild'], $daten['name'])?->id;
            }
            $artikel->save();

            $nummern = [];
            foreach ($daten['varianten'] as $i => $v) {
                $nummern[] = $v['nummer'];
                $artikel->varianten()->updateOrCreate(['nummer' => $v['nummer']], [
                    'bezeichnung' => $v['bezeichnung'] ? mb_substr($v['bezeichnung'], 0, 120) : null,
                    'preis_cent' => $v['preis_cent'],
                    'sort' => ($i + 1) * 10,
                ]);
            }
            // Beim Rüsthaus weggefallene Größen nicht mehr anbieten (bestehende Positionen bleiben).
            $artikel->varianten()->whereNotIn('nummer', $nummern)->update(['aktiv' => false]);

            return $artikel->fresh('varianten');
        });
    }

    /** Produktbild verkleinert in die Mediathek (Vermerk „Rüsthaus“). */
    private static function bild(string $url, string $name): ?Media
    {
        $daten = app(Ruesthaus::class)->bild($url);
        if ($daten === null) {
            return null;
        }
        $endung = in_array($e = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) ? $e : 'jpg';
        $pfad = 'media/shop/'.Str::slug(mb_substr($name, 0, 60)).'-'.Str::lower(Str::random(6)).'.'.$endung;
        File::ensureDirectoryExists(public_path('uploads/media/shop'));
        File::put(public_path('uploads/'.$pfad), $daten);

        return Media::query()->create([
            'path' => $pfad,
            'mime' => $endung === 'png' ? 'image/png' : ($endung === 'webp' ? 'image/webp' : 'image/jpeg'),
            'size' => strlen($daten),
            'alt_text' => mb_substr($name, 0, 250),
            'credit' => 'Rüsthaus',
            'rights_confirmed' => true,
        ]);
    }

    /**
     * Freien Artikel für das Bestellformular nachschlagen (gecacht, ohne Bild).
     *
     * @return array{gesperrt: bool, nummer?: string, name?: string, preis_cent?: int, varianten?: list<array{nummer: string, bezeichnung: ?string, preis_cent: int}>}|null
     */
    public static function nachschlagen(string $eingabe): ?array
    {
        $daten = app(Ruesthaus::class)->produkt($eingabe);
        if ($daten === null) {
            return null;
        }
        $nummer = trim($eingabe);
        if (Sperre::greift($daten['nummer'], $daten['kategorie']) || (! Ruesthaus::istLink($nummer) && Sperre::greift($nummer))) {
            return ['gesperrt' => true];
        }

        return ['gesperrt' => false, 'nummer' => $daten['nummer'], 'name' => $daten['name'], 'preis_cent' => $daten['preis_cent'], 'varianten' => $daten['varianten']];
    }

    /**
     * Zahlung für alle bestätigten, noch nicht abgerechneten Positionen
     * anlegen und die Aufforderung mit GiroCode verschicken (fällig zum
     * Bestelldatum). Gibt die Zahlung zurück oder null, wenn nichts offen ist.
     */
    public static function zahlungAnfordern(Bestellung $bestellung, ?string $hinweis = null): ?Zahlung
    {
        $bestellung->load('runde', 'positionen');
        $positionen = $bestellung->positionen->filter(fn (Position $p) => $p->bestaetigt && $p->zahlung_id === null && (int) $p->preis_cent > 0);
        $summe = (int) $positionen->sum(fn (Position $p) => $p->summeCent());
        if ($summe < 1 || $bestellung->storniert_am) {
            return null;
        }

        $zahlung = Zahlungen::anlegen($bestellung, $summe, self::PRAEFIX, 'Sammelbestellung '.$bestellung->runde->name, faelligAm: $bestellung->runde->bestelldatum->toDateString());
        Position::query()->whereIn('id', $positionen->pluck('id'))->update(['zahlung_id' => $zahlung->id]);
        if (Zahlungen::konto() !== null) {
            Zahlungen::auffordern($zahlung, $bestellung->email, $bestellung->name, $hinweis ?? 'Bestellt wird beim Rüsthaus nur, was bis zum Bestelldatum bezahlt ist.');
        }

        return $zahlung;
    }

    /** Freien Artikel bestätigen (Preis, ggf. Größe/Nummer korrigiert) und nachfordern. */
    public static function bestaetigen(Position $position, int $preisCent, ?string $nummer = null, ?string $groesse = null): void
    {
        $position->update(array_filter(['preis_cent' => $preisCent, 'nummer' => $nummer, 'groesse' => $groesse], fn ($v) => $v !== null) + ['bestaetigt' => true]);
        $bestellung = $position->bestellung;
        if ($bestellung->positionen()->where('bestaetigt', false)->doesntExist()) {
            self::zahlungAnfordern($bestellung, 'Deine Bestellung ist geprüft. Bestellt wird beim Rüsthaus nur, was bis zum Bestelldatum bezahlt ist.');
        }
    }

    /** Bestellung stornieren: offene Zahlungen entfallen, bezahlte bleiben für die Erstattung sichtbar. */
    public static function stornieren(Bestellung $bestellung, ?int $userId = null): void
    {
        foreach ($bestellung->zahlungen()->whereIn('status', ['offen'])->get() as $zahlung) {
            $zahlung->stornieren($userId, 'Sammelbestellung storniert');
        }
        $bestellung->update(['storniert_am' => now()]);
    }

    /**
     * Sammelliste: bezahlte Positionen einer Runde, nach Artikelnummer und Größe summiert.
     *
     * @return Collection<int, array{nummer: ?string, bezeichnung: string, groesse: ?string, menge: int}>
     */
    public static function sammelliste(Runde $runde): Collection
    {
        return Position::query()
            ->whereHas('bestellung', fn ($q) => $q->where('runde_id', $runde->id)->whereNull('storniert_am')->whereNotNull('bestaetigt_am'))
            ->whereHas('zahlung', fn ($q) => $q->where('status', 'bezahlt'))
            ->where('bestaetigt', true)
            ->get()
            ->groupBy(fn (Position $p) => ($p->nummer ?? '').'|'.($p->groesse ?? '').'|'.$p->bezeichnung)
            ->map(fn (Collection $gruppe) => [
                'nummer' => $gruppe->first()->nummer,
                'bezeichnung' => $gruppe->first()->bezeichnung,
                'groesse' => $gruppe->first()->groesse,
                'menge' => (int) $gruppe->sum('menge'),
            ])
            ->sortBy(fn (array $z) => ($z['nummer'] ?? 'zzz').$z['bezeichnung'])
            ->values();
    }

    public static function euro(?int $cent): string
    {
        return Zahlungen::euro($cent);
    }
}
