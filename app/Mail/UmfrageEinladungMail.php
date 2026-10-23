<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\EventRegistration;
use App\Models\Umfrage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Einladung zu einer Umfrage an eine angemeldete Person, mit persönlichem
 * Link (Token steht nur in der Mail, gespeichert ist sein Hash).
 */
class UmfrageEinladungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public Umfrage $umfrage, public EventRegistration $registration, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Str::limit('Umfrage: '.$this->umfrage->titel, 150));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.umfrage-einladung', with: [
            'umfrage' => $this->umfrage,
            'name' => $this->registration->first_name,
            'veranstaltung' => $this->registration->event?->title,
            'url' => route('umfragen.zeigen', ['code' => $this->umfrage->code, 't' => $this->token]),
        ]);
    }
}
