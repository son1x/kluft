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
 * Sicherheitshinweis an die Technik-Admins, wenn jemand weitreichende
 * Rechte (Verwaltung, Admin, Super-Admin) bekommt.
 */
class PrivilegedRoleGrantedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue;

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
        return ['mail'];
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
}
