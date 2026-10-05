<?php

namespace App\Filament\Resources\GeorgsboteIssues;

use App\Filament\Resources\GeorgsboteIssues\Pages\CreateGeorgsboteIssue;
use App\Filament\Resources\GeorgsboteIssues\Pages\EditGeorgsboteIssue;
use App\Filament\Resources\GeorgsboteIssues\Pages\ListGeorgsboteIssues;
use App\Filament\Resources\GeorgsboteIssues\Schemas\GeorgsboteIssueForm;
use App\Filament\Resources\GeorgsboteIssues\Tables\GeorgsboteIssuesTable;
use App\Models\GeorgsboteIssue;
use App\Support\Features;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GeorgsboteIssueResource extends Resource
{
    protected static ?string $model = GeorgsboteIssue::class;

    // Nur in Instanzen mit Georgsbote (config dpsg.features).
    public static function isDiscovered(): bool
    {
        return Features::enabled(Features::GEORGSBOTE);
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Inhalte';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Georgsbote-Ausgabe';

    protected static ?string $pluralModelLabel = 'Georgsbote';

    protected static ?string $slug = 'georgsbote';

    public static function form(Schema $schema): Schema
    {
        return GeorgsboteIssueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeorgsboteIssuesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeorgsboteIssues::route('/'),
            'create' => CreateGeorgsboteIssue::route('/create'),
            'edit' => EditGeorgsboteIssue::route('/{record}/edit'),
        ];
    }
}
