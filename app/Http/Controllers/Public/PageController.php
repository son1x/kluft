<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactTopic;
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

        if ($slug === config('dpsg.sections_page')) {
            return $this->sectionOverview($page);
        }

        if ($slug === config('dpsg.contact_page')) {
            $topics = ContactTopic::query()->active()->get();

            return view('pages.contact', [
                'page' => $page,
                'topics' => $topics,
                'selected' => old('thema', $topics->firstWhere('slug', request()->query('thema'))?->slug),
            ]);
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
            'dak' => $this->dak($page->stage),
            'staemmeCount' => $this->staemmeCounts()[$page->stage] ?? 0,
            'events' => Event::query()->visible()->upcoming()->where('stage', $page->stage)
                ->orderBy('starts_at')->limit(3)->get(),
        ]);
    }

    /**
     * Diözesanarbeitskreis einer Stufe: Stufenreferent*innen aus der
     * Diözesanleitung zuerst, dann die DAK-Mitglieder. Die Zwischen-
     * überschrift wird nur für die Anzeige gesetzt, nicht gespeichert.
     *
     * @return Collection<int, Person>
     */
    private function dak(string $stage): Collection
    {
        return Person::query()->visible()->where('stage', $stage)->whereIn('gremium', ['leitung', 'dak'])
            ->with('photo')->orderByRaw("gremium = 'dak'")->orderBy('sort')->orderBy('name')->get()
            ->each(fn (Person $p) => $p->subgroup = $p->gremium === 'dak'
                ? config('dpsg.site.ak_subgroups.dak', 'Im Arbeitskreis')
                : config('dpsg.site.ak_subgroups.leitung', 'Stufenreferent*innen'));
    }

    /**
     * Fachbereiche: je Sektion Kachel mit Farbe, Kurzbeschreibung und
     * Ansprechpersonen, in der Reihenfolge der Navigation.
     */
    private function sectionOverview(Page $page): View
    {
        // Nur echte Fachbereiche (nicht z. B. das Serviceangebot Beratung).
        $sections = array_filter(config('dpsg.sections'), fn (array $s) => ($s['kind'] ?? 'Fachbereich') === 'Fachbereich');
        $landings = Page::query()->published()->whereIn('slug', array_column($sections, 'landing'))->get()->keyBy('slug');
        $contacts = Person::query()->visible()->whereIn('section', array_keys($sections))->orderBy('sort')->get()->groupBy('section');

        $items = collect($sections)
            ->filter(fn (array $s) => $landings->has($s['landing']))
            ->map(fn (array $s, string $key) => [
                'key' => $key,
                'config' => $s,
                'page' => $landings[$s['landing']],
                'contacts' => $contacts->get($key, collect()),
            ])
            ->sortBy(fn (array $item) => [$item['page']->nav_sort, $item['page']->title])
            ->values();

        return view('pages.sections', ['page' => $page, 'items' => $items]);
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
