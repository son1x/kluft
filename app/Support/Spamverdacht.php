<?php

namespace App\Support;

/**
 * Spamverdacht bei Nachrichten über die Website (docs/nachrichten.md,
 * „Spamverdacht“). Verdächtiges wird nicht verworfen, sondern im
 * Nachrichten-Eingang zurückgehalten (ohne Eingang: Mail mit
 * „[Spamverdacht]“ im Betreff). So geht keine echte Nachricht verloren.
 */
class Spamverdacht
{
    /** Spamverdacht wird nach so vielen Tagen gelöscht (kontakt:prune). */
    public const TAGE = 30;

    private const LINK = '#(https?://|www\.)\S#i';

    /**
     * Grund des Verdachts oder null: Link in Nachricht, Betreff oder Name;
     * fehlender oder ungültiger Arbeitsnachweis (Feld „altcha“, z. B. ohne
     * JavaScript oder von einem Spam-Programm).
     *
     * @param  array<string, mixed>  $data  geprüfte Formulardaten (name, betreff, nachricht)
     */
    public static function grund(array $data, ?string $arbeitsnachweis): ?string
    {
        $gruende = [];
        foreach (['nachricht', 'betreff', 'name'] as $feld) {
            if (preg_match(self::LINK, (string) ($data[$feld] ?? ''))) {
                $gruende[] = 'Link in der Nachricht';
                break;
            }
        }
        if (! Arbeitsnachweis::gueltig($arbeitsnachweis)) {
            $gruende[] = 'ohne Arbeitsnachweis';
        }

        return $gruende ? implode(', ', $gruende) : null;
    }
}
