<?php

namespace App\Services\WordPress;

use App\Models\Media;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lädt Bilder und PDFs der alten Website in die Mediathek (F-MIG-04).
 * Nur vom konfigurierten WordPress-Host, nur erlaubte Dateitypen, mit
 * Größenlimit; dieselbe Quelle wird nur einmal übernommen (source_url).
 */
class MediaFetcher
{
    public const MAX_BYTES = 40 * 1024 * 1024;

    public const MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /** @var list<string> neu übernommene (bzw. im Testlauf zu übernehmende) Dateien */
    public array $fetched = [];

    public function __construct(private readonly string $host, private readonly bool $dryRun = false) {}

    /**
     * Adresse ohne Domain (/uploads/…), damit Inhalte auf Test- und
     * Produktivumgebung gleichermaßen funktionieren.
     */
    public static function path(Media $media): string
    {
        return '/'.ltrim((string) parse_url($media->url(), PHP_URL_PATH), '/');
    }

    public function isLocal(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === $this->host || $host === 'www.'.$this->host || 'www.'.$host === $this->host;
    }

    /**
     * Quelladresse ohne Cache-Parameter (WP Download Manager hängt
     * „refresh“ an) und für Bilder in Originalgröße statt „-300x300“.
     */
    public static function canonical(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';

        if (str_contains($path, '/wp-content/uploads/')) {
            $path = preg_replace('/-\d+x\d+(?=\.(?:jpe?g|png|gif|webp)$)/i', '', $path);
        }

        parse_str($parts['query'] ?? '', $query);
        $query = array_intersect_key($query, ['wpdmdl' => true]);

        return 'https://'.mb_strtolower($parts['host'] ?? '').$path.($query ? '?'.http_build_query($query) : '');
    }

    /**
     * @return Media|null null im Dry-Run
     */
    public function fetch(string $url, ?string $alt = null): ?Media
    {
        $source = self::canonical($url);
        if (! $this->isLocal($source)) {
            throw new RuntimeException("Fremder Host: {$source}");
        }

        if ($existing = Media::query()->where('source_url', $source)->first()) {
            return $existing;
        }

        if ($this->dryRun) {
            $this->fetched[] = $source;

            return null;
        }

        // In eine Temporärdatei laden statt in den Arbeitsspeicher (PDFs
        // des Georgsboten sind teils 30 MB groß).
        $tmp = tempnam(sys_get_temp_dir(), 'wpimport');
        try {
            $response = Http::timeout(180)->withOptions(['sink' => $tmp])->get($source);
            // Fällt die Originalgröße weg (selten), die verlinkte Größe nehmen.
            if ($response->status() === 404 && $source !== $url) {
                $response = Http::timeout(180)->withOptions(['sink' => $tmp])->get(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
            }
            if (! $response->successful()) {
                throw new RuntimeException("Download fehlgeschlagen ({$response->status()}): {$source}");
            }

            clearstatcache(true, $tmp);
            $bytes = filesize($tmp) ?: strlen($response->body());
            if ($bytes > self::MAX_BYTES) {
                throw new RuntimeException(sprintf('Datei zu groß (%d MB, erlaubt %d MB) – bitte verkleinert hochladen: %s', $bytes / 1048576, self::MAX_BYTES / 1048576, $source));
            }
            $body = filesize($tmp) ? (string) file_get_contents($tmp) : $response->body();
        } finally {
            @unlink($tmp);
        }

        // Typ aus dem Inhalt bestimmen, nicht aus Endung oder Header.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        if (! isset(self::MIMES[$mime])) {
            throw new RuntimeException("Dateityp {$mime} nicht erlaubt: {$source}");
        }

        $name = $this->filename($response->header('Content-Disposition'), $source, self::MIMES[$mime]);
        $path = 'media/wp/'.now()->format('Y').'/'.$name;
        $disk = Storage::disk('public');
        while ($disk->exists($path)) {
            $path = 'media/wp/'.now()->format('Y').'/'.Str::random(6).'-'.$name;
        }
        $disk->put($path, $body);

        $size = str_starts_with($mime, 'image/') ? @getimagesizefromstring($body) : false;

        try {
            $media = Media::query()->create([
                'path' => $path,
                'mime' => $mime,
                'size' => strlen($body),
                // Bei Bildern Alternativtext (leer = Nacharbeit), bei PDFs Beschreibung.
                'alt_text' => Str::limit(trim((string) $alt), 250, ''),
                'credit' => '', // Bildnachweis ist Nacharbeit (s. import_issues)
                'rights_confirmed' => false,
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
                'source_url' => $source,
            ]);
            $this->fetched[] = $source;

            return $media;
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }

    private function filename(?string $disposition, string $source, string $ext): string
    {
        $name = null;
        if ($disposition && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m)) {
            $name = rawurldecode($m[1]);
        }
        $name ??= basename((string) parse_url($source, PHP_URL_PATH)) ?: 'datei';
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'datei';

        return Str::limit($base, 80, '').'.'.$ext;
    }
}
