<?php

namespace App\Mail;

use App\Support\ParticipantLogin;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Einmalcode für „Meine Anmeldungen“.
 */
class ParticipantCodeMail extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' ist dein Code für „Meine Anmeldungen“');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.participant-code', with: [
            'minutes' => ParticipantLogin::CODE_MINUTES,
            'url' => route('participants.index'),
        ]);
    }
}
