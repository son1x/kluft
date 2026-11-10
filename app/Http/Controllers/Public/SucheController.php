<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Suche;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Suche auf der Website (docs/plan-ehrenamt.md Nr. 3). */
class SucheController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        return view('suche.index', [
            'q' => $q,
            'treffer' => $q !== '' ? Suche::finden($q) : collect(),
            'zuKurz' => $q !== '' && Suche::woerter($q) === [],
        ]);
    }
}
