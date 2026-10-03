<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alarm an die Admins, wenn das Monitoring eine Störung findet. Bewusst
 * nicht über die Warteschlange – die könnte ja selbst die Störung sein.
 */
class SystemProblemNotification extends Notification
{
    /**
     * @param  list<array{label: string, message: string}>  $problems
     */
    public function __construct(public array $problems) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Störung auf '.parse_url((string) config('app.url'), PHP_URL_HOST))
            ->line('Das Monitoring der Website hat eine Störung gefunden:');
        foreach ($this->problems as $problem) {
            $mail->line('• '.$problem['label'].': '.$problem['message']);
        }

        return $mail
            ->line('Diese Mail kommt höchstens alle sechs Stunden je Störung.')
            ->action('Zur Statusseite', route('status'));
    }
}
