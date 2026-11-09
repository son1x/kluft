<?php

namespace App\Http\Controllers\Redaktion;

use App\Http\Controllers\Controller;
use App\Services\Sammelbestellung\Ruesthaus;
use App\Support\Sammelbestellung;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschaubilder im Rüsthaus-Katalog (Backend): Die Content Security Policy
 * erlaubt nur eigene Bilder, und der Browser soll nichts direkt vom Rüsthaus
 * laden (SB-155). Kluft holt das kleine Vorschaubild (200 × 200) einmal,
 * mit kurzer Pause zwischen den Abrufen, und hebt es 30 Tage auf.
 */
class KatalogBildController extends Controller
{
    private const ORDNER = 'ruesthaus-katalog';

    public function __invoke(Request $request): Response
    {
        abort_unless(Sammelbestellung::zugriff($request->user()), 403);
        try {
            $url = Crypt::decryptString((string) $request->query('u'));
        } catch (DecryptException) {
            abort(404);
        }
        abort_unless(parse_url($url, PHP_URL_HOST) === Ruesthaus::HOST && str_starts_with((string) parse_url($url, PHP_URL_PATH), '/media/image/'), 404);

        $disk = Storage::disk('local');
        $pfad = self::ORDNER.'/'.sha1($url);
        if (! $disk->exists($pfad) || $disk->lastModified($pfad) < now()->subDays(30)->timestamp) {
            // Höchstens vier Bilder pro Sekunde (statische Dateien, kein Seitenaufbau beim Rüsthaus).
            $warten = (float) Cache::get('ruesthaus:bild', 0) + 0.25 - microtime(true);
            if ($warten > 0) {
                usleep((int) ($warten * 1_000_000));
            }
            Cache::put('ruesthaus:bild', microtime(true), 60);
            $antwort = Http::withHeaders(['User-Agent' => 'Kluft/1.0 (+'.rtrim((string) config('app.url'), '/').')'])->timeout(10)
                ->withOptions(['allow_redirects' => false])->get($url);
            $typ = (string) $antwort->header('Content-Type');
            abort_unless($antwort->status() === 200 && preg_match('#^image/(jpeg|png|webp|gif)#', $typ) && strlen($antwort->body()) < 2_000_000, 404);
            $disk->put($pfad, $antwort->body());
            $disk->put($pfad.'.typ', $typ);
        }

        return response($disk->get($pfad), 200, [
            'Content-Type' => (string) ($disk->get($pfad.'.typ') ?: 'image/jpeg'),
            'Cache-Control' => 'private, max-age=2592000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Adresse für ein Rüsthaus-Vorschaubild. */
    public static function adresse(?string $bild): ?string
    {
        return $bild ? route('shop.katalog.bild', ['u' => Crypt::encryptString($bild)]) : null;
    }
}
