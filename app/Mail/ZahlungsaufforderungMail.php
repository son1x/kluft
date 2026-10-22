<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Services\Zahlungen\Zahlungsaufforderung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/** Zahlungsaufforderung mit Kontodaten und GiroCode (docs/zahlungen.md). */
class ZahlungsaufforderungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(
        public string $name,
        public string $zweck,
        public Zahlungsaufforderung $aufforderung,
        public ?string $faellig = null,
        public ?string $hinweis = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Str::limit('Zahlung: '.$this->zweck, 150));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.zahlungsaufforderung');
    }
}
