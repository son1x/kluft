<?php

namespace App\Filament\Resources\Zuschussgeber;

use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Zuschussgeber\Pages\ManageZuschussgeber;
use App\Models\Zuschussgeber;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Geber;
use App\Support\Zuschuesse\Gebiete;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Zuschussgeber dieser Instanz (docs/pflichtenheft-zuschuesse.md, ZU-60 ff.):
 * eingebaute Vorlagen ein- und ausschalten, eigene mit Sätzen, Gebiet und
 * Frist anlegen (z. B. Kreisjugendamt oder Stadt eines anderen Stamms).
 */
class ZuschussgeberResource extends Resource
{
    protected static ?string $model = Zuschussgeber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $modelLabel = 'Zuschussgeber';

    protected static ?string $pluralModelLabel = 'Zuschussgeber';

    protected static ?string $slug = 'zuschussgeber';

    protected static ?int $navigationSort = 75;

    public static function canAccess(): bool
    {
        return Zuschuesse::aktiv() && (bool) Filament::auth()->user()?->isVerwaltung();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canAccess() && $record instanceof Zuschussgeber && $record->eigen();
    }

    private static function eigen(?Zuschussgeber $record): bool
    {
        return $record === null || $record->eigen();
    }

    public static function form(Schema $schema): Schema
    {
        $arten = collect(Zuschuesse::ARTEN)->only(Geber::ARTEN);

        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(150)
                ->placeholder('z. B. Kreisjugendamt Merzig-Wadern'),
            Toggle::make('aktiv')->label('Eingeschaltet')->default(true)->inline(false)
                ->helperText('Nur eingeschaltete stehen bei Veranstaltungen zur Wahl.'),
            Text::make(fn (?Zuschussgeber $record) => 'Eingebaute Vorlage mit festen Regeln und eigenem Vordruck ('.(Geber::VORLAGEN[$record?->vorlage][1] ?? '').'). Hier lassen sich nur Name und Schalter ändern.')
                ->visible(fn (?Zuschussgeber $record) => ! self::eigen($record))->columnSpanFull(),

            Fieldset::make('Regeln')->columns(3)->columnSpanFull()->visible(fn (?Zuschussgeber $record) => self::eigen($record))
                ->components([
                    TextInput::make('regeln.richtlinie')->label('Richtlinie (Name, Fassung)')->maxLength(200)->columnSpan(2),
                    Select::make('regeln.vordruck')->label('Vordruck')->native(false)->selectablePlaceholder(false)->default('allgemein')
                        ->options(['allgemein' => 'Allgemeiner Antrag aus Kluft', 'formular_a' => 'Formular A (Saarland, Land und Kreise)'])
                        ->helperText('Formular A: Jugendämter im Saarland, Kreis über das Gebiet.'),
                    Textarea::make('regeln.anschrift')->label('Anschrift der Stelle')->rows(2)->maxLength(300)->columnSpanFull(),
                    ...$arten->map(fn (string $label, string $art) => EventForm::euro("regeln.saetze.{$art}", $label)
                        ->helperText('je Tag und Person; leer = nicht gefördert'))->values()->all(),
                    EventForm::euro('regeln.juleica_zuschlag', 'Zuschlag mit Juleica')->helperText('wenn eine Betreuungsperson Juleica hat'),
                    TextInput::make('regeln.min_tn')->label('Mindestens Teilnehmende')->numeric()->minValue(0)->maxValue(500),
                    TextInput::make('regeln.min_tage')->label('Mindestens Tage')->numeric()->minValue(1)->maxValue(60),
                    TextInput::make('regeln.max_tage')->label('Höchstens Tage')->numeric()->minValue(1)->maxValue(365),
                    TextInput::make('regeln.alter_von')->label('Alter ab')->numeric()->minValue(0)->maxValue(99),
                    TextInput::make('regeln.alter_bis')->label('Alter bis (einschließlich)')->numeric()->minValue(0)->maxValue(99),
                    Select::make('regeln.betreuer')->label('Betreuer*innen')->native(false)->selectablePlaceholder(false)->default('schluessel')
                        ->options(['schluessel' => 'bis 14 TN zwei, je weitere 7 TN eine', 'fuenf' => 'eine je 5 TN', 'alle' => 'alle', 'keine' => 'keine']),
                    Toggle::make('regeln.betreuer_ab_18')->label('Betreuer*innen nur ab 18')->inline(false),
                    EventForm::euro('regeln.mindestbetrag', 'Mindestbetrag')->helperText('darunter keine Auszahlung'),
                    Select::make('regeln.gebiet')->label('Wohngebiet')->multiple()->searchable()->columnSpan(2)
                        ->getSearchResultsUsing(fn (string $search) => collect(Gebiete::auswahl())->filter(fn (string $l) => str_contains(mb_strtolower($l), mb_strtolower($search)))->take(50)->all())
                        ->getOptionLabelsUsing(fn (array $values) => collect($values)->mapWithKeys(fn ($v) => [$v => Gebiete::name((string) $v)])->all())
                        ->helperText('Kreise oder Gemeinden in Saarland und Rheinland-Pfalz; leer = keine Bedingung.'),
                    Radio::make('regeln.gebiet_regel')->label('Wer zählt?')->default('nur')
                        ->options(['nur' => 'nur Personen aus dem Gebiet', 'mehrheit' => 'alle, wenn die Mehrheit dort wohnt'])
                        ->visible(fn (Get $get) => filled($get('regeln.gebiet'))),
                    TextInput::make('regeln.frist_wochen')->label('Frist: Wochen nach Ende')->numeric()->minValue(0)->maxValue(104),
                    TextInput::make('regeln.frist_monate')->label('oder Monate nach Ende')->numeric()->minValue(0)->maxValue(24)
                        ->helperText('Ohne Angabe: 2 Monate.'),
                    Textarea::make('regeln.hinweis')->label('Hinweis für die Schätzung')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label('Name')->weight('bold')
                    ->description(fn (Zuschussgeber $r) => $r->eigen()
                        ? implode(' · ', array_filter([(string) ($r->regeln['richtlinie'] ?? ''), $r->regeln['gebiet'] ?? null ? implode(', ', array_map([Gebiete::class, 'name'], (array) $r->regeln['gebiet'])) : null]))
                        : Geber::VORLAGEN[$r->vorlage][1] ?? null),
                TextColumn::make('vorlage')->label('Art')->badge()
                    ->state(fn (Zuschussgeber $r) => $r->eigen() ? 'eigene Regeln' : 'Vorlage')
                    ->color(fn (string $state) => $state === 'Vorlage' ? 'info' : 'gray'),
                ToggleColumn::make('aktiv')->label('Eingeschaltet'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->visible(fn (Zuschussgeber $r) => $r->eigen())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageZuschussgeber::route('/')];
    }
}
