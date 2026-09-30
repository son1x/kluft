<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alarmmail bei jeder Notfall-Login-Anmeldung (Pflichtenheft 5.2).
 */
class BreakGlassLoginNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $loggedInUser,
        public string $ipTruncated,
    ) {}

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
            ->subject('⚠ Notfall-Login verwendet: '.$this->loggedInUser->name)
            ->line('Soeben hat sich jemand über den Notfall-Zugang (/notfall-login) angemeldet.')
            ->line('Account: '.$this->loggedInUser->email)
            ->line('Zeitpunkt: '.now()->format('d.m.Y H:i').' Uhr')
            ->line('IP (gekürzt): '.$this->ipTruncated)
            ->line('Falls das nicht erwartet war, sofort das Passwort und TOTP-Secret des Notfall-Accounts zurücksetzen und den Vorfall im Audit-Log prüfen.');
    }
}
