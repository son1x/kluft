<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Anmeldung zu einer Veranstaltung (Pflichtenheft 8.2). Zusatzangaben aus
 * dem Baukasten liegen verschlüsselt in `data`; Tokens nur als Hash.
 *
 * @property array<string, mixed>|null $data
 * @property Carbon|null $consent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $purge_at
 * @property Carbon|null $member_checked_at
 * @property bool|null $member_own_group
 * @property Carbon|null $certificate_approved_at
 */
class EventRegistration extends Model
{
    public const STATUSES = [
        'pending' => 'Angemeldet (unbestätigt)',
        'confirmed' => 'Bestätigt',
        'waitlist' => 'Warteliste',
        'cancelled' => 'Storniert',
    ];

    protected $fillable = [
        'event_id', 'status', 'first_name', 'last_name', 'email', 'birth_year', 'data',
        'guardian_name', 'guardian_consent', 'consent_text_version', 'consent_at',
        'confirm_token_hash', 'confirmed_at', 'cancel_token_hash', 'cancelled_at', 'purge_at',
    ];

    // member_* setzt nur der Mitgliederabgleich (forceFill), nie ein Formular.

    protected $hidden = ['confirm_token_hash', 'cancel_token_hash'];

    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'guardian_consent' => 'boolean',
            'birth_year' => 'integer',
            'consent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'purge_at' => 'datetime',
            'member_manual' => 'boolean',
            'member_checked_at' => 'datetime',
            'member_own_group' => 'boolean',
            'certificate_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $registration) => RegistrationDocument::purgeFor([$registration->id]));
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<RegistrationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RegistrationDocument::class)->orderBy('type')->orderByDesc('id');
    }

    /** Bescheinigung freigegeben: Veranstaltung bietet sie an, Anmeldung bestätigt und von der Leitung freigegeben. */
    public function certificateApproved(): bool
    {
        return $this->certificate_approved_at !== null && $this->status === 'confirmed' && (bool) $this->event?->certificate_enabled;
    }

    /** Hochgeladene Bescheinigung (ersetzt die erzeugte). */
    public function uploadedCertificate(): ?RegistrationDocument
    {
        return $this->documents->firstWhere('type', 'bescheinigung');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByConfirmToken(string $token): ?self
    {
        return static::query()->where('confirm_token_hash', static::hashToken($token))->first();
    }

    public static function findByCancelToken(string $token): ?self
    {
        return static::query()->where('cancel_token_hash', static::hashToken($token))->first();
    }

    /** Gast aus einer anderen Gliederung oder einem anderen Verband (Ankreuzfeld beim Stamm). */
    public function isGast(): bool
    {
        return ($this->data[Event::HERKUNFT] ?? null) === 'gast';
    }
}
