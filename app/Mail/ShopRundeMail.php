<?php

namespace App\Mail;

use App\Filament\Resources\Shop\RundeResource;
use App\Mail\Concerns\SentViaQueue;
use App\Models\Shop\Runde;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Hinweis an die Sammelbestellung: Bestelldatum erreicht, Runde noch offen (SB-22). */
class ShopRundeMail extends Mailable implements ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public Runde $runde) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sammelbestellung „'.$this->runde->name.'“: heute ist Bestelldatum');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.shop-runde', with: ['runde' => $this->runde, 'url' => RundeResource::getUrl('index', panel: 'admin')]);
    }
}
