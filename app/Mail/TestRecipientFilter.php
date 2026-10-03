<?php

namespace App\Mail;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Testumgebung (F-MAIL-06): Mails gehen an die Testadresse statt an echte
 * Personen – außer an die wenigen freigeschalteten Tester*innen
 * (MAIL_TEST_ALLOWLIST, höchstens zehn Einträge). Ein Eintrag ist eine
 * Adresse oder eine ganze Domain („@dpsg-trier.de“ – Vorsicht: dann
 * bekommen auch Ehrenamtliche mit Verbandsadresse echte Mails). Ist keine Testadresse
 * gesetzt (Produktion), bleibt alles unverändert.
 */
class TestRecipientFilter
{
    public const MAX_ALLOWED = 10;

    public function handle(MessageSending $event): void
    {
        $redirectTo = (string) config('mail.test_redirect_to');
        if ($redirectTo === '') {
            return;
        }

        $message = $event->message;
        $allowed = self::allowlist();
        $all = [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()];

        $keep = array_values(array_filter($all, fn (Address $a) => self::isAllowed($a->getAddress(), $allowed)));
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
     * Gültige Einträge, kleingeschrieben. Domains werden als „@domain.de“ notiert, „*@domain.de“ geht auch.
     *
     * @return list<string>
     */
    public static function allowlist(): array
    {
        $list = array_map(
            fn ($a) => (string) preg_replace('/^\*@/', '@', mb_strtolower(trim((string) $a))),
            (array) config('mail.test_allowlist', []),
        );

        $valid = array_filter($list, fn (string $a) => str_starts_with($a, '@')
            ? (bool) preg_match('/^@([a-z0-9-]+\.)+[a-z]{2,}$/', $a)
            : filter_var($a, FILTER_VALIDATE_EMAIL) !== false);

        return array_slice(array_values(array_unique($valid)), 0, self::MAX_ALLOWED);
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function isAllowed(string $address, array $allowed): bool
    {
        $address = mb_strtolower($address);
        $domain = '@'.substr(strrchr($address, '@') ?: '', 1);

        return in_array($address, $allowed, true) || in_array($domain, $allowed, true);
    }
}
