<?php

namespace App\Services\WhatsApp;

use App\Models\IntegrationToken;
use Illuminate\Support\Str;

/**
 * Zugangsdaten für die WhatsApp Cloud API, verschlüsselt in
 * integration_tokens (Provider „whatsapp“): Token des Systembenutzers,
 * Phone-Number-ID, WABA-ID, App-Secret und Verify-Token.
 */
class WhatsAppSettings
{
    public const PROVIDER = 'whatsapp';

    public function record(): ?IntegrationToken
    {
        return IntegrationToken::for(self::PROVIDER);
    }

    public function configured(): bool
    {
        $record = $this->record();

        return $record !== null && filled($record->access_token) && filled($record->account_id);
    }

    public function token(): ?string
    {
        return $this->record()?->access_token;
    }

    public function phoneNumberId(): ?string
    {
        return $this->record()?->account_id;
    }

    public function get(string $key): ?string
    {
        $value = $this->record()?->settings[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * @param  array{token?: string|null, phone_number_id?: string|null, waba_id?: string|null, app_secret?: string|null}  $data  leere Werte lassen Bestehendes unverändert
     */
    public function save(array $data): IntegrationToken
    {
        $record = $this->record() ?? new IntegrationToken(['provider' => self::PROVIDER]);
        $settings = $record->settings ?? [];

        if (filled($data['token'] ?? null)) {
            $record->access_token = trim((string) $data['token']);
        }
        if (filled($data['phone_number_id'] ?? null)) {
            $record->account_id = trim((string) $data['phone_number_id']);
        }
        foreach (['waba_id', 'app_secret'] as $key) {
            if (filled($data[$key] ?? null)) {
                $settings[$key] = trim((string) $data[$key]);
            }
        }
        $settings['verify_token'] ??= Str::random(40);
        $record->settings = $settings;
        $record->last_error = null;
        $record->save();

        return $record;
    }

    public function regenerateVerifyToken(): string
    {
        $record = $this->record() ?? $this->save([]);
        $settings = $record->settings ?? [];
        $settings['verify_token'] = Str::random(40);
        $record->settings = $settings;
        $record->save();

        return $settings['verify_token'];
    }

    public function forget(): void
    {
        $this->record()?->delete();
    }
}
