<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\CreatePerson;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\Pages\ListPeople;
use App\Models\Person;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Personenvorstellung auf den Seiten der Gremien (Diözesanleitung,
 * Diözesanbüro).
 */
class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Personen';

    protected static ?string $slug = 'personen';

    public static function form(Schema $schema): Schema
    {
        $gremien = collect(config('dpsg.gremien'))->map(fn (array $g) => $g['label'])->all();

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('name')->label('Name')->required()->maxLength(150)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, Get $get) => blank($get('email')) && $get('gremium') !== 'dak'
                            ? $set('email', Person::suggestedEmail($state)) : null),
                    TextInput::make('role')->label('Funktion')->placeholder('z. B. Referentin der Wölflingsstufe')->maxLength(150),
                    Select::make('gremium')->label('Gremium')->options($gremien)->in(array_keys($gremien))->required()->native(false)
                        ->live()
                        ->helperText('Bestimmt, auf welcher Seite die Person erscheint. DAK-Mitglieder erscheinen auf der Seite ihrer Stufe.'),
                    Select::make('stage')->label('Stufe')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $s) => $s['label'])->all())
                        ->in(array_keys(config('dpsg.stages')))
                        ->placeholder('–')
                        ->native(false)
                        ->required(fn (Get $get) => $get('gremium') === 'dak')
                        ->helperText('Für Stufenreferent*innen und DAK-Mitglieder: Sie erscheinen dann auf der Stufenseite.'),
                    TextInput::make('subgroup')->label('Zwischenüberschrift')
                        ->placeholder('z. B. Diözesanvorstand')
                        ->datalist(fn () => Person::query()->whereNotNull('subgroup')->distinct()->orderBy('subgroup')->pluck('subgroup')->all())
                        ->maxLength(100),
                    Select::make('badge')->label('Gestaltung des Initialen-Kreises')
                        ->options(config('dpsg.person_badges'))
                        ->in(array_keys(config('dpsg.person_badges')))
                        ->placeholder('DPSG-Blau')
                        ->native(false),
                    Select::make('section')->label('Ansprechperson für Fachbereich')
                        ->options(collect(config('dpsg.sections'))->map(fn (array $s) => $s['label'])->all())
                        ->placeholder('–')
                        ->native(false)
                        ->helperText('Erscheint dann auf der Startseite des Fachbereichs als Ansprechperson.'),
                    TextInput::make('email')->label('E-Mail')->email()->maxLength(255)
                        ->visible(fn (Get $get) => $get('gremium') !== 'dak')
                        ->placeholder(fn (Get $get) => Person::suggestedEmail($get('name')) ?? 'initial.nachname@'.config('dpsg.person_email_domain'))
                        ->helperText('Standard: Initial.Nachname@'.config('dpsg.person_email_domain').' – leer lassen, dann wird diese Adresse angezeigt. DAK-Mitglieder haben keine eigene Adresse.'),
                    Textarea::make('bio')->label('Vorstellungstext')->rows(6)->maxLength(3000)->columnSpanFull()
                        ->helperText('Absätze mit einer Leerzeile trennen. Lange Texte werden mit „Mehr lesen“ eingeklappt.'),
                    Select::make('photo_media_id')->label('Porträtfoto (optional)')
                        ->relationship('photo', 'alt_text')->searchable()->preload()
                        ->helperText('Ohne Foto erscheint ein Kreis mit den Initialen.'),
                    TextInput::make('sort')->label('Reihenfolge')->numeric()->default(0),
                    Toggle::make('consent')->label('Veröffentlichung mit der Person abgestimmt')
                        ->helperText('Name, Funktion, Text und ggf. Foto erscheinen erst, wenn die Person zugestimmt hat (KDG).'),
                    Toggle::make('active')->label('Aktiv')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('role')->label('Funktion')->searchable(),
                TextColumn::make('gremium')->label('Gremium')->badge()
                    ->formatStateUsing(fn (string $state) => config("dpsg.gremien.{$state}.label", $state)),
                TextColumn::make('subgroup')->label('Zwischenüberschrift')->toggleable(),
                IconColumn::make('consent')->label('Abgestimmt')->boolean(),
                IconColumn::make('active')->label('Aktiv')->boolean(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                SelectFilter::make('gremium')->label('Gremium')->options(collect(config('dpsg.gremien'))->map(fn (array $g) => $g['label'])->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
