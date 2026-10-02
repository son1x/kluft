<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'stage',
        'section',
        'scope',
        'title',
        'slug',
        'teaser',
        'body',
        'cover_media_id',
        'status',
        'reviewed_by',
        'published_at',
        'author_id',
        'wp_id',
        'wp_url',
        'import_hash',
        'import_issues',
        'import_reviewed',
        'ai_level',
        'ai_tool',
        'ai_content',
        'ai_person',
    ];

    /**
     * Stufen der KI-Kennzeichnung (CD-Leitfaden Abschnitt 13, F-CMS-14).
     */
    public const AI_LEVELS = [
        'none' => 'Keine KI genutzt',
        'assist' => 'Nur unterstützend (Rechtschreibung, Recherche, Formulierungshilfe) – keine Kennzeichnung nötig',
        'partial' => 'Teilweise KI-unterstützt',
        'full' => 'Vollständig KI-generiert',
    ];

    public const AI_CONTENT_TYPES = ['Text', 'Bild', 'Video', 'Text und Bild'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'import_issues' => 'array',
            'import_reviewed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * @return HasMany<SocialPost, $this>
     */
    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class)->latest();
    }

    /**
     * Automatischer Hinweistext für das Frontend, oder null, wenn keine
     * Kennzeichnung nötig ist.
     */
    public function aiNotice(): ?string
    {
        $tool = trim((string) $this->ai_tool) ?: 'unbekannt';

        return match ($this->ai_level) {
            'full' => ($this->ai_content ?: 'Text').' wurde mithilfe des KI-Tools '.$tool.' generiert.',
            'partial' => (trim((string) $this->ai_person) ?: $this->author?->name ?: 'Redaktion').' in Zusammenarbeit mit dem KI-Tool '.$tool.'.',
            default => null,
        };
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }
}
