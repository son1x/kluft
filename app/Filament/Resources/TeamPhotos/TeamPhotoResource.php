<?php

namespace App\Filament\Resources\TeamPhotos;

use App\Filament\Resources\TeamPhotos\Pages\CreateTeamPhoto;
use App\Filament\Resources\TeamPhotos\Pages\EditTeamPhoto;
use App\Filament\Resources\TeamPhotos\Pages\ListTeamPhotos;
use App\Models\Media;
use App\Models\Person;
use App\Models\TeamPhoto;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Gruppenfotos mit anklickbaren Personen.
 */
class TeamPhotoResource extends Resource
{
    protected static ?string $model = TeamPhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?string $modelLabel = 'Gruppenfoto';

    protected static ?string $pluralModelLabel = 'Gruppenfotos';

    protected static ?string $slug = 'gruppenfotos';

    public static function form(Schema $schema): Schema
    {
        $gremien = collect(config('dpsg.gremien'))->map(fn (array $g) => $g['label'])->all();

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->components([
                    Select::make('gremium')->label('Seite')->options($gremien)->in(array_keys($gremien))->required()->native(false),
                    Select::make('media_id')->label('Foto aus der Mediathek')
                        ->relationship('media', 'alt_text')->searchable()->preload()->required()->live(),
                    TextInput::make('title')->label('Überschrift')->placeholder('z. B. Unsere Diözesanleitung')->maxLength(150),
                    TextInput::make('note')->label('Zusatz zur Bildunterschrift')->placeholder('z. B. wer auf dem Foto fehlt')->maxLength(255),
                    Toggle::make('active')->label('Auf der Seite anzeigen')->default(true),
                ]),
            Section::make('Personen auf dem Foto')
                ->columnSpanFull()
                ->components([
                    ViewField::make('spots')
                        ->hiddenLabel()
                        ->view('filament.forms.spot-editor')
                        ->viewData(fn (Get $get) => [
                            'imageUrl' => Media::query()->find($get('media_id'))?->url(),
                            'people' => Person::query()->orderBy('gremium')->orderBy('sort')->get()
                                ->map(fn (Person $p) => ['id' => $p->id, 'name' => $p->name, 'initials' => $p->initials()])->values()->all(),
                        ])
                        ->afterStateHydrated(fn (ViewField $component, ?TeamPhoto $record) => $component->state(
                            $record?->spots()->get()->map(fn ($s) => ['person_id' => $s->person_id, 'x' => $s->x, 'y' => $s->y])->values()->all() ?? []
                        ))
                        ->dehydrated(false)
                        ->saveRelationshipsUsing(function (TeamPhoto $record, ?array $state) {
                            $valid = Person::query()->pluck('id')->all();
                            $record->spots()->delete();
                            foreach (collect($state ?? [])->unique('person_id') as $spot) {
                                $x = (float) ($spot['x'] ?? -1);
                                $y = (float) ($spot['y'] ?? -1);
                                if (in_array((int) ($spot['person_id'] ?? 0), $valid, true) && $x >= 0 && $x <= 100 && $y >= 0 && $y <= 100) {
                                    $record->spots()->create(['person_id' => (int) $spot['person_id'], 'x' => round($x, 2), 'y' => round($y, 2)]);
                                }
                            }
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('spots'))
            ->columns([
                TextColumn::make('title')->label('Überschrift')->placeholder('–'),
                TextColumn::make('gremium')->label('Seite')->badge()
                    ->formatStateUsing(fn (string $state) => config("dpsg.gremien.{$state}.label", $state)),
                TextColumn::make('spots_count')->label('Markierte Personen'),
                IconColumn::make('active')->label('Angezeigt')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamPhotos::route('/'),
            'create' => CreateTeamPhoto::route('/create'),
            'edit' => EditTeamPhoto::route('/{record}/edit'),
        ];
    }
}
