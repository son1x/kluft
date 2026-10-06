<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use App\Models\Gruppierung;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Features;
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
                    Toggle::make('member_check')->label('Mitgliedschaft mit der Mitgliederverwaltung abgleichen')
                        ->helperText(fn () => (NamiVerzeichnis::persoenlich()
                            ? 'Bestätigte Anmeldungen werden anhand von Name und Geburtsjahr mit '.app(Mitgliederverzeichnis::class)->name().' abgeglichen, sobald die Veranstaltungsleitung unter „Teilnehmende“ → „Mitgliedschaft prüfen“ startet und sich mit dem eigenen NaMi-Konto anmeldet'
                            : 'Nach der Bestätigung wird jede Anmeldung anhand von Name und Geburtsjahr mit '.app(Mitgliederverzeichnis::class)->name().' abgeglichen')
                            .' (z. B. für Versicherung oder Mitgliedsbeitrag). Der Einwilligungstext bekommt dafür einen Hinweis; am besten zusammen mit dem Feld „Geburtsjahr“.')
                        ->visible(fn () => MitgliederAbgleich::eingerichtet()),
                    Select::make('member_gruppierung_id')->label('Nur für Mitglieder von')
                        ->options(fn () => Gruppierung::query()->active()->orderBy('typ')->orderBy('name')->get()
                            ->mapWithKeys(fn (Gruppierung $g) => [$g->id => $g->displayName().($g->typ === 'bezirk' ? ' (mit allen Stämmen)' : '')])->all())
                        ->searchable()
                        ->placeholder('Offen für alle')
                        ->helperText('Z. B. für Stammes- oder Bezirksveranstaltungen. Die Seite weist darauf hin; der Mitgliederabgleich markiert, wer zu einer anderen Gruppierung gehört. Niemand wird automatisch abgelehnt.')
                        ->visible(fn () => Features::enabled(Features::NUR_MITGLIEDER)),
                ]),

            Section::make('Teilnahmebescheinigung')
                ->description('Die Bescheinigung entsteht als PDF mit Prüfcode. Teilnehmende bekommen sie erst, wenn die Veranstaltungsleitung ihre Teilnahme unter „Teilnehmende“ freigibt (einzeln oder für mehrere auf einmal).')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn () => Features::enabled(Features::TEILNAHMEBESCHEINIGUNG))
                ->components([
                    Toggle::make('certificate_enabled')->label('Teilnahmebescheinigung anbieten')->live()->columnSpanFull(),
                    Textarea::make('certificate_text')->label('Inhalte und Umfang (optional)')
                        ->rows(3)->maxLength(500)
                        ->placeholder('z. B. Modul 1b der Woodbadge-Ausbildung, 20 Unterrichtseinheiten')
                        ->visible(fn (Get $get) => (bool) $get('certificate_enabled')),
                    TextInput::make('certificate_signer')->label('Unterzeichnet von (optional)')
                        ->maxLength(150)
                        ->placeholder('Name, Funktion – z. B. Anna Beispiel, Diözesanvorsitzende')
                        ->helperText('Ohne Angabe steht dort der Name der Gliederung.')
                        ->visible(fn (Get $get) => (bool) $get('certificate_enabled')),
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
