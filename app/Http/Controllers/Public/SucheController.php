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
        $alle = $q !== '' ? Suche::finden($q) : collect();
        // Bereichsleiste (Apple HIG „Scope bar“): nur Bereiche mit Treffern, „Alle“ als Standard.
        $anzahl = collect(Suche::BEREICHE)->map(fn (array $arten) => $alle->whereIn('art', $arten)->count())->filter()->all();
        $bereich = array_key_exists((string) $request->query('bereich'), $anzahl) ? (string) $request->query('bereich') : null;

        return view('suche.index', [
            'q' => $q,
            'treffer' => $bereich ? $alle->whereIn('art', Suche::BEREICHE[$bereich])->values() : $alle,
            'gesamt' => $alle->count(),
            'anzahl' => $anzahl,
            'bereich' => $bereich,
            'zuKurz' => $q !== '' && Suche::woerter($q) === [],
        ]);
    }
}
