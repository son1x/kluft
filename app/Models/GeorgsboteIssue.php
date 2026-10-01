<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GeorgsboteIssue extends Model
{
    protected $fillable = [
        'year',
        'issue_no',
        'title',
        'pdf_path',
        'cover_path',
        'published',
        'published_at',
        'privacy_checked',
        'privacy_checked_by',
        'privacy_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'issue_no' => 'integer',
            'published' => 'boolean',
            'published_at' => 'datetime',
            'privacy_checked' => 'boolean',
            'privacy_checked_at' => 'datetime',
        ];
    }

    /**
     * Öffentlich nur, wenn freigegeben, Personenbezug geprüft und das
     * Veröffentlichungsdatum erreicht ist.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)
            ->where('privacy_checked', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function downloadUrl(): string
    {
        return route('georgsbote.pdf', ['jahr' => $this->year, 'ausgabe' => $this->issue_no]);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function label(): string
    {
        return "Ausgabe {$this->issue_no}/{$this->year}";
    }
}
