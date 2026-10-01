<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label('Übergeordnet')
                    ->searchable(),
                TextColumn::make('stage')
                    ->label('Stufe')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'review' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('show_in_nav')
                    ->label('Navigation')
                    ->boolean(),
                TextColumn::make('author.name')
                    ->label('Autor:in')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Veröffentlicht am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aktualisiert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nav_sort')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Entwurf',
                        'review' => 'Zur Prüfung',
                        'published' => 'Veröffentlicht',
                    ]),
                SelectFilter::make('stage')
                    ->label('Stufe')
                    ->options([
                        'biber' => 'Biber',
                        'woelflinge' => 'Wölflinge',
                        'jupfi' => 'Jungpfadfinder',
                        'pfadi' => 'Pfadfinder',
                        'rover' => 'Rover',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
