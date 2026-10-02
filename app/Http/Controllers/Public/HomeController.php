<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Eigene Domain einer Sektion (z. B. bolivienpartnerschaft.de)
        // zeigt deren Landingpage – ohne Weiterleitung (F-BOL-02, 4.2).
        foreach (config('dpsg.sections') as $key => $section) {
            if (in_array(mb_strtolower($request->getHost()), $section['domains'] ?? [], true)) {
                return app(SectionController::class)->show($request, $key);
            }
        }

        $startPage = Page::query()->published()->with('coverMedia')->where('slug', 'start')->first();

        // Ohne redaktionell angelegte Startseite bleibt die DB-arme
        // Platzhalterseite aktiv.
        if (! $startPage) {
            return view('under-construction');
        }

        $highlightPages = Page::query()
            ->published()
            ->whereIn('slug', ['bolivien', 'georgsbote', 'georgsbrief'])
            ->get()
            ->keyBy('slug');

        $posts = Post::query()
            ->published()
            ->with('coverMedia')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return view('home', [
            'page' => $startPage,
            'highlightPages' => $highlightPages,
            'posts' => $posts,
        ]);
    }
}
