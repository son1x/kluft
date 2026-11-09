<?php

namespace App\Mail;

use App\Filament\Resources\Events\EventResource;
use App\Mail\Concerns\SentViaQueue;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Info an Sammeladresse bzw. verantwortliche Person (App\Support\AnmeldeInfos).
 * Datensparsam: Name, ggf. Stamm, Stand der Plätze und der Link ins Backend;
 * alle weiteren Angaben stehen nur dort.
 */
class AnmeldungInfoMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public const BETREFF = [
        'confirmed' => 'Neue Anmeldung',
        'waitlist' => 'Neue Anmeldung (Warteliste)',
        'promoted' => 'Nachgerückt',
        'cancelled' => 'Abmeldung',
    ];

    public function __construct(public EventRegistration $registration, public string $art) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::BETREFF[$this->art] ?? 'Anmeldung').': '.$this->registration->event->title);
    }

    /** Vorab extern freigegebene Veranstaltung: auch auf dem Testsystem echt zustellen. */
    public function headers(): Headers
    {
        return new Headers(text: $this->registration->event->extern_freigegeben ? [TestRecipientFilter::EXTERN => '1'] : []);
    }

    public function content(): Content
    {
        $event = $this->registration->event;
        $bestaetigt = $event->confirmedCount();
        $warteliste = $event->registrations()->where('status', 'waitlist')->count();

        return new Content(markdown: 'mail.anmeldung-info', with: [
            'event' => $event,
            'person' => $this->registration->fullName(),
            'stamm' => filled($this->registration->data['stamm'] ?? null) ? (string) $this->registration->data['stamm'] : null,
            'art' => $this->art,
            'stand' => ($event->capacity ? "{$bestaetigt} von {$event->capacity} Plätzen belegt" : "{$bestaetigt} bestätigte Anmeldungen")
                .($warteliste > 0 ? ", {$warteliste} auf der Warteliste" : ''),
            'url' => EventResource::getUrl('edit', ['record' => $event], panel: 'admin'),
        ]);
    }
}
