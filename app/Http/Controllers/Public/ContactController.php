<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\Event;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Kontaktformular und Fragen zu Veranstaltungen (F-CMS-09): Nachrichten
 * gehen per Mail an die zuständige Person; mit der Funktion
 * „Nachrichten-Eingang“ zusätzlich in den Eingang im Backend (die
 * Verwaltung sieht alle). Spamschutz per Honeypot, Zeitfalle und
 * Rate-Limit (s. Route).
 */
class ContactController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $back = fn (string $status) => redirect()->to(url('/'.config('dpsg.contact_page')).'#kontaktformular')->with('kontakt_status', $status);

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $back('gesendet') : back()->withInput()->with('kontakt_status', 'abgelaufen');
        }

        $topics = ContactTopic::query()->active()->get();
        $data = $request->validate([
            ...$this->messageRules(),
            'thema' => $topics->isEmpty() ? ['nullable'] : ['required', Rule::in($topics->pluck('slug')->all())],
        ], $this->messages());

        $topic = $topics->firstWhere('slug', $data['thema'] ?? null);
        $recipients = $topic?->recipients() ?? [ContactTopic::fallbackEmail()];

        $ok = $this->deliver($recipients, new ContactMessageMail(
            topic: $topic->label ?? 'Kontaktformular',
            senderName: $data['name'],
            senderEmail: $data['email'],
            subjectLine: $data['betreff'] ?? null,
            body: $data['nachricht'],
        ));
        // Im Eingang gespeichert gilt die Nachricht als angekommen, auch wenn die Mail hakt.
        $ok = $this->speichern([
            'art' => 'kontakt', 'contact_topic_id' => $topic?->id, 'thema' => $topic->label ?? 'Kontaktformular',
            'betreff' => $data['betreff'] ?? null,
        ], $data, $recipients, $ok) || $ok;

        AuditLogger::log('kontakt:'.($ok ? 'gesendet' : 'fehler').':'.($topic->slug ?? 'ohne-thema'), request: $request);

        return $ok ? $back('gesendet') : back()->withInput()->with('kontakt_status', 'fehler');
    }

    public function askEvent(Request $request, string $slug): RedirectResponse
    {
        $event = Event::query()->visible()->where('slug', $slug)->firstOrFail();
        $back = fn (string $status) => redirect()->route('events.show', $event->slug)->with('frage_status', $status)->withFragment('frage');

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $back('gesendet') : back()->withInput()->with('frage_status', 'abgelaufen')->withFragment('frage');
        }

        // Eigene Fehler-Bag, damit Fehler nicht im Anmeldeformular erscheinen.
        $data = $request->validateWithBag('frage', $this->messageRules(), $this->messages());

        $recipients = [filled($event->contact_email) ? $event->contact_email : ContactTopic::fallbackEmail()];
        $ok = $this->deliver($recipients, new ContactMessageMail(
            topic: 'Frage zur Veranstaltung',
            senderName: $data['name'],
            senderEmail: $data['email'],
            subjectLine: $event->title,
            body: $data['nachricht'],
            context: $event->title.' ('.$event->dateLabel().') – '.route('events.show', $event->slug),
        ));
        $ok = $this->speichern([
            'art' => 'veranstaltung', 'event_id' => $event->id, 'thema' => mb_substr($event->title.' ('.$event->dateLabel().')', 0, 200),
            'betreff' => mb_substr($event->title, 0, 150),
        ], $data, $recipients, $ok) || $ok;

        AuditLogger::log('veranstaltung.frage:'.($ok ? 'gesendet' : 'fehler'), subjectType: 'event', subjectId: $event->id, request: $request);

        return $ok ? $back('gesendet') : back()->withInput()->with('frage_status', 'fehler')->withFragment('frage');
    }

    /** @return array<string, list<string>> */
    private function messageRules(): array
    {
        // Keine Zeilenumbrüche in Name/Betreff (landen in Mail-Kopfzeilen).
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'betreff' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'nachricht' => ['required', 'string', 'min:10', 'max:5000', function (string $attribute, mixed $value, \Closure $fail) {
                if (preg_match_all('#https?://#i', (string) $value) > 3) {
                    $fail('Bitte höchstens drei Links in die Nachricht schreiben.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'name.required' => 'Bitte gib deinen Namen an.',
            'email.required' => 'Bitte gib deine E-Mail-Adresse an, damit wir antworten können.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'thema.required' => 'Bitte wähle ein Thema.',
            'thema.in' => 'Bitte wähle ein Thema aus der Liste.',
            'nachricht.required' => 'Bitte schreib uns eine Nachricht.',
            'nachricht.min' => 'Die Nachricht ist etwas kurz – magst du noch ein paar Worte ergänzen?',
            'nachricht.max' => 'Die Nachricht darf höchstens 5000 Zeichen lang sein.',
        ];
    }

    /**
     * In den Nachrichten-Eingang (nur mit der Funktion). Liefert, ob gespeichert.
     *
     * @param  array<string, mixed>  $werte
     * @param  array<string, mixed>  $data
     * @param  list<string>  $recipients
     */
    private function speichern(array $werte, array $data, array $recipients, bool $zugestellt): bool
    {
        if (! Features::enabled(Features::NACHRICHTEN)) {
            return false;
        }
        try {
            ContactMessage::query()->create($werte + [
                'name' => $data['name'], 'email' => mb_strtolower((string) $data['email']), 'nachricht' => $data['nachricht'],
                'empfaenger' => array_values($recipients), 'zugestellt' => $zugestellt,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Kontaktnachricht konnte nicht gespeichert werden', ['error' => class_basename($e)]);

            return false;
        }
    }

    /** @param  list<string>  $recipients */
    private function deliver(array $recipients, ContactMessageMail $mail): bool
    {
        try {
            Mail::to($recipients)->send($mail);

            return true;
        } catch (Throwable $e) {
            // Ohne Inhalt und Adressen protokollieren.
            Log::error('Kontaktnachricht konnte nicht versendet werden', ['error' => class_basename($e).': '.preg_replace('/[^\s@<>]+@[^\s@<>]+/', '<email>', mb_substr($e->getMessage(), 0, 200))]);

            return false;
        }
    }
}
