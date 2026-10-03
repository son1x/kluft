<?php

namespace App\Mail;

use App\Models\RegistrationDocument;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Hinweis auf ein neues Dokument (Rechnung, Bescheinigung). Die Datei selbst
 * hängt nicht an – sie liegt unter „Meine Anmeldungen“.
 */
class RegistrationDocumentMail extends Mailable
{
    public function __construct(public RegistrationDocument $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neu für dich: '.$this->document->label().' – '.$this->document->registration->event->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.registration-document', with: [
            'registration' => $this->document->registration,
            'label' => $this->document->label(),
            'url' => route('participants.index'),
        ]);
    }
}
