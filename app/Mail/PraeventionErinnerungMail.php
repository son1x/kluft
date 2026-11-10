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

/**
 * Erinnerung an ablaufende Präventions-Nachweise: an die Person (ein
 * Eintrag) oder als Übersicht an den Vorstand.
 */
class PraeventionErinnerungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    /**
     * @param  list<array{name: string, art: string, bezeichnung: ?string, bis: string, tage: int}>  $eintraege
     */
    public function __construct(public array $eintraege, public bool $uebersicht = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->uebersicht
            ? 'Prävention: '.count($this->eintraege).' Nachweise laufen bald ab'
            : 'Dein Nachweis „'.$this->eintraege[0]['art'].'“ läuft bald ab');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.praevention-erinnerung');
    }
}
