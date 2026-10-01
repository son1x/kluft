<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GeorgsboteIssue;
use App\Models\Page;
use App\Services\BrevoNewsletter;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Georgsbote: Archiv (F-GB-01..05) und Newsletter-Anmeldung (F-GB-10..16).
 */
class GeorgsboteController extends Controller
{
    public function index(BrevoNewsletter $newsletter): View
    {
        return view('georgsbote.index', [
            'intro' => Page::query()->published()->where('slug', 'georgsbote')->first(),
            'jahrgaenge' => GeorgsboteIssue::query()->published()
                ->orderByDesc('year')->orderByDesc('issue_no')
                ->get()
                ->groupBy('year'),
            'newsletterAktiv' => $newsletter->isConfigured(),
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

    public function subscribe(Request $request, BrevoNewsletter $newsletter): RedirectResponse
    {
        // Honeypot: Bots füllen das versteckte Feld aus – stillschweigend
        // "Erfolg" melden, ohne etwas zu tun.
        if (filled($request->input('website'))) {
            return redirect()->route('georgsbote.index')->with('newsletter_status', 'angefragt');
        }

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'vorname' => ['nullable', 'string', 'max:100'],
            'einwilligung' => ['accepted'],
        ], [
            'email.required' => 'Bitte gib deine E-Mail-Adresse an.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'einwilligung.accepted' => 'Bitte bestätige die Einwilligung.',
        ]);

        if (! $newsletter->isConfigured()) {
            return back()->with('newsletter_status', 'inaktiv');
        }

        $ok = $newsletter->requestDoubleOptIn(mb_strtolower(trim($data['email'])), $data['vorname'] ?? null);

        // Audit ohne Klartext-Adresse, nur gekürzter Hash (F-GB-12).
        AuditLogger::log(
            'newsletter.doi:'.($ok ? 'angefragt' : 'fehler').':'.substr(hash('sha256', mb_strtolower(trim($data['email']))), 0, 12),
            request: $request,
        );

        return redirect()->route('georgsbote.index')
            ->with('newsletter_status', $ok ? 'angefragt' : 'fehler')
            ->withFragment('newsletter');
    }

    public function danke(): View
    {
        return view('georgsbote.danke');
    }
}
