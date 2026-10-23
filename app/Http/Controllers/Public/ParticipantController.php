<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\RegistrationDocument;
use App\Services\EventRegistrations;
use App\Services\Teilnahmebescheinigung;
use App\Support\ParticipantLogin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * „Meine Anmeldungen“ (Talia): Teilnehmende sehen ohne Konto – mit E-Mail
 * und Einmalcode – ihre Anmeldungen, stornieren selbst und laden Dokumente
 * wie Rechnungen oder Teilnahmebescheinigungen herunter.
 */
class ParticipantController extends Controller
{
    public function __construct(private ParticipantLogin $login) {}

    public function index(Request $request): View
    {
        $email = $this->login->email($request);
        if (! $email) {
            return view('participants.login', ['pendingEmail' => $request->session()->get('teilnehmende-code-an')]);
        }

        $registrations = EventRegistration::query()
            ->where('email', $email)
            ->whereIn('status', ['pending', 'confirmed', 'waitlist', 'cancelled'])
            ->with(['event', 'documents', 'zahlungen'])
            ->get()
            ->sortByDesc(fn (EventRegistration $r) => $r->event->starts_at);

        [$upcoming, $past] = $registrations->partition(fn (EventRegistration $r) => ($r->event->ends_at ?? $r->event->starts_at)->gte(now()->startOfDay()));

        return view('participants.overview', [
            'email' => $email,
            'upcoming' => $upcoming->sortBy(fn (EventRegistration $r) => $r->event->starts_at),
            'past' => $past,
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']], [], ['email' => 'E-Mail-Adresse']);
        if (filled($request->input('website'))) {
            return to_route('participants.index');
        }

        $this->login->sendCode($data['email']);
        $request->session()->put('teilnehmende-code-an', ParticipantLogin::normalize($data['email']));

        return to_route('participants.index');
    }

    public function verify(Request $request): RedirectResponse
    {
        $email = (string) $request->session()->get('teilnehmende-code-an');
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'Code']);

        if ($email === '' || ! $this->login->verify($email, $data['code'])) {
            return to_route('participants.index')->withErrors(['code' => 'Der Code stimmt nicht oder ist abgelaufen. Prüfe ihn oder fordere einen neuen an.']);
        }

        $request->session()->forget('teilnehmende-code-an');
        $this->login->login($request, $email);

        return to_route('participants.index');
    }

    public function restart(Request $request): RedirectResponse
    {
        $request->session()->forget('teilnehmende-code-an');

        return to_route('participants.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->login->logout($request);

        return to_route('participants.index')->with('status', 'Du bist abgemeldet.');
    }

    public function cancel(Request $request, EventRegistration $registration, EventRegistrations $registrations): RedirectResponse
    {
        $this->authorizeRegistration($request, $registration);

        if (! in_array($registration->status, ['confirmed', 'waitlist'], true) || $registration->event->starts_at->isPast()) {
            return to_route('participants.index')->with('status', 'Diese Anmeldung lässt sich nicht mehr stornieren. Bitte melde dich beim Team der Veranstaltung.');
        }

        $registrations->cancel($registration);

        return to_route('participants.index')->with('status', 'Die Anmeldung für '.$registration->fullName().' zu „'.$registration->event->title.'“ ist storniert.');
    }

    public function document(Request $request, RegistrationDocument $document): StreamedResponse
    {
        $this->authorizeRegistration($request, $document->registration);
        abort_unless(Storage::disk(RegistrationDocument::DISK)->exists($document->path), 404);

        return Storage::disk(RegistrationDocument::DISK)->download($document->path, $document->downloadName(), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Erzeugte Teilnahmebescheinigung; eine hochgeladene hat Vorrang. */
    public function certificate(Request $request, EventRegistration $registration, Teilnahmebescheinigung $bescheinigung): Response|RedirectResponse
    {
        $this->authorizeRegistration($request, $registration);
        abort_unless($registration->certificateApproved(), 404);

        if ($hochgeladen = $registration->uploadedCertificate()) {
            return to_route('participants.document', $hochgeladen);
        }

        return response($bescheinigung->pdf($registration), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $bescheinigung->filename($registration), 'Teilnahmebescheinigung.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeRegistration(Request $request, EventRegistration $registration): void
    {
        $email = $this->login->email($request);
        abort_if($email === null, 403, 'Bitte melde dich unter „Meine Anmeldungen“ an.');
        abort_unless(hash_equals($registration->email, $email), 404);
    }
}
