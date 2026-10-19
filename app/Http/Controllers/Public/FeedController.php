<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;

/**
 * RSS-Feed der Beiträge (Pflichtenheft 11.1): /feed für alle Meldungen,
 * /{sektion}/feed z. B. für die Bolivienpartnerschaft.
 */
class FeedController extends Controller
{
    public const LIMIT = 20;

    public function index(): Response
    {
        return $this->feed(config('dpsg.site.name').' – Aktuelles', route('posts.index'), null);
    }

    public function section(string $section): Response
    {
        $label = config("dpsg.sections.{$section}.label");
        abort_unless($label, 404);

        return $this->feed($label.' – Aktuelles', route('sections.posts', $section), $section);
    }

    private function feed(string $title, string $link, ?string $section): Response
    {
        $posts = Post::query()->published()
            ->when($section, fn ($q) => $q->where('section', $section))
            ->with('coverMedia')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        return response()
            ->view('feed.rss', [
                'title' => $title,
                'link' => $link,
                'self' => request()->url(),
                'posts' => $posts,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=900');
    }
}
