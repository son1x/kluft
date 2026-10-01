<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Veranstaltung mit Online-Anmeldung, Pflichtenheft 6.7.
 */
class Event extends Model
{
    public const STATUSES = [
        'draft' => 'Entwurf',
        'open' => 'Anmeldung offen',
        'closed' => 'Anmeldung geschlossen',
        'cancelled' => 'Abgesagt',
    ];

    protected $fillable = [
        'title', 'slug', 'teaser', 'description', 'starts_at', 'ends_at', 'location',
        'stage', 'section', 'target_group', 'cover_media_id', 'contact_name', 'contact_email',
        'cost_info', 'reg_opens_at', 'reg_closes_at', 'capacity', 'waitlist_enabled',
        'status', 'form_fields', 'retention_days', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reg_opens_at' => 'datetime',
            'reg_closes_at' => 'datetime',
            'waitlist_enabled' => 'boolean',
            'form_fields' => 'array',
            'capacity' => 'integer',
            'retention_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * Öffentlich sichtbar ist alles außer Entwürfen.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('ends_at', '>=', now())
            ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    /**
     * Aktive Felder aus dem Baukasten in fester Reihenfolge.
     *
     * @return array<string, array{label: string, type: string, required: bool, options?: array<string, string>}>
     */
    public function formFields(): array
    {
        $config = $this->form_fields ?? [];
        $fields = [];

        foreach (config('dpsg.event_fields') as $key => $field) {
            if (in_array($config[$key] ?? null, ['optional', 'required'], true)) {
                $fields[$key] = [...$field, 'required' => $config[$key] === 'required'];
            }
        }

        return $fields;
    }

    public function confirmedCount(): int
    {
        return $this->registrations()->where('status', 'confirmed')->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->confirmedCount() >= $this->capacity;
    }

    public function spotsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->confirmedCount());
    }

    public function registrationClosesAt(): CarbonInterface
    {
        return $this->reg_closes_at ?? $this->starts_at;
    }

    /**
     * Ob gerade Anmeldungen angenommen werden (Status, Zeitraum, Plätze).
     */
    public function acceptsRegistrations(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if ($this->reg_opens_at?->isFuture() || $this->registrationClosesAt()->isPast()) {
            return false;
        }

        return ! $this->isFull() || $this->waitlist_enabled;
    }

    /**
     * Grund, warum keine Anmeldung möglich ist (für die Detailseite).
     */
    public function registrationState(): string
    {
        return match (true) {
            $this->status === 'cancelled' => 'abgesagt',
            $this->status !== 'open' || $this->registrationClosesAt()->isPast() => 'geschlossen',
            (bool) $this->reg_opens_at?->isFuture() => 'bald',
            $this->isFull() && ! $this->waitlist_enabled => 'ausgebucht',
            $this->isFull() => 'warteliste',
            default => 'offen',
        };
    }

    /**
     * Löschdatum der Anmeldedaten (9.3: Standard 90 Tage nach Ende).
     */
    public function purgeAt(): CarbonInterface
    {
        return ($this->ends_at ?? $this->starts_at)->copy()->endOfDay()->addDays($this->retention_days ?: 90);
    }

    public function scopeLabel(): string
    {
        if ($this->stage) {
            return config("dpsg.stages.{$this->stage}.label", $this->stage);
        }
        if ($this->section) {
            return config("dpsg.sections.{$this->section}.label", $this->section);
        }

        return 'Diözesanverband';
    }

    public function dateLabel(): string
    {
        $start = $this->starts_at->locale('de');
        $end = $this->ends_at?->locale('de');

        if (! $end || $end->isSameDay($start)) {
            $label = $start->isoFormat('dd, D. MMMM YYYY');
            $time = $start->format('H:i') !== '00:00' ? ', '.$start->format('H:i').($end ? '–'.$end->format('H:i') : '').' Uhr' : '';

            return $label.$time;
        }

        return $start->isoFormat($start->year === $end->year ? 'D. MMMM' : 'D. MMMM YYYY').' – '.$end->isoFormat('D. MMMM YYYY');
    }

    /**
     * Ob die angegebene Person (nach Geburtsjahr) bei Veranstaltungsbeginn
     * jünger als das Einwilligungsalter sein könnte. Ohne Monat/Tag wird
     * vorsichtig gerechnet: im Zweifel ist die Einwilligung nötig.
     */
    public function mayBeUnderConsentAge(?int $birthYear): bool
    {
        return $birthYear !== null
            && $this->starts_at->year - $birthYear <= config('dpsg.guardian_consent_age', 16);
    }
}
