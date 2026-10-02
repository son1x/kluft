<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Gruppierung;
use App\Models\Page;
use App\Models\Person;
use App\Models\TeamPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        if ($page->stage && config("dpsg.stages.{$page->stage}")) {
            return $this->stage($page);
        }

        if ($slug === config('dpsg.stages_page')) {
            return $this->stageOverview($page);
        }

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

    /**
     * Stufenseite mit Kopfband in Stufenfarbe, Ansprechpersonen aus dem
     * Diözesanverband, Stämmen mit dieser Stufe und nächsten Terminen.
     */
    private function stage(Page $page): View
    {
        $stagePages = $this->stagePages();
        $index = $stagePages->search(fn (Page $p) => $p->is($page));

        return view('pages.stage', [
            'page' => $page,
            'stage' => config("dpsg.stages.{$page->stage}"),
            'stagePages' => $stagePages,
            'previous' => $index === false || $index === 0 ? null : $stagePages[$index - 1],
            'next' => $index === false ? null : $stagePages->get($index + 1),
            'people' => Person::query()->visible()->where('stage', $page->stage)->with('photo')->orderBy('sort')->get(),
            'staemmeCount' => $this->staemmeCounts()[$page->stage] ?? 0,
            'events' => Event::query()->visible()->upcoming()->where('stage', $page->stage)
                ->orderBy('starts_at')->limit(3)->get(),
        ]);
    }

    private function stageOverview(Page $page): View
    {
        return view('pages.stages', [
            'page' => $page,
            'stagePages' => $this->stagePages(),
            'staemmeCounts' => $this->staemmeCounts(),
        ]);
    }

    /**
     * Veröffentlichte Stufenseiten in pädagogischer Reihenfolge.
     *
     * @return Collection<int, Page>
     */
    private function stagePages(): Collection
    {
        $order = array_keys(config('dpsg.stages'));

        return Page::query()->published()->whereNotNull('stage')->get()
            ->filter(fn (Page $p) => in_array($p->stage, $order, true))
            ->sortBy(fn (Page $p) => array_search($p->stage, $order, true))
            ->unique('stage')
            ->values();
    }

    /**
     * Anzahl aktiver Stämme je angebotener Stufe.
     *
     * @return array<string, int>
     */
    private function staemmeCounts(): array
    {
        return Gruppierung::query()->active()->where('typ', 'stamm')
            ->join('gruppierung_stufen', 'gruppierung_stufen.gruppierung_id', '=', 'gruppierungen.id')
            ->groupBy('gruppierung_stufen.stufe')
            ->selectRaw('gruppierung_stufen.stufe, count(distinct gruppierungen.id) as anzahl')
            ->pluck('anzahl', 'stufe')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
