<?php

namespace App\Filament\Resources\Umfragen;

use App\Filament\Resources\Umfragen\Pages\AuswertungUmfrage;
use App\Filament\Resources\Umfragen\Pages\CreateUmfrage;
use App\Filament\Resources\Umfragen\Pages\EditUmfrage;
use App\Filament\Resources\Umfragen\Pages\ListUmfragen;
use App\Models\Event;
use App\Models\Umfrage;
use App\Models\UmfrageFrage;
use App\Services\Umfragen\Einladungen;
use App\Support\AuditLogger;
use App\Support\Umfragen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Umfragen und Vorlagen (docs/umfragen.md): anlegen, kopieren, als Vorlage
 * speichern, Teilnehmende einer Veranstaltung einladen, auswerten.
 */
class UmfrageResource extends Resource
{
    protected static ?string $model = Umfrage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'umfragen';

    protected static ?string $navigationLabel = 'Umfragen';

    protected static ?string $modelLabel = 'Umfrage';

    protected static ?string $pluralModelLabel = 'Umfragen';

    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return Umfragen::zugriff(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Allgemein')->columns(2)->columnSpanFull()->components([
                TextInput::make('titel')->label('Titel')->required()->maxLength(200)->columnSpanFull(),
                Select::make('status')->label('Status')->options(Umfrage::STATUS)->default('entwurf')->required()->native(false)
                    ->hidden(fn (Get $get) => (bool) $get('ist_vorlage'))
                    ->helperText('Nur „Offen“ nimmt Antworten an (im Zeitraum, falls gesetzt).'),
                Toggle::make('anonym')->label('Anonym')->default(true)->inline(false)
                    ->helperText('Anonym: keine Namen, Mail-Adressen oder Uhrzeiten an den Antworten. Aus: Namen bzw. die Anmeldung der Person stehen bei der Antwort.'),
                DateTimePicker::make('beginnt_am')->label('Offen ab')->seconds(false)->native(false),
                DateTimePicker::make('endet_am')->label('Offen bis')->seconds(false)->native(false)->after('beginnt_am'),
                Select::make('event_id')->label('Nur für Teilnehmende einer Veranstaltung')->native(false)->searchable()
                    ->options(fn () => Event::query()->orderByDesc('starts_at')->limit(200)->get()->mapWithKeys(fn (Event $e) => [$e->id => $e->title.' ('.$e->starts_at?->format('d.m.Y').')'])->all())
                    ->helperText('Dann gibt es keinen öffentlichen Link: Teilnehmende bekommen per „Einladen“ einen persönlichen Link. Ohne Anonymität ist die Antwort ihrer Anmeldung zugeordnet (Name und Mail müssen nicht abgefragt werden).'),
                TextInput::make('zugangscode')->label('Zugangscode (optional)')->maxLength(50)
                    ->helperText('Wer den öffentlichen Link öffnet, muss den Code eingeben.'),
                Toggle::make('mehrfach')->label('Mehrfach aus einem Browser teilnehmen')->inline(false)
                    ->helperText('Aus (Standard): nach dem Absenden ist die Umfrage in diesem Browser erledigt. Ein harter Schutz ist das nicht.'),
                TextInput::make('aufbewahrung_monate')->label('Antworten löschen nach (Monaten)')->numeric()->integer()->minValue(1)->maxValue(60)->default(12)->required()
                    ->helperText('Gezählt ab dem Ende (bzw. der letzten Änderung, wenn kein Ende gesetzt ist).'),
                Hidden::make('ist_vorlage')->default(false),
            ]),
            Section::make('Texte')->columnSpanFull()->collapsible()->components([
                RichEditor::make('einleitung')->label('Einleitung')->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList'])->columnSpanFull(),
                RichEditor::make('danke')->label('Text nach dem Absenden')->toolbarButtons(['bold', 'italic', 'link'])->columnSpanFull()
                    ->placeholder('Danke für deine Teilnahme!'),
            ]),
            Section::make('Gewinnspiel')->columnSpanFull()->collapsible()->collapsed()->columns(2)->components([
                Toggle::make('gewinnspiel')->label('Nach dem Absenden zum Gewinnspiel einladen')->live()->inline(false)
                    ->helperText('Name und Mail werden getrennt von den Antworten gespeichert und lassen sich ihnen nicht zuordnen.'),
                Textarea::make('gewinnspiel_text')->label('Text zum Gewinnspiel')->rows(3)->visible(fn (Get $get) => (bool) $get('gewinnspiel'))
                    ->placeholder('Unter allen Teilnehmenden verlosen wir …'),
            ]),
            Section::make('Fragen')->columnSpanFull()->components([
                Repeater::make('fragen')->relationship()->hiddenLabel()->orderColumn('sort')
                    ->collapsible()->cloneable()->reorderableWithButtons()
                    ->addActionLabel('Frage hinzufügen')
                    ->defaultItems(0)
                    ->deletable(fn (?Umfrage $record) => ! $record?->antworten()->exists())
                    ->itemLabel(fn (array $state) => trim((Umfragen::TYPEN[$state['typ'] ?? ''] ?? 'Frage').': '.Str::limit((string) ($state['text'] ?? ''), 80)))
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::frageDaten($data))
                    ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::frageDaten($data))
                    ->schema(self::frageSchema()),
            ]),
        ]);
    }

    /** @return list<Component|Field> */
    private static function frageSchema(): array
    {
        $ist = fn (string ...$typen) => fn (Get $get) => in_array($get('typ'), $typen, true);
        $gesperrt = fn (?UmfrageFrage $record) => $record !== null && $record->umfrage?->antworten()->exists();

        return [
            Hidden::make('schluessel')->default(fn () => (string) Str::uuid()),
            Grid::make(3)->components([
                Select::make('typ')->label('Art')->options(Umfragen::TYPEN)->required()->live()->native(false)->default('einfach')
                    ->disabled($gesperrt)->dehydrated()
                    ->helperText(fn (?UmfrageFrage $record) => $gesperrt($record) ? 'Es gibt schon Antworten: Art und Antwortmöglichkeiten sind gesperrt.' : null),
                TextInput::make('text')->label(fn (Get $get) => $get('typ') === 'abschnitt' ? 'Überschrift' : 'Frage')->required()->maxLength(500)->columnSpan(2),
            ]),
            Textarea::make('hilfe')->label(fn (Get $get) => $get('typ') === 'abschnitt' ? 'Text' : 'Hilfetext (optional)')->rows(2),
            TagsInput::make('optionen')->label('Antwortmöglichkeiten')->reorderable()->splitKeys(['Tab'])
                ->visible($ist('einfach', 'mehrfach', 'bewertung'))
                ->required(fn (Get $get) => in_array($get('typ'), ['einfach', 'mehrfach'], true))
                ->disabled($gesperrt)->dehydrated()
                ->helperText(fn (Get $get) => $get('typ') === 'bewertung'
                    ? 'Beste zuerst. Leer: '.implode(', ', Umfragen::BEWERTUNG_STANDARD).'. Mit Enter übernehmen.'
                    : 'Jede Möglichkeit mit Enter übernehmen; Reihenfolge per Ziehen.'),
            Grid::make(4)->components([
                Toggle::make('pflicht')->label('Pflichtfrage')->inline(false)->visible(fn (Get $get) => $get('typ') !== 'abschnitt'),
                Toggle::make('kommentar')->label('Kommentarfeld dazu')->inline(false)->visible($ist('einfach', 'mehrfach', 'skala', 'bewertung', 'janein'))
                    ->helperText('„Hier hast du Platz für eigene Gedanken …“'),
                Toggle::make('einstellungen.sonstiges')->label('„Sonstiges: ___“ anbieten')->inline(false)->visible($ist('einfach', 'mehrfach')),
                TextInput::make('einstellungen.nicht_zutreffend')->label('Zusätzliche Antwort ohne Wertung')->placeholder('z. B. nicht teilgenommen')->maxLength(100)
                    ->visible($ist('bewertung')),
                TextInput::make('einstellungen.min')->label('Mindestens')->numeric()->integer()->minValue(0)->visible($ist('mehrfach')),
                TextInput::make('einstellungen.max')->label('Höchstens')->numeric()->integer()->minValue(0)->visible($ist('mehrfach')),
                Select::make('einstellungen.von')->label('Skala von')->options([0 => '0', 1 => '1'])->default(1)->native(false)->visible($ist('skala')),
                Select::make('einstellungen.bis')->label('bis')->options(array_combine(range(2, 10), range(2, 10)))->default(5)->native(false)->visible($ist('skala')),
                TextInput::make('einstellungen.label_von')->label('Beschriftung unten')->placeholder('trifft gar nicht zu')->maxLength(60)->visible($ist('skala')),
                TextInput::make('einstellungen.label_bis')->label('Beschriftung oben')->placeholder('trifft voll zu')->maxLength(60)->visible($ist('skala')),
            ]),
            Grid::make(2)->components([
                Select::make('bedingung.frage')->label('Nur zeigen, wenn bei …')->native(false)->live()->placeholder('immer zeigen')
                    ->options(fn (Get $get) => self::bedingungsFragen($get)),
                Select::make('bedingung.werte')->label('… geantwortet wurde')->multiple()->native(false)
                    ->visible(fn (Get $get) => filled($get('bedingung.frage')))
                    ->required(fn (Get $get) => filled($get('bedingung.frage')))
                    ->options(fn (Get $get) => self::bedingungsWerte($get)),
            ])->visible(fn (Get $get) => self::bedingungsFragen($get) !== []),
        ];
    }

    /**
     * Frühere Auswahlfragen derselben Umfrage (für „Nur zeigen, wenn …“).
     *
     * @return array<string, string>
     */
    private static function bedingungsFragen(Get $get): array
    {
        $eigener = (string) $get('schluessel');
        $fragen = [];
        foreach ((array) $get('../../fragen') as $item) {
            if (($item['schluessel'] ?? null) === $eigener) {
                break;
            }
            if (in_array($item['typ'] ?? null, Umfragen::AUSWAHL, true) && filled($item['schluessel'] ?? null)) {
                $fragen[$item['schluessel']] = Str::limit((string) ($item['text'] ?? 'Frage'), 70);
            }
        }

        return $fragen;
    }

    /** @return array<string, string> */
    private static function bedingungsWerte(Get $get): array
    {
        $ziel = (string) $get('bedingung.frage');
        foreach ((array) $get('../../fragen') as $item) {
            if (($item['schluessel'] ?? null) === $ziel) {
                $frage = new UmfrageFrage(['typ' => $item['typ'] ?? '', 'optionen' => $item['optionen'] ?? [], 'einstellungen' => $item['einstellungen'] ?? []]);
                $werte = Umfragen::moeglichkeiten($frage);

                return array_combine($werte, $werte) ?: [];
            }
        }

        return [];
    }

    /**
     * Leere Einstellungen und Bedingungen nicht speichern.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function frageDaten(array $data): array
    {
        $data['schluessel'] = filled($data['schluessel'] ?? null) ? $data['schluessel'] : (string) Str::uuid();
        $data['einstellungen'] = array_filter((array) ($data['einstellungen'] ?? []), fn ($v) => $v !== null && $v !== '' && $v !== false) ?: null;
        $data['bedingung'] = filled($data['bedingung']['frage'] ?? null) && ($data['bedingung']['werte'] ?? []) !== []
            ? ['frage' => (string) $data['bedingung']['frage'], 'werte' => array_values((array) $data['bedingung']['werte'])] : null;
        $data['optionen'] = array_values(array_filter(array_map('trim', (array) ($data['optionen'] ?? [])), fn ($o) => $o !== '')) ?: null;
        if (($data['typ'] ?? '') === 'abschnitt') {
            $data['pflicht'] = false;
            $data['kommentar'] = false;
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('antworten'))
            ->columns([
                TextColumn::make('titel')->label('Titel')->searchable()->wrap()
                    ->description(fn (Umfrage $u) => $u->event ? 'Für Teilnehmende: '.$u->event->title : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Umfrage $u) => $u->ist_vorlage ? 'Vorlage' : (Umfrage::STATUS[$u->status] ?? $u->status))
                    ->color(fn (Umfrage $u) => match (true) {
                        $u->ist_vorlage => 'gray', $u->istOffen() => 'success', $u->status === 'offen' => 'warning', default => 'gray',
                    }),
                TextColumn::make('endet_am')->label('Offen bis')->dateTime('d.m.Y H:i')->placeholder('–')->sortable(),
                TextColumn::make('antworten_count')->label('Antworten')->sortable(),
                IconColumn::make('anonym')->label('Anonym')->boolean(),
                TextColumn::make('updated_at')->label('Geändert')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('art')->label('Art')->options(['umfragen' => 'Umfragen', 'vorlagen' => 'Vorlagen', 'archiv' => 'Archiv'])->default('umfragen')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'vorlagen' => $query->where('ist_vorlage', true),
                        'archiv' => $query->where('ist_vorlage', false)->where('status', 'archiviert'),
                        'umfragen' => $query->where('ist_vorlage', false)->where('status', '!=', 'archiviert'),
                        default => $query,
                    }),
            ])
            ->recordUrl(fn (Umfrage $u) => $u->ist_vorlage ? static::getUrl('edit', ['record' => $u]) : static::getUrl('auswertung', ['record' => $u]))
            ->recordActions([
                Action::make('auswertung')->label('Auswertung')->icon('heroicon-o-chart-bar')
                    ->hidden(fn (Umfrage $u) => $u->ist_vorlage)
                    ->url(fn (Umfrage $u) => static::getUrl('auswertung', ['record' => $u])),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('link')->label('Link anzeigen')->icon('heroicon-o-link')
                        ->hidden(fn (Umfrage $u) => $u->ist_vorlage || $u->nurEingeladene())
                        ->modalHeading('Link zur Umfrage')->modalSubmitAction(false)->modalCancelActionLabel('Schließen')
                        ->modalDescription(fn (Umfrage $u) => $u->url().($u->zugangscode ? ' – Zugangscode: '.$u->zugangscode : '').($u->istOffen() ? '' : ' (zurzeit nicht offen)')),
                    self::einladenAction(),
                    Action::make('kopieren')->label('Kopieren')->icon('heroicon-o-document-duplicate')
                        ->requiresConfirmation()->modalDescription('Neue Umfrage mit denselben Fragen, ohne Antworten, als Entwurf.')
                        ->action(function (Umfrage $u) {
                            abort_unless(static::canViewAny(), 403);
                            $kopie = $u->kopieren(['titel' => mb_substr($u->titel.' (Kopie)', 0, 200), 'ist_vorlage' => false, 'erstellt_von' => Filament::auth()->id()]);
                            AuditLogger::log('umfrage.kopiert', Filament::auth()->user(), Umfrage::class, $kopie->id, request());
                            Notification::make()->title('Kopie angelegt')->success()->send();

                            return redirect(static::getUrl('edit', ['record' => $kopie]));
                        }),
                    Action::make('vorlage')->label('Als Vorlage speichern')->icon('heroicon-o-bookmark')
                        ->hidden(fn (Umfrage $u) => $u->ist_vorlage)
                        ->schema([TextInput::make('titel')->label('Name der Vorlage')->required()->maxLength(200)])
                        ->fillForm(fn (Umfrage $u) => ['titel' => $u->titel])
                        ->action(function (Umfrage $u, array $data) {
                            abort_unless(static::canViewAny(), 403);
                            $vorlage = $u->kopieren(['titel' => $data['titel'], 'ist_vorlage' => true, 'event_id' => null, 'beginnt_am' => null, 'endet_am' => null, 'zugangscode' => null, 'erstellt_von' => Filament::auth()->id()]);
                            AuditLogger::log('umfrage.vorlage', Filament::auth()->user(), Umfrage::class, $vorlage->id, request());
                            Notification::make()->title('Vorlage gespeichert')->success()->send();
                        }),
                    DeleteAction::make()->modalDescription('Die Umfrage wird mit allen Antworten und Gewinnspiel-Einträgen gelöscht.'),
                ]),
            ]);
    }

    public static function einladenAction(): Action
    {
        return Action::make('einladen')->label('Teilnehmende einladen')->icon('heroicon-o-paper-airplane')
            ->visible(fn (Umfrage $u) => $u->nurEingeladene() && ! $u->ist_vorlage)
            ->requiresConfirmation()
            ->modalDescription(fn (Umfrage $u) => 'Alle bestätigten Teilnehmenden von „'.$u->event?->title.'“ mit Mail-Adresse bekommen einen persönlichen Link. Wer schon eingeladen ist, bekommt keine zweite Mail.'
                .($u->istOffen() ? '' : ' Achtung: Die Umfrage ist zurzeit nicht offen.'))
            ->action(function (Umfrage $u) {
                abort_unless(static::canViewAny(), 403);
                $n = app(Einladungen::class)->einladen($u);
                AuditLogger::log('umfrage.eingeladen:'.$n, Filament::auth()->user(), Umfrage::class, $u->id, request());
                Notification::make()->title("{$n} Einladungen verschickt.")->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUmfragen::route('/'),
            'create' => CreateUmfrage::route('/create'),
            'edit' => EditUmfrage::route('/{record}/edit'),
            'auswertung' => AuswertungUmfrage::route('/{record}/auswertung'),
        ];
    }
}
