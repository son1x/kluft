<?php

namespace App\Filament\Resources\Praevention;

use App\Filament\Resources\Praevention\Pages\CreatePraeventionNachweis;
use App\Filament\Resources\Praevention\Pages\EditPraeventionNachweis;
use App\Filament\Resources\Praevention\Pages\ListPraeventionNachweise;
use App\Models\Person;
use App\Models\PraeventionNachweis;
use App\Support\Praevention;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Präventions-Nachweise der Leitenden (docs/praevention.md): Führungszeugnis,
 * Präventionsschulung, Juleica, je mit „gültig bis“ und Ampel.
 */
class PraeventionResource extends Resource
{
    protected static ?string $model = PraeventionNachweis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'praevention';

    protected static ?string $navigationLabel = 'Prävention';

    protected static ?string $modelLabel = 'Nachweis';

    protected static ?string $pluralModelLabel = 'Präventions-Nachweise';

    protected static ?int $navigationSort = 34;

    public static function canViewAny(): bool
    {
        return Praevention::zugriff(Filament::auth()->user());
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

    public static function getNavigationBadge(): ?string
    {
        $n = PraeventionNachweis::query()->whereDate('gueltig_bis', '<=', today()->addDays(PraeventionNachweis::BALD_TAGE))->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Abgelaufen oder läuft in den nächsten 90 Tagen ab';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Person')->columns(2)->columnSpanFull()->components([
                Select::make('person_id')->label('Aus „Personen“ übernehmen')
                    ->options(fn () => Person::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->placeholder('Person suchen …')->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($person = Person::query()->find($state)) {
                            $set('name', $person->name);
                            $set('email', $person->email);
                        }
                    })
                    ->helperText('Optional. Leitende ohne Eintrag unter „Personen“ einfach mit Namen eintragen.')
                    ->columnSpanFull(),
                TextInput::make('name')->label('Name')->required()->maxLength(150),
                TextInput::make('email')->label('E-Mail')->email()->maxLength(255)
                    ->helperText('Für die Erinnerung vor Ablauf. Ohne Adresse geht sie nur an den Vorstand.'),
            ]),
            Section::make('Nachweis')->columns(2)->columnSpanFull()
                ->description('Gespeichert werden nur Daten: keine Kopie und keine Inhalte des Führungszeugnisses.')
                ->components([
                    ToggleButtons::make('art')->label('Art')->options(PraeventionNachweis::ARTEN)
                        ->default('efz')->required()->inline()->grouped()->live()->columnSpanFull(),
                    TextInput::make('bezeichnung')->label('Schulung')->placeholder('z. B. Basisschulung, Vertiefung')->maxLength(150)
                        ->visible(fn (Get $get) => $get('art') === 'schulung')->columnSpanFull(),
                    DatePicker::make('datum')->label(fn (Get $get) => PraeventionNachweis::DATUM_LABEL[$get('art')] ?? 'Datum')
                        ->native(false)->displayFormat('d.m.Y')->maxDate(today())->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                            if (filled($state) && blank($get('gueltig_bis')) && in_array($get('art'), ['efz', 'schulung'], true)) {
                                $set('gueltig_bis', Carbon::parse($state)->addYears(5)->toDateString());
                            }
                        }),
                    DatePicker::make('gueltig_bis')->label('Gültig bis')->required()->native(false)->displayFormat('d.m.Y')
                        ->helperText(fn (Get $get) => $get('art') === 'juleica' ? 'Steht auf der Karte.' : 'Wird mit 5 Jahren nach dem Datum vorbelegt; bitte nach eurer Regel anpassen.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('gueltig_bis')
            ->defaultGroup(Group::make('name')->label('Person')->collapsible())
            ->columns([
                TextColumn::make('art')->label('Art')->badge()->color('gray')
                    ->formatStateUsing(fn (PraeventionNachweis $record) => $record->artLabel())
                    ->description(fn (PraeventionNachweis $record) => $record->bezeichnung),
                TextColumn::make('ampel')->label('Status')->badge()
                    ->state(fn (PraeventionNachweis $record) => match ($record->ampel()) {
                        'abgelaufen' => 'Abgelaufen',
                        'bald' => 'Noch '.$record->restTage().' Tage',
                        default => 'Gültig',
                    })
                    ->color(fn (PraeventionNachweis $record) => match ($record->ampel()) {
                        'abgelaufen' => 'danger', 'bald' => 'warning', default => 'success',
                    })
                    ->icon(fn (PraeventionNachweis $record) => match ($record->ampel()) {
                        'abgelaufen' => Heroicon::OutlinedXCircle, 'bald' => Heroicon::OutlinedClock, default => Heroicon::OutlinedCheckCircle,
                    }),
                TextColumn::make('gueltig_bis')->label('Gültig bis')->date('d.m.Y')->sortable(),
                TextColumn::make('datum')->label('Datum')->date('d.m.Y')->placeholder('–')->toggleable()->visibleFrom('md'),
                TextColumn::make('name')->label('Person')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('art')->label('Art')->options(PraeventionNachweis::ARTEN),
                SelectFilter::make('status')->label('Status')
                    ->options(['handeln' => 'Abgelaufen oder bald fällig', 'abgelaufen' => 'Abgelaufen'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'handeln' => $query->whereDate('gueltig_bis', '<=', today()->addDays(PraeventionNachweis::BALD_TAGE)),
                        'abgelaufen' => $query->whereDate('gueltig_bis', '<', today()),
                        default => $query,
                    }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('Noch keine Nachweise')
            ->emptyStateDescription('Tragt hier ein, wann ihr das Führungszeugnis eingesehen habt und wer wann an einer Präventionsschulung teilgenommen hat. Kluft erinnert 90 und 30 Tage vor Ablauf.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPraeventionNachweise::route('/'),
            'create' => CreatePraeventionNachweis::route('/neu'),
            'edit' => EditPraeventionNachweis::route('/{record}/bearbeiten'),
        ];
    }
}
