<?php

namespace App\Mail;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Testumgebung (F-MAIL-06): Mails gehen an die Testadresse statt an echte
 * Personen – außer an die wenigen freigeschalteten Tester*innen
 * (MAIL_TEST_ALLOWLIST, höchstens fünf). Ist keine Testadresse gesetzt
 * (Produktion), bleibt alles unverändert.
 */
class TestRecipientFilter
{
    public const MAX_ALLOWED = 5;

    public function handle(MessageSending $event): void
    {
        $redirectTo = (string) config('mail.test_redirect_to');
        if ($redirectTo === '') {
            return;
        }

        $message = $event->message;
        $allowed = self::allowlist();
        $all = [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];

        $keep = array_values(array_filter($all, fn (Address $a) => in_array(mb_strtolower($a->getAddress()), $allowed, true)));
        if (count($keep) === count($all)) {
            return;
        }

        // Für die Testadresse nachvollziehbar, an wen die Mail gegangen wäre.
        $message->getHeaders()->addTextHeader('X-Test-Umgeleitet-Von', implode(', ', array_map(
            fn (Address $a) => $a->getAddress(),
            array_filter($all, fn (Address $a) => ! in_array($a, $keep, true)),
        )));

        $message->to(...$keep, ...[new Address($redirectTo)]);
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
    }

    /**
     * @return list<string>
     */
    public static function allowlist(): array
    {
        $list = array_map(fn ($a) => mb_strtolower(trim((string) $a)), (array) config('mail.test_allowlist', []));

        return array_slice(array_values(array_unique(array_filter($list, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)))), 0, self::MAX_ALLOWED);
    }
}
