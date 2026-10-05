<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Weiterleitungen alter Adressen (Pflichtenheft F-MIG-05). Die
 * WordPress-Weiterleitungen legt der Import an; weitere (z. B. alte
 * Seiten) lassen sich hier ergänzen.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Weiterleitung';

    protected static ?string $pluralModelLabel = 'Weiterleitungen';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('from_path')->label('Alte Adresse')
                        ->required()->maxLength(500)
                        ->placeholder('/alter-beitrag')
                        ->helperText('Nur der Pfad, z. B. /veranstaltungen-2024 – Domain und Schrägstrich am Ende werden ignoriert.')
                        ->dehydrateStateUsing(fn (?string $state) => Redirect::normalize((string) $state))
                        ->rule(fn (?Redirect $record) => Rule::unique('redirects', 'from_path')->ignore($record?->id))
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                            if (Redirect::normalize((string) $value) === '/') {
                                $fail('Die Startseite kann nicht umgeleitet werden.');
                            }
                        }),
                    TextInput::make('to_path')->label('Neue Adresse')
                        ->required()->maxLength(500)
                        ->placeholder('/aktuelles/neuer-beitrag')
                        ->helperText('Pfad auf dieser Website (beginnt mit /) oder vollständige https-Adresse.')
                        ->rule('regex:#^(/|https://)#'),
                    Select::make('status_code')->label('Art')
                        ->options([301 => 'Dauerhaft (301)', 302 => 'Vorübergehend (302)'])
                        ->default(301)->required()->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label('Alte Adresse')->searchable()->sortable()->wrap(),
                TextColumn::make('to_path')->label('Neue Adresse')->searchable()->wrap()
                    ->url(fn (Redirect $r) => str_starts_with($r->to_path, '/') ? url($r->to_path) : $r->to_path, shouldOpenInNewTab: true),
                TextColumn::make('source')->label('Herkunft')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'wordpress' ? 'WordPress-Import' : 'Manuell'),
                TextColumn::make('hits')->label('Aufrufe')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Zuletzt')->since()->sortable()->placeholder('–'),
            ])
            ->filters([
                SelectFilter::make('source')->label('Herkunft')->options(['wordpress' => 'WordPress-Import', 'manuell' => 'Manuell']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
