<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Schemas\PostForm;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Inhalte';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Beitrag';

    protected static ?string $pluralModelLabel = 'Aktuelles / Blog';

    /**
     * Query-Scoping nach Nutzer-Scope (Pflichtenheft 5.4): Autoren sehen
     * nur eigene Beiträge, Stufenadmins zusätzlich alle Beiträge ihrer
     * Stufe.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if (! $user || $user->isVerwaltung() || $user->hasRole('vorstand')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('author_id', $user->id)
            ->orWhereIn('stage', $user->scopeKeysFor('stufenadmin'))
            ->orWhereIn('section', $user->scopeKeysFor('sektionsadmin')));
    }

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
