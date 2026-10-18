<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\User;
use App\Support\Features;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Fragen zur Veranstaltung (Nachrichten-Eingang) gebündelt bei der
 * Veranstaltung – für alle, die ihre Teilnehmenden verwalten dürfen.
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'contactMessages';

    protected static ?string $title = 'Fragen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = Filament::auth()->user();

        return Features::enabled(Features::NACHRICHTEN) && $user instanceof User && $user->can('manageParticipants', $ownerRecord);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord instanceof Event ? $ownerRecord->contactMessages()->where('status', 'neu')->count() : 0;

        return $n > 0 ? (string) $n : null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Keine Fragen')
            ->emptyStateDescription('Fragen aus dem Formular „Frage stellen“ auf der Veranstaltungsseite erscheinen hier und gehen per Mail an die Ansprechperson.')
            ->recordAction('ansehen')
            ->columns(ContactMessageResource::spalten(mitThema: false))
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContactMessage::STATUSES)->multiple(),
            ])
            ->recordActions(ContactMessageResource::aktionen());
    }
}
