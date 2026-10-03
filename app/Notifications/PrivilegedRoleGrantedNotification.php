<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Models\User;
use App\Notifications\Concerns\PushesViaNtfy;
use App\Notifications\Messages\NtfyMessage;
use App\Support\PushEvents;
use App\Support\RoleCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sicherheitshinweis an die Technik-Admins, wenn jemand weitreichende
 * Rechte (Verwaltung, Admin, Super-Admin) bekommt.
 */
class PrivilegedRoleGrantedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use PushesViaNtfy, Queueable, SentViaQueue;

    /**
     * @param  list<string>  $assignments
     */
    public function __construct(
        public User $target,
        public array $assignments,
        public ?string $grantedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush($notifiable, PushEvents::BENUTZER);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Hinweis: weitreichende Rechte für '.$this->target->name)
            ->line(sprintf('%s hat %s (%s) diese Rechte gegeben:', $this->grantedBy ?? 'Jemand', $this->target->name, $this->target->email));

        foreach ($this->assignments as $key) {
            $mail->line('• '.(RoleCatalog::all()[$key] ?? $key));
        }

        return $mail
            ->action('Konto ansehen', url('/admin/users/'.$this->target->id.'/edit'))
            ->line('Nur zur Information. War das nicht abgesprochen, bitte die Rolle prüfen; jede Änderung steht im Audit-Log.');
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        // Ohne Namen: Push-Dienste sind Dritte.
        return new NtfyMessage(
            title: 'Weitreichende Rechte vergeben',
            message: implode(', ', array_map(fn (string $key) => RoleCatalog::all()[$key] ?? $key, $this->assignments)).' – bitte im Backend prüfen.',
            click: url('/admin/users/'.$this->target->id.'/edit'),
            tags: ['warning'],
            priority: 4,
        );
    }
}
