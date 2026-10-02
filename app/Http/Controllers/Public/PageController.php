<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Person;
use App\Models\TeamPhoto;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        // Gremiumsseiten (z. B. Diözesanleitung) zeigen ihre Personen und
        // das Gruppenfoto unter dem Seitentext (F-CMS-12).
        $gremium = collect(config('dpsg.gremien'))->search(fn (array $g) => $g['page'] === $slug);

        return view('pages.show', [
            'page' => $page,
            'people' => $gremium === false ? collect() : Person::query()->visible()
                ->where('gremium', $gremium)->with('photo')->orderBy('sort')->get(),
            'teamPhoto' => $gremium === false ? null : TeamPhoto::query()->where('gremium', $gremium)->where('active', true)
                ->with(['media', 'spots.person'])->latest()->first(),
        ]);
    }
}
