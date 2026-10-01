<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Benutzerverwaltung, s. Pflichtenheft 5.4 („Benutzer/Rollen global
 * verwalten“, „Autoren im eigenen Scope freischalten“) und 5.6.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return RoleCatalog::canManageUsersGlobally($actor) || RoleCatalog::delegatedScopeKeys($actor) !== [];
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }

    public function update(User $actor, User $target): bool
    {
        if ($target->is_local_admin && ! $actor->isSuperAdmin()) {
            return false;
        }

        if (RoleCatalog::canManageUsersGlobally($actor)) {
            // Verwaltung verwaltet alle außer Admin/Super-Admin (5.4).
            return ! $target->isAdmin() || $actor->isAdmin();
        }

        return static::visibleTo($actor, User::query())->whereKey($target->getKey())->exists();
    }

    /**
     * Konten werden deaktiviert statt gelöscht (Nachvollziehbarkeit im
     * Audit-Log, Wiederherstellbarkeit).
     */
    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    /**
     * Für Stufen-/Sektionsadmins sichtbare Konten: Personen mit einer
     * Zuweisung im eigenen Scope sowie noch nicht freigeschaltete Konten
     * ohne Zuweisung (damit sie diese als Autor:in freischalten können).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function visibleTo(User $actor, Builder $query): Builder
    {
        if (RoleCatalog::canManageUsersGlobally($actor)) {
            return $actor->isSuperAdmin() ? $query : $query->where('is_local_admin', false);
        }

        $keys = RoleCatalog::delegatedScopeKeys($actor);

        return $query
            ->where('is_local_admin', false)
            ->where(function (Builder $q) use ($keys) {
                $q->whereHas('roleAssignments', fn (Builder $a) => $a
                    ->whereIn('role', ['autor', 'stufenadmin', 'sektionsadmin'])
                    ->whereIn('scope_key', $keys === [] ? ['__none__'] : $keys))
                    ->orWhere(fn (Builder $p) => $p->where('status', 'pending')->whereDoesntHave('roleAssignments'));
            });
    }
}
