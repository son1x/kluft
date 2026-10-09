<?php

namespace App\Services\Update;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Holt die signierte Release-Beschreibung (manifest.json + .sig) und prüft
 * die Ed25519-Signatur mit dem öffentlichen Schlüssel aus der Konfiguration.
 * Ohne gültige Signatur gibt es kein Release – auch keine Anzeige.
 */
class UpdateQuelle
{
    /**
     * @throws UpdateFehler
     */
    public function neuestes(): Release
    {
        $url = (string) config('kluft.updates.manifest_url');
        if ($url === '') {
            throw new UpdateFehler('Keine Update-Adresse eingerichtet (KLUFT_UPDATE_URL).');
        }
        $schluessel = self::schluessel();

        try {
            $manifest = Http::timeout(15)->get($url);
            $signatur = Http::timeout(15)->get($url.'.sig');
        } catch (ConnectionException $e) {
            throw new UpdateFehler('Update-Server nicht erreichbar: '.$e->getMessage());
        }
        if (! $manifest->successful() || ! $signatur->successful()) {
            throw new UpdateFehler('Update-Server antwortet nicht wie erwartet (HTTP '.$manifest->status().'/'.$signatur->status().').');
        }

        $sig = base64_decode(trim($signatur->body()), true);
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($sig, $manifest->body(), $schluessel)) {
            throw new UpdateFehler('Die Signatur des Releases ist ungültig. Es wird nichts installiert.');
        }

        $daten = json_decode($manifest->body(), true);
        if (! is_array($daten)) {
            throw new UpdateFehler('Die Release-Beschreibung ist kein gültiges JSON.');
        }

        return Release::ausManifest($daten, $url);
    }

    /**
     * @throws UpdateFehler
     */
    public static function schluessel(): string
    {
        $schluessel = base64_decode(trim((string) config('kluft.updates.public_key')), true);
        if ($schluessel === false || strlen($schluessel) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new UpdateFehler('Kein gültiger öffentlicher Schlüssel für Updates eingerichtet (KLUFT_UPDATE_PUBLIC_KEY).');
        }

        return $schluessel;
    }
}
