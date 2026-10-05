<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Tables\EventsTable;
use App\Models\Event;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Termine & Veranstaltungen';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Veranstaltung';

    protected static ?string $pluralModelLabel = 'Veranstaltungen';

    /**
     * Query-Scoping (5.4, F-EV-08): Stufen-/Sektionsadmins sehen nur
     * Veranstaltungen ihres Scopes; Verwaltung und Vorstand alle.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if (! $user || $user->isVerwaltung() || $user->hasRole('vorstand')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereIn('stage', $user->scopeKeysFor('stufenadmin'))
            ->orWhereIn('section', $user->scopeKeysFor('sektionsadmin')));
    }

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
