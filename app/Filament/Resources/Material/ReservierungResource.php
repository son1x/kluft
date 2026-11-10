<?php

namespace App\Filament\Resources\Material;

use App\Filament\Resources\Material\Pages\CreateReservierung;
use App\Filament\Resources\Material\Pages\EditReservierung;
use App\Filament\Resources\Material\Pages\ListReservierungen;
use App\Mail\MaterialMail;
use App\Models\Material;
use App\Models\MaterialReservierung;
use App\Support\Materialverleih;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use UnitEnum;

/**
 * Reservierungen im Materialverleih: angefragt → zugesagt → ausgegeben →
 * zurück. Zusagen prüft die Belegung.
 */
class ReservierungResource extends Resource
{
    protected static ?string $model = MaterialReservierung::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Materialverleih';

    protected static ?string $slug = 'material-reservierungen';

    protected static ?string $navigationLabel = 'Reservierungen';

    protected static ?string $modelLabel = 'Reservierung';

    protected static ?string $pluralModelLabel = 'Reservierungen';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Materialverleih::zugriff(Filament::auth()->user());
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
        if (! static::canViewAny()) {
            return null;
        }
        $n = MaterialReservierung::query()->where('status', 'angefragt')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Offene Anfragen';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Was und wann')->columns(2)->columnSpanFull()->components([
                Select::make('material_id')->label('Material')->options(fn () => MaterialResource::auswahl())
                    ->searchable()->required()->live()->columnSpanFull()
                    ->default(fn () => request()->integer('material') ?: null),
                DatePicker::make('von')->label('Von')->required()->native(false)->displayFormat('d.m.Y')->live(),
                DatePicker::make('bis')->label('Bis')->required()->native(false)->displayFormat('d.m.Y')->afterOrEqual('von')->live(),
                TextInput::make('menge')->label('Anzahl')->integer()->minValue(1)->default(1)->required()->live(onBlur: true)
                    ->visible(fn (Get $get) => (Material::query()->find($get('material_id'))?->menge ?? 1) > 1),
                Placeholder::make('verfuegbar')->label('Verfügbar')
                    ->content(function (Get $get, ?MaterialReservierung $record) {
                        $m = Material::query()->find($get('material_id'));
                        if (! $m || blank($get('von')) || blank($get('bis'))) {
                            return 'Material und Zeitraum wählen.';
                        }
                        $frei = $m->frei($get('von'), $get('bis'), $record?->id);

                        return $frei === 0 ? 'Im Zeitraum schon vergeben.' : "{$frei} von {$m->menge} frei.";
                    }),
            ]),
            Section::make('Für wen')->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Name')->required()->maxLength(150),
                TextInput::make('gruppierung')->label('Gruppe / Stamm')->maxLength(150),
                TextInput::make('email')->label('E-Mail')->email()->maxLength(255)->helperText('Für Zusage, Absage und Rückgabe-Erinnerung.'),
                TextInput::make('telefon')->label('Telefon')->tel()->maxLength(40),
                Textarea::make('zweck')->label('Wofür')->rows(2)->maxLength(500)->columnSpanFull(),
            ]),
            Section::make('Status')->columns(2)->columnSpanFull()->components([
                Select::make('status')->label('Status')->options(MaterialReservierung::STATUS)->default('zugesagt')->required()->native(false),
                Toggle::make('benachrichtigen')->label('Bei Zusage oder Absage eine Mail schicken')->default(true)->inline(false)->dehydrated(false),
                Textarea::make('maengel')->label('Mängel bei der Rückgabe')->rows(2)->maxLength(2000)->columnSpanFull(),
                Textarea::make('notiz')->label('Interne Notiz')->rows(2)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('von')
            ->modifyQueryUsing(fn ($query) => $query->with('material'))
            ->columns([
                TextColumn::make('material.name')->label('Material')->searchable()->weight('bold')
                    ->description(fn (MaterialReservierung $record) => $record->zeitraum().($record->menge > 1 ? ' · '.$record->menge.' Stück' : '')),
                TextColumn::make('name')->label('Für')->searchable(['name', 'gruppierung'])
                    ->description(fn (MaterialReservierung $record) => $record->gruppierung),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => MaterialReservierung::STATUS[$state] ?? $state)
                    ->color(fn (string $state) => MaterialReservierung::FARBEN[$state] ?? 'gray')
                    ->description(fn (MaterialReservierung $record) => str_starts_with((string) $record->notiz, 'Spamverdacht') ? 'Spamverdacht' : null),
            ])
            ->filters([
                SelectFilter::make('material_id')->label('Material')->options(fn () => MaterialResource::auswahl()),
            ])
            ->recordActions([
                Action::make('zusagen')->label('Zusagen')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (MaterialReservierung $record) => $record->status === 'angefragt')
                    ->requiresConfirmation()
                    ->modalDescription(fn (MaterialReservierung $record) => $record->konflikt() ?? 'Frei. '.(filled($record->email) ? 'Die Person bekommt eine Mail.' : 'Keine E-Mail-Adresse hinterlegt.'))
                    ->action(function (MaterialReservierung $record) {
                        if ($fehler = $record->konflikt()) {
                            Notification::make()->title('Nicht zugesagt')->body($fehler.' Zeitraum oder Anzahl ändern oder absagen.')->danger()->persistent()->send();

                            return;
                        }
                        self::status($record, 'zugesagt');
                    }),
                Action::make('ausgeben')->label('Ausgegeben')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn (MaterialReservierung $record) => $record->status === 'zugesagt')
                    ->action(fn (MaterialReservierung $record) => self::status($record, 'ausgegeben')),
                Action::make('zurueck')->label('Zurück')->icon(Heroicon::OutlinedArrowDownTray)->color('success')
                    ->visible(fn (MaterialReservierung $record) => $record->status === 'ausgegeben')
                    ->schema([Textarea::make('maengel')->label('Mängel (optional)')->rows(3)->maxLength(2000)
                        ->helperText('Was fehlt oder ist kaputt? Bleibt an der Reservierung stehen.')])
                    ->action(fn (MaterialReservierung $record, array $data) => self::status($record, 'zurueck', $data['maengel'] ?? null)),
                ActionGroup::make([
                    Action::make('ablehnen')->label('Absagen')->icon(Heroicon::OutlinedXMark)->color('danger')
                        ->visible(fn (MaterialReservierung $record) => in_array($record->status, ['angefragt', 'zugesagt'], true))
                        ->schema([Textarea::make('grund')->label('Nachricht an die Person (optional)')->rows(3)->maxLength(500)])
                        ->action(fn (MaterialReservierung $record, array $data) => self::status($record, 'abgelehnt', grund: $data['grund'] ?? null)),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('Keine Reservierungen')
            ->emptyStateDescription('Reserviert über „Neue Reservierung“ oder die Belegung eines Materials.');
    }

    public static function status(MaterialReservierung $record, string $status, ?string $maengel = null, ?string $grund = null): void
    {
        $record->update(['status' => $status] + ($status === 'zurueck' ? ['maengel' => $maengel] : []));
        if (in_array($status, ['zugesagt', 'abgelehnt'], true) && filled($record->email)) {
            Mail::to($record->email, $record->name)->send(new MaterialMail($record, $status, $grund));
        }
        Notification::make()->title(MaterialReservierung::STATUS[$status])->success()->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservierungen::route('/'),
            'create' => CreateReservierung::route('/neu'),
            'edit' => EditReservierung::route('/{record}/bearbeiten'),
        ];
    }
}
