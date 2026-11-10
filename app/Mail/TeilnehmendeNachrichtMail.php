<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\EventRegistration;
use App\Support\Testsystem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Nachricht an eine angemeldete Person (Rundmail aus dem Reiter
 * „Teilnehmende“ oder Erinnerung vor der Veranstaltung). Je Person eine Mail,
 * Antworten gehen an die Kontaktadresse der Veranstaltung.
 */
class TeilnehmendeNachrichtMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public EventRegistration $registration, public string $betreff, public string $text) {}

    public function envelope(): Envelope
    {
        $event = $this->registration->event;

        return new Envelope(
            subject: $this->betreff,
            replyTo: filled($event->contact_email) ? [new Address($event->contact_email, $event->contact_name)] : [],
        );
    }

    /** Vorab extern freigegebene Veranstaltung: auch auf dem Testsystem echt zustellen. */
    public function headers(): Headers
    {
        return new Headers(text: $this->registration->event->extern_freigegeben ? [TestRecipientFilter::EXTERN => '1'] : []);
    }

    public function content(): Content
    {
        $event = $this->registration->event;

        return new Content(markdown: 'mail.teilnehmende-nachricht', with: [
            'event' => $event,
            'name' => $this->registration->first_name,
            'text' => $this->text,
            'url' => route('events.show', $event->slug),
            // Vor dem Livegang (vorab freigegeben, Testsystem) ist „Meine Anmeldungen“ nicht öffentlich.
            'meine' => ! ($event->extern_freigegeben && Testsystem::aktiv()),
        ]);
    }
}
