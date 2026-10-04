<?php

namespace App\Support;

/**
 * Rufnummern ins E.164-Format bringen (+4915112345678), ohne Zusatzpaket.
 * Nationale Nummern ohne Ländervorwahl gelten als deutsch.
 */
class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/[\s\-\/().]/u', '', trim((string) $input)) ?? '';

        $e164 = match (true) {
            str_starts_with($digits, '+') => $digits,
            str_starts_with($digits, '00') => '+'.substr($digits, 2),
            str_starts_with($digits, '0') => '+49'.substr($digits, 1),
            // So liefert WhatsApp die Nummern (wa_id): ohne Plus.
            (bool) preg_match('/^[1-9]\d{9,14}$/', $digits) => '+'.$digits,
            default => '',
        };

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $e164)) {
            return null;
        }
        // Deutsche Nummern: nach +49 mindestens 6 Ziffern, keine 0 direkt danach.
        if (str_starts_with($e164, '+49') && (strlen($e164) < 9 || $e164[3] === '0')) {
            return null;
        }

        return $e164;
    }

    /** Für die Anzeige im Backend: +49 151 ••••• 678 */
    public static function mask(string $e164): string
    {
        $len = strlen($e164);

        return substr($e164, 0, 3).' '.substr($e164, 3, 3).' •••••'.substr($e164, max(6, $len - 3));
    }

    /** wa.me erwartet die Nummer ohne Plus. */
    public static function waMe(string $e164, ?string $text = null): string
    {
        return 'https://wa.me/'.ltrim($e164, '+').($text !== null ? '?text='.rawurlencode($text) : '');
    }
}
