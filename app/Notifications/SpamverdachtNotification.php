<?php

namespace App\Notifications;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\Messages\NtfyMessage;
use App\Support\MailEvents;
use App\Support\PushEvents;
use App\Support\Spamverdacht;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Täglicher Sammelhinweis: Nachrichten mit Spamverdacht warten im
 * Nachrichten-Eingang (docs/nachrichten.md). Per Mail und/oder Push, wie es
 * die Person gewählt hat. Bewusst ohne Inhalt, Namen oder Adressen; der
 * Push-Dienst ist ein Dritter.
 */
class SpamverdachtNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $neu,
        public int $gesamt,
        public ?Carbon $aeltesteLoeschung = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }
        $kanaele = [];
        if ($notifiable->wantsMail(MailEvents::SPAMVERDACHT)) {
            $kanaele[] = 'mail';
        }
        if ($notifiable->wantsPush(PushEvents::SPAMVERDACHT)) {
            $kanaele[] = NtfyChannel::class;
        }

        return $kanaele;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titel())
            ->line($this->text())
            ->line('Diese Nachrichten wurden nicht an die Empfänger*innen gemailt. Bitte kurz durchsehen: Echte Nachrichten mit „Kein Spam – zustellen“ weitergeben, Spam einfach liegen lassen.');
        if ($this->aeltesteLoeschung !== null) {
            $mail->line('Die älteste wird am '.$this->aeltesteLoeschung->format('d.m.Y').' automatisch gelöscht.');
        }

        return $mail
            ->action('Zum Nachrichten-Eingang', self::url())
            ->line('Diesen Hinweis stellst du im Nachrichten-Eingang unter „Benachrichtigung“ ab oder auf Push um.');
    }

    public function toNtfy(object $notifiable): NtfyMessage
    {
        return new NtfyMessage(
            title: $this->titel(),
            message: $this->text().' Bitte im Nachrichten-Eingang durchsehen.',
            click: self::url(),
            tags: ['warning'],
        );
    }

    private function titel(): string
    {
        return $this->neu === 1 ? '1 neue Nachricht mit Spamverdacht' : $this->neu.' neue Nachrichten mit Spamverdacht';
    }

    private function text(): string
    {
        return $this->gesamt === 1
            ? 'Im Nachrichten-Eingang wartet 1 Nachricht mit Spamverdacht.'
            : 'Im Nachrichten-Eingang warten '.$this->gesamt.' Nachrichten mit Spamverdacht (zurückgehalten, gelöscht nach '.Spamverdacht::TAGE.' Tagen).';
    }

    /** Nachrichten-Eingang, gefiltert auf Spamverdacht. */
    public static function url(): string
    {
        return ContactMessageResource::getUrl('index', ['tableFilters' => ['status' => ['values' => ['spam']]]], panel: 'admin');
    }
}
