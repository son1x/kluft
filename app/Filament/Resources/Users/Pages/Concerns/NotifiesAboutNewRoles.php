<?php

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\PrivilegedRoleGrantedNotification;
use App\Notifications\RolesGrantedNotification;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

/**
 * Mail an die Person bei neuem Konto bzw. neuen Rechten, dazu ein
 * Sicherheitshinweis an die Technik-Admins bei weitreichenden Rollen.
 */
trait NotifiesAboutNewRoles
{
    /** @var list<string> */
    protected array $rolesBefore = [];

    protected function rememberRoles(): void
    {
        $this->rolesBefore = $this->roleKeys();
    }

    protected function notifyAboutNewRoles(bool $newAccount): void
    {
        /** @var User $user */
        $user = $this->record->refresh();
        $added = array_values(array_diff($this->roleKeys(), $this->rolesBefore));
        $actor = Filament::auth()->user();

        if ($added === [] || $user->status === 'disabled') {
            return;
        }

        $by = $actor instanceof User ? $actor->name : null;

        if (! ($actor instanceof User && $actor->is($user))) {
            $user->notify(new RolesGrantedNotification($added, $newAccount, $by));
        }

        $privileged = array_values(array_filter($added, RoleCatalog::isPrivileged(...)));
        if ($privileged !== []) {
            $admins = User::query()
                ->where('status', 'active')
                ->whereKeyNot($user->getKey())
                ->when($actor instanceof User, fn ($q) => $q->whereKeyNot($actor->getKey()))
                ->whereHas('roleAssignments', fn ($q) => $q->where('role', 'admin'))
                ->get();
            Notification::send($admins, new PrivilegedRoleGrantedNotification($user, $privileged, $by));
        }
    }

    /**
     * @return list<string>
     */
    private function roleKeys(): array
    {
        /** @var User $user */
        $user = $this->record;

        return $user->roleAssignments()->get()
            ->map(fn (RoleAssignment $a) => RoleCatalog::encode($a))
            ->values()
            ->all();
    }
}
