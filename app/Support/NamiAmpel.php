<?php

namespace App\Support;

use App\Models\User;
use App\Services\Mitglieder\NamiVerzeichnis;
use Illuminate\Support\HtmlString;

/**
 * Ampel für NaMi 2: Ist der Server erreichbar, und klappt die Anmeldung mit
 * dem im Profil gespeicherten Zugang? Für Profil und „Mitgliedschaft prüfen“.
 */
class NamiAmpel
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const OFF = 'off';

    /**
     * @return list<array{status: string, label: string, message: string}>
     */
    public static function lampen(?User $user = null): array
    {
        if (config('mitglieder.driver') !== 'nami') {
            return [];
        }

        $lampen = [NamiVerzeichnis::erreichbarGemerkt()
            ? ['status' => self::OK, 'label' => 'NaMi-Server', 'message' => 'erreichbar']
            : ['status' => self::FAIL, 'label' => 'NaMi-Server', 'message' => 'nicht erreichbar – bitte später erneut versuchen']];

        if ($user && NamiVerzeichnis::persoenlich() && Features::enabled(Features::NAMI_PROFIL)) {
            $lampen[] = ['status' => self::status($user), 'label' => 'Dein NaMi-Zugang', 'message' => self::meldung($user)];
        }

        return $lampen;
    }

    public static function status(User $user): string
    {
        return match (true) {
            ! $user->hasNamiZugang() => self::OFF,
            filled($user->nami_error) => self::FAIL,
            $user->nami_checked_at !== null => self::OK,
            default => self::WARN,
        };
    }

    public static function meldung(User $user): string
    {
        $wann = $user->nami_checked_at?->format('d.m.Y, H:i');

        return match (self::status($user)) {
            self::OFF => 'nicht gespeichert – Eingabe bei jeder Prüfung (speichern unter „Mein Konto → Mein NaMi-Zugang“)',
            self::FAIL => 'Anmeldung am '.$wann.' fehlgeschlagen: '.$user->nami_error,
            self::OK => 'Anmeldung klappt (zuletzt '.$wann.')',
            default => 'gespeichert, noch nicht geprüft',
        };
    }

    public static function html(?User $user = null): HtmlString
    {
        return new HtmlString(view('filament.nami-ampel', ['lampen' => self::lampen($user)])->render());
    }
}
