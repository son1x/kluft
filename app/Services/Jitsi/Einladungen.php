<?php

namespace App\Services\Jitsi;

use App\Mail\VideokonferenzMail;
use App\Models\User;
use App\Models\Videokonferenz;
use App\Support\Adressbuch;
use Illuminate\Support\Facades\Mail;

/**
 * Versand der Einladungen zu einer Videokonferenz: je Adresse eine eigene
 * Mail (Adressen sind füreinander unsichtbar), über die Warteschlange.
 */
class Einladungen
{
    /**
     * @param  list<string>|null  $moderation
     * @param  list<string>|null  $teilnehmende
     * @return array{moderation: int, teilnehmende: int}
     */
    public static function senden(Videokonferenz $k, ?array $moderation = null, ?array $teilnehmende = null): array
    {
        $moderation = self::bereinigen($moderation ?? $k->moderation_emails ?? []);
        // Wer moderiert, braucht keine zweite Mail als Teilnehmer*in.
        $teilnehmende = array_values(array_diff(self::bereinigen($teilnehmende ?? $k->teilnehmende_emails ?? []), $moderation));

        foreach ($moderation as $email) {
            Mail::to($email)->queue(new VideokonferenzMail($k, true));
        }
        foreach ($teilnehmende as $email) {
            Mail::to($email)->queue(new VideokonferenzMail($k, false));
        }

        if ($moderation !== [] || $teilnehmende !== []) {
            $k->forceFill(['eingeladen_am' => now()])->save();
        }

        return ['moderation' => count($moderation), 'teilnehmende' => count($teilnehmende)];
    }

    /**
     * Adressen aus dem Formular: eingetippte plus die aus gewählten
     * Verteilern des Adressbuchs (nur sichtbare).
     *
     * @param  array<string, mixed>  $data
     * @return array{moderation: list<string>, teilnehmende: list<string>}
     */
    public static function ausFormular(array $data, ?User $user): array
    {
        return [
            'moderation' => self::bereinigen([...($data['moderation_emails'] ?? []), ...Adressbuch::adressen((array) ($data['moderation_verteiler'] ?? []), $user)]),
            'teilnehmende' => self::bereinigen([...($data['teilnehmende_emails'] ?? []), ...Adressbuch::adressen((array) ($data['teilnehmende_verteiler'] ?? []), $user)]),
        ];
    }

    /**
     * Gültige, kleingeschriebene Adressen ohne Doppelte.
     *
     * @param  array<int, mixed>  $adressen
     * @return list<string>
     */
    public static function bereinigen(array $adressen): array
    {
        $gueltig = array_filter(array_map(fn ($a) => mb_strtolower(trim((string) $a)), $adressen), fn (string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false);

        return array_values(array_unique($gueltig));
    }
}
