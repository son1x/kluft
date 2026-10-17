<?php

namespace App\Models;

use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\PushEvents;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $last_login_at
 * @property string|null $ntfy_topic
 * @property list<string>|null $ntfy_events
 * @property string|null $nami_user
 * @property string|null $nami_password
 * @property Carbon|null $nami_checked_at
 * @property string|null $nami_error
 * @property string $login
 * @property string $kind
 * @property string|null $member_number
 * @property Carbon|null $member_checked_at
 * @property string|null $member_check_result
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'upn',
        'entra_oid',
        'status',
        'is_local_admin',
        'password',
        'totp_secret',
        'provisioned_by',
        'last_login_at',
        'ntfy_topic',
        'ntfy_events',
        'login',
        'kind',
        'member_number',
    ];

    public const LOGIN_OPTIONS = [
        'email' => 'E-Mail-Code',
        'entra' => 'Microsoft 365',
    ];

    public const KIND_OPTIONS = [
        'person' => 'Person (namentlich)',
        'rolle' => 'Rollen-Zugang (Funktionspostfach)',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'ntfy_topic',
        'nami_user',
        'nami_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_local_admin' => 'boolean',
            'totp_secret' => 'encrypted',
            'ntfy_topic' => 'encrypted',
            'ntfy_events' => 'array',
            'nami_user' => 'encrypted',
            'nami_password' => 'encrypted',
            'nami_checked_at' => 'datetime',
            'member_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Gesperrte Konten behalten keinen NaMi-Zugang.
        static::saving(function (User $user) {
            if ($user->isDirty('status') && $user->status === 'disabled') {
                $user->forceFill(['nami_user' => null, 'nami_password' => null, 'nami_checked_at' => null, 'nami_error' => null]);
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('status')) {
                AuditLogger::log("user.status:{$user->status}", auth()->user(), 'user', $user->id, request());
            }
        });
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * Ob der Nutzer die Rolle irgendwo (in irgendeinem Scope) besitzt.
     */
    public function hasRole(string $role): bool
    {
        return $this->roleAssignments->contains('role', $role);
    }

    /**
     * Ob der Nutzer die Rolle im konkreten Scope besitzt, z. B.
     * hasRoleInScope('stufenadmin', 'stage', 'biber').
     * scope_key = null prüft global/own-Zuweisungen ohne Schlüssel.
     */
    public function hasRoleInScope(string $role, string $scopeType, ?string $scopeKey = null): bool
    {
        return $this->roleAssignments->contains(
            fn (RoleAssignment $assignment) => $assignment->role === $role
                && $assignment->scope_type === $scopeType
                && $assignment->scope_key === $scopeKey
        );
    }

    /**
     * Scope-Schlüssel einer Rolle, z. B. scopeKeysFor('stufenadmin') => ['biber'].
     *
     * @return list<string>
     */
    public function scopeKeysFor(string $role): array
    {
        return $this->roleAssignments->where('role', $role)->pluck('scope_key')->filter()->values()->all();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->isSuperAdmin();
    }

    public function isVerwaltung(): bool
    {
        return $this->hasRole('verwaltung') || $this->isAdmin();
    }

    /** Kasse (Mitgliedsbeiträge); Admins ebenso. */
    public function isKasse(): bool
    {
        return $this->hasRole('kasse') || $this->isAdmin();
    }

    /**
     * WhatsApp-Newsletter-Admin (docs/whatsapp_newsletter.md): darf das
     * ganze Modul inkl. Verbindung zu Meta; Admins (Technik) ebenso.
     */
    public function isWhatsAppAdmin(): bool
    {
        return $this->hasRole('whatsapp') || $this->isAdmin();
    }

    /**
     * Ob die Person Push-Nachrichten (ntfy) für dieses Ereignis will und
     * es mit ihren Rechten bekommen darf, s. App\Support\PushEvents.
     */
    public function wantsPush(string $event): bool
    {
        return $this->status === 'active'
            && Features::enabled(Features::PUSH)
            && filled($this->ntfy_topic)
            && in_array($event, $this->ntfy_events ?? [], true)
            && array_key_exists($event, PushEvents::availableFor($this));
    }

    /** Persönlicher NaMi-Zugang im Profil gespeichert (verschlüsselt). */
    public function hasNamiZugang(): bool
    {
        return filled($this->nami_user) && filled($this->nami_password);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Rollen-Zugang über ein Funktionspostfach statt einer Person. */
    public function isRolle(): bool
    {
        return $this->kind === 'rolle';
    }

    /**
     * Darf sich mit einem Einmalcode per E-Mail anmelden: Funktion an,
     * Konto aktiv, mit Rolle, Anmeldeweg E-Mail; nie der Notfallzugang.
     */
    public function canUseCodeLogin(): bool
    {
        return Features::enabled(Features::CODE_LOGIN)
            && $this->login === 'email'
            && ! $this->is_local_admin
            && $this->isActive()
            && $this->roleAssignments->isNotEmpty();
    }

    /**
     * Zugriff auf das Filament-Adminpanel: nur aktive Nutzer mit
     * mindestens einer Rollenzuweisung. „wartet auf Freischaltung“
     * (status=pending) und deaktivierte Konten haben keinen Zugriff,
     * s. Pflichtenheft 5.1.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && $this->roleAssignments->isNotEmpty();
    }
}
