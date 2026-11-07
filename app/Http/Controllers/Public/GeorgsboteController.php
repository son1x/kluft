<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GeorgsboteIssue;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Georgsbote: Archiv der PDF-Ausgaben (F-GB-01..05), daneben die
 * Georgsbriefe (Newsletter) als PDF-Archiv. Die Newsletter-Anmeldung
 * gehört zum Newsletter (NewsletterController).
 */
class GeorgsboteController extends Controller
{
    /**
     * Reiter „Georgsbote“ / „Georgsbrief“ (?art=brief) und Suche in Titel
     * und Inhalt (?q=) – ohne JavaScript, Links bleiben teilbar.
     */
    public function index(Request $request): View
    {
        $art = $request->query('art') === GeorgsboteIssue::BRIEF ? GeorgsboteIssue::BRIEF : GeorgsboteIssue::BOTE;
        $suche = trim(mb_substr((string) $request->query('q', ''), 0, 100));

        $treffer = GeorgsboteIssue::query()->published()
            ->when($suche !== '', function (Builder $query) use ($suche) {
                $like = '%'.str_replace(['%', '_', '\\'], '', $suche).'%';
                $query->where(fn (Builder $q) => $q->where('title', 'like', $like)->orWhere('inhalt', 'like', $like));
            })
            ->orderByDesc('year')->orderByDesc('issue_no')
            ->get();

        return view('georgsbote.index', [
            'intro' => Page::query()->published()->where('slug', 'georgsbote')->first(),
            'art' => $art,
            'suche' => $suche,
            'anzahl' => $treffer->countBy('art'),
            'jahrgaenge' => $treffer->where('art', $art)->groupBy('year'),
        ]);
    }

    public function pdf(int $jahr, int $ausgabe): StreamedResponse
    {
        return $this->ausliefern(GeorgsboteIssue::BOTE, $jahr, $ausgabe, "Georgsbote-{$jahr}-{$ausgabe}.pdf");
    }

    public function brief(int $jahr, int $monat): StreamedResponse
    {
        return $this->ausliefern(GeorgsboteIssue::BRIEF, $jahr, $monat, sprintf('Georgsbrief-%d-%02d.pdf', $jahr, $monat));
    }

    private function ausliefern(string $art, int $jahr, int $nr, string $dateiname): StreamedResponse
    {
        $issue = GeorgsboteIssue::query()->published()
            ->where('art', $art)->where('year', $jahr)->where('issue_no', $nr)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($issue->pdf_path), 404);

        // Zähler ohne Personenbezug (F-GB-05).
        $issue->increment('downloads');

        return Storage::disk('local')->response($issue->pdf_path, $dateiname, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'public, max-age=86400',
        ], 'inline');
    }
}
