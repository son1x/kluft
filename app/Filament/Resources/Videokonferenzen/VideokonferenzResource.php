<?php

namespace App\Filament\Resources\Videokonferenzen;

use App\Filament\Resources\Videokonferenzen\Pages\CreateVideokonferenz;
use App\Filament\Resources\Videokonferenzen\Pages\ListVideokonferenzen;
use App\Filament\Resources\Videokonferenzen\Pages\ViewVideokonferenz;
use App\Models\Videokonferenz;
use App\Services\Jitsi\Jitsi;
use App\Support\Videokonferenzen;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Videokonferenzen auf der Jitsi-Installation des DV (docs/videokonferenzen.md):
 * terminieren, Teilnahme-Link und Einladung kopieren, Moderator-Link bis zum
 * Ende des Folgetags. Jede*r sieht die eigenen, Verwaltung und Admins alle.
 */
class VideokonferenzResource extends Resource
{
    protected static ?string $model = Videokonferenz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'videokonferenzen';

    protected static ?string $navigationLabel = 'Videokonferenzen';

    protected static ?string $modelLabel = 'Videokonferenz';

    protected static ?string $pluralModelLabel = 'Videokonferenzen';

    protected static ?int $navigationSort = 31;

    public static function canViewAny(): bool
    {
        return Videokonferenzen::zugriff(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Videokonferenz && Videokonferenzen::darf(Filament::auth()->user(), $record);
    }

    public static function canEdit(Model $record): bool
    {
        // Raumname und Links hängen am Termin; für Änderungen neu anlegen.
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');

        return Videokonferenzen::alleSehen(Filament::auth()->user())
            ? $query
            : $query->where('user_id', Filament::auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->components([
                TextInput::make('titel')->label('Titel')->required()->maxLength(100)
                    ->placeholder('z. B. DAK Wölflinge')
                    ->helperText('Daraus entsteht der Raumname, mit einem zufälligen Anhang, damit niemand Räume errät.'),
                DateTimePicker::make('beginn')->label('Beginn')->required()->seconds(false)->native(false)
                    ->minDate(now()->subDay()->startOfDay())
                    ->helperText('Der Moderator-Link gilt bis zum Ende des Folgetags.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Termin')->columns(2)->columnSpanFull()->components([
                TextEntry::make('titel')->label('Titel'),
                TextEntry::make('beginn')->label('Beginn')
                    ->formatStateUsing(fn ($state) => $state?->copy()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm').' Uhr'),
                TextEntry::make('user.name')->label('Angelegt von')->placeholder('–'),
                TextEntry::make('raum')->label('Raumname')->fontFamily('mono'),
            ]),
            Section::make('Für alle Teilnehmenden')->columnSpanFull()->components([
                TextEntry::make('teilnahme')->label('Teilnahme-Link')
                    ->state(fn (Videokonferenz $record) => $record->teilnahmeLink())
                    ->copyable()->copyMessage('Kopiert')->fontFamily('mono')
                    ->url(fn (Videokonferenz $record) => $record->teilnahmeLink(), true),
                TextEntry::make('einladung')->label('Einladungstext')
                    ->state(fn (Videokonferenz $record) => $record->einladung())
                    ->copyable()->copyMessage('Kopiert')
                    ->extraAttributes(['style' => 'white-space: pre-line'])
                    ->helperText('Gäste warten, bis jemand mit Moderator-Link oder Microsoft-Anmeldung im Raum ist.'),
            ]),
            Section::make('Nur für die Moderation')->columnSpanFull()
                ->description('Wer diesen Link hat, ist ohne Anmeldung Moderator*in dieses Raums. Nicht in Einladungen oder Mails an Teilnehmende verwenden. Ein Link lässt sich nicht einzeln zurückziehen.')
                ->components([
                    TextEntry::make('moderator')->label('Moderator-Link')
                        ->state(fn (Videokonferenz $record) => $record->moderatorLink()
                            ?? (! app(Jitsi::class)->eingerichtet() ? 'Noch nicht eingerichtet: Secret JITSI_SECRET fehlt (docs/videokonferenzen.md).' : 'Abgelaufen.'))
                        ->copyable(fn (Videokonferenz $record) => $record->moderatorLink() !== null)->copyMessage('Kopiert')
                        ->fontFamily('mono')
                        ->extraAttributes(['style' => 'word-break: break-all']),
                    TextEntry::make('ablauf')->label('Gültig bis')
                        ->state(fn (Videokonferenz $record) => $record->moderatorAblauf()->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm:ss').' Uhr'),
                    TextEntry::make('login')->label('Für Mitglieder mit Microsoft-Konto')
                        ->state(fn (Videokonferenz $record) => 'https://login.'.parse_url($record->teilnahmeLink(), PHP_URL_HOST).'/room/'.$record->raum)
                        ->copyable()->copyMessage('Kopiert')->fontFamily('mono')
                        ->helperText('Anmelden und direkt als Moderator*in einsteigen.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('beginn', 'desc')
            ->columns([
                TextColumn::make('titel')->label('Titel')->searchable()->wrap(),
                TextColumn::make('beginn')->label('Beginn')->dateTime('D, d.m.Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Angelegt von')->placeholder('–')
                    ->visible(fn () => Videokonferenzen::alleSehen(Filament::auth()->user())),
                TextColumn::make('status')->label('Moderator-Link')->badge()
                    ->state(fn (Videokonferenz $record) => $record->moderatorGueltig() ? 'gültig' : 'abgelaufen')
                    ->color(fn (string $state) => $state === 'gültig' ? 'success' : 'gray'),
            ])
            ->recordActions([
                ViewAction::make()->label('Links'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Noch keine Videokonferenzen')
            ->emptyStateDescription('„Neue Videokonferenz“ legt einen Raum auf der Jitsi-Installation des DV an.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideokonferenzen::route('/'),
            'create' => CreateVideokonferenz::route('/neu'),
            'view' => ViewVideokonferenz::route('/{record}'),
        ];
    }
}
