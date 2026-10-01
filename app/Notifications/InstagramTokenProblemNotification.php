<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis an die Admins, wenn das Instagram-Token nicht erneuert werden
 * konnte (F-IG-04).
 */
class InstagramTokenProblemNotification extends Notification
{
    use Queueable;

    public function __construct(public string $error, public ?string $expiresAt) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Instagram-Token konnte nicht erneuert werden')
            ->line('Die automatische Erneuerung des Instagram-Zugangs ist fehlgeschlagen.')
            ->line('Fehler: '.$this->error)
            ->line('Das aktuelle Token gilt voraussichtlich bis: '.($this->expiresAt ?? 'unbekannt'))
            ->line('Danach können keine Beiträge mehr auf Instagram geteilt werden. Bitte in der Meta-App ein neues Long-Lived-Token erzeugen und im Backend unter „Integrationen“ eintragen.')
            ->action('Zu den Integrationen', url('/admin/integrations'));
    }
}
