<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Vorwarnung bzw. Hinweis: Backend-Konto wird mangels Anmeldung gesperrt. */
class KontoInaktivMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public string $name, public bool $gesperrt, public string $datum) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->gesperrt ? 'Dein Zugang wurde gesperrt' : 'Dein Zugang wird bald gesperrt');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.konto-inaktiv');
    }
}
