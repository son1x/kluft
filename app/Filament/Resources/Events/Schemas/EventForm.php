<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Veranstaltung')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', str($state)->slug()))
                        ->columnSpan(2),
                    TextInput::make('slug')
                        ->label('URL-Pfad (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Erreichbar unter /veranstaltungen/{slug}'),
                    TextInput::make('location')->label('Ort')->maxLength(255),
                    // Nextcloud-Termin, aus dem die Veranstaltung angelegt wurde.
                    Hidden::make('calendar_uid'),
                    DateTimePicker::make('starts_at')->label('Beginn')->required()->seconds(false),
                    DateTimePicker::make('ends_at')->label('Ende')->seconds(false)->afterOrEqual('starts_at'),
                    Select::make('stage')
                        ->label('Stufe')
                        ->options(fn () => self::stageOptions())
                        ->in(fn () => array_keys(self::stageOptions()))
                        ->native(false)
                        ->visible(fn () => self::stageOptions() !== [])
                        ->required(fn (Get $get) => ! self::isGlobal() && blank($get('section')))
                        ->live()
                        ->helperText('Bestimmt, wer die Teilnehmerdaten sehen darf. Leer = '.config('dpsg.site.level').' (nur Verwaltung).'),
                    Select::make('section')
                        ->label('Sektion')
                        ->options(fn () => self::sectionOptions())
                        ->in(fn () => array_keys(self::sectionOptions()))
                        ->native(false)
                        ->visible(fn () => self::sectionOptions() !== [])
                        ->required(fn (Get $get) => ! self::isGlobal() && blank($get('stage')))
                        ->live(),
                    TextInput::make('target_group')->label('Zielgruppe')->placeholder('z. B. Leitende aller Stufen')->maxLength(255),
                    TextInput::make('cost_info')->label('Kostenbeitrag (Info)')->placeholder('z. B. 45 € inkl. Verpflegung')->maxLength(255),
                    TextInput::make('contact_name')->label('Kontaktperson')->maxLength(255),
                    TextInput::make('contact_email')->label('Kontakt-E-Mail')->email()->maxLength(255)
                        ->helperText('Bitte eine Funktionsadresse (z. B. woelflinge@'.config('dpsg.site.domain').').'),
                    Select::make('cover_media_id')
                        ->label('Titelbild')
                        ->relationship('coverMedia', 'alt_text')
                        ->searchable()
                        ->preload(),
                    Textarea::make('teaser')->label('Kurzbeschreibung')->maxLength(500)->rows(2)->columnSpan(2),
                    RichEditor::make('description')->label('Beschreibung')->helperText('Video oder Karte einbetten: Link auf YouTube, Vimeo, OpenStreetMap oder Google Maps allein in eine eigene Zeile setzen – auf der Website erscheint ein Platzhalter, der erst nach Klick lädt.')->columnSpan(2),
                ]),

            Section::make('Anmeldung')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Select::make('status')
                        ->label('Status')
                        ->options(Event::STATUSES)
                        ->default('draft')
                        ->native(false)
                        ->required(),
                    TextInput::make('capacity')->label('Teilnehmerlimit')->numeric()->minValue(1)->helperText('Leer = unbegrenzt'),
                    DateTimePicker::make('reg_opens_at')->label('Anmeldung ab')->seconds(false)->helperText('Leer = sofort'),
                    DateTimePicker::make('reg_closes_at')->label('Anmeldeschluss')->seconds(false)->helperText('Leer = bis zum Beginn'),
                    Toggle::make('waitlist_enabled')->label('Warteliste, wenn ausgebucht'),
                ]),

            Section::make('Formular-Baukasten')
                ->description('Name und E-Mail werden immer abgefragt. Frag nur ab, was du für diese Veranstaltung wirklich brauchst (Datensparsamkeit).')
                ->columns(3)
                ->columnSpanFull()
                ->components(collect(config('dpsg.event_fields'))->map(fn (array $field, string $key) => Select::make("form_fields.{$key}")
                    ->label($field['label'])
                    ->helperText($field['hint'] ?? null)
                    ->options(['none' => 'Nicht abfragen', 'optional' => 'Optional', 'required' => 'Pflichtfeld'])
                    ->in(['none', 'optional', 'required'])
                    ->default('none')
                    ->afterStateHydrated(fn (Select $component, ?string $state) => $component->state($state ?: 'none'))
                    ->selectablePlaceholder(false)
                    ->native(false))->values()->all()),

            Section::make('Datenschutz')
                ->columnSpanFull()
                ->components([
                    TextInput::make('retention_days')
                        ->label('Anmeldedaten löschen nach … Tagen (ab Ende)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(90)
                        ->required()
                        ->helperText('Standard 90 Tage (Pflichtenheft 9.3). Unbestätigte Anmeldungen werden nach 48 Stunden gelöscht.'),
                ]),
        ]);
    }

    private static function isGlobal(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    /**
     * @return array<string, string>
     */
    public static function stageOptions(): array
    {
        $all = collect(config('dpsg.stages'))->map(fn (array $stage) => $stage['label']);

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->scopeKeysFor('stufenadmin') ?? [])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function sectionOptions(): array
    {
        $all = collect(RoleCatalog::sections());

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->scopeKeysFor('sektionsadmin') ?? [])->all();
    }
}
