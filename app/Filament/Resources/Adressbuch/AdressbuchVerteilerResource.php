<?php

namespace App\Filament\Resources\Adressbuch;

use App\Filament\Resources\Adressbuch\Pages\CreateAdressbuchVerteiler;
use App\Filament\Resources\Adressbuch\Pages\EditAdressbuchVerteiler;
use App\Filament\Resources\Adressbuch\Pages\ListAdressbuchVerteiler;
use App\Models\AdressbuchVerteiler;
use App\Support\Adressbuch;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Verteiler im Adressbuch (docs/adressbuch.md).
 */
class AdressbuchVerteilerResource extends Resource
{
    protected static ?string $model = AdressbuchVerteiler::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'verteiler';

    protected static ?string $navigationLabel = 'Verteiler';

    protected static ?string $modelLabel = 'Verteiler';

    protected static ?string $pluralModelLabel = 'Verteiler';

    protected static ?int $navigationSort = 33;

    public static function canViewAny(): bool
    {
        return Adressbuch::zugriff(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof AdressbuchVerteiler && Adressbuch::darfBearbeiten(Filament::auth()->user(), $record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return Adressbuch::sichtbareVerteiler(Filament::auth()->user())->with('user')->withCount('kontakte');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                TextInput::make('name')->label('Name')->required()->maxLength(100)->placeholder('z. B. DAK Wölflinge'),
                Toggle::make('privat')->label('Nur für mich')->inline(false)
                    ->helperText('Aus: Alle mit Zugriff aufs Adressbuch sehen und nutzen den Verteiler. An: nur du (und die Verwaltung).'),
                Textarea::make('beschreibung')->label('Beschreibung')->rows(2)->maxLength(1000)->columnSpanFull(),
                Select::make('kontakte')->label('Kontakte')->columnSpanFull()
                    ->relationship('kontakte', 'email')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->anzeige())
                    ->multiple()->searchable(['name', 'email', 'organisation'])->preload()
                    ->createOptionForm(AdressbuchKontaktResource::felder())
                    ->helperText('Viele Adressen auf einmal: in der Liste „Adressen einfügen“.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable()->description(fn (AdressbuchVerteiler $r) => $r->beschreibung),
                TextColumn::make('kontakte_count')->label('Kontakte')->sortable(),
                IconColumn::make('privat')->label('Nur für mich')->boolean(),
                TextColumn::make('user.name')->label('Angelegt von')->placeholder('–')->toggleable(),
            ])
            ->recordActions([
                self::kopierenAktion(),
                self::einfuegenAktion(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Noch keine Verteiler')
            ->emptyStateDescription('Verteiler sammeln Kontakte, z. B. „DAK Wölflinge“. Bei Videokonferenzen lassen sie sich als Ganzes einladen.');
    }

    /** Alle Adressen zum Kopieren, mit Semikolon getrennt (Outlook). */
    public static function kopierenAktion(): Action
    {
        return Action::make('kopieren')
            ->label('Adressen kopieren')
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->modalHeading(fn (AdressbuchVerteiler $record) => 'Adressen: '.$record->name)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen')
            ->schema([
                TextEntry::make('liste')->label('Für Outlook und andere Mailprogramme (ins Feld „Bcc“ einfügen)')
                    ->state(fn (AdressbuchVerteiler $record) => static::liste($record) ?: 'Keine Kontakte.')
                    ->copyable()->copyMessage('Kopiert')
                    ->extraAttributes(['style' => 'word-break: break-all']),
            ]);
    }

    /** „Name <mail>; mail; …“ für das Bcc-Feld. */
    public static function liste(AdressbuchVerteiler $verteiler): string
    {
        return implode('; ', $verteiler->kontakte->map->anzeige()->all());
    }

    public static function einfuegenAktion(): Action
    {
        return Action::make('einfuegen')
            ->label('Adressen einfügen')
            ->icon('heroicon-o-document-plus')
            ->color('gray')
            ->visible(fn (AdressbuchVerteiler $record) => static::canEdit($record))
            ->modalDescription('Eine Adresse je Zeile oder mit Semikolon getrennt, z. B. aus Outlook („Name <mail>“) oder einer Excel-Spalte. Bekannte Adressen werden nicht doppelt angelegt.')
            ->schema([
                Textarea::make('text')->label('Adressen')->rows(10)->required()
                    ->placeholder("Anna Müller <anna@example.org>\nben@example.org"),
            ])
            ->action(function (AdressbuchVerteiler $record, array $data) {
                abort_unless(static::canEdit($record), 403);
                $eintraege = Adressbuch::zerlegen((string) $data['text']);
                $ergebnis = Adressbuch::uebernehmen($record, $eintraege, Filament::auth()->user());
                AuditLogger::log('adressbuch.eingefuegt', Filament::auth()->user(), AdressbuchVerteiler::class, $record->id, request());
                Notification::make()->success()->title('Adressen übernommen')
                    ->body(count($eintraege).' erkannt, '.$ergebnis['uebernommen'].' neu im Verteiler, davon '.$ergebnis['neu'].' neue Kontakte.')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdressbuchVerteiler::route('/'),
            'create' => CreateAdressbuchVerteiler::route('/neu'),
            'edit' => EditAdressbuchVerteiler::route('/{record}/bearbeiten'),
        ];
    }
}
