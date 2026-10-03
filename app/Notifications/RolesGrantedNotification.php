<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Geht an die Person, die ein Konto oder neue Rechte im Backend bekommen
 * hat (eine Mail je Speichern, alle neuen Rollen zusammen).
 */
class RolesGrantedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue;

    /**
     * @param  list<string>  $assignments  Schlüssel „rolle|scope_type|scope_key“
     */
    public function __construct(
        public array $assignments,
        public bool $newAccount,
        public ?string $grantedBy,
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
        $who = $this->grantedBy ?? 'Das Diözesanbüro';
        $name = $notifiable instanceof User ? $notifiable->name : '';
        $email = $notifiable instanceof User ? $notifiable->email : '';

        $mail = (new MailMessage)
            ->subject($this->newAccount
                ? 'Dein Zugang zur Website der DPSG Trier ist eingerichtet'
                : 'Neue Rechte auf der Website der DPSG Trier')
            ->greeting(trim('Hallo '.$name).',')
            ->line($this->newAccount
                ? $who.' hat dir einen Zugang zum Redaktionsbereich der Website dpsg-trier.de eingerichtet, mit diesen Rechten:'
                : $who.' hat dir im Redaktionsbereich der Website dpsg-trier.de neue Rechte gegeben:');

        foreach ($this->assignments as $key) {
            $mail->line('• '.RoleCatalog::describe($key));
        }

        return $mail
            ->action('Zum Redaktionsbereich', url('/admin'))
            ->line('Du meldest dich mit deinem Microsoft-365-Konto ('.$email.') an; ein eigenes Passwort gibt es nicht.')
            ->line('Hast du damit nicht gerechnet? Dann melde dich bitte beim Diözesanbüro.');
    }
}
