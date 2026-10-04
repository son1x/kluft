<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Notifications\Concerns\PushesViaNtfy;
use App\Notifications\Messages\NtfyMessage;
use App\Support\PushEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweise aus dem WhatsApp-Newsletter an das Team (Aussendung wartet auf
 * Freigabe, Vorlage abgelehnt, Qualität gesunken, Versand angehalten).
 * Ohne Telefonnummern.
 */
class WhatsAppNotice extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use PushesViaNtfy, Queueable, SentViaQueue;

    public function __construct(public string $title, public string $body, public string $link, public bool $urgent = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, PushEvents::WHATSAPP);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('WhatsApp-Newsletter: '.$this->title)
            ->line($this->body)
            ->action('Im Backend ansehen', $this->link);
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return new NtfyMessage(
            title: 'WhatsApp: '.$this->title,
            message: $this->body,
            click: $this->link,
            tags: [$this->urgent ? 'warning' : 'speech_balloon'],
            priority: $this->urgent ? 4 : 3,
        );
    }
}
