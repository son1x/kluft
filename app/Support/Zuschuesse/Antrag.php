<?php

namespace App\Support\Zuschuesse;

use App\Models\Event;
use App\Models\SiteSetting;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\Zahlungen;
use App\Support\Zuschuesse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Gemeinsame Maske „Zuschussantrag“ (docs/pflichtenheft-zuschuesse.md,
 * Phase 4): Antragsteller je Veranstaltung mit Standard für den Stamm,
 * Fristen und Stand je Zuschussgeber (eingereicht, bewilligt, ausgezahlt)
 * und die Prüfung, was den Anträgen noch fehlt.
 */
class Antrag
{
    public const STANDARD = 'zuschuss_antragsteller';

    /** Angaben zum Antragsteller: Schlüssel => Bezeichnung. */
    public const FELDER = [
        'name' => 'Antragsteller (Verband/Gruppe)',
        'anschrift' => 'Anschrift (Straße, PLZ, Ort)',
        'rueckfragen' => 'Ansprechperson (Rückfragen)',
        'telefon' => 'Telefon',
        'email' => 'E-Mail',
        'bank' => 'Bank',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'inhaber' => 'Kontoinhaber*in',
        'vertretung' => 'Vertretungsberechtigte*r (unterschreibt)',
        'leitung_name' => 'Leiter*in der Maßnahme',
        'leitung_anschrift' => 'Anschrift der Leitung',
    ];

    /** Felder, die der Stamm als Standard für neue Anträge merken kann. */
    public const STANDARD_FELDER = ['name', 'anschrift', 'rueckfragen', 'telefon', 'email', 'bank', 'iban', 'bic', 'inhaber', 'vertretung'];

    /** Stand je Zuschussgeber. */
    public const STATUS = [
        'offen' => 'noch nicht eingereicht',
        'eingereicht' => 'eingereicht',
        'bewilligt' => 'bewilligt',
        'abgelehnt' => 'abgelehnt',
        'ausgezahlt' => 'ausgezahlt',
    ];

    /** Erinnerung so viele Tage vor der Frist. */
    public const ERINNERUNGEN = [14, 3];

    /** @return array<string, string> */
    public static function standard(): array
    {
        $wert = SiteSetting::query()->whereKey(self::STANDARD)->value('value');
        if (blank($wert)) {
            return [];
        }
        try {
            $daten = json_decode(Crypt::decryptString((string) $wert), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($daten) ? array_filter(array_intersect_key($daten, array_flip(self::STANDARD_FELDER)), 'filled') : [];
    }

    /** @param array<string, mixed> $werte */
    public static function standardSpeichern(array $werte, ?int $userId): void
    {
        $daten = array_map(fn ($v) => trim((string) $v), array_filter(array_intersect_key($werte, array_flip(self::STANDARD_FELDER)), 'filled'));
        SiteSetting::query()->updateOrCreate(['key' => self::STANDARD], ['value' => Crypt::encryptString((string) json_encode($daten)), 'updated_by' => $userId]);
    }

    /**
     * Vorschlag aus den vorhandenen Daten: Kontakt der Website, Konto aus
     * „Zahlungen“, Kontakt der Veranstaltung.
     *
     * @return array<string, ?string>
     */
    public static function vorschlag(Event $event): array
    {
        $kontakt = (array) config('dpsg.contact');
        $konto = Zahlungen::konto();

        return [
            'name' => (string) ($kontakt['name'] ?? config('dpsg.site.name')),
            'anschrift' => trim(implode(', ', array_filter([$kontakt['street'] ?? null, $kontakt['city'] ?? null]))) ?: null,
            'rueckfragen' => $event->contact_name ?: ($kontakt['office'] ?? null),
            'telefon' => $kontakt['phone'] ?? null,
            'email' => $event->contact_email ?: ($kontakt['email'] ?? null),
            'bank' => null,
            'iban' => $konto ? trim(chunk_split($konto['iban'], 4, ' ')) : null,
            'bic' => $konto['bic'] ?? null,
            'inhaber' => $konto['inhaber'] ?? null,
            'vertretung' => null,
            'leitung_name' => $event->contact_name ?: null,
            'leitung_anschrift' => null,
        ];
    }

    /**
     * Was auf den Anträgen steht: Angabe der Veranstaltung, sonst der
     * Standard des Stamms, sonst der Vorschlag.
     *
     * @return array<string, ?string>
     */
    public static function werte(Event $event): array
    {
        $eigen = (array) $event->zuschuss_antrag;
        $standard = self::standard();
        $vorschlag = self::vorschlag($event);
        $werte = [];
        foreach (array_keys(self::FELDER) as $feld) {
            $werte[$feld] = filled($eigen[$feld] ?? null) ? (string) $eigen[$feld] : ($standard[$feld] ?? $vorschlag[$feld] ?? null);
        }

        return $werte;
    }

    /** Ende der Maßnahme (ohne Ende: Beginn). */
    private static function ende(Event $event): Carbon
    {
        return ($event->ends_at ?? $event->starts_at)->copy()->startOfDay();
    }

    /** Frist nach Richtlinie (Tabelle in Kapitel 0 des Pflichtenhefts). */
    public static function regelfrist(Event $event, string $geber): Carbon
    {
        $ende = self::ende($event);

        return match ($geber) {
            'land', 'kreis_sls' => $ende->copy()->addMonthsNoOverflow(3),
            // Stadt: 2 Monate nach Ende, spätestens 30.09. (Zuschusszeitraum 01.10.–30.09.).
            'stadt_sls' => $ende->copy()->addMonthsNoOverflow(2)->min(Carbon::create($ende->month >= 10 ? $ende->year + 1 : $ende->year, 9, 30)),
            'bistum_trier' => $ende->copy()->addWeeks(8),
            default => $ende->copy()->addMonthsNoOverflow(2),
        };
    }

    /** @return array<string, mixed> Gespeicherter Stand eines Gebers. */
    public static function eintrag(Event $event, string $geber): array
    {
        return (array) (((array) $event->zuschuss_stand)[$geber] ?? []);
    }

    public static function frist(Event $event, string $geber): Carbon
    {
        $frist = self::eintrag($event, $geber)['frist'] ?? null;

        return filled($frist) ? Carbon::parse((string) $frist)->startOfDay() : self::regelfrist($event, $geber);
    }

    public static function status(Event $event, string $geber): string
    {
        $e = self::eintrag($event, $geber);

        return match (true) {
            filled($e['ausgezahlt_am'] ?? null) => 'ausgezahlt',
            (bool) ($e['abgelehnt'] ?? false) => 'abgelehnt',
            filled($e['bewilligt_cent'] ?? null) || filled($e['bewilligt_am'] ?? null) => 'bewilligt',
            filled($e['eingereicht_am'] ?? null) => 'eingereicht',
            default => 'offen',
        };
    }

    /** @return list<string> Gewählte Zuschussgeber. */
    public static function geber(Event $event): array
    {
        return array_values(array_intersect(array_keys(Zuschuesse::GEBER), (array) ($event->zuschuss_geber ?: array_keys(Zuschuesse::GEBER))));
    }

    /**
     * Stand aller gewählten Geber mit Schätzung.
     *
     * @return list<array{key: string, name: string, frist: Carbon, status: string, geschaetzt: int, beantragt: ?int, bewilligt: ?int, eingereicht_am: ?string, bewilligt_am: ?string, aktenzeichen: ?string, ausgezahlt_am: ?string}>
     */
    public static function uebersicht(Event $event): array
    {
        $schaetzung = collect(Foerderrechner::rechnen($event)['geber'])->keyBy('key');
        $zeilen = [];
        foreach (self::geber($event) as $key) {
            $e = self::eintrag($event, $key);
            $zeilen[] = [
                'key' => $key,
                'name' => Zuschuesse::GEBER[$key],
                'frist' => self::frist($event, $key),
                'status' => self::status($event, $key),
                'geschaetzt' => (int) ($schaetzung[$key]['betrag'] ?? 0),
                'beantragt' => filled($e['beantragt_cent'] ?? null) ? (int) $e['beantragt_cent'] : null,
                'bewilligt' => filled($e['bewilligt_cent'] ?? null) ? (int) $e['bewilligt_cent'] : null,
                'eingereicht_am' => $e['eingereicht_am'] ?? null,
                'bewilligt_am' => $e['bewilligt_am'] ?? null,
                'aktenzeichen' => $e['aktenzeichen'] ?? null,
                'ausgezahlt_am' => $e['ausgezahlt_am'] ?? null,
            ];
        }

        return $zeilen;
    }

    /**
     * Betrag eines anderen Gebers für die Anträge: bewilligt, sonst
     * beantragt, sonst geschätzt; abgelehnt zählt 0.
     */
    public static function betrag(array $zeile): int
    {
        return match (true) {
            $zeile['status'] === 'abgelehnt' => 0,
            $zeile['bewilligt'] !== null => $zeile['bewilligt'],
            $zeile['beantragt'] !== null => $zeile['beantragt'],
            default => $zeile['geschaetzt'],
        };
    }

    /**
     * Bewilligte Zuschüsse der anderen Stellen (z. B. für Abschnitt V der Stadt).
     *
     * @return list<array{name: string, datum: ?string, aktenzeichen: ?string, betrag: int}>
     */
    public static function bewilligteDritte(Event $event, string $ausser): array
    {
        return collect(self::uebersicht($event))
            ->filter(fn (array $z) => $z['key'] !== $ausser && in_array($z['status'], ['bewilligt', 'ausgezahlt'], true) && $z['bewilligt'] !== null)
            ->map(fn (array $z) => ['name' => $z['name'], 'datum' => $z['bewilligt_am'] ? Carbon::parse($z['bewilligt_am'])->format('d.m.Y') : null,
                'aktenzeichen' => $z['aktenzeichen'], 'betrag' => $z['bewilligt']])
            ->values()->all();
    }

    /**
     * Was den Unterlagen noch fehlt, je Unterlage (für die Maske).
     *
     * @return array<string, list<string>>
     */
    public static function fehlend(Event $event): array
    {
        $as = self::werte($event);
        $b = (array) $event->zuschuss_bericht;
        $f = Zuschuesse::finanzen($event);
        $allgemein = array_keys(array_filter([
            'Ort der Veranstaltung' => blank($event->location),
            'Art der Maßnahme' => blank($event->zuschuss_art),
            self::FELDER['name'] => blank($as['name']),
            self::FELDER['anschrift'] => blank($as['anschrift']),
            self::FELDER['iban'] => blank($as['iban']),
            self::FELDER['inhaber'] => blank($as['inhaber']),
            self::FELDER['rueckfragen'] => blank($as['rueckfragen']),
            'Ausgaben im Kostenplan' => $f['ausgaben'] === 0,
            'Teilnehmende (bestätigt und anwesend)' => Zuschuesse::personen($event)->isEmpty(),
        ]));
        $personen = count(Zuschuesse::fehlend($event));
        if ($personen > 0) {
            $allgemein[] = "Angaben bei {$personen} Person(en) (Anschrift, Geburtsdatum, Geschlecht)";
        }

        $fehlt = [];
        foreach (array_keys(ZuschussPdf::unterlagen($event)) as $art) {
            $fehlt[$art] = match ($art) {
                'liste', 'schaetzung' => array_values(array_filter($allgemein, fn (string $l) => in_array($l, ['Ort der Veranstaltung', 'Teilnehmende (bestätigt und anwesend)'], true) || str_starts_with($l, 'Angaben bei'))),
                'a' => [...$allgemein, ...(blank($as['bank']) ? [self::FELDER['bank']] : [])],
                'bericht' => [...array_keys(array_filter([
                    'Programm' => blank($b['programm'] ?? null),
                    'Thema / Schwerpunkt' => $event->zuschuss_art !== 'freizeit' && blank($b['thema'] ?? null),
                    'Inhalt, Methodik, Zielsetzung' => $event->zuschuss_art !== 'freizeit' && blank($b['methodik'] ?? null),
                ]))],
                'c' => array_keys(array_filter(['Unterkunft' => blank($b['unterkunft'] ?? null)])),
                'stadt' => [...$allgemein, ...array_keys(array_filter([
                    'Beschreibung (Inhalt, Methodik)' => blank($b['methodik'] ?? null) && blank($b['thema'] ?? null),
                    'Entfernung Saarlouis – Ziel' => $event->zuschuss_art === 'freizeit' && blank($b['entfernung_km'] ?? null),
                    self::FELDER['vertretung'] => blank($as['vertretung']),
                    self::FELDER['leitung_name'] => blank($as['leitung_name']),
                    self::FELDER['leitung_anschrift'] => blank($as['leitung_anschrift']),
                ]))],
                'bistum' => [...$allgemein, ...array_keys(array_filter(['Programm' => blank($b['programm'] ?? null)]))],
                default => [],
            };
        }

        return $fehlt;
    }
}
