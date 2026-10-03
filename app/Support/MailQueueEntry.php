<?php

namespace App\Support;

use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lesbare Angaben zu einem Eintrag der Mail-Warteschlange (jobs bzw.
 * failed_jobs): welche Mail, an wen. Die Nutzlast ist verschlüsselt
 * (ShouldBeEncrypted) und wird nur für die Anzeige im Backend entschlüsselt.
 */
class MailQueueEntry
{
    public const LABELS = [
        'App\Mail\EventRegistrationMail' => 'Veranstaltungsanmeldung',
        'App\Mail\ContactMessageMail' => 'Kontaktformular',
        'App\Mail\RegistrationDocumentMail' => 'Dokument zur Anmeldung',
    ];

    /**
     * @return array{label: string, recipient: ?string}
     */
    public static function describe(string $payload): array
    {
        $data = json_decode($payload, true) ?: [];
        $class = (string) ($data['displayName'] ?? '');
        $recipient = null;

        try {
            $command = (string) ($data['data']['command'] ?? '');
            if (! str_starts_with($command, 'O:')) {
                $command = (string) Crypt::decrypt($command);
            }
            $job = unserialize($command, ['allowed_classes' => true]);
            if ($job instanceof SendQueuedMailable) {
                $class = $job->mailable::class;
                $recipient = $job->mailable->to[0]['address'] ?? null;
            }
        } catch (Throwable) {
            // z. B. Anmeldung inzwischen gelöscht – dann nur die Art anzeigen.
        }

        return [
            'label' => self::LABELS[$class] ?? Str::afterLast($class, '\\'),
            'recipient' => $recipient,
        ];
    }

    /**
     * Erste Zeile der Fehlermeldung, ohne Stacktrace.
     */
    public static function shortError(string $exception): string
    {
        return Str::limit(trim(strtok($exception, "\n") ?: ''), 220);
    }
}
