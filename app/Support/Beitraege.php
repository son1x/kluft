<?php

namespace App\Support;

use App\Models\Gruppierung;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Mitgliedsbeiträge eines Stamms: Halbjahre (wie die Rechnung des
 * Bundesverbands, 1 = Januar–Juni, 2 = Juli–Dezember), Beitragssätze je
 * Beitragsart und wer die Kasse sieht.
 */
class Beitraege
{
    private const SAETZE = 'beitraege.saetze';

    private const ABRUF = 'beitraege.abruf';

    /** Funktion an und Person mit Rolle „Kasse“ (oder Admin). */
    public static function zugriff(?User $user): bool
    {
        return Features::enabled(Features::MITGLIEDSBEITRAEGE) && (bool) $user?->isKasse();
    }

    public static function aktuell(?Carbon $datum = null): string
    {
        $datum ??= now();

        return $datum->year.'-'.($datum->month <= 6 ? 1 : 2);
    }

    public static function gueltig(string $halbjahr): bool
    {
        return (bool) preg_match('/^20\d{2}-[12]$/', $halbjahr);
    }

    public static function label(string $halbjahr): string
    {
        [$jahr, $teil] = explode('-', $halbjahr) + [1 => '1'];

        return $teil.'. Halbjahr '.$jahr;
    }

    /**
     * Erster und letzter Tag des Halbjahres.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function zeitraum(string $halbjahr): array
    {
        [$jahr, $teil] = array_map('intval', explode('-', $halbjahr));
        $beginn = Carbon::create($jahr, $teil === 1 ? 1 : 7, 1)->startOfDay();

        return [$beginn, $beginn->copy()->addMonths(6)->subDay()->endOfDay()];
    }

    /**
     * Auswahl: dieses, das vorige und das nächste Halbjahr plus alle mit Beiträgen.
     *
     * @param  list<string>  $vorhanden
     * @return array<string, string>
     */
    public static function auswahl(array $vorhanden = []): array
    {
        $jetzt = now();
        $liste = [self::aktuell($jetzt->copy()->subMonths(6)), self::aktuell($jetzt), self::aktuell($jetzt->copy()->addMonths(6)), ...$vorhanden];
        $liste = array_values(array_unique(array_filter($liste, self::gueltig(...))));
        rsort($liste);

        return array_combine($liste, array_map(self::label(...), $liste));
    }

    /**
     * Beitrag je Halbjahr in Cent, nach Beitragsart (Bezeichnung aus NaMi).
     *
     * @return array<string, int>
     */
    public static function saetze(): array
    {
        $json = json_decode((string) SiteSetting::query()->whereKey(self::SAETZE)->value('value'), true);

        return is_array($json) ? array_map('intval', array_filter($json, 'is_numeric')) : [];
    }

    /**
     * @param  array<string, int>  $saetze
     */
    public static function saetzeSetzen(array $saetze, ?int $userId = null): void
    {
        $saetze = array_filter(array_map(fn ($c) => max(0, (int) $c), $saetze), fn (int $c) => $c > 0);
        SiteSetting::query()->updateOrCreate(['key' => self::SAETZE], ['value' => (string) json_encode($saetze, JSON_UNESCAPED_UNICODE), 'updated_by' => $userId]);
    }

    /** Stand des letzten NaMi-Abrufs: laeuft, fertig oder fehler. */
    public static function abrufStand(string $status, string $text): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::ABRUF], ['value' => (string) json_encode(['status' => $status, 'text' => mb_substr($text, 0, 300), 'am' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * @return array{status: string, text: string, am: ?Carbon}|null
     */
    public static function letzterAbruf(): ?array
    {
        $json = json_decode((string) SiteSetting::query()->whereKey(self::ABRUF)->value('value'), true);

        return is_array($json) ? ['status' => (string) ($json['status'] ?? ''), 'text' => (string) ($json['text'] ?? ''), 'am' => isset($json['am']) ? Carbon::parse($json['am']) : null] : null;
    }

    /** DPSG-Nummer des Stamms (Einrichtung oder Gruppierung „Stamm“). */
    public static function stammNummer(): ?string
    {
        $nummer = config('dpsg.einrichtung.nummer') ?: Gruppierung::query()->where('typ', 'stamm')->value('nummer');

        return filled($nummer) ? (string) $nummer : null;
    }

    public static function euro(?int $cent): string
    {
        return $cent === null ? '–' : number_format($cent / 100, 2, ',', '.').' €';
    }
}
