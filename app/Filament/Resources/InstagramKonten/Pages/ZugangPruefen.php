<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Models\InstagramKonto;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Neues Token vor dem Speichern bei Instagram prüfen und Benutzername,
 * Konto-ID und Ablauf übernehmen. Mit Haken „Zum Teilen verwenden“ muss das
 * Token (neu oder gespeichert) auch veröffentlichen dürfen.
 */
trait ZugangPruefen
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function zugangPruefen(array $data, ?InstagramKonto $konto = null): array
    {
        $client = app(InstagramClient::class);
        if (filled($data['access_token'] ?? null)) {
            $data['access_token'] = trim((string) $data['access_token']);
            try {
                $ich = $client->me($data['access_token']);
            } catch (InstagramException $e) {
                Notification::make()->title('Token nicht gültig')->body($e->getMessage())->danger()->send();

                throw new Halt;
            }
            $data += ['benutzername' => $ich['username'] ?: null, 'ig_user_id' => $ich['user_id'], 'token_ablauf' => now()->addDays(60), 'token_erneuert' => now(), 'fehler' => null];
        }

        $token = $data['access_token'] ?? $konto?->access_token;
        $userId = $data['ig_user_id'] ?? $konto?->ig_user_id;
        $neuZumTeilen = ! empty($data['teilen']) && ($konto === null || ! $konto->teilen || filled($data['access_token'] ?? null));
        if ($neuZumTeilen && (blank($token) || blank($userId) || ! $client->darfVeroeffentlichen((string) $token, (string) $userId))) {
            Notification::make()->title('Dieses Token darf nicht veröffentlichen')
                ->body('Zum Teilen braucht das Token die Berechtigung „instagram_business_content_publish“. Token in der Meta-App mit dieser Berechtigung neu erzeugen oder den Haken „Zum Teilen verwenden“ weglassen.')
                ->danger()->send();

            throw new Halt;
        }

        return $data;
    }
}
