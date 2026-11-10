<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\MaterialReservierung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Materialverleih: „anfrage“ an die Materialwart*innen; „zugesagt“,
 * „abgelehnt“ und „rueckgabe“ (Erinnerung) an die Person.
 */
class MaterialMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public MaterialReservierung $reservierung, public string $art, public ?string $grund = null) {}

    public function envelope(): Envelope
    {
        $name = $this->reservierung->material->name;

        return new Envelope(subject: match ($this->art) {
            'anfrage' => 'Materialanfrage: '.$name.' ('.$this->reservierung->zeitraum().')',
            'zugesagt' => 'Zusage: '.$name,
            'abgelehnt' => 'Leider keine Zusage: '.$name,
            default => 'Rückgabe morgen: '.$name,
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.material');
    }
}
