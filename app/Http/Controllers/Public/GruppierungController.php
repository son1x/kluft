<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gruppierung;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Bezirke- und Stämme-Verzeichnis (Pflichtenheft 6.11): Listenansicht
 * ohne JavaScript nutzbar, Karte als Erweiterung darüber.
 */
class GruppierungController extends Controller
{
    public function index(Request $request): View
    {
        [$bezirk, $stufe] = $this->filters($request);

        $bezirke = Gruppierung::query()->active()->where('typ', 'bezirk')->ordered()->get();

        $staemme = Gruppierung::query()->active()->where('typ', 'stamm')
            ->filtered($bezirk, $stufe)
            ->with('bezirk')
            ->ordered()
            ->get();
        Gruppierung::preloadStufen($bezirke->concat($staemme));
        $staemme = $staemme->groupBy('parent_id');

        $sections = $bezirke
            ->when($bezirk, fn ($c) => $c->where('slug', $bezirk))
            ->map(fn (Gruppierung $b) => ['bezirk' => $b, 'staemme' => $staemme->get($b->id, collect())])
            ->when($stufe, fn ($c) => $c->filter(fn (array $s) => $s['staemme']->isNotEmpty()))
            ->values();

        $ohneBezirk = $bezirk ? collect() : $staemme->get('', collect());

        return view('gruppierungen.index', [
            // Redaktioneller Einleitungstext (Seite mit dem Pfad des Verzeichnisses, zugleich Menüpunkt).
            'intro' => Page::query()->published()->where('slug', config('dpsg.directory.path'))->first(),
            'bezirke' => $bezirke,
            'sections' => $sections,
            'ohneBezirk' => $ohneBezirk,
            'filterBezirk' => $bezirk,
            'filterStufe' => $stufe,
            'mitStandort' => Gruppierung::query()->active()->whereNotNull('lat')->whereNotNull('lng')->count(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        [$bezirk, $stufe] = $this->filters($request);
        $key = 'gruppierungen.geojson:'.Gruppierung::cacheVersion().':'.md5("{$bezirk}|{$stufe}");

        $data = Cache::remember($key, now()->addDay(), function () use ($bezirk, $stufe) {
            return [
                'type' => 'FeatureCollection',
                'features' => Gruppierung::query()->active()
                    ->whereNotNull('lat')->whereNotNull('lng')
                    ->filtered($bezirk, $stufe)
                    ->with(['bezirk', 'logo'])
                    ->ordered()
                    ->get()
                    ->tap(fn ($all) => Gruppierung::preloadStufen($all))
                    ->map(fn (Gruppierung $g) => [
                        'type' => 'Feature',
                        'id' => $g->id,
                        'geometry' => ['type' => 'Point', 'coordinates' => [$g->lng, $g->lat]],
                        'properties' => [
                            'slug' => $g->slug,
                            'nummer' => $g->nummer,
                            'ungefaehr' => $g->hasApproximatePosition(),
                            'name' => $g->displayName(),
                            'typ' => $g->typ,
                            'bezirk' => $g->bezirk?->name,
                            'strasse' => $g->strasse,
                            'plz' => $g->plz,
                            'ort' => $g->ort,
                            'email' => $g->kontakt_email,
                            'telefon' => $g->kontakt_telefon,
                            'ansprechperson' => $g->publicAnsprechperson(),
                            'website' => $g->safeUrl('website_url'),
                            'social' => $g->safeUrl('social_url'),
                            'stufen' => $g->stufen(),
                            'logo' => $g->logo?->url(),
                        ],
                    ])
                    ->values()
                    ->all(),
            ];
        });

        return response()->json($data)
            ->header('Content-Type', 'application/geo+json')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Nur bekannte Werte zulassen; Unbekanntes wird ignoriert statt zu
     * einer Fehlerseite zu führen.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function filters(Request $request): array
    {
        $bezirk = $request->query('bezirk');
        $stufe = $request->query('stufe');

        $bezirk = is_string($bezirk) && Gruppierung::query()->where('typ', 'bezirk')->where('slug', $bezirk)->exists() ? $bezirk : null;
        $stufe = is_string($stufe) && array_key_exists($stufe, config('dpsg.stages')) ? $stufe : null;

        return [$bezirk, $stufe];
    }
}
