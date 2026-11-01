<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SiteSetting;
use App\Services\Zuschuss\ZuschussPdf;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Nextcloud-Ablage per WebDAV (docs/pflichtenheft-zuschuesse.md, ZU-40):
 * je Veranstaltung mit Zuschuss ein Ordner „JJJJ-MM-TT Titel“ mit den
 * Unterlagen und einem Unterordner „Belege“. Nur in diese Richtung; Kluft
 * überschreibt seine eigenen Dateien und löscht in Nextcloud nichts.
 * Zugangsdaten verschlüsselt in site_settings, nie im Repository.
 */
class NextcloudAblage
{
    public const KEY = 'nextcloud_ablage';

    /** @return array{url: string, benutzer: string, passwort: string}|null */
    public static function zugang(): ?array
    {
        $wert = SiteSetting::query()->whereKey(self::KEY)->value('value');
        if (blank($wert)) {
            return null;
        }
        try {
            $daten = json_decode(Crypt::decryptString((string) $wert), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($daten) && filled($daten['url'] ?? null) ? $daten : null;
    }

    public static function eingerichtet(): bool
    {
        return Zuschuesse::aktiv() && self::zugang() !== null;
    }

    /** Leeres Passwort behält das gespeicherte. */
    public static function speichern(string $url, string $benutzer, ?string $passwort, ?int $userId): void
    {
        $alt = self::zugang();
        $daten = ['url' => rtrim(trim($url), '/'), 'benutzer' => trim($benutzer), 'passwort' => filled($passwort) ? (string) $passwort : (string) ($alt['passwort'] ?? '')];
        SiteSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => Crypt::encryptString((string) json_encode($daten)), 'updated_by' => $userId]);
    }

    public static function entfernen(): void
    {
        SiteSetting::query()->whereKey(self::KEY)->delete();
    }

    private static function http(): PendingRequest
    {
        $zugang = self::zugang() ?? throw new RuntimeException('Die Nextcloud-Ablage ist nicht eingerichtet.');

        return Http::withBasicAuth($zugang['benutzer'], $zugang['passwort'])->timeout(60);
    }

    private static function pfad(string ...$teile): string
    {
        return (string) self::zugang()['url'].'/'.implode('/', array_map('rawurlencode', $teile));
    }

    /** Ordnername „JJJJ-MM-TT Titel“, ohne Zeichen, die Nextcloud nicht mag. */
    public static function ordner(Event $event): string
    {
        $titel = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', (string) $event->title));

        return $event->starts_at->format('Y-m-d').' '.mb_substr((string) preg_replace('/\s+/', ' ', $titel), 0, 80);
    }

    /** Verbindung prüfen: Ordner lesbar? Liefert eine Meldung für das Backend. */
    public static function testen(): string
    {
        $antwort = self::http()->withHeaders(['Depth' => '0'])->send('PROPFIND', (string) self::zugang()['url'].'/');

        return match (true) {
            $antwort->status() === 207 => 'Verbindung in Ordnung, der Ordner ist erreichbar.',
            $antwort->status() === 401 => 'Anmeldung abgelehnt (HTTP 401): Benutzer oder App-Passwort prüfen.',
            $antwort->status() === 404 => 'Ordner nicht gefunden (HTTP 404): Adresse prüfen.',
            default => 'Unerwartete Antwort (HTTP '.$antwort->status().').',
        };
    }

    private static function ordnerAnlegen(string $url): void
    {
        $antwort = self::http()->send('MKCOL', $url);
        // 201 angelegt, 405 gibt es schon.
        if (! in_array($antwort->status(), [201, 405], true)) {
            throw new RuntimeException('Ordner anlegen: HTTP '.$antwort->status());
        }
    }

    /**
     * Unterlagen der Veranstaltung ablegen. Liefert die Zahl der Dateien.
     *
     * @throws RuntimeException
     */
    public static function abgleichen(Event $event): int
    {
        if (! self::eingerichtet() || ! $event->zuschussAktiv()) {
            return 0;
        }
        $ordner = self::ordner($event);
        self::ordnerAnlegen(self::pfad($ordner));
        self::ordnerAnlegen(self::pfad($ordner, 'Belege'));

        $pdf = app(ZuschussPdf::class);
        $n = 0;
        foreach (array_keys(ZuschussPdf::unterlagen($event)) as $art) {
            $antwort = self::http()->withBody($pdf->unterlage($event, $art), 'application/pdf')
                ->put(self::pfad($ordner, ZuschussPdf::dateiname($event, $art)));
            if (! $antwort->successful()) {
                throw new RuntimeException('Hochladen: HTTP '.$antwort->status());
            }
            $n++;
        }

        return $n;
    }
}
