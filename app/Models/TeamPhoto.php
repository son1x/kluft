<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gruppenfoto eines Gremiums mit markierten Personen.
 */
class TeamPhoto extends Model
{
    protected $fillable = ['gremium', 'media_id', 'title', 'note', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return HasMany<TeamPhotoSpot, $this>
     */
    public function spots(): HasMany
    {
        return $this->hasMany(TeamPhotoSpot::class)->orderBy('x');
    }

    /**
     * Bildunterschrift „von links: …“ aus den Markierungen (nur Personen,
     * deren Veröffentlichung abgestimmt ist).
     */
    public function caption(): ?string
    {
        $names = $this->spots->filter(fn (TeamPhotoSpot $s) => $s->person?->consent && $s->person->active)->map(fn (TeamPhotoSpot $s) => $s->person->name);

        return $names->isEmpty() ? null : 'Von links: '.$names->implode(', ').'.';
    }
}
