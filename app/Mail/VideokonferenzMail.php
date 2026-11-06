<?php

namespace App\Mail;

use App\Mail\Concerns\SentViaQueue;
use App\Models\Videokonferenz;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Einladung zu einer Videokonferenz (docs/videokonferenzen.md), je Person
 * eine Mail. Für die Moderation mit Moderator-Link; der Token entsteht erst
 * beim Versand und liegt nicht in der Warteschlange. Mit Kalendereintrag.
 */
class VideokonferenzMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue, SerializesModels;

    public function __construct(public Videokonferenz $konferenz, public bool $moderation = false) {}

    public function envelope(): Envelope
    {
        $absender = $this->konferenz->user;

        return new Envelope(
            subject: Str::limit(($this->moderation ? 'Moderation: ' : 'Einladung: ').$this->konferenz->titel, 150),
            replyTo: $absender?->email ? [new Address($absender->email, $absender->name)] : [],
        );
    }

    public function content(): Content
    {
        $beginn = $this->konferenz->beginn->copy()->locale('de');

        return new Content(markdown: 'mail.videokonferenz', with: [
            'k' => $this->konferenz,
            'moderation' => $this->moderation,
            'wann' => $beginn->isoFormat('dddd, D. MMMM YYYY, HH:mm').' Uhr',
            'teilnahme' => $this->konferenz->teilnahmeLink(),
            'moderatorLink' => $this->moderation ? $this->konferenz->moderatorLink() : null,
            'gueltigBis' => $this->konferenz->moderatorAblauf()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm').' Uhr',
            'absender' => $this->konferenz->user?->name,
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->konferenz->ics(), 'videokonferenz.ics')->withMime('text/calendar; charset=utf-8'),
        ];
    }
}
