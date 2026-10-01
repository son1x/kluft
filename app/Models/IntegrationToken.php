<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zugangstoken externer Dienste (8.2 `integration_tokens`), verschlüsselt
 * gespeichert und nie an den Browser ausgeliefert.
 */
class IntegrationToken extends Model
{
    protected $fillable = [
        'provider', 'account_id', 'account_name', 'access_token', 'expires_at', 'refreshed_at', 'last_error',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }

    public static function for(string $provider): ?self
    {
        return static::query()->where('provider', $provider)->first();
    }
}
