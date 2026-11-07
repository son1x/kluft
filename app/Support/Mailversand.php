<?php

namespace App\Support;

/**
 * Wird überhaupt verschickt? Mit Mailer „log“ schreibt der Server Mails nur
 * ins Protokoll (docs/deploy.md „Mail“), nichts verlässt den Server.
 */
class Mailversand
{
    public static function nurProtokoll(): bool
    {
        return config('mail.default') === 'log';
    }
}
