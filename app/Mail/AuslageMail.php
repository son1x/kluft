<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\Auslage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Auslagen: „eingereicht“ an die Kasse, sonst Status an die Person.
 */
class AuslageMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public Auslage $auslage, public string $art) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->art) {
            'eingereicht' => 'Neue Auslage zum Prüfen: '.$this->auslage->name,
            'freigegeben' => 'Deine Auslage ist freigegeben',
            'abgelehnt' => 'Deine Auslage wurde abgelehnt',
            default => 'Deine Auslage ist überwiesen',
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.auslage');
    }
}
