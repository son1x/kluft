<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\PushesViaNtfy;
use App\Notifications\Messages\NtfyMessage;
use App\Support\PushEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Geht an alle Verwaltung/Admin-Nutzer, wenn sich ein M365-Konto ohne
 * vorherige Rollenzuweisung anmeldet (s. Pflichtenheft 5.1). Das Konto
 * selbst hat bis zur Zuweisung keinerlei Zugriff.
 */
class NewPendingUserNotification extends Notification
{
    use PushesViaNtfy, Queueable;

    public function __construct(public User $pendingUser) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, PushEvents::BENUTZER);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Neues Konto wartet auf Freischaltung: '.$this->pendingUser->name)
            ->line(sprintf(
                '%s (%s) hat sich per Microsoft 365 angemeldet, hat aber noch keine Rolle im CMS.',
                $this->pendingUser->name,
                $this->pendingUser->email,
            ))
            ->action('Rolle zuweisen', url('/admin/users/'.$this->pendingUser->id.'/edit'))
            ->line('Ohne Rollenzuweisung hat das Konto keinen Zugriff.');
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return new NtfyMessage(
            title: 'Neues Konto wartet auf Freischaltung',
            message: 'Ohne Rolle hat das Konto keinen Zugriff. Im Backend zuweisen.',
            click: url('/admin/users/'.$this->pendingUser->id.'/edit'),
            tags: ['bust_in_silhouette'],
        );
    }
}
