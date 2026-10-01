<?php

namespace App\Filament\Resources\Media\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')
                    ->label('Vorschau')
                    ->disk('public'),
                TextColumn::make('alt_text')
                    ->label('Alt-Text')
                    ->searchable(),
                TextColumn::make('credit')
                    ->label('Bildnachweis')
                    ->searchable(),
                TextColumn::make('uploadedBy.name')
                    ->label('Hochgeladen von'),
                TextColumn::make('created_at')
                    ->label('Hochgeladen am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
