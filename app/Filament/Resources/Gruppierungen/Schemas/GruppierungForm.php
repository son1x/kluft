<?php

namespace App\Filament\Resources\Gruppierungen\Schemas;

use App\Models\Gruppierung;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GruppierungForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gruppierung')
                ->columns(2)
                ->components([
                    Select::make('typ')
                        ->label('Typ')
                        ->options(['bezirk' => 'Bezirk', 'stamm' => 'Stamm'])
                        ->default('stamm')
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('parent_id')
                        ->label('Bezirk')
                        ->relationship('bezirk', 'name', fn ($query) => $query->where('typ', 'bezirk')->orderBy('name'))
                        ->native(false)
                        ->visible(fn (Get $get) => $get('typ') === 'stamm')
                        ->helperText('Optional – der Stamm erscheint dann auch auf der Bezirksseite.'),
                    TextInput::make('nummer')
                        ->label('DPSG-Nummer')
                        ->placeholder('21-01-08')
                        ->regex('/^21-\\d{2}-\\d{2}$/')
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['regex' => 'Format: Diözesanverband-Bezirk-Stamm, z. B. 21-01-08 (Bezirke: 21-01-00).'])
                        ->helperText('Diözesanverband-Bezirk-Stamm, z. B. 21-01-08; Bezirke enden auf -00. Bestimmt die Reihenfolge.'),
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $get('slug') ?: $set('slug', str($state)->slug()))
                        ->helperText(fn (Get $get) => $get('typ') === 'bezirk' ? 'Ohne „Bezirk“ davor, z. B. „Saar-Hochwald“.' : 'z. B. „Stamm St. Georg Musterstadt“'),
                    TextInput::make('slug')
                        ->label('Kurzname (URL)')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    CheckboxList::make('stufen')
                        ->label('Angebotene Stufen')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $stage) => $stage['label'])->all())
                        ->columns(3)
                        // Neue Stämme: Wölflinge bis Rover; Biber nur, wenn
                        // ausdrücklich angekreuzt (Bibergruppen gibt es nicht überall).
                        ->afterStateHydrated(fn (CheckboxList $component, ?Gruppierung $record) => $component->state(
                            $record ? $record->stufen() : ['woelflinge', 'jupfi', 'pfadi', 'rover']
                        ))
                        ->helperText('Biber nur ankreuzen, wenn der Stamm eine Bibergruppe hat. Bei Bezirken bleibt die Liste leer.')
                        ->dehydrated(false)
                        ->saveRelationshipsUsing(fn (Gruppierung $record, ?array $state) => $record->syncStufen($state ?? []))
                        ->columnSpanFull(),
                    Textarea::make('beschreibung')
                        ->label('Kurzbeschreibung')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Select::make('logo_media_id')
                        ->label('Logo')
                        ->relationship('logo', 'alt_text')
                        ->searchable()
                        ->preload(),
                    Toggle::make('active')
                        ->label('Aktiv (öffentlich sichtbar)')
                        ->default(true)
                        ->helperText('Ruhende Stämme ausblenden statt löschen.'),
                ]),

            Section::make('Kontakt')
                ->description('Bitte Funktionsadressen verwenden (z. B. stamm-xy@dpsg-trier.de), keine privaten Kontaktdaten.')
                ->columns(2)
                ->components([
                    TextInput::make('kontakt_email')->label('E-Mail (Funktionsadresse)')->email()->maxLength(255),
                    TextInput::make('kontakt_telefon')->label('Telefon (optional)')->tel()->maxLength(50),
                    TextInput::make('website_url')->label('Website')->url()->maxLength(255),
                    TextInput::make('social_url')->label('Social Media')->url()->maxLength(255),
                    TextInput::make('ansprechperson')
                        ->label('Ansprechperson (optional)')
                        ->maxLength(255)
                        ->live(onBlur: true),
                    Toggle::make('ansprechperson_einwilligung')
                        ->label('Einwilligung der Person zur Veröffentlichung liegt vor')
                        ->accepted(fn (Get $get) => filled($get('ansprechperson')))
                        ->validationMessages(['accepted' => 'Der Name darf nur mit Einwilligung der Person veröffentlicht werden.']),
                ]),

            Section::make('Adresse & Standort')
                ->description('Die Adresse wird nur angezeigt. Den Kartenpunkt setzt du per Klick auf die Karte (kein automatisches Geocoding).')
                ->columns(3)
                ->components([
                    TextInput::make('strasse')->label('Straße und Hausnummer')->maxLength(255)->columnSpan(3),
                    TextInput::make('plz')->label('PLZ')->maxLength(10),
                    TextInput::make('ort')->label('Ort')->maxLength(255)->columnSpan(2),
                    ViewField::make('karte')
                        ->view('filament.forms.map-picker')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('lat')->label('Breitengrad')->numeric()->minValue(-90)->maxValue(90)->live(onBlur: true),
                    TextInput::make('lng')->label('Längengrad')->numeric()->minValue(-180)->maxValue(180)->live(onBlur: true),
                    TextInput::make('sort')->label('Sortierung')->numeric()->default(0),
                ]),
        ]);
    }
}
