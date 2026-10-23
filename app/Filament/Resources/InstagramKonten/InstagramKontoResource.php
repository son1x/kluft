<?php

namespace App\Filament\Resources\InstagramKonten;

use App\Filament\Resources\InstagramKonten\Pages\CreateInstagramKonto;
use App\Filament\Resources\InstagramKonten\Pages\EditInstagramKonto;
use App\Filament\Resources\InstagramKonten\Pages\ListInstagramKonten;
use App\Models\InstagramKonto;
use App\Services\Instagram\InstagramFeed;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Instagram-Einblendung (docs/instagram.md): eigene Konten, deren neueste
 * Beiträge auf Startseite oder Fachbereichen erscheinen. Nur für Admins,
 * weil hier Zugangstokens hinterlegt werden.
 */
class InstagramKontoResource extends Resource
{
    protected static ?string $model = InstagramKonto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $slug = 'instagram-konten';

    protected static ?string $navigationLabel = 'Instagram-Einblendung';

    protected static ?string $modelLabel = 'Instagram-Konto';

    protected static ?string $pluralModelLabel = 'Instagram-Konten';

    protected static ?int $navigationSort = 51;

    public static function canViewAny(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
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
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('name')->label('Anzeigename')->required()->maxLength(100)
                        ->placeholder('z. B. DPSG Trier'),
                    TextInput::make('anzahl')->label('Anzahl Beiträge')->numeric()->integer()->minValue(2)->maxValue(12)->default(8)->required(),
                    TextInput::make('access_token')->label('Zugangstoken (Long-Lived)')->password()->revealable(false)
                        ->required(fn (?InstagramKonto $record) => $record === null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->maxLength(2000)
                        ->columnSpanFull()
                        ->helperText(fn (?InstagramKonto $record) => ($record ? 'Leer lassen, um das gespeicherte Token zu behalten. ' : '')
                            .'Aus der Meta-App (Instagram API mit Instagram-Login). Wie das geht, steht oben unter „Anleitung: Token erzeugen“. Wird verschlüsselt gespeichert, nicht wieder angezeigt und automatisch verlängert.'),
                    CheckboxList::make('orte')->label('Anzeigen auf')->options(fn () => InstagramKonto::orte())->columns(2)->columnSpanFull()
                        ->helperText('Oberhalb von „Aktuelles“. Mehrere Konten auf einer Seite werden gemischt, neueste zuerst.'),
                    Toggle::make('aktiv')->label('Aktiv')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Konto')->description(fn (InstagramKonto $k) => $k->benutzername ? '@'.$k->benutzername : null),
                TextColumn::make('orte')->label('Anzeigen auf')->badge()
                    ->formatStateUsing(fn (string $state) => InstagramKonto::orte()[$state] ?? $state),
                TextColumn::make('beitraege_count')->label('Beiträge')->counts('beitraege'),
                TextColumn::make('abgerufen_am')->label('Zuletzt abgerufen')->since()->placeholder('noch nie')
                    ->description(fn (InstagramKonto $k) => $k->fehler),
                TextColumn::make('token_ablauf')->label('Token bis')->date('d.m.Y')->placeholder('–'),
                IconColumn::make('aktiv')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                Action::make('abrufen')
                    ->label('Jetzt abrufen')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (InstagramKonto $record, InstagramFeed $feed) {
                        abort_unless(static::canViewAny(), 403);
                        $anzahl = $feed->abrufen($record);
                        AuditLogger::log('instagram.feed.abruf', Filament::auth()->user(), InstagramKonto::class, $record->id, request());
                        $record->fehler
                            ? Notification::make()->title('Abruf fehlgeschlagen')->body($record->fehler)->danger()->send()
                            : Notification::make()->title("{$anzahl} Beiträge abgerufen.")->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstagramKonten::route('/'),
            'create' => CreateInstagramKonto::route('/create'),
            'edit' => EditInstagramKonto::route('/{record}/edit'),
        ];
    }
}
