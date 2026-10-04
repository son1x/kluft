<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Abonnent:in des WhatsApp-Newsletters: nur verschlüsselte Nummer und
 * Einwilligungsnachweis – kein Name, kein Profilbild, keine Inhalte.
 *
 * @property string $phone
 * @property string $phone_hash
 * @property string $status
 * @property string $source
 * @property string $consent_version
 * @property Carbon|null $requested_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $last_inbound_at
 * @property int $failed_streak
 */
class WhatsAppSubscriber extends Model
{
    protected $table = 'whatsapp_subscribers';

    protected $fillable = ['phone', 'phone_hash', 'status', 'source', 'consent_version', 'requested_at', 'confirmed_at', 'last_inbound_at', 'failed_streak'];

    protected $hidden = ['phone'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'failed_streak' => 'integer',
        ];
    }

    /** Nicht umkehrbarer Prüfwert der Nummer (Suche, Sperrliste). */
    public static function hash(string $e164): string
    {
        return hash_hmac('sha256', $e164, 'whatsapp|'.config('app.key'));
    }

    public static function findByPhone(string $e164): ?self
    {
        return static::query()->where('phone_hash', static::hash($e164))->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function masked(): string
    {
        return PhoneNumber::mask($this->phone);
    }

    /** Im 24-Stunden-Servicefenster sind freie Antworten kostenlos erlaubt. */
    public function inServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }
}
