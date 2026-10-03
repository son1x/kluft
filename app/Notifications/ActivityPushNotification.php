<?php

namespace App\Notifications;

use App\Mail\Concerns\SentViaQueue;
use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Reine Push-Nachricht (ntfy) zu Anmeldungen oder Beiträgen. Läuft über
 * die Warteschlange, damit eine Anmeldung nie an einem langsamen
 * Push-Dienst hängt. Enthält nur Texte ohne Personenbezug.
 */
class ActivityPushNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SentViaQueue;

    public function __construct(
        public string $event,
        public NtfyMessage $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && $notifiable->wantsPush($this->event) ? [NtfyChannel::class] : [];
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return $this->message;
    }
}
