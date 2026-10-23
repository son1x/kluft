<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\Pages\ManageSperren;
use App\Models\Shop\Sperre;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Sperrliste der Sammelbestellung, z. B. Gruppenzelte (SB-15). */
class SperreResource extends Resource
{
    use Zugriff;

    protected static ?string $model = Sperre::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Sammelbestellung';

    protected static ?string $modelLabel = 'Sperre';

    protected static ?string $pluralModelLabel = 'Sperrliste';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('typ')->label('Art')->options(Sperre::TYPEN)->required()->native(false)->default('kategorie'),
            TextInput::make('wert')->label('Wert')->required()->maxLength(80)
                ->helperText('Rüsthaus-Kategorie = erster Teil der Adresse, z. B. „zelte“ in ruesthaus.de/zelte/…'),
            TextInput::make('notiz')->label('Notiz')->maxLength(160)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('typ')->label('Art')->formatStateUsing(fn (string $state) => Sperre::TYPEN[$state] ?? $state),
            TextColumn::make('wert')->label('Wert')->weight('bold'),
            TextColumn::make('notiz')->label('Notiz')->placeholder('–'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSperren::route('/')];
    }
}
