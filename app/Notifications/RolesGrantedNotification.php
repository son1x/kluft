<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Models\User;
use App\Notifications\Concerns\PushesViaNtfy;
use App\Notifications\Messages\NtfyMessage;
use App\Support\Features;
use App\Support\PushEvents;
use App\Support\RoleCatalog;
use App\Support\Site;
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
    use PushesViaNtfy, Queueable, SentViaQueue;

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
        return $this->withPush($notifiable, PushEvents::RECHTE);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->grantedBy ?? Site::office('nom', capitalize: true);
        $site = 'der '.config('dpsg.site.short_name');
        $domain = config('dpsg.site.domain');
        $name = $notifiable instanceof User && ! $notifiable->isRolle() ? $notifiable->name : '';
        $email = $notifiable instanceof User ? $notifiable->email : '';
        $perCode = $notifiable instanceof User && $notifiable->login === 'email' && Features::enabled(Features::CODE_LOGIN);
        $rolle = $notifiable instanceof User && $notifiable->isRolle() ? $notifiable->name : null;

        $mail = (new MailMessage)
            ->subject($this->newAccount
                ? 'Dein Zugang zur Website '.$site.' ist eingerichtet'
                : 'Neue Rechte auf der Website '.$site)
            ->greeting(trim('Hallo '.$name).',')
            ->line($this->newAccount
                ? $who.' hat dir einen Zugang zum Redaktionsbereich der Website '.$domain.' eingerichtet, mit diesen Rechten:'
                : $who.' hat dir im Redaktionsbereich der Website '.$domain.' neue Rechte gegeben:');

        foreach ($this->assignments as $key) {
            $mail->line('• '.RoleCatalog::describe($key));
        }

        return $mail
            ->action('Zum Redaktionsbereich', url('/admin'))
            ->line($perCode
                ? 'Zum Anmelden gibst du die Adresse '.$email.' ein und bekommst einen Code per E-Mail; ein Passwort gibt es nicht.'
                    .($rolle !== null ? ' Der Zugang „'.$rolle.'“ gehört zu diesem Postfach: Wer es hat, kann sich anmelden.' : '')
                : 'Du meldest dich mit deinem Microsoft-365-Konto ('.$email.') an; ein eigenes Passwort gibt es nicht.')
            ->line('Hast du damit nicht gerechnet? Dann melde dich bitte '.Site::office('bei').'.');
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return new NtfyMessage(
            title: 'Neue Rechte im Redaktionsbereich',
            message: implode(', ', array_map(fn (string $key) => RoleCatalog::all()[$key] ?? $key, $this->assignments)),
            click: url('/admin'),
            tags: ['key'],
        );
    }
}
