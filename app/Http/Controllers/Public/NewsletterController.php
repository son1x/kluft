<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\BrevoNewsletter;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Newsletter der Gliederung (beim DV „Georgsbrief“, config dpsg.newsletter):
 * Anmeldung per Double-Opt-In bei Brevo (F-GB-10..16). Die E-Mail-Adresse
 * wird nicht im CMS gespeichert.
 */
class NewsletterController extends Controller
{
    public function index(BrevoNewsletter $newsletter): View
    {
        return view('newsletter.index', [
            'intro' => Page::query()->published()->where('slug', config('dpsg.newsletter.slug'))->first(),
            'newsletterAktiv' => $newsletter->isConfigured(),
        ]);
    }

    public function subscribe(Request $request, BrevoNewsletter $newsletter): RedirectResponse
    {
        // Honeypot: Bots füllen das versteckte Feld aus – stillschweigend
        // "Erfolg" melden, ohne etwas zu tun.
        if (filled($request->input('website'))) {
            return redirect()->route('newsletter.index')->with('newsletter_status', 'angefragt');
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

        return redirect()->route('newsletter.index')
            ->with('newsletter_status', $ok ? 'angefragt' : 'fehler')
            ->withFragment('newsletter');
    }

    public function danke(): View
    {
        return view('newsletter.danke');
    }
}
