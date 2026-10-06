<?php

namespace App\Services\Mitglieder;

use App\Models\EventRegistration;
use App\Models\Gruppierung;
use Illuminate\Support\Str;

/**
 * Gleicht eine Anmeldung mit der Mitgliederverwaltung ab. Treffer zählt,
 * wenn Nachname und erster Vorname übereinstimmen (ohne Groß-/Klein-
 * schreibung, Umlaute, Bindestriche) und – sofern beide bekannt – das
 * Geburtsjahr. Die Mitgliedsnummer kommt nur aus der Mitgliederverwaltung;
 * ist sie von einem früheren Abgleich bekannt, ordnet sie eindeutig zu.
 * Mehrere Treffer löst die E-Mail-Adresse auf, sonst
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
        'gast' => 'Gast (nicht abgeglichen)',
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
        // Gäste aus anderen Gliederungen/Verbänden: nicht abfragen, die eigene
        // Mitgliederverwaltung (bzw. die eigenen NaMi-Rechte) kennt sie nicht.
        if ($registration->isGast()) {
            $registration->forceFill(['member_status' => 'gast', 'member_number' => null, 'member_group' => null, 'member_manual' => false, 'member_checked_at' => now()])->save();

            return 'gast';
        }

        $vorname = self::ersterVorname($registration->first_name);
        $gefunden = $this->verzeichnis->suche($vorname, trim($registration->last_name));

        // Mitgliedsnummer aus einem früheren Abgleich: ordnet eindeutig zu, wenn
        // sie unter den Namenstreffern ist (z. B. bei Namensgleichheit).
        $nummer = ltrim((string) $registration->member_number, '0');
        $perNummer = $nummer === '' ? null : collect($gefunden)->first(fn (Mitglied $m) => ltrim($m->nummer, '0') === $nummer);
        if ($perNummer) {
            return $this->speichern($registration, $perNummer->aktiv ? 'mitglied' : 'ehemalig', $perNummer);
        }

        $treffer = collect($gefunden)
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

        return $this->speichern($registration, $status, $status === 'unklar' ? null : $mitglied);
    }

    private function speichern(EventRegistration $registration, string $status, ?Mitglied $mitglied): string
    {
        if ($mitglied) {
            $this->stammUebernehmen($registration, $mitglied);
        }
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
     * Stamm und Bezirk aus der Mitgliederverwaltung übernehmen – nur wenn die
     * Veranstaltung danach fragt und die Person selbst nichts angegeben hat.
     * Zuordnung über die DPSG-Nummer in der Gruppierungsbezeichnung von NaMi
     * (z. B. „… 210108“ = 21-01-08), sonst über den Namen.
     */
    private function stammUebernehmen(EventRegistration $registration, Mitglied $mitglied): void
    {
        $felder = $registration->event->formFields();
        if (! isset($felder['stamm']) || blank($mitglied->gruppierung)) {
            return;
        }

        $daten = (array) ($registration->data ?? []);
        $gruppierung = self::gruppierung((string) $mitglied->gruppierung);
        $stamm = $gruppierung?->typ === 'stamm' ? $gruppierung->name : ($gruppierung ? null : (string) $mitglied->gruppierung);
        $bezirk = $gruppierung?->typ === 'bezirk' ? $gruppierung->name : $gruppierung?->bezirk?->name;

        if (blank($daten['stamm'] ?? null) && filled($stamm)) {
            $daten['stamm'] = Str::limit($stamm, 150, '');
        }
        if (isset($felder['bezirk']) && blank($daten['bezirk'] ?? null) && filled($bezirk)) {
            $daten['bezirk'] = $bezirk;
        }
        $registration->data = $daten;
    }

    private static function gruppierung(string $name): ?Gruppierung
    {
        if (preg_match('/\b(\d{2})(\d{2})(\d{2})\b/', $name, $m)) {
            $treffer = Gruppierung::query()->with('bezirk')->where('nummer', "{$m[1]}-{$m[2]}-{$m[3]}")->first();
            if ($treffer) {
                return $treffer;
            }
        }
        $norm = self::norm($name);

        return Gruppierung::query()->with('bezirk')->active()->get()
            ->sortByDesc(fn (Gruppierung $g) => mb_strlen($g->name))
            ->first(fn (Gruppierung $g) => mb_strlen(self::norm($g->name)) >= 4 && str_contains($norm, self::norm($g->name)));
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
