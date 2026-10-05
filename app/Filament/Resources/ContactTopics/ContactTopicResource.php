<?php

namespace App\Filament\Resources\ContactTopics;

use App\Filament\Resources\ContactTopics\Pages\CreateContactTopic;
use App\Filament\Resources\ContactTopics\Pages\EditContactTopic;
use App\Filament\Resources\ContactTopics\Pages\ListContactTopics;
use App\Models\ContactTopic;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Themen des Kontaktformulars und wohin die Nachrichten gehen (F-CMS-09).
 */
class ContactTopicResource extends Resource
{
    protected static ?string $model = ContactTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $modelLabel = 'Kontaktthema';

    protected static ?string $pluralModelLabel = 'Kontaktformular';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Erscheint in der Auswahl „Worum geht es?“ auf der Kontaktseite. Nachrichten werden nur per E-Mail weitergeleitet und nicht gespeichert.')
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    TextInput::make('label')->label('Thema')
                        ->required()->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation) {
                            if ($operation === 'create' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->label('Kurzname für Links')
                        ->required()->maxLength(60)->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText(fn (?string $state) => 'Link mit vorausgewähltem Thema: '.url('/'.config('dpsg.contact_page')).'?thema='.($state ?: '…')),
                    TextInput::make('email')->label('Empfänger (feste Adresse)')
                        ->email()->maxLength(254)
                        ->placeholder('z. B. bolivien@'.config('dpsg.site.domain'))
                        ->helperText('Leer lassen: Nachrichten gehen an die Stufenreferent*innen bzw. Ansprechpersonen des Fachbereichs (aus „Personen“), sonst '.Site::office('an').'.')
                        ->columnSpanFull(),
                    Select::make('stage')->label('Stufe')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $s) => $s['label']))
                        ->placeholder('–'),
                    Select::make('section')->label('Fachbereich')
                        ->options(collect(config('dpsg.sections'))->map(fn (array $s) => $s['label']))
                        ->placeholder('–'),
                    TextInput::make('sort')->label('Reihenfolge')->numeric()->default(100),
                    Toggle::make('active')->label('In der Auswahl anzeigen')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('label')->label('Thema')->searchable()->weight('bold'),
                TextColumn::make('recipients')->label('Geht an')
                    ->state(fn (ContactTopic $record) => $record->recipientsLabel())
                    ->wrap(),
                TextColumn::make('slug')->label('Kurzname')->color('gray'),
                IconColumn::make('active')->label('Aktiv')->boolean(),
                TextColumn::make('sort')->label('Reihenfolge')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactTopics::route('/'),
            'create' => CreateContactTopic::route('/create'),
            'edit' => EditContactTopic::route('/{record}/edit'),
        ];
    }
}
