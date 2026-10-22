<?php

namespace App\Support;

use App\Mail\ZahlungsaufforderungMail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\Zahlungen\Ueberweisung;
use App\Services\Zahlungen\Zahlungsweg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Baustein „Zahlungen“ (docs/zahlungen.md): Forderungen anlegen, Aufforderung
 * mit GiroCode verschicken, Bankverbindung der Gliederung. Nutzer (z. B. die
 * Sammelbestellung) legen Zahlungen an ihren eigenen Datensätzen an und hören
 * auf App\Events\ZahlungEingegangen.
 */
class Zahlungen
{
    private const KONTO = 'zahlungen.konto';

    /** Funktion an und Kasse (im Stamm) bzw. Verwaltung (DV, Bezirk) oder Admin. */
    public static function zugriff(?User $user): bool
    {
        if (! Features::enabled(Features::ZAHLUNGEN) || $user === null) {
            return false;
        }

        return config('dpsg.site.level') === 'Stamm' ? $user->isKasse() : ($user->isKasse() || $user->isVerwaltung());
    }

    /**
     * Neue Forderung am Auslöser. Präfix: 2–4 Großbuchstaben je Nutzer,
     * z. B. „RH“ (Rüsthaus), „VA“ (Veranstaltung), „MB“ (Mitgliedsbeitrag).
     */
    public static function anlegen(?Model $zahlbar, int $betragCent, string $praefix, string $zweck, ?string $faelligAm = null): Zahlung
    {
        if ($betragCent < 1) {
            throw new InvalidArgumentException('Der Betrag muss größer als 0 sein.');
        }
        if (! preg_match('/^[A-Z]{2,4}$/', $praefix)) {
            throw new InvalidArgumentException('Präfix: 2–4 Großbuchstaben.');
        }

        $zahlung = new Zahlung([
            'referenz' => 'NEU-'.bin2hex(random_bytes(8)),
            'zweck' => mb_substr(trim($zweck), 0, 140),
            'betrag_cent' => $betragCent,
            'status' => 'offen',
            'weg' => 'ueberweisung',
            'faellig_am' => $faelligAm,
        ]);
        if ($zahlbar !== null) {
            $zahlung->zahlbar()->associate($zahlbar);
        }
        $zahlung->save();
        // Eindeutig über die ID; höchstens 35 Zeichen, nur A–Z, 0–9 und „-“.
        $zahlung->update(['referenz' => sprintf('%s-%s-%04d', $praefix, now()->format('Y'), $zahlung->id)]);

        return $zahlung;
    }

    public static function weg(Zahlung $zahlung): Zahlungsweg
    {
        return match ($zahlung->weg) {
            default => new Ueberweisung,
        };
    }

    /** Zahlungsaufforderung per Mail (mit GiroCode), Zeitpunkt wird vermerkt. */
    public static function auffordern(Zahlung $zahlung, string $email, string $name, ?string $hinweis = null): void
    {
        $aufforderung = self::weg($zahlung)->anfordern($zahlung);
        Mail::to($email, $name)->send(new ZahlungsaufforderungMail($name, $zahlung->zweck, $aufforderung, $zahlung->faellig_am?->format('d.m.Y'), $hinweis));
        $zahlung->update(['aufgefordert_am' => now()]);
    }

    /**
     * Bankverbindung der Gliederung (Backend, nicht im Repository).
     *
     * @return array{inhaber: string, iban: string, bic: string}|null
     */
    public static function konto(): ?array
    {
        $daten = json_decode((string) SiteSetting::query()->whereKey(self::KONTO)->value('value'), true);
        if (! is_array($daten) || blank($daten['inhaber'] ?? null) || ! self::ibanGueltig((string) ($daten['iban'] ?? ''))) {
            return null;
        }

        return ['inhaber' => (string) $daten['inhaber'], 'iban' => self::ibanNormal((string) $daten['iban']), 'bic' => strtoupper(trim((string) ($daten['bic'] ?? '')))];
    }

    public static function kontoSetzen(string $inhaber, string $iban, ?string $bic, ?int $userId): void
    {
        if (! self::ibanGueltig($iban)) {
            throw new InvalidArgumentException('Die IBAN ist ungültig.');
        }
        SiteSetting::query()->updateOrCreate(['key' => self::KONTO], [
            'value' => (string) json_encode(['inhaber' => mb_substr(trim($inhaber), 0, 70), 'iban' => self::ibanNormal($iban), 'bic' => strtoupper(trim((string) $bic))], JSON_UNESCAPED_UNICODE),
            'updated_by' => $userId,
        ]);
    }

    /** IBAN-Prüfsumme (ISO 13616, mod 97). */
    public static function ibanGueltig(string $iban): bool
    {
        $iban = self::ibanNormal($iban);
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $zahl = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $zeichen) {
            $zahl .= ctype_alpha($zeichen) ? (string) (ord($zeichen) - 55) : $zeichen;
        }
        $rest = 0;
        foreach (str_split($zahl, 7) as $teil) {
            $rest = (int) (($rest.$teil) % 97);
        }

        return $rest === 1;
    }

    public static function ibanNormal(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    public static function euro(?int $cent): string
    {
        return $cent === null ? '–' : number_format($cent / 100, 2, ',', '.').' €';
    }
}
