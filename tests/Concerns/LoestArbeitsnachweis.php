<?php

namespace Tests\Concerns;

use App\Support\Arbeitsnachweis;

/**
 * Löst den Arbeitsnachweis der Nachrichtenformulare wie das Widget im
 * Browser (ALTCHA v3, PBKDF2/SHA-256), mit sehr leichter Aufgabe, damit die
 * Tests schnell bleiben. Geprüft wird trotzdem vollständig.
 */
trait LoestArbeitsnachweis
{
    protected function arbeitsnachweis(): string
    {
        config(['kluft.arbeitsnachweis.kosten' => 1, 'kluft.arbeitsnachweis.praefix' => '0']);
        $aufgabe = Arbeitsnachweis::aufgabe();
        $p = $aufgabe['parameters'];

        for ($zaehler = 0; ; $zaehler++) {
            $schluessel = bin2hex(hash_pbkdf2('sha256', hex2bin($p['nonce']).pack('N', $zaehler), hex2bin($p['salt']), $p['cost'], $p['keyLength'], true));
            if (str_starts_with($schluessel, $p['keyPrefix'])) {
                return base64_encode(json_encode(['challenge' => $aufgabe, 'solution' => ['counter' => $zaehler, 'derivedKey' => $schluessel, 'time' => 1]]));
            }
        }
    }
}
