<?php

namespace App\Mail;

use App\Models\SiteSetting;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Testumgebung (F-MAIL-06): Mails gehen an die Testadresse statt an echte
 * Personen – außer an die wenigen freigeschalteten Tester*innen
 * (MAIL_TEST_ALLOWLIST und Ergänzungen unter Verwaltung → E-Mail-Versand,
 * zusammen höchstens zehn Einträge). Ein Eintrag ist eine
 * Adresse oder eine ganze Domain („@dpsg-trier.de“ – Vorsicht: dann
 * bekommen auch Ehrenamtliche mit Verbandsadresse echte Mails). Ist keine Testadresse
 * gesetzt (Produktion), bleibt alles unverändert.
 */
class TestRecipientFilter
{
    public const MAX_ALLOWED = 10;

    /** Ergänzungen aus dem Backend (Admins), ohne das GitHub-Secret zu ändern. */
    public const SETTING = 'mail.test_allowlist_backend';

    /** Kopfzeile für Mails, die trotz Testsystem an die echten Empfänger*innen gehen (vorab extern freigegebene Veranstaltungen). */
    public const EXTERN = 'X-Kluft-Extern';

    public function handle(MessageSending $event): void
    {
        $redirectTo = (string) config('mail.test_redirect_to');
        if ($redirectTo === '') {
            $event->message->getHeaders()->remove(self::EXTERN);

            return;
        }

        $message = $event->message;
        if ($message->getHeaders()->has(self::EXTERN)) {
            $message->getHeaders()->remove(self::EXTERN);

            return;
        }
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
     * Gültige Einträge aus Secret und Backend, kleingeschrieben, höchstens zehn.
     *
     * Domains werden als „@domain.de“ notiert, „*@domain.de“ geht auch.
     *
     * @return list<string>
     */
    public static function allowlist(): array
    {
        return array_slice(array_values(array_unique([...self::ausSecret(), ...self::ausBackend()])), 0, self::MAX_ALLOWED);
    }

    /** @return list<string> */
    public static function ausSecret(): array
    {
        return self::gueltig((array) config('mail.test_allowlist', []));
    }

    /** @return list<string> */
    public static function ausBackend(): array
    {
        try {
            $wert = SiteSetting::query()->whereKey(self::SETTING)->value('value');
        } catch (Throwable) {
            return []; // ohne Datenbank (z. B. vor der Installation)
        }

        return self::gueltig((array) json_decode((string) $wert, true));
    }

    /** @param  list<string>  $eintraege */
    public static function backendSetzen(array $eintraege, ?int $userId): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::SETTING], ['value' => (string) json_encode(self::gueltig($eintraege)), 'updated_by' => $userId]);
    }

    public static function normalisieren(string $eintrag): string
    {
        return (string) preg_replace('/^\*@/', '@', mb_strtolower(trim($eintrag)));
    }

    public static function istGueltig(string $eintrag): bool
    {
        return str_starts_with($eintrag, '@')
            ? (bool) preg_match('/^@([a-z0-9-]+\.)+[a-z]{2,}$/', $eintrag)
            : filter_var($eintrag, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param  array<mixed>  $liste
     * @return list<string>
     */
    private static function gueltig(array $liste): array
    {
        $liste = array_map(fn ($a) => self::normalisieren((string) $a), $liste);

        return array_values(array_unique(array_filter($liste, fn (string $a) => self::istGueltig($a))));
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
