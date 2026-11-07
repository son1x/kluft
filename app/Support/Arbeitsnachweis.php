<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Arbeitsnachweis gegen Massen-Spam (docs/nachrichten.md), selbst gehostet
 * nach dem Verfahren von ALTCHA v3 (npm „altcha“, Widget im Formular): Der
 * Browser sucht einen Zähler, bei dem PBKDF2/SHA-256 über Nonce+Zähler einen
 * Schlüssel mit vorgegebenem Anfang ergibt. Das kostet im Browser etwa eine
 * Sekunde, für Massen-Spam viel Rechenzeit; der Server prüft mit einer
 * einzigen Ableitung. Keine Daten an Dritte, kein Cookie.
 *
 * Fehlt der Nachweis oder ist er ungültig, wird die Nachricht nicht verworfen,
 * sondern als Spamverdacht zurückgehalten (ohne JavaScript geht nichts
 * verloren).
 */
class Arbeitsnachweis
{
    public const ALGORITHMUS = 'PBKDF2/SHA-256';

    /** Wie lange eine Aufgabe gilt (wie das Formular, s. SpamGuard). */
    public const GUELTIG_SEKUNDEN = SpamGuard::MAX_SECONDS;

    /**
     * Neue Aufgabe für das Widget.
     *
     * @return array{parameters: array<string, int|string>, signature: string}
     */
    public static function aufgabe(): array
    {
        $parameter = [
            'algorithm' => self::ALGORITHMUS,
            'cost' => self::kosten(),
            'expiresAt' => now()->timestamp + self::GUELTIG_SEKUNDEN,
            'keyLength' => 32,
            'keyPrefix' => self::praefix(),
            'nonce' => bin2hex(random_bytes(16)),
            'salt' => bin2hex(random_bytes(16)),
        ];

        return ['parameters' => $parameter, 'signature' => self::signatur($parameter)];
    }

    /**
     * Lösung aus dem Formularfeld „altcha“ (base64-JSON) prüfen. Jede
     * Aufgabe gilt nur einmal.
     */
    public static function gueltig(?string $payload): bool
    {
        try {
            $daten = json_decode((string) base64_decode((string) $payload, true), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        $parameter = $daten['challenge']['parameters'] ?? null;
        $signatur = $daten['challenge']['signature'] ?? null;
        $zaehler = $daten['solution']['counter'] ?? null;
        $schluessel = $daten['solution']['derivedKey'] ?? null;
        if (! is_array($parameter) || ! is_string($signatur) || ! is_int($zaehler) || $zaehler < 0 || $zaehler > 0xFFFFFFFF
            || ! is_string($schluessel) || ! preg_match('/^(?:[0-9a-f]{2})+$/', $schluessel)) {
            return false;
        }
        if (! hash_equals(self::signatur($parameter), $signatur)) {
            return false;
        }
        // Nach der Signatur stammen die Parameter von uns.
        if (($parameter['algorithm'] ?? null) !== self::ALGORITHMUS || (int) ($parameter['expiresAt'] ?? 0) < now()->timestamp) {
            return false;
        }

        $abgeleitet = bin2hex(hash_pbkdf2('sha256', hex2bin((string) $parameter['nonce']).pack('N', $zaehler), hex2bin((string) $parameter['salt']),
            (int) $parameter['cost'], (int) ($parameter['keyLength'] ?? 32), true));
        if (! hash_equals($abgeleitet, $schluessel) || ! str_starts_with($abgeleitet, strtolower((string) $parameter['keyPrefix']))) {
            return false;
        }

        // Nur einmal: dieselbe Lösung ein zweites Mal gilt nicht.
        try {
            return Cache::add('arbeitsnachweis:'.hash('sha256', $signatur), true, self::GUELTIG_SEKUNDEN + 60);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * HMAC über die nach Schlüsseln sortierten Parameter als JSON (wie
     * canonicalJSON in ALTCHA).
     *
     * @param  array<string, mixed>  $parameter
     */
    private static function signatur(array $parameter): string
    {
        ksort($parameter);

        return hash_hmac('sha256', (string) json_encode($parameter, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), self::geheimnis());
    }

    /** Aus dem APP_KEY abgeleitet, kein eigenes Geheimnis nötig. */
    private static function geheimnis(): string
    {
        return hash_hmac('sha256', 'kluft-arbeitsnachweis', (string) config('app.key'));
    }

    /** PBKDF2-Durchläufe je Versuch. */
    public static function kosten(): int
    {
        return max(1, (int) config('kluft.arbeitsnachweis.kosten', 5000));
    }

    /** Anfang des Schlüssels als Hex (Schwierigkeit: „00“ = im Mittel 256 Versuche). */
    public static function praefix(): string
    {
        return strtolower((string) config('kluft.arbeitsnachweis.praefix', '00'));
    }
}
