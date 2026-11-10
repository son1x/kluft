<?php

namespace App\Filament\Pages;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\RoleCatalog;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Mein Konto → Meine Rollen und Rechte: nur zum Lesen, welche Rollen die
 * Person hat, was sie damit darf und wer Rechte vergibt. Hilft bei „Warum
 * sehe ich X nicht?“, ohne dass jemand die Benutzerverwaltung braucht.
 */
class MeineRollen extends Page
{
    protected string $view = 'filament.pages.meine-rollen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Mein Konto';

    protected static ?string $navigationLabel = 'Meine Rollen und Rechte';

    protected static ?string $title = 'Meine Rollen und Rechte';

    protected static ?string $slug = 'meine-rollen';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isActive();
    }

    /**
     * @return list<array{rolle: string, erklaerung: ?string, seit: ?string, von: ?string, bis: ?string}>
     */
    public function rollen(): array
    {
        return $this->user()->roleAssignments()->with('grantedBy')->orderBy('id')->get()
            ->map(fn (RoleAssignment $a) => [
                'rolle' => RoleCatalog::label($a),
                'erklaerung' => RoleCatalog::erklaerung($a->role),
                'seit' => $a->created_at?->format('d.m.Y'),
                'von' => $a->grantedBy?->name,
                'bis' => $a->gueltig_bis?->format('d.m.Y'),
            ])->values()->all();
    }

    public function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
