<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transaktionsmails der Veranstaltungsanmeldung. Datensparsam (F-MAIL-04):
 * nur Veranstaltungstitel, Name, Status und Links – keine Zusatzangaben.
 */
class EventRegistrationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public const SUBJECTS = [
        'doi' => 'Bitte bestätige deine Anmeldung',
        'confirmed' => 'Anmeldung bestätigt',
        'waitlist' => 'Du stehst auf der Warteliste',
        'promoted' => 'Du bist nachgerückt',
        'cancelled' => 'Anmeldung storniert',
    ];

    public function __construct(
        public EventRegistration $registration,
        public string $kind,
        public ?string $token = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::SUBJECTS[$this->kind].': '.$this->registration->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-registration',
            with: [
                'event' => $this->registration->event,
                'name' => $this->registration->first_name,
                'confirmUrl' => $this->kind === 'doi' && $this->token ? route('registrations.confirm', $this->token) : null,
                'cancelUrl' => $this->kind !== 'doi' && $this->token ? route('registrations.cancel', $this->token) : null,
            ],
        );
    }
}
