<?php

namespace App\Filament\Resources\Gruppierungen\Tables;

use App\Models\Gruppierung;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GruppierungenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn (Gruppierung $record) => $record->displayName())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('typ')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'bezirk' ? 'Bezirk' : 'Stamm')
                    ->color(fn (string $state) => $state === 'bezirk' ? 'primary' : 'gray'),
                TextColumn::make('bezirk.name')
                    ->label('Bezirk')
                    ->placeholder('–'),
                TextColumn::make('ort')
                    ->label('Ort')
                    ->searchable(),
                TextColumn::make('stufen')
                    ->label('Stufen')
                    ->state(fn (Gruppierung $record) => collect($record->stufen())->map(fn ($s) => config("dpsg.stages.{$s}.label"))->all())
                    ->badge(),
                IconColumn::make('standort')
                    ->label('Standort')
                    ->state(fn (Gruppierung $record) => $record->hasPosition())
                    ->boolean(),
                ToggleColumn::make('active')
                    ->label('Aktiv'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                SelectFilter::make('typ')
                    ->label('Typ')
                    ->options(['bezirk' => 'Bezirk', 'stamm' => 'Stamm']),
                SelectFilter::make('parent_id')
                    ->label('Bezirk')
                    ->relationship('bezirk', 'name', fn (Builder $query) => $query->where('typ', 'bezirk')),
                TernaryFilter::make('active')
                    ->label('Aktiv'),
                Filter::make('ohne_standort')
                    ->label('Ohne Standort')
                    ->query(fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('lat')->orWhereNull('lng'))),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
