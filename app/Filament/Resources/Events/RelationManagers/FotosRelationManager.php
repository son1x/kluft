<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\EventFoto;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Reiter „Fotos“ der Veranstaltung: Leitende laden hoch, eine zweite Person
 * gibt frei (Vier-Augen). Freigegebene erscheinen als Galerie auf der
 * Veranstaltungsseite; einzelne lassen sich ausblenden.
 */
class FotosRelationManager extends RelationManager
{
    protected static string $relationship = 'fotos';

    protected static ?string $title = 'Fotos';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'Foto';

    protected static ?string $pluralModelLabel = 'Fotos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) Filament::auth()->user()?->can('manageParticipants', $ownerRecord);
    }

    /** Zahl im Reiter: Fotos, die auf Freigabe warten. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord instanceof Event ? $ownerRecord->fotos()->where('status', 'eingereicht')->count() : 0;

        return $n > 0 ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Hinweis, wer keine Foto-Einwilligung gegeben hat (nur wenn abgefragt). */
    private function ohneEinwilligung(): ?string
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();
        if (! $event->foto_einwilligung) {
            return null;
        }
        $ohne = $event->registrations()->whereIn('status', ['confirmed', 'waitlist'])
            ->where(fn ($query) => $query->where('foto_einwilligung', false)->orWhereNull('foto_einwilligung'))
            ->orderBy('last_name')->get();
        if ($ohne->isEmpty()) {
            return 'Alle Teilnehmenden haben in Fotos eingewilligt.';
        }
        $namen = $this->ich()->can('viewParticipants', $event)
            ? ': '.$ohne->map(fn ($r) => $r->fullName())->implode(', ')
            : '';

        return $ohne->count().' '.($ohne->count() === 1 ? 'Person' : 'Personen').' ohne Foto-Einwilligung'.$namen.'. Bitte keine Fotos freigeben, auf denen sie zu erkennen sind.';
    }

    private function ich(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt_text')->label('Was ist zu sehen? (Alternativtext)')->maxLength(300)->columnSpanFull()
                ->helperText('Für Menschen, die das Bild nicht sehen, z. B. „Wölflinge bauen ein Floß am Seeufer“.'),
            TextInput::make('credit')->label('Foto von')->maxLength(150),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn () => $this->ohneEinwilligung())
            ->defaultSort('sort')
            ->reorderable('sort')
            ->contentGrid(['default' => 2, 'md' => 3, 'xl' => 4])
            ->columns([
                Stack::make([
                    ImageColumn::make('vorschau')->label('')
                        ->state(fn (EventFoto $record) => $record->vorschauUrl())
                        ->imageHeight(160)->extraImgAttributes(['class' => 'w-full rounded-lg object-cover', 'loading' => 'lazy', 'alt' => '']),
                    TextColumn::make('status')->badge()
                        ->formatStateUsing(fn (string $state) => EventFoto::STATUS[$state] ?? $state)
                        ->color(fn (string $state) => EventFoto::FARBEN[$state] ?? 'gray'),
                    TextColumn::make('alt_text')->size('sm')->color('gray')->limit(60)->placeholder('Ohne Alternativtext'),
                    TextColumn::make('hochgeladenVon.name')->size('xs')->color('gray')->prefix('Hochgeladen von '),
                ])->space(2),
            ])
            ->headerActions([
                Action::make('hochladen')->label('Fotos hochladen')->icon(Heroicon::OutlinedArrowUpTray)
                    ->modalDescription('Die Fotos erscheinen erst auf der Website, wenn eine zweite Person sie freigibt.')
                    ->schema([
                        FileUpload::make('dateien')->label('Fotos')->multiple()->maxFiles(30)->maxSize(15360)->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk(EventFoto::DISK)->directory('event-fotos')->visibility('private')->required()
                            ->panelLayout('grid'),
                        TextInput::make('credit')->label('Foto von')->maxLength(150)->default(fn () => $this->ich()->name),
                        Checkbox::make('einwilligung')->label('Die erkennbar abgebildeten Personen haben eingewilligt, bei Minderjährigen die Personensorgeberechtigten.')
                            ->accepted()->validationMessages(['accepted' => 'Ohne Einwilligung keine Fotos von erkennbaren Personen.']),
                    ])
                    ->action(function (array $data) {
                        $start = (int) $this->getOwnerRecord()->fotos()->max('sort');
                        foreach (array_values((array) $data['dateien']) as $i => $pfad) {
                            $this->getOwnerRecord()->fotos()->create([
                                'path' => $pfad, 'credit' => $data['credit'] ?? null, 'status' => 'eingereicht',
                                'sort' => $start + $i + 1, 'hochgeladen_von' => $this->ich()->id,
                            ]);
                        }
                        Notification::make()->title(count((array) $data['dateien']).' Foto(s) hochgeladen')
                            ->body('Jetzt muss eine zweite Person sie freigeben.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('freigeben')->label('Freigeben')->icon(Heroicon::OutlinedCheck)->color('success')->button()->size('sm')
                    ->visible(fn (EventFoto $record) => $record->status !== 'freigegeben')
                    ->disabled(fn (EventFoto $record) => ! $record->darfFreigeben($this->ich()))
                    ->tooltip(fn (EventFoto $record) => $record->hochgeladen_von === $this->ich()->id ? 'Vier-Augen: Freigeben muss eine andere Person.' : null)
                    ->action(fn (EventFoto $record) => $this->freigeben(collect([$record]))),
                ActionGroup::make([
                    Action::make('ausblenden')->label('Ausblenden')->icon(Heroicon::OutlinedEyeSlash)
                        ->visible(fn (EventFoto $record) => $record->status === 'freigegeben')
                        ->action(fn (EventFoto $record) => $record->update(['status' => 'ausgeblendet'])),
                    Action::make('ansehen')->label('In voller Größe')->icon(Heroicon::OutlinedArrowsPointingOut)
                        ->url(fn (EventFoto $record) => $record->vorschauUrl(), shouldOpenInNewTab: true),
                    EditAction::make()->label('Alternativtext'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('alleFreigeben')->label('Freigeben')->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => $this->freigeben($records))
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateHeading('Noch keine Fotos')
            ->emptyStateDescription('Ladet nach der Veranstaltung Fotos hoch. Eine zweite Person prüft sie und gibt sie frei; dann erscheinen sie als Galerie auf der Veranstaltungsseite.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventFoto>  $fotos
     */
    private function freigeben(\Illuminate\Support\Collection $fotos): void
    {
        $ich = $this->ich();
        $ok = 0;
        $eigene = 0;
        foreach ($fotos as $foto) {
            if ($foto->status === 'freigegeben') {
                continue;
            }
            if (! $foto->darfFreigeben($ich)) {
                $eigene++;

                continue;
            }
            try {
                $foto->freigeben($ich);
                $ok++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $n = Notification::make()->title($ok === 1 ? '1 Foto freigegeben' : "{$ok} Fotos freigegeben");
        if ($eigene > 0) {
            $n->body($eigene.' eigene(s) übersprungen: Freigeben muss eine andere Person (Vier-Augen).')->warning();
        } else {
            $n->success();
        }
        $n->send();
    }
}
