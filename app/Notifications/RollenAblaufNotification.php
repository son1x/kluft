<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Support\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Hinweis, dass Rollen bald ablaufen (30 und 7 Tage vorher): an die Person
 * selbst und gesammelt an die Verwaltung.
 */
class RollenAblaufNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue;

    /**
     * @param  list<array{person: string, rolle: string, bis: string}>  $eintraege
     */
    public function __construct(
        public array $eintraege,
        public bool $verwaltung = false,
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
        $site = config('dpsg.site.short_name');
        $mail = (new MailMessage)
            ->subject($this->verwaltung ? 'Rollen laufen bald ab' : 'Deine Rechte auf der Website laufen bald ab')
            ->greeting('Hallo,')
            ->line($this->verwaltung
                ? 'diese Rollen im Redaktionsbereich der Website '.$site.' laufen bald ab:'
                : 'diese Rechte im Redaktionsbereich der Website '.$site.' laufen bald ab:');

        foreach ($this->eintraege as $e) {
            $mail->line('• '.($this->verwaltung ? $e['person'].': ' : '').$e['rolle'].' – gültig bis '.$e['bis']);
        }

        return $mail
            ->line('Am Tag danach wird die Rolle automatisch entfernt.')
            ->line($this->verwaltung
                ? 'Soll es weitergehen, setzt in der Benutzerverwaltung ein neues Datum oder lasst das Feld leer.'
                : 'Machst du weiter? Dann melde dich bitte '.Site::office('bei').', damit die Rolle verlängert wird.')
            ->action('Zum Redaktionsbereich', url('/admin'));
    }
}
