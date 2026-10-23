<?php

namespace App\Filament\Resources\Shop\RelationManagers;

use App\Filament\Resources\Shop\ArtikelResource;
use App\Models\Shop\Position;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Positionen einer Bestellung; freie Artikel prüfen, bestätigen oder streichen. */
class PositionenRelationManager extends RelationManager
{
    protected static string $relationship = 'positionen';

    protected static ?string $title = 'Positionen';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mitglied')->label('Für')->description(fn (Position $record) => $record->stufe ? config("dpsg.stages.{$record->stufe}.label") : null),
                TextColumn::make('bezeichnung')->label('Artikel')->wrap()
                    ->formatStateUsing(fn (Position $record, string $state) => ($record->set ? $record->set->name.': ' : '').$state)
                    ->description(fn (Position $record) => trim(($record->groesse ?? '').($record->nummer ? ' · Nr. '.$record->nummer : ''), ' ·')),
                TextColumn::make('menge')->label('Anzahl'),
                TextColumn::make('preis_cent')->label('Preis je Stück')->formatStateUsing(fn ($state) => Sammelbestellung::euro($state))->placeholder('–'),
                IconColumn::make('bestaetigt')->label('geprüft')->boolean(),
                TextColumn::make('zahlung.status')->label('Zahlung')->placeholder('–')->badge(),
                TextColumn::make('ausgegeben_am')->label('Ausgegeben')->date('d.m.Y')->placeholder('–')
                    ->description(fn (Position $record) => $record->ausgegeben_am ? trim(($record->ausgeber?->name ?? '').($record->ausgabe_vermerk ? ' · '.$record->ausgabe_vermerk : ''), ' ·') : null),
            ])
            ->recordActions([
                Action::make('ausgeben')->label(fn (Position $record) => $record->ausgegeben_am ? 'Ausgabe zurücknehmen' : 'Ausgegeben')
                    ->icon(fn (Position $record) => $record->ausgegeben_am ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check-circle')
                    ->color(fn (Position $record) => $record->ausgegeben_am ? 'gray' : 'success')
                    ->visible(fn (Position $record) => $record->bezahlt())
                    ->action(function (Position $record) {
                        SammelbestellungAblauf::ausgeben($record, Filament::auth()->user(), null, ! $record->ausgegeben_am);
                        AuditLogger::log('sammelbestellung.position.ausgegeben', Filament::auth()->user(), 'shop_positionen', $record->id, request());
                    }),
                Action::make('bestaetigen')->label('Bestätigen')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Position $record) => ! $record->bestaetigt && ! $record->bestellung->storniert_am)
                    ->fillForm(fn (Position $record) => ['preis' => $record->preis_cent, 'nummer' => $record->nummer, 'groesse' => $record->groesse])
                    ->schema([
                        ArtikelResource::euroFeld(TextInput::make('preis')->label('Preis je Stück')->required()),
                        TextInput::make('nummer')->label('Rüsthaus-Nr.')->maxLength(40),
                        TextInput::make('groesse')->label('Größe / Ausführung')->maxLength(120),
                    ])
                    ->modalDescription('Sind alle freien Artikel der Bestellung geprüft, geht die Zahlungsaufforderung für sie raus.')
                    ->action(function (Position $record, array $data) {
                        Sammelbestellung::bestaetigen($record, (int) $data['preis'], $data['nummer'] ?: null, $data['groesse'] ?: null);
                        AuditLogger::log('sammelbestellung.position.bestaetigt', Filament::auth()->user(), 'shop_positionen', $record->id, request());
                        Notification::make()->title('Bestätigt')->success()->send();
                    }),
                Action::make('streichen')->label('Streichen')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (Position $record) => $record->zahlung_id === null)
                    ->requiresConfirmation()
                    ->action(function (Position $record) {
                        $bestellung = $record->bestellung;
                        $record->delete();
                        AuditLogger::log('sammelbestellung.position.gestrichen', Filament::auth()->user(), 'shop_positionen', $record->id, request());
                        if ($bestellung->bestaetigt_am && $bestellung->positionen()->where('bestaetigt', false)->doesntExist()) {
                            Sammelbestellung::zahlungAnfordern($bestellung);
                        }
                        Notification::make()->title('Gestrichen')->success()->send();
                    }),
            ]);
    }
}
