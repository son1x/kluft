<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Models\SocialPost;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),
                TextColumn::make('scope')
                    ->label('Geltungsbereich')
                    ->badge(),
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
                TextColumn::make('author.name')
                    ->label('Autor:in')
                    ->searchable(),
                TextColumn::make('instagram')
                    ->label('Instagram')
                    ->state(fn (Post $record) => $record->socialPosts->first()?->status)
                    ->formatStateUsing(fn (?string $state) => SocialPost::STATUSES[$state] ?? '–')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'published' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->url(fn (Post $record) => $record->socialPosts->first()?->permalink, shouldOpenInNewTab: true)
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label('Veröffentlicht am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('socialPosts'))
            ->defaultSort('created_at', 'desc')
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
