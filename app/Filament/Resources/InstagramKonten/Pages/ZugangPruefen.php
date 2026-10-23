<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Neues Token vor dem Speichern bei Instagram prüfen und Benutzername,
 * Konto-ID und Ablauf übernehmen.
 */
trait ZugangPruefen
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function zugangPruefen(array $data): array
    {
        if (blank($data['access_token'] ?? null)) {
            return $data;
        }
        $data['access_token'] = trim((string) $data['access_token']);
        try {
            $konto = app(InstagramClient::class)->me($data['access_token']);
        } catch (InstagramException $e) {
            Notification::make()->title('Token nicht gültig')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $data + ['benutzername' => $konto['username'] ?: null, 'ig_user_id' => $konto['user_id'], 'token_ablauf' => now()->addDays(60), 'token_erneuert' => now(), 'fehler' => null];
    }
}
