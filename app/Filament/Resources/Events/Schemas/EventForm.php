<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Filament\Resources\Beitraege\BeitragResource;
use App\Models\Event;
use App\Models\Gruppierung;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AnmeldeInfos;
use App\Support\Features;
use App\Support\PersonenSuche;
use App\Support\RoleCatalog;
use App\Support\Slug;
use App\Support\Teilnahmebeitraege;
use App\Support\TeilnehmendeNachrichten;
use App\Support\Testsystem;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Geber;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class EventForm
{
    /** Eurobetrag als Eingabe, gespeichert in Cent. */
    public static function euro(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->suffix('€')->inputMode('decimal')->rules(['nullable', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'])
            ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : number_format($state / 100, 2, ',', ''))
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round((float) str_replace(',', '.', (string) $state) * 100));
    }

    /** Kosten- und Finanzierungsplan und Berichtsfelder für die Maske „Zuschussantrag“. @return list<Fieldset> */
    public static function zuschussFelder(): array
    {
        return [
            Fieldset::make('Kosten- und Finanzierungsplan')->columns(3)->columnSpanFull()
                ->components([
                    ...collect([
                        'fahrt' => 'Fahrt', 'unterkunft' => 'Übernachtung/Unterkunft', 'verpflegung' => 'Verpflegung',
                        'raummiete' => 'Raummieten', 'material' => 'Arbeitsmaterialien', 'durchfuehrung' => 'Durchführung, Vor- und Nachbereitung',
                        'honorare' => 'Honorare',
                    ])->map(fn (string $label, string $key) => self::euro("zuschuss_finanzen.{$key}", $label))->values()->all(),
                    self::euro('zuschuss_finanzen.teilnahmebeitraege', 'Teilnahmebeiträge (Einnahme)')
                        ->helperText('Leer: Kluft nimmt die Summe der Teilnahmebeiträge bestätigter Anmeldungen.'),
                    self::euro('zuschuss_finanzen.spenden', 'Spenden, andere Zuschüsse (Einnahme)'),
                    self::euro('zuschuss_finanzen.sonstige', 'Sonstige Einnahmen'),
                ]),
            Fieldset::make('Für Bericht und Anträge')->columns(2)->columnSpanFull()
                ->components([
                    TextInput::make('zuschuss_bericht.thema')->label('Thema / Schwerpunkt')->maxLength(200)
                        ->helperText('Bildung und Schulung (Formular E), Bistum (Programmfolge).'),
                    TextInput::make('zuschuss_bericht.unterkunft')->label('Unterkunft (z. B. Zeltplatz)')->maxLength(200)
                        ->helperText('Für die Aufenthaltsbestätigung (Formular C).'),
                    Textarea::make('zuschuss_bericht.programm')->label('Programm (eine Zeile je Programmpunkt)')->rows(6)->maxLength(5000)->columnSpanFull()
                        ->placeholder("Sa 10–12 Uhr: Geländespiel – Referent*in\nSa 14–17 Uhr: Workshop Erste Hilfe"),
                    Textarea::make('zuschuss_bericht.methodik')->label('Inhalt, Methodik, Zielsetzung')->rows(3)->maxLength(3000)
                        ->helperText('Formular E und Stadt Saarlouis.'),
                    Textarea::make('zuschuss_bericht.bericht')->label('Sachbericht / Ergebnis der Bewertung')->rows(3)->maxLength(3000)
                        ->helperText('Leer: „wurde programmgemäß durchgeführt“.'),
                    TextInput::make('zuschuss_bericht.entfernung_km')->label('Entfernung Saarlouis – Ziel (km)')->numeric()->minValue(0)->maxValue(5000)
                        ->helperText('Für den Antrag der Stadt Saarlouis.'),
                ]),
        ];
    }

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
                        ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set('slug', Slug::ausTitel($state)))
                        ->columnSpan(2),
                    Slug::feld(TextInput::make('slug'))
                        ->label('URL-Pfad (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        // Ohne Jahreszahl ist die Adresse im nächsten Jahr belegt: Hinweis und Knopf.
                        ->helperText(fn (Get $get) => self::ohneJahr($get('slug'))
                            ? new HtmlString('Erreichbar unter /veranstaltungen/{slug}. <span class="font-medium text-warning-600 dark:text-warning-400">Ohne Jahreszahl ist die Adresse im nächsten Jahr schon belegt.</span>')
                            : 'Erreichbar unter /veranstaltungen/{slug}')
                        ->hintAction(Action::make('jahrAnhaengen')
                            ->label(fn (Get $get) => 'Jahr anhängen ('.self::jahr($get).')')
                            ->icon('heroicon-m-plus')
                            ->color('warning')
                            ->visible(fn (Get $get) => self::ohneJahr($get('slug')))
                            ->action(fn (Get $get, Set $set) => $set('slug', Slug::bereinigen($get('slug').'-'.self::jahr($get))))),
                    TextInput::make('location')->label('Ort')->maxLength(255),
                    // Nextcloud-Termin, aus dem die Veranstaltung angelegt wurde.
                    Hidden::make('calendar_uid'),
                    // Zeit wie im Apple-Kalender: Schalter „Ganztägig“, Datum und Uhrzeit
                    // getrennt. Gespeichert werden weiter starts_at/ends_at (zusammengesetzt).
                    Toggle::make('ganztaegig')->label('Ganztägig')->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::zeitZusammensetzen($get, $set))
                        ->columnSpanFull(),
                    Hidden::make('starts_at'),
                    Hidden::make('ends_at'),
                    Grid::make(2)->columnSpan(1)->schema([
                        DatePicker::make('beginn_datum')->label('Beginn')->dehydrated(false)
                            ->required(fn (Get $get) => blank($get('starts_at')))
                            ->validationMessages(['required' => 'Bitte das Datum des Beginns angeben.'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                // Wie im Apple-Kalender: Ende rückt mit, wenn es fehlt oder davor liegt.
                                if ($state && (blank($get('ende_datum')) || $get('ende_datum') < $state)) {
                                    $set('ende_datum', $state);
                                }
                                self::zeitZusammensetzen($get, $set);
                            })
                            ->columnSpan(fn (Get $get) => $get('ganztaegig') ? 2 : 1),
                        TimePicker::make('beginn_zeit')->label('Uhrzeit')->seconds(false)->dehydrated(false)
                            ->hidden(fn (Get $get) => (bool) $get('ganztaegig'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                // Ende eine Stunde später vorschlagen, solange keins gesetzt ist.
                                if ($state && blank($get('ende_zeit')) && $get('ende_datum') === $get('beginn_datum')) {
                                    $set('ende_zeit', Carbon::parse($state)->addHour()->format('H:i'));
                                }
                                self::zeitZusammensetzen($get, $set);
                            }),
                    ]),
                    Grid::make(2)->columnSpan(1)->schema([
                        DatePicker::make('ende_datum')->label('Ende')->dehydrated(false)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::zeitZusammensetzen($get, $set))
                            ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                if (filled($get('starts_at')) && filled($get('ends_at')) && $get('ends_at') < $get('starts_at')) {
                                    $fail('Das Ende liegt vor dem Beginn.');
                                }
                            })
                            ->columnSpan(fn (Get $get) => $get('ganztaegig') ? 2 : 1),
                        TimePicker::make('ende_zeit')->label('Uhrzeit')->seconds(false)->dehydrated(false)
                            ->hidden(fn (Get $get) => (bool) $get('ganztaegig'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::zeitZusammensetzen($get, $set)),
                    ]),
                    Select::make('stage')
                        ->label('Stufe')
                        ->options(fn () => self::stageOptions())
                        ->in(fn () => array_keys(self::stageOptions()))
                        ->native(false)
                        ->visible(fn () => self::stageOptions() !== [])
                        ->required(fn (Get $get) => ! self::isGlobal() && blank($get('section')))
                        ->live()
                        ->helperText('Bestimmt, wer die Teilnehmerdaten sehen darf. Leer = '.config('dpsg.site.level').' (nur Verwaltung). „Leiter*innen“: für Leitende, dazu liest der Vorstand mit.'),
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
                    TextInput::make('contact_name')->label('Kontaktperson')->maxLength(255)
                        // Nur der Name; die öffentliche Kontakt-E-Mail bleibt eine Funktionsadresse.
                        ->suffixAction(self::personSuchen('kontakt', 'Kontaktperson suchen', fn (array $person, Set $set) => $set('contact_name', $person['name']))),
                    TextInput::make('contact_email')->label('Kontakt-E-Mail')->email()->maxLength(255)
                        ->helperText('Bitte eine Funktionsadresse (z. B. woelflinge@'.config('dpsg.site.domain').').'),
                    Fieldset::make('Info-Mails an das Team')
                        ->columnSpan(2)
                        ->columns(2)
                        ->components([
                            Toggle::make('info_sammeladresse')->label('Sammeladresse informieren')->default(true)
                                ->helperText(fn () => 'Bei jeder bestätigten Anmeldung, Nachrücken und Abmeldung eine kurze Mail an '.(AnmeldeInfos::sammeladresse() ?? '– (keine Sammeladresse eingestellt)').'. Ändern: Veranstaltungen → „Sammeladresse“.'),
                            Toggle::make('info_verantwortlich')->label('Verantwortliche Person informieren')->default(true)->live()
                                ->helperText('z. B. Bildungsreferent*in oder DL-Mitglied. Ohne Adresse: wer die Veranstaltung angelegt hat.'),
                            TextInput::make('verantwortlich_email')->label('Verantwortlich (E-Mail)')->email()->maxLength(255)
                                ->default(fn () => Filament::auth()->user()?->email)
                                ->placeholder(fn (?Event $record) => $record?->creator?->email ?? Filament::auth()->user()?->email)
                                ->datalist(fn () => AnmeldeInfos::vorschlaege())
                                ->suffixAction(self::personSuchen('verantwortlich', 'Verantwortliche Person suchen', fn (array $person, Set $set) => $set('verantwortlich_email', $person['email'])))
                                ->visible(fn (Get $get) => (bool) $get('info_verantwortlich'))
                                ->columnSpan(2),
                        ]),
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
                    Toggle::make('extern_freigegeben')
                        ->label('Vorab extern freigeben')
                        ->helperText(fn (?Event $record) => 'Für die Zeit vor dem Livegang: Die Veranstaltung ist auch auf dem geschlossenen Testsystem öffentlich, Mails zu ihren Anmeldungen gehen echt raus, und das Formular lässt sich als iframe einbinden'
                            .(config('dpsg.einbetten_von') !== [] ? ' (von '.implode(', ', (array) config('dpsg.einbetten_von')).')' : '')
                            .($record?->slug ? ': '.route('events.embed', $record->slug) : '').'.')
                        ->visible(fn () => Testsystem::aktiv() || config('dpsg.einbetten_von') !== [])
                        ->columnSpanFull(),
                    TextInput::make('capacity')->label('Teilnehmerlimit')->numeric()->minValue(1)->helperText('Leer = unbegrenzt'),
                    DateTimePicker::make('reg_opens_at')->label('Anmeldung ab')->seconds(false)->helperText('Leer = sofort'),
                    DateTimePicker::make('reg_closes_at')->label('Anmeldeschluss')->seconds(false)->helperText('Leer = bis zum Beginn'),
                    Toggle::make('waitlist_enabled')->label('Warteliste, wenn ausgebucht'),
                    Toggle::make('mehrere_personen')->label('Mehrere Personen in einer Anmeldung (Familien)')
                        ->helperText('Im Formular „Weitere Person hinzufügen“: bis zu 5 weitere mit Vorname, Nachname, Geburtsjahr und Teilnahmebeitrag; alle übrigen Angaben werden von der ersten Person übernommen. Eine Bestätigungsmail, je Person eine Anmeldung.'),
                    Toggle::make('foto_einwilligung')->label('Foto-Einwilligung abfragen')
                        ->helperText('Im Formular eine freiwillige Ja/Nein-Frage je Person, ob Fotos auf der Website und in euren Kanälen gezeigt werden dürfen. Die Antwort steht in der Teilnehmendenliste und bei den Fotos.'),
                    Toggle::make('adults_only')->label('Für Erwachsene (eigenverantwortliche Anmeldung)')
                        ->helperText('Z. B. Elternabend, Leitungsrunde oder Schulung: Alle melden sich selbst an, ohne Abschnitt für Personensorgeberechtigte. Wer laut Geburtsjahr jünger als '.config('dpsg.guardian_consent_age').' sein könnte, kann sich nicht anmelden.'),
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
                    Repeater::make('preise')
                        ->label('Teilnahmebeitrag')
                        ->schema([
                            TextInput::make('bezeichnung')->label('Bezeichnung')->required()->maxLength(100)->placeholder('z. B. Mitglied, Nichtmitglied, Geschwisterkind'),
                            TextInput::make('cent')->label('Betrag')->suffix('€')->required()->rules(BeitragResource::EURO)->inputMode('decimal')
                                ->formatStateUsing(fn ($state) => is_numeric($state) ? BeitragResource::alsEuro((int) $state) : $state)
                                ->dehydrateStateUsing(fn ($state) => BeitragResource::alsCent($state)),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Preisstufe hinzufügen')
                        ->reorderable()
                        ->columnSpan(2)
                        ->helperText(fn () => 'Leer = kein Teilnahmebeitrag. Bei mehreren Stufen wählt die Person bei der Anmeldung. '
                            .(Teilnahmebeitraege::aktiv()
                                ? 'Nach der Bestätigung geht automatisch eine Zahlungsaufforderung mit GiroCode raus; den Stand sieht die Kasse unter „Kasse → Zahlungen“.'
                                : 'Zahlungsaufforderungen gibt es, sobald unter Verwaltung → Funktionen „Zahlungen“ an ist und eine Bankverbindung hinterlegt ist; bis dahin steht der Betrag nur als Information an der Anmeldung.')),
                    TextInput::make('zahlungsfrist_tage')->label('Zahlungsfrist (Tage)')->numeric()->minValue(1)->maxValue(120)->placeholder('14')
                        ->helperText('Ab Bestätigung, höchstens bis zum Vortag der Veranstaltung.'),
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

            Section::make('Zuschuss')
                ->description('Für Jugendamt, Landesjugendamt, Stadt und Bistum. Ist „Zuschuss beantragen“ an, fragt das Anmeldeformular Anschrift, Geburtsdatum und Geschlecht als Pflicht ab.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn () => Zuschuesse::aktiv())
                ->components([
                    Toggle::make('zuschuss')->label('Zuschuss beantragen')->live()->columnSpanFull(),
                    Select::make('zuschuss_art')->label('Art der Maßnahme')->options(Zuschuesse::ARTEN)->native(false)
                        ->required(fn (Get $get) => (bool) $get('zuschuss'))->visible(fn (Get $get) => (bool) $get('zuschuss')),
                    CheckboxList::make('zuschuss_geber')->label('Zuschussgeber')->options(fn (?Event $record) => Geber::auswahl($record))
                        ->helperText('Welche zur Wahl stehen, legt die Verwaltung unter „Zuschussgeber“ fest. Ohne Auswahl: alle eingeschalteten.')
                        ->visible(fn (Get $get) => (bool) $get('zuschuss')),
                    Toggle::make('zuschuss_fern')->label('Ziel weiter als 250 km entfernt')->helperText('Die Stadt Saarlouis fördert Lager dann mit 2,50 € statt 1,50 € je Tag.')
                        ->visible(fn (Get $get) => (bool) $get('zuschuss')),
                    Text::make('Kosten, Programm, Antragsteller, Fristen und Bewilligungen stehen gesammelt in der Maske „Zuschussantrag“ (Knopf oben, nach dem Speichern).')
                        ->columnSpanFull()->visible(fn (Get $get) => (bool) $get('zuschuss')),
                ]),

            // Erinnerung vor Beginn (docs/plan-ehrenamt.md Nr. 1, Befehl veranstaltungen:erinnerungen).
            Section::make('Erinnerung an die Teilnehmenden')
                ->description('Eine Mail an alle Bestätigten einige Tage vor Beginn, einmal. Wird das Datum geändert, wird neu erinnert.')
                ->columns(3)
                ->columnSpanFull()
                ->collapsible()
                ->components([
                    Select::make('erinnerung_tage')->label('Erinnerung')->placeholder('keine')
                        ->options(collect([1, 2, 3, 5, 7, 10, 14])->mapWithKeys(fn (int $t) => [$t => $t === 1 ? '1 Tag vorher' : $t.' Tage vorher'])->all())
                        ->live(),
                    Textarea::make('erinnerung_text')->label('Text der Erinnerung')->rows(4)->maxLength(5000)->columnSpan(2)
                        ->placeholder(TeilnehmendeNachrichten::ERINNERUNG_STANDARD)
                        ->helperText(fn (?Event $record) => 'Leer: der Standardtext. Anrede und Kasten mit Datum, Ort und Link kommen automatisch dazu.'
                            .($record?->erinnerung_gesendet_am ? ' Verschickt am '.$record->erinnerung_gesendet_am->format('d.m.Y H:i').'.' : ''))
                        ->visible(fn (Get $get) => filled($get('erinnerung_tage'))),
                ]),

            Section::make('Formular-Baukasten')
                ->description('Name und E-Mail werden immer abgefragt. Frag nur ab, was du für diese Veranstaltung wirklich brauchst (Datensparsamkeit).')
                ->columns(3)
                ->columnSpanFull()
                ->components([...collect(config('dpsg.event_fields'))->map(fn (array $field, string $key) => Select::make("form_fields.{$key}")
                    ->label($field['label'])
                    ->helperText($field['hint'] ?? null)
                    ->options(['none' => 'Nicht abfragen', 'optional' => 'Optional', 'required' => 'Pflichtfeld'])
                    ->in(['none', 'optional', 'required'])
                    ->default('none')
                    ->afterStateHydrated(fn (Select $component, ?string $state) => $component->state($state ?: 'none'))
                    ->selectablePlaceholder(false)
                    ->native(false))->values()->all(),
                    Repeater::make('form_fields.eigene')
                        ->label('Eigene Felder')
                        ->helperText('Für Fragen, die es oben nicht gibt, z. B. „Anzahl der Kinder“ oder „Welche Aktion?“. Sie stehen im Formular vor den Anmerkungen, in der Liste der Anmeldungen und im Export. Wird ein Feld gelöscht, verschwinden auch die Antworten darauf aus der Anzeige.')
                        ->schema([
                            Hidden::make('schluessel'),
                            TextInput::make('label')->label('Frage')->required()->maxLength(150)->columnSpan(2),
                            Select::make('typ')->label('Art')->options(Event::EIGENE_TYPEN)->default('text')->required()
                                ->in(array_keys(Event::EIGENE_TYPEN))->selectablePlaceholder(false)->native(false)->live(),
                            Textarea::make('optionen')->label('Antwortmöglichkeiten')->helperText('Eine pro Zeile.')->rows(3)->columnSpan(2)
                                ->required(fn (Get $get) => in_array($get('typ'), ['auswahl', 'mehrfach'], true))
                                ->visible(fn (Get $get) => in_array($get('typ'), ['auswahl', 'mehrfach'], true)),
                            Toggle::make('pflicht')->label('Pflichtfeld')->inline(false),
                            Toggle::make('sensibel')->label('Sensible Angabe')->inline(false)->columnSpan(2)
                                ->helperText(fn () => 'Z. B. Gesundheit, Allergien, Medikamente, Ernährung. Nicht im Excel-Export und in der Liste für unterwegs (außer ausdrücklich gewählt), gelöscht '.Event::sensibleTage().' Tage nach der Veranstaltung, mit Hinweis im Formular.'),
                            TextInput::make('hinweis')->label('Hinweis unter dem Feld (optional)')->maxLength(300)->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->maxItems(Event::EIGENE_MAX)
                        ->collapsible()
                        ->itemLabel(fn (array $state) => filled($state['label'] ?? null) ? $state['label'].' ('.(Event::EIGENE_TYPEN[$state['typ'] ?? ''] ?? '').')'.(($state['sensibel'] ?? false) ? ' · sensibel' : '') : null)
                        ->addActionLabel('Eigenes Feld hinzufügen'),
                ]),

            Section::make('Zusatzinfos fürs Team')
                ->description('Angaben, die nicht im Anmeldeformular stehen, sondern das Team einträgt – z. B. Zimmer, Zelt, Platz oder Gruppe. Zu sehen und zu bearbeiten in der Teilnehmendenliste („Angaben bearbeiten“) und unter „Vor Ort“; wer den gleichen Wert hat (z. B. gleiches Zimmer), wird dort zusammen angezeigt.')
                ->columnSpanFull()
                ->collapsible()
                ->components([
                    TagsInput::make('zusatz_felder')->label('Felder')
                        ->placeholder('z. B. Zimmer, Zelt, Platz – mit Enter bestätigen')
                        ->splitKeys([',', 'Enter'])
                        ->reorderable()
                        ->nestedRecursiveRules(['string', 'max:60'])
                        ->rules(['array', 'max:'.Event::ZUSATZ_MAX])
                        ->helperText('Höchstens '.Event::ZUSATZ_MAX.'. Wird ein Feld umbenannt, sind die bisherigen Werte darunter nicht mehr zu sehen.'),
                ]),

            Section::make('Teilnahmeliste mit Unterschriften')
                ->description('Für Veranstaltungen mit Zuschussantrag ist das immer an. Sonst hier einschalten, wenn die Teilnehmenden unter „Vor Ort“ auf dem Tablet unterschreiben sollen.')
                ->columnSpanFull()
                ->collapsible()
                ->components([
                    Toggle::make('unterschriftenliste')->label('Teilnahmeliste mit digitalen Unterschriften (Vor Ort)')
                        ->helperText('Die Liste gibt es dann unter „Teilnehmende → Teilnahmeliste (PDF)“; mit „Festschreiben“ wird sie samt Unterschriften abgelegt, die Einzelunterschriften werden danach gelöscht.'),
                ]),

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
                        ->helperText(fn (Get $get) => 'Standard 90 Tage (Pflichtenheft 9.3). Unbestätigte Anmeldungen werden nach 48 Stunden gelöscht. Als „sensibel“ markierte Angaben löschen wir schon '.Event::sensibleTage().' Tage nach der Veranstaltung.'
                            .($get('zuschuss') ? ' Zuschuss: Die Frist muss bis nach der Abrechnung reichen. Die eingereichte Teilnahmeliste und den Verwendungsnachweis bewahrt ihr nach den Vorgaben des Zuschussgebers gesondert auf (oft mehrere Jahre) – nicht hier.' : '')),
                ]),
        ]);
    }

    /**
     * Lupe am Feld: Person aus Benutzerkonten und Personen der Website suchen
     * und übernehmen.
     *
     * @param  Closure(array{name: string, email: string}, Set): void  $uebernehmen
     */
    private static function personSuchen(string $name, string $titel, Closure $uebernehmen): Action
    {
        return Action::make('suchen_'.$name)
            ->label('Suchen')
            ->icon('heroicon-o-magnifying-glass')
            ->modalHeading($titel)
            ->modalSubmitActionLabel('Übernehmen')
            ->modalWidth('lg')
            ->schema([
                Select::make('person')
                    ->label('Name oder E-Mail')
                    ->placeholder('Mindestens zwei Buchstaben eintippen …')
                    ->searchable()
                    ->required()
                    ->searchPrompt('Mindestens zwei Buchstaben eintippen')
                    ->noSearchResultsMessage('Niemand gefunden. Du kannst die Angabe auch von Hand eintragen.')
                    ->getSearchResultsUsing(fn (string $search) => PersonenSuche::suchen($search, Filament::auth()->user()))
                    ->getOptionLabelUsing(fn (?string $value) => PersonenSuche::label($value, Filament::auth()->user()))
                    ->helperText('Sucht in Benutzerkonten und Personen der Website.'),
            ])
            ->action(function (array $data, Set $set) use ($uebernehmen) {
                $person = PersonenSuche::finden($data['person'] ?? null, Filament::auth()->user());
                if ($person) {
                    $uebernehmen($person, $set);
                }
            });
    }

    /** Slug ohne Jahreszahl (z. B. „spirihike“ statt „spirihike-2027“)? */
    public static function ohneJahr(mixed $slug): bool
    {
        return filled($slug) && ! preg_match('/(?<!\d)(19|20)\d{2}(?!\d)/', (string) $slug);
    }

    /** Jahr der Veranstaltung (Beginn), sonst das aktuelle. */
    private static function jahr(Get $get): string
    {
        $datum = $get('beginn_datum') ?: $get('starts_at');

        return filled($datum) ? Carbon::parse($datum)->format('Y') : now()->format('Y');
    }

    /**
     * Felder für Datum und Uhrzeit aus Beginn/Ende (Bearbeiten, Kalender-Übernahme).
     *
     * @return array{beginn_datum: ?string, beginn_zeit: ?string, ende_datum: ?string, ende_zeit: ?string}
     */
    public static function zeitFelder(mixed $beginn, mixed $ende, bool $ganztaegig): array
    {
        // In der Zeitzone der Website (beim Bearbeiten kommen ISO-Zeitstempel in UTC).
        $b = filled($beginn) ? Carbon::parse($beginn)->setTimezone(config('app.timezone')) : null;
        $e = filled($ende) ? Carbon::parse($ende)->setTimezone(config('app.timezone')) : null;

        return [
            'beginn_datum' => $b?->toDateString(),
            'beginn_zeit' => $b && ! $ganztaegig ? $b->format('H:i') : null,
            'ende_datum' => $e?->toDateString(),
            'ende_zeit' => $e && ! $ganztaegig ? $e->format('H:i') : null,
        ];
    }

    /** Beginn und Ende aus Datum, Uhrzeit und „Ganztägig“ zusammensetzen. */
    public static function zeitZusammensetzen(Get $get, Set $set): void
    {
        $zeit = fn (?string $z, string $sonst) => filled($z) ? substr((string) $z, 0, 5) : $sonst;
        $datum = $get('beginn_datum');
        if (blank($datum)) {
            return;
        }
        $ganztaegig = (bool) $get('ganztaegig');
        $set('starts_at', $datum.' '.($ganztaegig ? '00:00' : $zeit($get('beginn_zeit'), '00:00')).':00');

        $endeDatum = $get('ende_datum');
        $endeZeit = $get('ende_zeit');
        if ($ganztaegig) {
            $set('ends_at', ($endeDatum ?: $datum).' 23:59:00');
        } elseif (blank($endeDatum) && blank($endeZeit)) {
            $set('ends_at', null);
        } else {
            $set('ends_at', ($endeDatum ?: $datum).' '.$zeit($endeZeit, '23:59').':00');
        }
    }

    private static function isGlobal(): bool
    {
        return (bool) Filament::auth()->user()?->darfUeberall('veranstaltungen.bearbeiten');
    }

    /**
     * @return array<string, string>
     */
    public static function stageOptions(): array
    {
        // „Leiter*innen“ nur für Personen mit Rechten auf der ganzen Website;
        // Stufenadmins sehen nur ihre Stufen.
        $all = collect(Event::stufen());

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->bereicheFuer('veranstaltungen.bearbeiten', 'stage') ?? [])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function sectionOptions(): array
    {
        $all = collect(RoleCatalog::sections());

        return self::isGlobal()
            ? $all->all()
            : $all->only(Filament::auth()->user()?->bereicheFuer('veranstaltungen.bearbeiten', 'section') ?? [])->all();
    }
}
