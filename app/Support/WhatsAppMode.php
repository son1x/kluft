<?php

namespace App\Support;

/**
 * Schalter für den WhatsApp-Newsletter (config whatsapp.mode). Beide
 * Varianten sind im Code vorbereitet; sichtbar ist nur die eingeschaltete.
 */
class WhatsAppMode
{
    public static function current(): string
    {
        return (string) config('whatsapp.mode', 'off');
    }

    /** Kostenloser WhatsApp-Kanal. */
    public static function channel(): bool
    {
        return self::current() === 'channel';
    }

    /** WhatsApp Business (Cloud API). */
    public static function api(): bool
    {
        return self::current() === 'api';
    }

    public static function enabled(): bool
    {
        return self::current() !== 'off';
    }
}
