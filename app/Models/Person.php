<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Person in Diözesanleitung oder Diözesanbüro (Personenvorstellung).
 */
class Person extends Model
{
    protected $table = 'people';

    protected $fillable = [
        'gremium', 'subgroup', 'name', 'role', 'stage', 'email', 'bio',
        'photo_media_id', 'consent', 'active', 'sort',
    ];

    protected function casts(): array
    {
        return ['consent' => 'boolean', 'active' => 'boolean', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    /**
     * Öffentlich: aktiv und Veröffentlichung abgestimmt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('active', true)->where('consent', true);
    }

    public function initials(): string
    {
        return Str::of($this->name)->squish()->explode(' ')
            ->filter(fn (string $part) => $part !== '' && ! in_array(mb_strtolower($part), ['von', 'van', 'de', 'der', 'zu'], true))
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->pipe(fn ($parts) => $parts->count() > 1 ? $parts->first().$parts->last() : $parts->first())
            ?? '?';
    }

    public function anchor(): string
    {
        return 'person-'.Str::slug($this->name);
    }

    public function gremiumLabel(): string
    {
        return config("dpsg.gremien.{$this->gremium}.label", $this->gremium);
    }

    public function pageUrl(): string
    {
        return url('/'.config("dpsg.gremien.{$this->gremium}.page", '')).'#'.$this->anchor();
    }
}
