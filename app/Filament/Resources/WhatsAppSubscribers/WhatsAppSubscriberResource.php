<?php

namespace App\Filament\Resources\WhatsAppSubscribers;

use App\Filament\Resources\WhatsAppSubscribers\Pages\ListWhatsAppSubscribers;
use App\Models\WhatsAppSubscriber;
use App\Support\AuditLogger;
use App\Support\WhatsAppAccess;
use App\Support\WhatsAppMode;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Abonnent:innen des WhatsApp-Newsletters: nur maskierte Nummern, kein
 * Export (docs/whatsapp_newsletter.md, F-WA-08).
 */
class WhatsAppSubscriberResource extends Resource
{
    protected static ?string $model = WhatsAppSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'WhatsApp-Newsletter';

    protected static ?string $slug = 'whatsapp/abonnenten';

    protected static ?string $modelLabel = 'Abonnent:in';

    protected static ?string $pluralModelLabel = 'Abonnent:innen';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return WhatsAppMode::api() && WhatsAppAccess::manage(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::canViewAny() ? (string) WhatsAppSubscriber::query()->active()->count() : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('confirmed_at', 'desc')
            ->columns([
                TextColumn::make('phone')->label('Nummer')->state(fn (WhatsAppSubscriber $record) => $record->masked())->fontFamily('mono'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Aktiv' : 'Unbestätigt')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'warning'),
                TextColumn::make('source')->label('Weg')->formatStateUsing(fn (string $state) => $state === 'link' ? 'QR-Code / Link' : 'Formular'),
                TextColumn::make('confirmed_at')->label('Bestätigt (DOI)')->dateTime('d.m.Y H:i')->sortable()->placeholder('–'),
                TextColumn::make('consent_version')->label('Einwilligung'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Aktiv', 'pending' => 'Unbestätigt']),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('Die Nummer wird gelöscht und bekommt keine Nachrichten mehr. Sie kann sich selbst mit START wieder anmelden.')
                    ->after(fn () => AuditLogger::log('whatsapp.abonnent.geloescht', Filament::auth()->user(), request: request())),
            ])
            ->emptyStateHeading('Noch keine Abonnent:innen')
            ->emptyStateDescription('Anmeldung über /whatsapp (QR-Code oder Link mit START).');
    }

    public static function getPages(): array
    {
        return ['index' => ListWhatsAppSubscribers::route('/')];
    }
}
