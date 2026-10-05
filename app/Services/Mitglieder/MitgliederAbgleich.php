<?php

namespace App\Services\Mitglieder;

use App\Models\EventRegistration;
use Illuminate\Support\Str;

/**
 * Gleicht eine Anmeldung mit der Mitgliederverwaltung ab. Treffer zählt,
 * wenn Nachname und erster Vorname übereinstimmen (ohne Groß-/Klein-
 * schreibung, Umlaute, Bindestriche) und – sofern beide bekannt – das
 * Geburtsjahr. Mehrere Treffer löst die E-Mail-Adresse auf, sonst
 * „unklar“ zur Entscheidung im Backend. Gespeichert wird nur das Ergebnis.
 */
class MitgliederAbgleich
{
    public const STATUSES = [
        'mitglied' => 'Mitglied',
        'ehemalig' => 'Ehemals Mitglied',
        'nicht_gefunden' => 'Nicht gefunden',
        'unklar' => 'Unklar (mehrere Treffer)',
        'kein_mitglied' => 'Kein Mitglied',
    ];

    public function __construct(private Mitgliederverzeichnis $verzeichnis) {}

    public static function eingerichtet(): bool
    {
        return in_array(config('mitglieder.driver'), ['nami', 'hitobito'], true);
    }

    /**
     * @throws MitgliederverzeichnisException
     */
    public function pruefe(EventRegistration $registration): string
    {
        $vorname = self::ersterVorname($registration->first_name);
        $treffer = collect($this->verzeichnis->suche($vorname, trim($registration->last_name)))
            ->filter(fn (Mitglied $m) => self::norm($m->nachname) === self::norm($registration->last_name)
                && self::norm(self::ersterVorname($m->vorname)) === self::norm($vorname))
            ->filter(fn (Mitglied $m) => ! $registration->birth_year || ! $m->geburtsdatum || $m->geburtsdatum->year === $registration->birth_year)
            ->values();

        // Aktive Mitgliedschaften vor beendeten; bei mehreren entscheidet die E-Mail.
        $aktiv = $treffer->where('aktiv', true)->values();
        $kandidaten = $aktiv->isNotEmpty() ? $aktiv : $treffer;
        if ($kandidaten->count() > 1) {
            $perMail = $kandidaten->filter(fn (Mitglied $m) => $m->email && mb_strtolower($m->email) === mb_strtolower($registration->email))->values();
            $kandidaten = $perMail->count() === 1 ? $perMail : $kandidaten;
        }

        $mitglied = $kandidaten->count() === 1 ? $kandidaten->first() : null;
        $status = match (true) {
            $kandidaten->isEmpty() => 'nicht_gefunden',
            $mitglied === null => 'unklar',
            $mitglied->aktiv => 'mitglied',
            default => 'ehemalig',
        };

        $registration->forceFill([
            'member_status' => $status,
            'member_number' => $mitglied?->nummer,
            'member_group' => $mitglied ? Str::limit((string) $mitglied->gruppierung, 150, '') : null,
            'member_manual' => false,
            'member_checked_at' => now(),
        ])->save();

        return $status;
    }

    /**
     * Entscheidung im Backend (z. B. bei „unklar“); wird von späteren
     * automatischen Läufen nicht überschrieben.
     */
    public static function manuell(EventRegistration $registration, string $status, ?string $nummer, ?string $gruppierung): void
    {
        $registration->forceFill([
            'member_status' => $status,
            'member_number' => $status === 'mitglied' ? $nummer : null,
            'member_group' => $status === 'mitglied' ? $gruppierung : null,
            'member_manual' => true,
            'member_checked_at' => now(),
        ])->save();
    }

    public static function norm(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return (string) preg_replace('/[^a-z]+/', '', Str::ascii($name));
    }

    private static function ersterVorname(string $vorname): string
    {
        return (string) Str::of($vorname)->trim()->replace('-', ' ')->explode(' ')->first();
    }
}
