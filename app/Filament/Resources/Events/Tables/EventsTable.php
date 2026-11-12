<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Anzahlen ohne Personendaten – auch für den Vorstand (5.3).
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'registrations as confirmed_count' => fn (Builder $q) => $q->where('status', 'confirmed'),
                'registrations as waitlist_count' => fn (Builder $q) => $q->where('status', 'waitlist'),
                'registrations as pending_count' => fn (Builder $q) => $q->where('status', 'pending'),
            ]))
            ->columns([
                TextColumn::make('starts_at')->label('Beginn')->sortable()
                    ->formatStateUsing(fn (Event $record) => $record->ganztaegig
                        ? $record->starts_at->format('d.m.Y').($record->ends_at && ! $record->ends_at->isSameDay($record->starts_at) ? ' – '.$record->ends_at->format('d.m.Y') : '')
                        : $record->starts_at->format('d.m.Y H:i'))
                    ->description(fn (Event $record) => $record->ganztaegig ? 'ganztägig' : null),
                TextColumn::make('title')->label('Titel')->searchable()->wrap(),
                TextColumn::make('scope')->label('Stufe/Sektion')->state(fn (Event $record) => $record->scopeLabel()),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Event::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'success',
                        'cancelled' => 'danger',
                        'closed' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('confirmed_count')->label('Bestätigt')
                    ->formatStateUsing(fn ($state, Event $record) => $record->capacity ? "{$state} / {$record->capacity}" : (string) $state),
                TextColumn::make('waitlist_count')->label('Warteliste'),
                TextColumn::make('pending_count')->label('Unbestätigt')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('Noch keine Veranstaltungen')
            ->emptyStateDescription('Oben rechts auf „Erstellen“: Titel, Termin und Ort eintragen. Mit Online-Anmeldung kommen Teilnehmerliste und Bestätigungsmails dazu. Als Entwurf speichern, bis alles stimmt.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->defaultSort('starts_at')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Event::STATUSES),
                TernaryFilter::make('upcoming')
                    ->label('Zeitraum')
                    ->placeholder('Alle')
                    ->trueLabel('Kommende')
                    ->falseLabel('Vergangene')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->upcoming(),
                        false: fn (Builder $q) => $q->whereNot(fn (Builder $q) => $q->upcoming()),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                // Direkt zum Reiter „Teilnehmende“ (EditEvent, ?relation=0).
                Action::make('teilnehmende')->label('Teilnehmende')->icon('heroicon-o-user-group')->color('gray')
                    ->url(fn (Event $record) => EventResource::getUrl(Filament::auth()->user()?->can('update', $record) ? 'edit' : 'view', ['record' => $record, 'relation' => '0']))
                    ->visible(fn (Event $record) => (bool) Filament::auth()->user()?->can('viewParticipants', $record)),
                ViewAction::make()->hidden(fn (Event $record) => Filament::auth()->user()?->can('update', $record)),
                // Frühere Veranstaltung als Vorlage (F-EV-07); Anmeldungen
                // werden nicht mitkopiert.
                ReplicateAction::make()
                    ->label('Duplizieren')
                    ->excludeAttributes(['confirmed_count', 'waitlist_count', 'pending_count'])
                    ->visible(fn (Event $record) => Filament::auth()->user()?->can('update', $record))
                    ->beforeReplicaSaved(function (Event $replica) {
                        $base = $replica->slug.'-kopie';
                        $slug = $base;
                        for ($i = 2; Event::query()->where('slug', $slug)->exists(); $i++) {
                            $slug = "{$base}-{$i}";
                        }
                        $replica->slug = $slug;
                        $replica->title .= ' (Kopie)';
                        $replica->status = 'draft';
                        $replica->created_by = Filament::auth()->id();
                    })
                    ->successRedirectUrl(fn (Event $replica) => EventResource::getUrl('edit', ['record' => $replica])),
            ]);
    }
}
