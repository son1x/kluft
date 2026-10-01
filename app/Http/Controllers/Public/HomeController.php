<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $startPage = Page::query()->published()->with('coverMedia')->where('slug', 'start')->first();

        // Ohne redaktionell angelegte Startseite bleibt die DB-arme
        // Platzhalterseite aktiv.
        if (! $startPage) {
            return view('under-construction');
        }

        $highlightPages = Page::query()
            ->published()
            ->whereIn('slug', ['bolivien', 'georgsbote'])
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
