<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class UserForm
{
    public const STATUS_OPTIONS = [
        'active' => 'Aktiv',
        'pending' => 'Wartet auf Freischaltung',
        'disabled' => 'Deaktiviert',
    ];

    public static function configure(Schema $schema): Schema
    {
        $actor = Filament::auth()->user();
        $global = RoleCatalog::canManageUsersGlobally($actor);
        $grantable = RoleCatalog::grantableBy($actor);

        return $schema->components([
            Section::make('M365-Konto')
                ->description('Die Anmeldung erfolgt ausschließlich über Microsoft 365. Wird ein Konto hier vorab angelegt, wird es bei der ersten Anmeldung über die E-Mail-Adresse verknüpft.')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (?User $record) => $record !== null && ! $global),
                    TextInput::make('email')
                        ->label('M365-E-Mail-Adresse')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->disabled(fn (?User $record) => $record !== null && (! $global || filled($record->entra_oid)))
                        ->dehydrateStateUsing(fn (?string $state) => $state === null ? null : mb_strtolower(trim($state)))
                        ->helperText(fn (?User $record) => filled($record?->entra_oid)
                            ? 'Bereits mit dem M365-Konto verknüpft – wird bei jeder Anmeldung aus Entra übernommen.'
                            : 'z. B. vorname.nachname@dpsg-trier.de'),
                    Select::make('status')
                        ->label('Status')
                        ->options(self::STATUS_OPTIONS)
                        ->default('active')
                        ->required()
                        ->native(false)
                        ->disabled(! $global)
                        ->helperText('Deaktivierte Konten verlieren sofort den Zugriff aufs Backend.'),
                    Placeholder::make('last_login_at')
                        ->label('Letzte Anmeldung')
                        ->content(fn (?User $record) => $record?->last_login_at?->format('d.m.Y H:i') ?? 'noch nie'),
                ]),

            Section::make('Rollen')
                ->description($global
                    ? 'Mehrere Zuweisungen je Person möglich. Jede Änderung wird im Audit-Log protokolliert.'
                    : 'Du kannst Autor:innen in deinem Bereich freischalten oder entziehen.')
                ->components([
                    Repeater::make('roleAssignments')
                        ->hiddenLabel()
                        ->relationship(
                            'roleAssignments',
                            modifyQueryUsing: fn (Builder $query) => $global ? $query : self::scopeToGrantable($query, $grantable),
                        )
                        ->schema([
                            Select::make('assignment')
                                ->hiddenLabel()
                                ->options($grantable)
                                ->in(array_keys($grantable))
                                ->required()
                                ->distinct()
                                ->native(false),
                        ])
                        ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => ['assignment' => RoleCatalog::encode($data)])
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::toColumns($data, $grantable))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::toColumns($data, $grantable))
                        ->addActionLabel('Rolle hinzufügen')
                        ->defaultItems(0),
                ]),
        ]);
    }

    /**
     * @param  array<string, string>  $grantable
     * @return array{role: string, scope_type: string, scope_key: ?string, granted_by: int|null}
     */
    private static function toColumns(array $data, array $grantable): array
    {
        // Zweite Prüfung zusätzlich zur Formular-Validierung: niemand darf
        // per manipuliertem Request eine Rolle außerhalb des eigenen
        // Vergaberechts setzen.
        if (! array_key_exists($data['assignment'] ?? '', $grantable)) {
            throw ValidationException::withMessages(['roleAssignments' => 'Diese Rolle darfst du nicht vergeben.']);
        }

        return [...RoleCatalog::decode($data['assignment']), 'granted_by' => Filament::auth()->id()];
    }

    /**
     * @param  Builder<RoleAssignment>  $query
     * @param  array<string, string>  $grantable
     */
    private static function scopeToGrantable(Builder $query, array $grantable): Builder
    {
        return $query->where(function (Builder $q) use ($grantable) {
            foreach (array_keys($grantable) as $key) {
                $parts = RoleCatalog::decode($key);
                $q->orWhere(fn (Builder $w) => $w
                    ->where('role', $parts['role'])
                    ->where('scope_type', $parts['scope_type'])
                    ->where('scope_key', $parts['scope_key']));
            }
            if ($grantable === []) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
