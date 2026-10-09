<?php

namespace App\Filament\Resources\Gruppierungen;

use App\Filament\Resources\Gruppierungen\Pages\CreateGruppierung;
use App\Filament\Resources\Gruppierungen\Pages\EditGruppierung;
use App\Filament\Resources\Gruppierungen\Pages\ListGruppierungen;
use App\Filament\Resources\Gruppierungen\Schemas\GruppierungForm;
use App\Filament\Resources\Gruppierungen\Tables\GruppierungenTable;
use App\Models\Gruppierung;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GruppierungResource extends Resource
{
    protected static ?string $model = Gruppierung::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Bezirk/Stamm';

    public static function getPluralModelLabel(): string
    {
        return (string) (config('dpsg.directory.title') ?: 'Gruppierungen');
    }

    protected static ?string $slug = 'gruppierungen';

    public static function form(Schema $schema): Schema
    {
        return GruppierungForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GruppierungenTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGruppierungen::route('/'),
            'create' => CreateGruppierung::route('/create'),
            'edit' => EditGruppierung::route('/{record}/edit'),
        ];
    }
}
