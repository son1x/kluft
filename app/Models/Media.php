<?php

namespace App\Models;

use App\Services\ImageVariants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'path',
        'mime',
        'size',
        'alt_text',
        'credit',
        'rights_confirmed',
        'uploaded_by',
        'variants',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'rights_confirmed' => 'boolean',
            'size' => 'integer',
            'variants' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // WebP-Varianten bei jedem neuen oder ausgetauschten Bild erzeugen
        // (F-CMS-04) und beim Löschen mit entfernen.
        static::saved(function (Media $media) {
            if ($media->wasRecentlyCreated || $media->wasChanged('path')) {
                app(ImageVariants::class)->generate($media);
            }
        });
        static::deleted(fn (Media $media) => app(ImageVariants::class)->deleteVariants($media));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * WebP-srcset, z. B. "…-480.webp 480w, …-960.webp 960w"; leer, wenn es
     * (noch) keine Varianten gibt.
     */
    public function webpSrcset(): string
    {
        return collect($this->variants ?? [])
            ->map(fn (string $path, int|string $width) => Storage::disk('public')->url($path).' '.$width.'w')
            ->implode(', ');
    }
}
