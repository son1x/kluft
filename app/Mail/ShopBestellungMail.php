<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\Shop\Bestellung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mails der Sammelbestellung: Bestätigungslink (doi), Übersicht nach der
 * Bestätigung (bestaetigt) und Hinweis an die Sammelbestellerin, wenn freie
 * Artikel zu prüfen sind (pruefen). Die Zahlungsaufforderung schickt der
 * Baustein „Zahlungen“.
 */
class ShopBestellungMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public const BETREFF = [
        'doi' => 'Bitte bestätige deine Bestellung',
        'bestaetigt' => 'Deine Bestellung ist eingegangen',
        'pruefen' => 'Sammelbestellung: freie Artikel prüfen',
        'storno' => 'Deine Bestellung ist storniert',
        'abholbereit' => 'Deine Bestellung ist da',
    ];

    public function __construct(public Bestellung $bestellung, public string $art, public ?string $token = null, public ?string $hinweis = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::BETREFF[$this->art].' – '.config('dpsg.site.short_name', config('dpsg.site.name')));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shop-bestellung', with: [
            'bestellung' => $this->bestellung->loadMissing('positionen', 'runde'),
            'art' => $this->art,
            'hinweis' => $this->hinweis,
            'link' => $this->token ? route('shop.show', $this->token) : null,
        ]);
    }
}
