<?php

namespace App\Filament\Resources\GlossaryTerms;

use App\Filament\Resources\GlossaryTerms\Pages\CreateGlossaryTerm;
use App\Filament\Resources\GlossaryTerms\Pages\EditGlossaryTerm;
use App\Filament\Resources\GlossaryTerms\Pages\ListGlossaryTerms;
use App\Models\GlossaryTerm;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Begriffe für den Hover im Text (z. B. DAK, FAK, Juleica). Das erste
 * Vorkommen je Seite wird automatisch erklärt.
 */
class GlossaryTermResource extends Resource
{
    protected static ?string $model = GlossaryTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'Begriff';

    protected static ?string $pluralModelLabel = 'Begriffe (Glossar)';

    protected static ?int $navigationSort = 89;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Das erste Vorkommen des Begriffs in Seiten, Beiträgen und Veranstaltungen wird gepunktet unterstrichen; beim Darüberfahren oder Antippen erscheint die Erklärung. Groß-/Kleinschreibung muss genau stimmen.')
                ->columnSpanFull()
                ->components([
                    TextInput::make('term')->label('Begriff / Abkürzung')
                        ->required()->maxLength(60)
                        ->unique(ignoreRecord: true)
                        ->placeholder('z. B. DAK'),
                    Textarea::make('explanation')->label('Erklärung')
                        ->required()->maxLength(300)->rows(3)
                        ->placeholder('Diözesanarbeitskreis: Arbeitskreis einer Altersstufe …'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('term')
            ->columns([
                TextColumn::make('term')->label('Begriff')->searchable()->sortable()->weight('bold'),
                TextColumn::make('explanation')->label('Erklärung')->searchable()->wrap(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlossaryTerms::route('/'),
            'create' => CreateGlossaryTerm::route('/create'),
            'edit' => EditGlossaryTerm::route('/{record}/edit'),
        ];
    }
}
