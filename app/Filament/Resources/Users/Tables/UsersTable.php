<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use App\Support\Features;
use App\Support\RoleCatalog;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
                // Zweistufig wie im Formular: Rolle, optional Bereich.
                Filter::make('rolle')
                    ->schema(function () {
                        $auswahl = RoleCatalog::rollenAuswahl(RoleCatalog::all());

                        return [
                            Select::make('rolle')->label('Rolle')->options($auswahl['rollen'])->native(false)->live()
                                ->afterStateUpdated(fn (Set $set) => $set('bereich', null)),
                            Select::make('bereich')->label('Bereich')->placeholder('alle Bereiche')->native(false)->searchable()
                                ->options(fn (Get $get) => $auswahl['bereiche'][$get('rolle')] ?? [])
                                ->visible(fn (Get $get) => isset($auswahl['bereiche'][$get('rolle')])),
                        ];
                    })
                    ->query(function (Builder $query, array $data) {
                        if (blank($data['rolle'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('roleAssignments', function (Builder $a) use ($data) {
                            $a->where('role', $data['rolle']);
                            if (filled($data['bereich'] ?? null)) {
                                [$typ, $bereich] = explode('|', (string) $data['bereich'], 2);
                                $a->where('scope_type', $typ)->where('scope_key', $bereich === '' ? null : $bereich);
                            }
                        });
                    })
                    ->indicateUsing(function (array $data) {
                        if (blank($data['rolle'] ?? null)) {
                            return null;
                        }
                        $auswahl = RoleCatalog::rollenAuswahl(RoleCatalog::all());
                        $bereich = filled($data['bereich'] ?? null) ? (array_merge([], ...array_values($auswahl['bereiche'][$data['rolle']] ?? []))[$data['bereich']] ?? null) : null;

                        return 'Rolle: '.($auswahl['rollen'][$data['rolle']] ?? $data['rolle']).($bereich ? ' – '.$bereich : '');
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
