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
