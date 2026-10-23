<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Gespiegelter Instagram-Beitrag: Bild liegt lokal, der Link führt zu Instagram.
 *
 * @property int $id
 * @property string $media_id
 * @property string $art
 * @property string $permalink
 * @property ?string $text
 * @property string $bild
 * @property Carbon $veroeffentlicht_am
 */
class InstagramBeitrag extends Model
{
    protected $table = 'instagram_beitraege';

    protected $fillable = ['instagram_konto_id', 'media_id', 'art', 'permalink', 'text', 'bild', 'veroeffentlicht_am'];

    protected function casts(): array
    {
        return ['veroeffentlicht_am' => 'datetime'];
    }

    /**
     * @return BelongsTo<InstagramKonto, $this>
     */
    public function konto(): BelongsTo
    {
        return $this->belongsTo(InstagramKonto::class, 'instagram_konto_id');
    }

    public function bildUrl(): string
    {
        return Storage::disk('public')->url($this->bild);
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $beitrag) => Storage::disk('public')->delete($beitrag->bild));
    }
}
