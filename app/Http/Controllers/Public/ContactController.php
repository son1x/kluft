<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessageMail;
use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\Event;
use App\Models\Person;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\Mailformular;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
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

    /** Formular an eine einzelne Adresse (Funktion „E-Mail-Adressen öffnen ein Formular“). */
    public function schreiben(Request $request): View
    {
        $email = Mailformular::adresse($request->query('an'));
        abort_unless(Mailformular::aktiv() && $email !== null, 404);

        return view('pages.mailformular', [
            'an' => (string) $request->query('an'),
            'email' => $email,
            'empfaenger' => Mailformular::empfaenger($email),
        ]);
    }

    public function sendDirekt(Request $request): RedirectResponse
    {
        $schluessel = (string) $request->input('an');
        $email = Mailformular::adresse($schluessel);
        abort_unless(Mailformular::aktiv() && $email !== null, 404);
        $back = fn (string $status) => redirect()->route('mailformular.zeigen', ['an' => $schluessel])->with('kontakt_status', $status);

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $back('gesendet') : back()->withInput()->with('kontakt_status', 'abgelaufen');
        }

        $data = $request->validate($this->messageRules(), $this->messages());
        $an = Mailformular::empfaenger($email);
        $recipients = [$email];

        $ok = $this->deliver($recipients, new ContactMessageMail(
            topic: 'Nachricht über die Website',
            senderName: $data['name'],
            senderEmail: $data['email'],
            subjectLine: $data['betreff'] ?? null,
            body: $data['nachricht'],
        ));
        $ok = $this->speichern([
            'art' => 'direkt', 'thema' => mb_substr('An '.$an['name'], 0, 200), 'betreff' => $data['betreff'] ?? null,
        ], $data, $recipients, $ok) || $ok;

        AuditLogger::log('kontakt.direkt:'.($ok ? 'gesendet' : 'fehler'), request: $request);

        return $ok ? $back('gesendet') : back()->withInput()->with('kontakt_status', 'fehler');
    }

    /**
     * Formular unter einer Gremiumsseite (z. B. Diözesanleitung): an eine
     * Person der Seite oder an alle. Adressen kommen nur aus den Personen
     * der Seite, nie aus der Anfrage.
     */
    public function sendGremium(Request $request, string $gremium): RedirectResponse
    {
        $seite = config("dpsg.gremien.{$gremium}.page");
        abort_unless($seite && config("dpsg.gremien.{$gremium}.formular"), 404);
        $personen = Person::fuerGremiumsseite($gremium)->filter(fn (Person $p) => $p->publicEmail() !== null);
        abort_if($personen->isEmpty(), 404);
        $back = fn (string $status) => redirect()->to(url('/'.$seite).'#gremium-kontakt')->with('gremium_status', $status);

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $back('gesendet') : redirect()->to(url('/'.$seite).'#gremium-kontakt')->withInput()->with('gremium_status', 'abgelaufen');
        }

        $data = $request->validateWithBag('gremium', $this->messageRules() + [
            'an' => ['required', 'in:alle,'.$personen->pluck('id')->implode(',')],
        ], $this->messages() + ['an.required' => 'Bitte wähle, an wen die Nachricht gehen soll.', 'an.in' => 'Bitte wähle aus der Liste.']);

        $label = (string) config("dpsg.gremien.{$gremium}.label", $gremium);
        $an = $data['an'] === 'alle' ? $personen : $personen->where('id', (int) $data['an']);
        $recipients = $an->map(fn (Person $p) => mb_strtolower((string) $p->publicEmail()))->unique()->values()->all();
        $thema = $data['an'] === 'alle' ? 'An alle: '.$label : 'An '.$an->first()?->name.' ('.$label.')';

        $ok = $this->deliver($recipients, new ContactMessageMail(
            topic: $thema,
            senderName: $data['name'],
            senderEmail: $data['email'],
            subjectLine: $data['betreff'] ?? null,
            body: $data['nachricht'],
        ));
        $ok = $this->speichern(['art' => 'gremium', 'thema' => mb_substr($thema, 0, 200), 'betreff' => $data['betreff'] ?? null], $data, $recipients, $ok) || $ok;

        AuditLogger::log('kontakt.gremium:'.$gremium.':'.($ok ? 'gesendet' : 'fehler'), request: $request);

        return $ok ? $back('gesendet') : redirect()->to(url('/'.$seite).'#gremium-kontakt')->withInput()->with('gremium_status', 'fehler');
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
                'empfaenger' => $recipients, 'zugestellt' => $zugestellt,
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
