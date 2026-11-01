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

/** Erinnerung: Juleica läuft bald ab bzw. ist abgelaufen (ZU-06). */
class JuleicaErinnerungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public string $name, public string $bis, public bool $abgelaufen, public bool $anVerwaltung) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->abgelaufen ? 'Juleica abgelaufen' : 'Juleica läuft bald ab').($this->anVerwaltung ? ': '.$this->name : ''));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.juleica-erinnerung');
    }
}
