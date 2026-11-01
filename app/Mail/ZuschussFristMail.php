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

/** Erinnerung an die Antragsfrist eines Zuschussgebers (Phase 4). */
class ZuschussFristMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public string $veranstaltung, public string $geber, public string $frist, public int $tage, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zuschussantrag: Frist '.$this->frist.' – '.$this->geber);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.zuschuss-frist');
    }
}
