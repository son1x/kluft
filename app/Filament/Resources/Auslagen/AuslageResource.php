<?php

namespace App\Filament\Resources\Auslagen;

use App\Filament\Resources\Auslagen\Pages\CreateAuslage;
use App\Filament\Resources\Auslagen\Pages\EditAuslage;
use App\Filament\Resources\Auslagen\Pages\ListAuslagen;
use App\Filament\Resources\Auslagen\Pages\ViewAuslage;
use App\Mail\AuslageMail;
use App\Models\Auslage;
use App\Models\User;
use App\Support\Auslagen;
use App\Support\Zahlungen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use UnitEnum;

/**
 * Auslagen und Fahrtkosten erstatten (docs/auslagen.md): Alle mit Konto
 * reichen ein und sehen ihre eigenen; die Kasse sieht alle, gibt frei, lehnt
 * ab und zahlt aus (SEPA-Sammelüberweisung).
 */
class AuslageResource extends Resource
{
    protected static ?string $model = Auslage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = 'Mein Konto';

    protected static ?string $slug = 'auslagen';

    protected static ?string $navigationLabel = 'Auslagen erstatten';

    protected static ?string $modelLabel = 'Auslage';

    protected static ?string $pluralModelLabel = 'Auslagen';

    protected static ?int $navigationSort = 5;

    private static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function canViewAny(): bool
    {
        return Auslagen::zugriff(self::user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return Auslagen::kasse(self::user()) || (static::canViewAny() && $record->user_id === self::user()?->id);
    }

    public static function canEdit(Model $record): bool
    {
        /** @var Auslage $record */
        return $record->bearbeitbar() && (Auslagen::kasse(self::user()) || (static::canViewAny() && $record->user_id === self::user()?->id));
    }

    public static function canDelete(Model $record): bool
    {
        /** @var Auslage $record */
        return $record->bearbeitbar() && static::canViewAny() && $record->user_id === self::user()?->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return Auslagen::kasse(self::user()) ? $query : $query->where('user_id', self::user()?->id ?? 0);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Auslagen::kasse(self::user())) {
            return null;
        }
        $n = Auslage::query()->where('status', 'eingereicht')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Zu prüfen';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Was möchtest du erstattet haben?')->columns(2)->columnSpanFull()->components([
                ToggleButtons::make('art')->hiddenLabel()->options(Auslage::ARTEN)
                    ->icons(['betrag' => Heroicon::OutlinedReceiptPercent, 'fahrt' => Heroicon::OutlinedTruck])
                    ->default('betrag')->required()->inline()->grouped()->live()->columnSpanFull(),
                TextInput::make('anlass')->label('Wofür')->required()->maxLength(200)
                    ->placeholder(fn (Get $get) => $get('art') === 'fahrt' ? 'z. B. Fahrt zur DL-Sitzung' : 'z. B. Bastelmaterial Gruppenstunde')
                    ->columnSpanFull(),
                DatePicker::make('datum')->label(fn (Get $get) => $get('art') === 'fahrt' ? 'Tag der Fahrt' : 'Datum des Belegs')
                    ->required()->native(false)->displayFormat('d.m.Y')->maxDate(today())->default(today()),
                TextInput::make('betrag')->label('Betrag')->prefix('€')->required(fn (Get $get) => $get('art') === 'betrag')
                    ->inputMode('decimal')->placeholder('0,00')
                    ->regex('/^\d{1,5}([.,]\d{1,2})?$/')->validationMessages(['regex' => 'Bitte einen Betrag wie 12,50 eingeben.'])
                    ->visible(fn (Get $get) => $get('art') === 'betrag'),
                TextInput::make('km')->label('Gefahrene Kilometer')->suffix('km')->required(fn (Get $get) => $get('art') === 'fahrt')
                    ->inputMode('decimal')
                    ->regex('/^\d{1,4}([.,]\d)?$/')->validationMessages(['regex' => 'Bitte Kilometer wie 84 oder 84,5 eingeben.'])->live(onBlur: true)
                    ->helperText(fn () => 'Hin und zurück. Erstattet werden '.Zahlungen::euro(Auslagen::kmSatzCent()).' je km.')
                    ->visible(fn (Get $get) => $get('art') === 'fahrt'),
                TextInput::make('strecke')->label('Strecke')->maxLength(200)->placeholder('z. B. Trier – Saarbrücken und zurück')
                    ->visible(fn (Get $get) => $get('art') === 'fahrt')->columnSpanFull(),
                Placeholder::make('erstattung')->label('Erstattung')
                    ->content(fn (Get $get, ?Auslage $record) => Zahlungen::euro((int) round(((float) str_replace(',', '.', (string) $get('km'))) * ($record->km_satz_cent ?? Auslagen::kmSatzCent()))))
                    ->visible(fn (Get $get) => $get('art') === 'fahrt' && filled($get('km'))),
                FileUpload::make('belege')->label('Belege')->multiple()->maxFiles(10)->maxSize(10240)
                    ->disk(Auslage::DISK)->directory('auslagen')->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/heic', 'image/webp', 'application/pdf'])
                    ->required(fn (Get $get) => $get('art') === 'betrag')
                    ->openable()->downloadable()->panelLayout('grid')
                    ->helperText('Foto oder PDF der Quittung, bis 10 MB je Datei. Bei Fahrten nicht nötig.')
                    ->columnSpanFull(),
            ]),
            Section::make('Bankverbindung')->columns(2)->columnSpanFull()
                ->description('Wird verschlüsselt gespeichert und nach der Auszahlung an der Auslage gelöscht.')
                ->components([
                    TextInput::make('konto_inhaber')->label('Kontoinhaber*in')->required()->maxLength(70)
                        ->default(fn () => self::user()?->auslagen_konto['inhaber'] ?? (self::user()?->isRolle() ? null : self::user()?->name)),
                    TextInput::make('konto_iban')->label('IBAN')->required()->maxLength(42)
                        ->default(fn () => self::user()?->auslagen_konto['iban'] ?? null)
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! Zahlungen::ibanGueltig((string) $value)) {
                                $fail('Die IBAN ist nicht gültig.');
                            }
                        }),
                    Toggle::make('konto_merken')->label('Für die nächste Erstattung merken')->default(true)
                        ->visibleOn('create')->columnSpanFull(),
                ]),
            Section::make('Prüfung')->columnSpanFull()->visibleOn('view')->components([
                Placeholder::make('status_text')->hiddenLabel()
                    ->content(fn (?Auslage $record) => $record === null ? '' : Auslage::STATUS[$record->status]
                        .($record->prueferin ? ' von '.$record->prueferin->name : '')
                        .($record->geprueft_am ? ' am '.$record->geprueft_am->format('d.m.Y') : '')
                        .($record->ausgezahlt_am ? ', ausgezahlt am '.$record->ausgezahlt_am->format('d.m.Y') : '')
                        .($record->grund ? '. Grund: '.$record->grund : '')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $kasse = Auslagen::kasse(self::user());

        return $table
            ->defaultSort('datum', 'desc')
            ->columns([
                TextColumn::make('anlass')->label('Wofür')->searchable(['anlass', 'name'])->wrap()
                    ->description(fn (Auslage $record) => implode(' · ', array_filter([
                        $kasse ? $record->name : null,
                        $record->datum->format('d.m.Y'),
                        $record->beschreibung(),
                        count($record->belege ?? []) > 0 ? count($record->belege).' Beleg(e)' : null,
                    ]))),
                TextColumn::make('summe_cent')->label('Betrag')->alignEnd()->sortable()
                    ->formatStateUsing(fn (int $state) => Zahlungen::euro($state)),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Auslage::STATUS[$state] ?? $state)
                    ->color(fn (string $state) => Auslage::FARBEN[$state] ?? 'gray')
                    ->description(fn (Auslage $record) => $record->status === 'abgelehnt' ? $record->grund : null),
            ])
            ->recordActions([
                Action::make('freigeben')->label('Freigeben')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Auslage $record) => $kasse && $record->status === 'eingereicht')
                    ->requiresConfirmation()->modalDescription(fn (Auslage $record) => $record->name.': '.Zahlungen::euro($record->summe_cent).' für „'.$record->anlass.'“')
                    ->action(fn (Auslage $record) => self::status($record, 'freigegeben')),
                Action::make('ablehnen')->label('Ablehnen')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Auslage $record) => $kasse && $record->status === 'eingereicht')
                    ->schema([Textarea::make('grund')->label('Grund (geht an die Person)')->required()->maxLength(500)->rows(3)])
                    ->action(fn (Auslage $record, array $data) => self::status($record, 'abgelehnt', grund: $data['grund'])),
                Action::make('ausgezahlt')->label('Ausgezahlt')->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn (Auslage $record) => $kasse && $record->status === 'freigegeben')
                    ->schema([DatePicker::make('am')->label('Ausgezahlt am')->default(today())->required()->native(false)->displayFormat('d.m.Y')])
                    ->action(fn (Auslage $record, array $data) => self::status($record, 'ausgezahlt', am: $data['am'])),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()->label('Zurückziehen')->modalHeading('Auslage zurückziehen?'),
                ]),
            ])
            ->toolbarActions($kasse ? [
                BulkAction::make('sepa')->label('SEPA-Datei für die Bank')->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (Collection $records) {
                        try {
                            $xml = Auslagen::sepaXml($records);
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return null;
                        }

                        return response()->streamDownload(fn () => print ($xml), 'auslagen-'.now()->format('Y-m-d').'.xml', ['Content-Type' => 'application/xml']);
                    }),
                BulkAction::make('alleAusgezahlt')->label('Als ausgezahlt markieren')->icon(Heroicon::OutlinedBanknotes)
                    ->requiresConfirmation()->modalDescription('Nur freigegebene Auslagen; die Personen bekommen eine Mail, die Bankverbindung an der Auslage wird gelöscht.')
                    ->action(function (Collection $records) {
                        $n = $records->where('status', 'freigegeben')->each(fn (Auslage $a) => self::status($a, 'ausgezahlt', am: today()->toDateString(), leise: true))->count();
                        Notification::make()->title($n.' Auslage(n) als ausgezahlt markiert')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ] : [])
            ->emptyStateIcon(Heroicon::OutlinedReceiptRefund)
            ->emptyStateHeading('Noch keine Auslagen')
            ->emptyStateDescription('Hast du etwas für den Verband bezahlt oder bist gefahren? Reiche es hier ein, mit Foto der Quittung. Die Kasse prüft und überweist.');
    }

    public static function status(Auslage $record, string $status, ?string $grund = null, ?string $am = null, bool $leise = false): void
    {
        $record->update([
            'status' => $status,
            'grund' => $grund,
            'geprueft_von' => $status === 'ausgezahlt' && $record->geprueft_von ? $record->geprueft_von : self::user()?->id,
            'geprueft_am' => $status === 'ausgezahlt' && $record->geprueft_am ? $record->geprueft_am : now(),
            'ausgezahlt_am' => $status === 'ausgezahlt' ? $am : null,
        ]);
        Mail::to($record->email, $record->name)->send(new AuslageMail($record, $status));
        if (! $leise) {
            Notification::make()->title(Auslage::STATUS[$status])->success()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuslagen::route('/'),
            'create' => CreateAuslage::route('/neu'),
            'view' => ViewAuslage::route('/{record}'),
            'edit' => EditAuslage::route('/{record}/bearbeiten'),
        ];
    }
}
