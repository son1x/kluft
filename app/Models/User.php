<?php

namespace App\Models;

use App\Support\AuditLogger;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property \Illuminate\Support\Carbon|null $last_login_at
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
        ];
    }

    protected static function booted(): void
    {
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

    public function isActive(): bool
    {
        return $this->status === 'active';
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
