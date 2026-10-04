<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sperrliste nach Abmeldung: nur der Prüfwert der Nummer, nach 12 Monaten
 * gelöscht (whatsapp:prune).
 *
 * @property string $phone_hash
 * @property string $reason
 * @property Carbon|null $created_at
 */
class WhatsAppSuppression extends Model
{
    protected $table = 'whatsapp_suppressions';

    protected $primaryKey = 'phone_hash';

    protected $keyType = 'string';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = ['phone_hash', 'reason'];

    public static function isSuppressed(string $e164): bool
    {
        return static::query()->whereKey(WhatsAppSubscriber::hash($e164))->exists();
    }

    public static function add(string $e164, string $reason): void
    {
        static::query()->updateOrCreate(['phone_hash' => WhatsAppSubscriber::hash($e164)], ['reason' => $reason]);
    }
}
