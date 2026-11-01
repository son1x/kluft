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

/** Bestätigungslink für „Meine Daten aktualisieren“. */
class DatenmeldungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public string $mitglied, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätige die aktualisierten Daten – '.config('dpsg.site.short_name', config('dpsg.site.name')));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.datenmeldung');
    }
}
