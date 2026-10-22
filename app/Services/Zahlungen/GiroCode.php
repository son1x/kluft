<?php

namespace App\Services\Zahlungen;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use InvalidArgumentException;

/**
 * GiroCode (EPC-QR, European Payments Council EPC069-12, Version 002): Die
 * Banking-App liest Empfänger, IBAN, Betrag und Verwendungszweck aus dem
 * QR-Code. BIC ist innerhalb des EWR optional.
 */
final class GiroCode
{
    public static function nutzdaten(string $empfaenger, string $iban, ?string $bic, int $betragCent, string $verwendungszweck): string
    {
        if ($betragCent < 1 || $betragCent > 99999999999) {
            throw new InvalidArgumentException('Betrag außerhalb des EPC-Bereichs.');
        }
        $zeilen = [
            'BCD',
            '002',
            '1',                                          // UTF-8
            'SCT',
            strtoupper(trim((string) $bic)),
            mb_substr(self::einzeilig($empfaenger), 0, 70),
            strtoupper(str_replace(' ', '', $iban)),
            'EUR'.number_format($betragCent / 100, 2, '.', ''),
            '',                                           // Purpose-Code
            '',                                           // strukturierte Referenz (nicht zusammen mit Text)
            mb_substr(self::einzeilig($verwendungszweck), 0, 140),
        ];

        return implode("\n", $zeilen);
    }

    /** QR-Code als PNG (base64, ohne data:-Präfix). */
    public static function png(string $nutzdaten): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'quietzoneSize' => 4,
        ]);

        return base64_encode((new QRCode($options))->render($nutzdaten));
    }

    private static function einzeilig(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
