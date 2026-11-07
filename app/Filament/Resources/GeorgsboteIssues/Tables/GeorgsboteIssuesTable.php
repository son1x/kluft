<?php

namespace App\Filament\Resources\GeorgsboteIssues\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeorgsboteIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Titelbild nur bei Georgsboten (Georgsbriefe haben keins).
                ImageColumn::make('cover_path')->label('Titel')
                    ->disk('public')
                    ->imageHeight(64)
                    ->visible(fn ($livewire) => ($livewire->activeTab ?? 'bote') !== 'brief'),
                TextColumn::make('year')->label('Jahrgang')->sortable(),
                TextColumn::make('issue_no')->label('Nr./Monat')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable(['title', 'inhalt'])
                    ->description(fn ($record) => str((string) $record->inhalt)->limit(90)->toString() ?: null),
                IconColumn::make('privacy_checked')->label('Geprüft')->boolean(),
                IconColumn::make('published')->label('Veröffentlicht')->boolean(),
                TextColumn::make('downloads')->label('Abrufe')->numeric()->sortable(),
            ])
            ->defaultSort(fn ($query) => $query->orderByDesc('year')->orderByDesc('issue_no'))
            ->filters([
                TernaryFilter::make('published')->label('Veröffentlicht'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
