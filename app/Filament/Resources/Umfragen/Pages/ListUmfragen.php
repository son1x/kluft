<?php

namespace App\Filament\Resources\Umfragen\Pages;

use App\Filament\Resources\Umfragen\UmfrageResource;
use App\Models\Umfrage;
use App\Support\AuditLogger;
use App\Support\Umfragen;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListUmfragen extends ListRecords
{
    protected static string $resource = UmfrageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ausVorlage')
                ->label('Aus Vorlage')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->modalHeading('Neue Umfrage aus einer Vorlage')
                ->schema([
                    Select::make('vorlage')->label('Vorlage')->required()->native(false)->options(fn () => self::vorlagen()),
                    TextInput::make('titel')->label('Titel der neuen Umfrage (optional)')->maxLength(200),
                ])
                ->action(function (array $data) {
                    abort_unless(UmfrageResource::canCreate(), 403);
                    [$art, $key] = explode(':', (string) $data['vorlage'], 2) + [1 => ''];
                    $umfrage = $art === 'db'
                        ? Umfrage::query()->vorlagen()->findOrFail((int) $key)->kopieren(['ist_vorlage' => false, 'erstellt_von' => Filament::auth()->id()])
                        : Umfragen::ausVorlage($key, Filament::auth()->id());
                    if (filled($data['titel'] ?? null)) {
                        $umfrage->update(['titel' => $data['titel']]);
                    }
                    AuditLogger::log('umfrage.angelegt', Filament::auth()->user(), Umfrage::class, $umfrage->id, request());

                    return redirect(UmfrageResource::getUrl('edit', ['record' => $umfrage]));
                }),
            CreateAction::make()->label('Neue Umfrage'),
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function vorlagen(): array
    {
        $eigene = Umfrage::query()->vorlagen()->orderBy('titel')->pluck('titel', 'id')->mapWithKeys(fn ($t, $id) => ['db:'.$id => $t])->all();
        $mit = collect(Umfragen::vorlagen())->mapWithKeys(fn (array $v, string $k) => ['mit:'.$k => $v['titel']])->all();

        return array_filter(['Eigene Vorlagen' => $eigene, 'Mitgeliefert' => $mit]);
    }
}
