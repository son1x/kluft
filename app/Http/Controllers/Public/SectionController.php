<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Sektionen mit eigener Landingpage (Pflichtenheft 6.2), z. B.
 * /bolivien bzw. bolivienpartnerschaft.de.
 */
class SectionController extends Controller
{
    public function show(Request $request, string $section): View
    {
        $config = config("dpsg.sections.{$section}");
        abort_unless($config, 404);

        return view('sections.show', [
            'section' => $section,
            'config' => $config,
            'landing' => Page::query()->published()->with('coverMedia')->where('slug', $config['landing'])->first(),
            'pages' => $this->sectionPages($section, $config['landing']),
            'posts' => Post::query()->published()->with('coverMedia')
                ->where('section', $section)
                ->orderByDesc('published_at')
                ->limit(6)
                ->get(),
            'canonical' => $this->isSectionHost($request, $config)
                ? rtrim(config('app.url'), '/').'/'.$config['landing']
                : null,
        ]);
    }

    public function posts(string $section): View
    {
        $config = config("dpsg.sections.{$section}");
        abort_unless($config, 404);

        return view('sections.posts', [
            'section' => $section,
            'config' => $config,
            'posts' => Post::query()->published()->where('section', $section)->orderByDesc('published_at')->paginate(12),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Page>
     */
    public static function sectionPages(string $section, string $landingSlug)
    {
        return Page::query()->published()
            ->where('section', $section)
            ->where('slug', '!=', $landingSlug)
            ->orderBy('nav_sort')->orderBy('title')
            ->get();
    }

    private function isSectionHost(Request $request, array $config): bool
    {
        return in_array(mb_strtolower($request->getHost()), $config['domains'] ?? [], true);
    }
}
