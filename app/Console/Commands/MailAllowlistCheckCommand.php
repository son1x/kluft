<?php

namespace App\Console\Commands;

use App\Mail\TestRecipientFilter;
use Illuminate\Console\Command;

/**
 * Zeigt, welche Einträge aus MAIL_TEST_ALLOWLIST auf dem Server gelten –
 * Adressen nur gekürzt (t…@dpsg-trier.de), damit sie nicht im Log stehen.
 */
class MailAllowlistCheckCommand extends Command
{
    protected $signature = 'mail:check-allowlist';

    protected $description = 'Zeigt die wirksamen Ausnahmen der Test-Mail-Umleitung (gekürzt)';

    public function handle(): int
    {
        $redirect = (string) config('mail.test_redirect_to');
        $this->line('Umleitung an Testadresse: '.($redirect !== '' ? 'aktiv' : 'aus (alle Mails gehen an die echten Empfänger*innen)'));

        $raw = array_values(array_filter(array_map('trim', (array) config('mail.test_allowlist', []))));
        $allowed = TestRecipientFilter::allowlist();

        $this->line('Einträge im Secret: '.count($raw).' · davon wirksam: '.count($allowed).' (höchstens '.TestRecipientFilter::MAX_ALLOWED.')');
        foreach ($allowed as $i => $entry) {
            $this->line(' '.($i + 1).'. '.self::mask($entry));
        }

        $ignored = count($raw) - count($allowed);
        if ($ignored > 0) {
            $this->warn("{$ignored} Eintrag/Einträge werden ignoriert (ungültig, doppelt oder über der Grenze von ".TestRecipientFilter::MAX_ALLOWED.'). Tipp: „@dpsg-trier.de“ deckt alle Adressen der Domain mit einem Eintrag ab.');
        }

        return self::SUCCESS;
    }

    private static function mask(string $entry): string
    {
        if (str_starts_with($entry, '@')) {
            return 'ganze Domain '.$entry;
        }

        [$local, $domain] = explode('@', $entry, 2);

        return mb_substr($local, 0, 1).'…@'.$domain;
    }
}
