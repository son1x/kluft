<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use App\Support\Features;
use App\Support\RoleCatalog;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable(),
                TextColumn::make('zugang')
                    ->label('Zugang')
                    ->state(fn (User $record) => array_filter([
                        $record->isRolle() ? 'Rolle' : 'Person',
                        User::LOGIN_OPTIONS[$record->login] ?? null,
                        $record->member_checked_at ? 'NaMi: '.(str_starts_with((string) $record->member_check_result, 'Aktives') ? 'aktiv' : 'prüfen') : null,
                    ]))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Rolle' => 'info',
                        'NaMi: aktiv' => 'success',
                        'NaMi: prüfen' => 'warning',
                        default => 'gray',
                    })
                    ->visible(fn () => Features::enabled(Features::CODE_LOGIN)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => UserForm::STATUS_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('roles')
                    ->label('Rollen')
                    ->state(fn (User $record) => $record->roleAssignments->map(fn ($a) => RoleCatalog::label($a))->all())
                    ->badge()
                    ->listWithLineBreaks(),
                TextColumn::make('last_login_at')
                    ->label('Letzte Anmeldung')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('noch nie')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(UserForm::STATUS_OPTIONS),
                SelectFilter::make('assignment')
                    ->label('Rolle / Bereich')
                    ->options(RoleCatalog::all())
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }
                        $parts = RoleCatalog::decode($data['value']);

                        return $query->whereHas('roleAssignments', fn (Builder $a) => $a
                            ->where('role', $parts['role'])
                            ->where('scope_type', $parts['scope_type'])
                            ->where('scope_key', $parts['scope_key']));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
