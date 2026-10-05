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
 * Hinweis, dass die Teilnahmebescheinigung freigegeben ist. Das PDF hängt
 * nicht an – es liegt unter „Meine Anmeldungen“.
 */
class TeilnahmebescheinigungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public EventRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deine Teilnahmebescheinigung – '.$this->registration->event->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-document', with: [
            'registration' => $this->registration,
            'label' => 'Teilnahmebescheinigung',
            'url' => route('participants.index'),
        ]);
    }
}
