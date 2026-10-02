<?php

namespace App\Services\WordPress;

use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Models\WpImportRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * WordPress-Migration (Pflichtenheft 6.9): holt Beiträge über die
 * REST-API, wandelt sie um und legt sie als Entwurf an.
 *
 * - wiederholbar über wp_id; lokal bearbeitete oder schon veröffentlichte
 *   Beiträge werden nicht überschrieben
 * - „Link-Beiträge“ (WordPress leitet auf PDF, Video oder Newsletter um)
 *   werden erkannt; das Ziel wird als Link bzw. PDF übernommen
 * - Kommentare und Benutzerdaten werden nicht übernommen (F-MIG-07)
 * - jede alte Adresse bekommt eine 301-Weiterleitung (F-MIG-05)
 */
class WordPressImporter
{
    /** @var array<string, int> */
    private array $counts = ['gefunden' => 0, 'neu' => 0, 'aktualisiert' => 0, 'übersprungen' => 0, 'fehler' => 0, 'medien' => 0, 'weiterleitungen' => 0];

    /** @var list<array<string, mixed>> */
    private array $log = [];

    private string $base;

    private string $host;

    public function __construct(?string $source = null)
    {
        $this->base = rtrim($source ?? config('dpsg.wp_import.source'), '/');
        $this->host = mb_strtolower((string) parse_url($this->base, PHP_URL_HOST));
        if ($this->host === '' || ! str_starts_with($this->base, 'https://')) {
            throw new RuntimeException('Ungültige Quelle (nur https): '.$this->base);
        }
    }

    /**
     * @param  callable(string): void|null  $progress
     */
    public function run(CarbonInterface $since, bool $dryRun = false, ?int $limit = null, ?callable $progress = null): WpImportRun
    {
        $run = WpImportRun::query()->create([
            'dry_run' => $dryRun,
            'options' => ['source' => $this->base, 'since' => $since->toDateString(), 'limit' => $limit],
            'started_at' => now(),
        ]);

        $media = new MediaFetcher(preg_replace('/^www\./', '', $this->host), $dryRun);

        try {
            $posts = $this->fetchPosts($since, $limit);
            $this->counts['gefunden'] = count($posts);

            // Alte Pfade aller gefundenen Beiträge → neue Pfade, damit interne
            // Links zwischen Beiträgen direkt richtig zeigen.
            $paths = [];
            foreach ($posts as $wp) {
                $paths[Redirect::normalize($wp['link'])] = '/aktuelles/'.$wp['slug'];
            }

            foreach ($posts as $wp) {
                $progress && $progress(html_entity_decode($wp['title']['rendered'] ?? '', ENT_QUOTES | ENT_HTML5));
                try {
                    $this->importPost($wp, new ContentConverter($media, $paths), $media, $dryRun);
                } catch (Throwable $e) {
                    $this->counts['fehler']++;
                    $this->entry($wp, 'fehler', $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $this->counts['fehler']++;
            $this->log[] = ['wp_id' => null, 'title' => 'Abruf', 'action' => 'fehler', 'message' => $e->getMessage()];
        }

        $this->counts['medien'] = count(array_unique($media->fetched));
        $run->update(['counts' => $this->counts, 'log' => $this->log, 'finished_at' => now()]);

        return $run;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPosts(CarbonInterface $since, ?int $limit): array
    {
        $posts = [];
        $page = 1;
        do {
            $response = Http::timeout(60)->acceptJson()->get($this->base.'/wp-json/wp/v2/posts', [
                'per_page' => 100,
                'page' => $page,
                'after' => $since->copy()->startOfDay()->format('Y-m-d\TH:i:s'),
                'orderby' => 'date',
                'order' => 'asc',
                'status' => 'publish',
                '_embed' => 'author,wp:featuredmedia,wp:term',
            ]);
            if (! $response->successful()) {
                throw new RuntimeException("WordPress-API antwortet mit {$response->status()}");
            }
            $posts = [...$posts, ...$response->json()];
            $pages = (int) ($response->header('X-WP-TotalPages') ?: 1);
            $page++;
        } while ($page <= $pages && ($limit === null || count($posts) < $limit));

        return $limit === null ? $posts : array_slice($posts, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $wp
     */
    private function importPost(array $wp, ContentConverter $converter, MediaFetcher $media, bool $dryRun): void
    {
        $wpId = (int) $wp['id'];
        $slug = Str::limit(Str::slug($wp['slug'] ?: $wp['title']['rendered']), 180, '');
        $title = trim(html_entity_decode(strip_tags($wp['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5));
        $existing = Post::query()->where('wp_id', $wpId)->first();

        if (Post::query()->where('slug', $slug)->where(fn ($q) => $q->whereNull('wp_id')->orWhere('wp_id', '!=', $wpId))->exists()) {
            $this->counts['übersprungen']++;
            $this->entry($wp, 'übersprungen', "Adresse /aktuelles/{$slug} ist schon durch einen anderen Beitrag belegt");

            return;
        }

        if ($existing && $existing->status !== 'draft') {
            $this->ensureRedirect($wp['link'], $slug, $dryRun);
            $this->counts['übersprungen']++;
            $this->entry($wp, 'übersprungen', 'Bereits veröffentlicht bzw. in Prüfung – wird nicht mehr überschrieben');

            return;
        }

        if ($existing && $existing->import_hash && ! hash_equals($existing->import_hash, hash('sha256', (string) $existing->body))) {
            $this->ensureRedirect($wp['link'], $slug, $dryRun);
            $this->counts['übersprungen']++;
            $this->entry($wp, 'übersprungen', 'Im CMS bearbeitet – Import überschreibt das nicht');

            return;
        }

        $body = $converter->convert((string) ($wp['content']['rendered'] ?? ''));
        $issues = $converter->issues;

        // Link-Beitrag? WordPress leitet die Beitragsadresse dann woandershin um.
        if ($target = $this->linkTarget((string) $wp['link'])) {
            [$html, $targetIssues] = $this->targetLink($target, $media, $title);
            $body = trim($body."\n".$html);
            $issues = [...$issues, ...$targetIssues];
        }

        if (trim(strip_tags($body, '<img>')) === '') {
            $issues[] = 'Beitrag ohne Inhalt – ergänzen oder nicht veröffentlichen';
            $body = '<p></p>';
        }

        $coverId = null;
        if ($featured = $wp['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null) {
            try {
                $coverId = $media->fetch($featured, $wp['_embedded']['wp:featuredmedia'][0]['alt_text'] ?? null)?->id;
                $issues[] = 'Bildrechte und Bildnachweis der übernommenen Bilder prüfen';
            } catch (Throwable $e) {
                $issues[] = 'Titelbild nicht übernommen: '.$e->getMessage();
            }
        }

        $scope = $this->scopeFor($wp);
        $issues[] = 'Vor Veröffentlichung auf Personenbezug prüfen (Namen, Fotos, Kontaktdaten)';

        $data = [
            'title' => Str::limit($title ?: $slug, 250, ''),
            'slug' => $slug,
            'teaser' => $this->teaser($wp, $body),
            'body' => $body,
            'scope' => $scope['scope'],
            'stage' => $scope['stage'],
            'section' => $scope['section'],
            'cover_media_id' => $coverId,
            'status' => 'draft',
            'published_at' => $wp['date'] ?? now(),
            'author_id' => $this->authorId($wp),
            'wp_url' => $wp['link'],
            'import_hash' => hash('sha256', $body),
            'import_issues' => array_values(array_unique($issues)),
            'import_reviewed' => false,
        ];

        if (! $dryRun) {
            Post::query()->updateOrCreate(['wp_id' => $wpId], $data);
        }
        $this->ensureRedirect($wp['link'], $slug, $dryRun);

        $action = $existing ? 'aktualisiert' : 'neu';
        $this->counts[$action]++;
        $this->entry($wp, $action, null, count($data['import_issues']));
    }

    /**
     * Ziel einer Umleitung der Beitragsadresse (Plugin „Page Links To“),
     * sonst null.
     */
    private function linkTarget(string $permalink): ?string
    {
        if (! str_starts_with($permalink, 'https://') || ! str_contains(mb_strtolower($permalink), $this->host)) {
            return null;
        }

        $response = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($permalink);
        if (! in_array($response->status(), [301, 302, 307, 308], true)) {
            return null;
        }

        $location = (string) $response->header('Location');
        if ($location === '' || Redirect::normalize($location) === Redirect::normalize($permalink)) {
            return null;
        }

        return str_starts_with($location, '/') ? $this->base.$location : $location;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function targetLink(string $target, MediaFetcher $media, string $title): array
    {
        $target = ContentConverter::withoutTracking($target);
        $issues = [];

        if ($media->isLocal($target)) {
            parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
            $path = (string) parse_url($target, PHP_URL_PATH);
            if (isset($query['wpdmdl']) || str_contains($path, '/wp-content/uploads/')) {
                try {
                    $file = $media->fetch($target, 'Datei zum Beitrag „'.$title.'“');
                } catch (Throwable $e) {
                    // Beitrag trotzdem übernehmen, Datei vorerst auf der alten Seite verlinken.
                    return ['<p><a href="'.e(MediaFetcher::canonical($target)).'">PDF öffnen</a></p>', ['Datei nicht übernommen – '.$e->getMessage()]];
                }
                $url = $file ? MediaFetcher::path($file) : MediaFetcher::canonical($target);
                $label = $file && $file->mime !== 'application/pdf' ? 'Datei öffnen' : 'PDF öffnen';

                return ['<p><a href="'.e($url).'">'.$label.'</a></p>', ['PDF übernommen – auf Personenbezug und Bildrechte prüfen']];
            }

            return ['<p><a href="'.e(Redirect::normalize($target)).'">Weiterlesen</a></p>', ['Link auf alte Seite '.Redirect::normalize($target).' – Ziel prüfen']];
        }

        $issues[] = 'Externer Link – prüfen, ob er noch erreichbar ist: '.$target;

        return ['<p><a href="'.e($target).'">'.e(ContentConverter::linkLabel($target)).'</a></p>', $issues];
    }

    private function ensureRedirect(string $permalink, string $slug, bool $dryRun): void
    {
        $from = Redirect::normalize($permalink);
        $to = '/aktuelles/'.$slug;
        if ($from === '/' || $from === $to) {
            return;
        }

        $existing = Redirect::query()->where('from_path', $from)->first();
        if ($existing && $existing->source !== 'wordpress') {
            return; // von Hand gepflegte Weiterleitung hat Vorrang
        }
        if (! $dryRun && (! $existing || $existing->to_path !== $to)) {
            Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'source' => 'wordpress']);
        }
        if (! $existing) {
            $this->counts['weiterleitungen']++;
        }
    }

    /**
     * @param  array<string, mixed>  $wp
     */
    private function teaser(array $wp, string $body): string
    {
        $text = fn (string $html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
        $teaser = $text((string) ($wp['excerpt']['rendered'] ?? '')) ?: $text($body);
        $teaser = preg_replace('/\s*(\[(…|&hellip;|\.\.\.)\]|…)$/u', '', $teaser) ?: '';

        return Str::limit($teaser ?: 'Beitrag aus dem Archiv der alten Website.', 280);
    }

    /**
     * Kategorien → Stufe/Fachbereich über config dpsg.wp_import.categories.
     *
     * @param  array<string, mixed>  $wp
     * @return array{scope: string, stage: ?string, section: ?string}
     */
    private function scopeFor(array $wp): array
    {
        $map = config('dpsg.wp_import.categories', []);
        foreach ($wp['_embedded']['wp:term'][0] ?? [] as $term) {
            $target = $map[$term['slug'] ?? ''] ?? null;
            if (isset($target['stage']) && config("dpsg.stages.{$target['stage']}")) {
                return ['scope' => 'stage', 'stage' => $target['stage'], 'section' => null];
            }
            if (isset($target['section']) && config("dpsg.sections.{$target['section']}")) {
                return ['scope' => 'section', 'stage' => null, 'section' => $target['section']];
            }
        }

        return ['scope' => 'general', 'stage' => null, 'section' => null];
    }

    /**
     * WP-Autor → CMS-Konto über config dpsg.wp_import.authors (Slug →
     * E-Mail); sonst ohne Autor („Redaktion“). WP-Benutzerdaten selbst
     * werden nicht gespeichert.
     *
     * @param  array<string, mixed>  $wp
     */
    private function authorId(array $wp): ?int
    {
        $slug = $wp['_embedded']['author'][0]['slug'] ?? null;
        $email = $slug ? (config('dpsg.wp_import.authors', [])[$slug] ?? null) : null;

        return $email ? User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->value('id') : null;
    }

    /**
     * @param  array<string, mixed>  $wp
     */
    private function entry(array $wp, string $action, ?string $message = null, int $issues = 0): void
    {
        $this->log[] = [
            'wp_id' => $wp['id'] ?? null,
            'title' => html_entity_decode((string) ($wp['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5),
            'date' => substr((string) ($wp['date'] ?? ''), 0, 10),
            'action' => $action,
            'message' => $message,
            'issues' => $issues,
        ];
    }
}
