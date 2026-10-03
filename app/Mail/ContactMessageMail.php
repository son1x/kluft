<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Nachricht aus dem Kontaktformular bzw. Frage zu einer Veranstaltung
 * (F-CMS-09, F-MAIL-01). Wird nur verschickt, nicht gespeichert. Bewusst
 * als reiner Text: Eingaben der Absender*innen werden nie als HTML oder
 * Markdown dargestellt. „Antworten“ geht direkt an die Absender*in.
 */
class ContactMessageMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(
        public string $topic,
        public string $senderName,
        public string $senderEmail,
        public ?string $subjectLine,
        public string $body,
        public ?string $context = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = filled($this->subjectLine) ? $this->subjectLine : Str::limit(Str::squish($this->body), 50);

        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: Str::limit($this->topic.': '.$subject, 150),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message');
    }
}
