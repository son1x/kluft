<?php

namespace App\Services\Instagram;

use App\Models\InstagramKonto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Instagram-Einblendung (docs/instagram.md, F-IG-07): holt die neuesten
 * Beiträge eigener Konten und spiegelt die Bilder lokal. Besucher*innen
 * laden nichts von Meta; erst der Klick führt zu Instagram.
 */
class InstagramFeed
{
    /** Bilder nur von den Servern, die Instagram dafür nutzt. */
    private const BILD_HOSTS = ['cdninstagram.com', 'fbcdn.net'];

    private const BREITE = 640;

    public function __construct(private InstagramClient $client) {}

    /** Beiträge abrufen; Fehler landen am Konto. Liefert die Zahl der Beiträge. */
    public function abrufen(InstagramKonto $konto): int
    {
        try {
            $this->tokenErneuern($konto);
            $gesehen = [];
            foreach ($this->client->media($konto->access_token, $konto->anzahl + 4) as $media) {
                if (count($gesehen) >= $konto->anzahl) {
                    break;
                }
                $id = (string) ($media['id'] ?? '');
                $art = (string) ($media['media_type'] ?? '');
                $url = (string) ($art === 'VIDEO' ? ($media['thumbnail_url'] ?? '') : ($media['media_url'] ?? ''));
                if ($id === '' || $url === '' || ! filled($media['permalink'] ?? null) || ! str_starts_with((string) $media['permalink'], 'https://www.instagram.com/')) {
                    continue;
                }

                $beitrag = $konto->beitraege()->firstOrNew(['media_id' => $id]);
                if (! $beitrag->exists && ($bild = $this->bild($url, $konto->id, $id)) === null) {
                    continue;
                }
                $beitrag->fill([
                    'art' => mb_substr($art, 0, 20),
                    'permalink' => (string) $media['permalink'],
                    'text' => filled($media['caption'] ?? null) ? mb_substr((string) $media['caption'], 0, 1000) : null,
                    'veroeffentlicht_am' => Carbon::parse((string) ($media['timestamp'] ?? 'now')),
                ] + (isset($bild) ? ['bild' => $bild] : []))->save();
                unset($bild);
                $gesehen[] = $id;
            }

            // Ältere (oder auf Instagram gelöschte) Beiträge samt Bild entfernen.
            $konto->beitraege()->whereNotIn('media_id', $gesehen ?: ['-'])->get()->each->delete();
            $konto->forceFill(['abgerufen_am' => now(), 'fehler' => null])->save();

            return count($gesehen);
        } catch (Throwable $e) {
            $konto->forceFill(['fehler' => mb_substr($e instanceof InstagramException ? $e->getMessage() : 'Abruf fehlgeschlagen ('.class_basename($e).').', 0, 500)])->save();

            return 0;
        }
    }

    /** Long-Lived-Token rechtzeitig verlängern (frühestens 24 h nach dem letzten Mal). */
    private function tokenErneuern(InstagramKonto $konto): void
    {
        $faellig = $konto->token_ablauf === null || $konto->token_ablauf->lt(now()->addDays(20));
        if (! $faellig || $konto->token_erneuert?->gt(now()->subDay())) {
            return;
        }
        $neu = $this->client->refreshToken($konto->access_token);
        $konto->forceFill([
            'access_token' => $neu['access_token'],
            'token_ablauf' => $neu['expires_in'] > 0 ? now()->addSeconds($neu['expires_in']) : now()->addDays(60),
            'token_erneuert' => now(),
        ])->save();
    }

    /** Bild laden, auf höchstens 640 px Breite verkleinern und als JPEG ablegen. */
    private function bild(string $url, int $kontoId, string $mediaId): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $erlaubt = str_starts_with($url, 'https://') && collect(self::BILD_HOSTS)->contains(fn (string $h) => $host === $h || str_ends_with($host, '.'.$h));
        if (! $erlaubt || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $response = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($url);
        if (! $response->successful() || strlen($response->body()) > 15 * 1024 * 1024) {
            return null;
        }
        $quelle = @imagecreatefromstring($response->body());
        if ($quelle === false) {
            return null;
        }

        [$w, $h] = [imagesx($quelle), imagesy($quelle)];
        $zielW = min($w, self::BREITE);
        $zielH = (int) round($h * $zielW / $w);
        $ziel = imagecreatetruecolor($zielW, $zielH);
        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $zielW, $zielH, $w, $h);
        ob_start();
        imagejpeg($ziel, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($quelle);
        imagedestroy($ziel);

        $pfad = 'instagram-feed/'.$kontoId.'/'.preg_replace('/\D/', '', $mediaId).'-'.bin2hex(random_bytes(4)).'.jpg';
        Storage::disk('public')->put($pfad, $jpeg);

        return $pfad;
    }
}
