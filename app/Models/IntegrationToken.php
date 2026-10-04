<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Zugangstoken externer Dienste (8.2 `integration_tokens`), verschlüsselt
 * gespeichert und nie an den Browser ausgeliefert.
 *
 * @property Carbon|null $expires_at
 * @property Carbon|null $refreshed_at
 * @property array<string, mixed>|null $settings
 */
class IntegrationToken extends Model
{
    protected $fillable = [
        'provider', 'account_id', 'account_name', 'access_token', 'settings', 'expires_at', 'refreshed_at', 'last_error',
    ];

    protected $hidden = ['access_token', 'settings'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'settings' => 'encrypted:array',
            'expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public static function for(string $provider): ?self
    {
        return static::query()->where('provider', $provider)->first();
    }
}
