<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GeorgsboteIssue;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Georgsbote: Archiv der PDF-Ausgaben (F-GB-01..05). Die Newsletter-
 * Anmeldung gehört zum Newsletter (NewsletterController).
 */
class GeorgsboteController extends Controller
{
    public function index(): View
    {
        return view('georgsbote.index', [
            'intro' => Page::query()->published()->where('slug', 'georgsbote')->first(),
            'jahrgaenge' => GeorgsboteIssue::query()->published()
                ->orderByDesc('year')->orderByDesc('issue_no')
                ->get()
                ->groupBy('year'),
        ]);
    }

    public function pdf(int $jahr, int $ausgabe): StreamedResponse
    {
        $issue = GeorgsboteIssue::query()->published()
            ->where('year', $jahr)->where('issue_no', $ausgabe)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($issue->pdf_path), 404);

        // Zähler ohne Personenbezug (F-GB-05).
        $issue->increment('downloads');

        return Storage::disk('local')->response($issue->pdf_path, "Georgsbote-{$jahr}-{$ausgabe}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'public, max-age=86400',
        ], 'inline');
    }
}
