<?php

namespace App\Filament\Resources\Shop\RelationManagers;

use App\Filament\Resources\Shop\ArtikelResource;
use App\Models\Shop\Position;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
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
                    ->description(fn (Position $record) => trim(($record->groesse ?? '').($record->nummer ? ' · Nr. '.$record->nummer : ''), ' ·')),
                TextColumn::make('menge')->label('Anzahl'),
                TextColumn::make('preis_cent')->label('Preis je Stück')->formatStateUsing(fn ($state) => Sammelbestellung::euro($state))->placeholder('–'),
                IconColumn::make('bestaetigt')->label('geprüft')->boolean(),
                TextColumn::make('zahlung.status')->label('Zahlung')->placeholder('–')->badge(),
            ])
            ->recordActions([
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
