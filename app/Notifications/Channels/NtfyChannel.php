<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Messages\NtfyMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Versand an ntfy (https://ntfy.sh oder eigener Server, NTFY_SERVER).
 * Veröffentlicht per JSON an das geheime Thema der Person.
 *
 * Push ist eine Ergänzung: Fehler werden protokolliert, aber nie
 * weitergereicht – Mails und Anmeldungen laufen davon unabhängig.
 */
class NtfyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || blank($notifiable->ntfy_topic) || ! method_exists($notification, 'toNtfy')) {
            return;
        }

        /** @var NtfyMessage $message */
        $message = $notification->toNtfy($notifiable);
        self::publish((string) $notifiable->ntfy_topic, $message);
    }

    /**
     * Präfix neuer Themen aus der Domain der Website, ohne Subdomain und
     * Endung: test.dpsg-trier.de und dpsg-trier.de ergeben „dpsg-trier-“.
     * So gilt eine Zugriffsregel auf dem eigenen ntfy-Server (`dpsg-trier-*`)
     * für Test und Live. NTFY_PREFIX überschreibt.
     */
    public static function praefix(): string
    {
        $praefix = (string) (config('services.ntfy.prefix') ?: '');
        if ($praefix === '') {
            $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            $teile = array_values(array_filter(explode('.', $host)));
            $praefix = count($teile) >= 2 ? $teile[count($teile) - 2] : ($teile[0] ?? '');
        }
        $praefix = trim((string) preg_replace('/[^a-z0-9-]+/', '-', strtolower($praefix)), '-');

        return ($praefix === '' || $praefix === 'localhost' ? 'kluft' : substr($praefix, 0, 30)).'-';
    }

    public static function publish(string $topic, NtfyMessage $message): bool
    {
        $request = Http::acceptJson()->timeout(5);
        if ($token = config('services.ntfy.token')) {
            $request = $request->withToken((string) $token);
        }

        try {
            $response = $request->post(rtrim((string) config('services.ntfy.server'), '/'), array_filter([
                'topic' => $topic,
                'title' => $message->title,
                'message' => $message->message,
                'click' => $message->click,
                'tags' => $message->tags ?: null,
                'priority' => $message->priority,
            ], fn ($value) => $value !== null));
        } catch (ConnectionException $e) {
            Log::warning('ntfy nicht erreichbar', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('ntfy lehnt Nachricht ab', ['status' => $response->status()]);

            return false;
        }

        return true;
    }
}
