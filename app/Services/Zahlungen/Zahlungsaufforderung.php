<?php

namespace App\Services\Zahlungen;

/**
 * Angaben für die Zahlungsaufforderung (Mail, Seite). Bei Überweisung
 * Empfänger, IBAN, BIC, Betrag, Verwendungszweck und GiroCode als PNG
 * (base64); bei einem Online-Weg später ein Bezahl-Link.
 */
final class Zahlungsaufforderung
{
    public function __construct(
        public readonly string $weg,
        public readonly int $betragCent,
        public readonly string $verwendungszweck,
        public readonly ?string $empfaenger = null,
        public readonly ?string $iban = null,
        public readonly ?string $bic = null,
        public readonly ?string $qrPngBase64 = null,
        public readonly ?string $link = null,
    ) {}
}
