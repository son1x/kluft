<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Support\AuditLogger;
use App\Support\KontoVorschlag;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPeople extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mein Konto → „Mein Eintrag“: Vorschläge aus E-Mail bzw. Name, die Verwaltung hakt ab.
            Action::make('kontoVorschlaege')
                ->label('Konto-Vorschläge prüfen')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn () => PersonResource::canCreate() && (new KontoVorschlag)->alle()->isNotEmpty())
                ->modalHeading('Konten zuordnen')
                ->modalDescription('Diese Einträge passen eindeutig zu einem Konto. Verknüpft wird nur, was du anhakst. Danach pflegt die Person unter „Mein Konto → Mein Eintrag auf der Website“ Text, Foto und Einwilligung selbst.')
                ->schema(fn () => [
                    CheckboxList::make('personen')->label('Vorschläge')
                        ->options((new KontoVorschlag)->alle()->mapWithKeys(fn (array $v) => [
                            $v['person']->id => $v['person']->name.' ('.$v['person']->gremiumLabel().') ↔ Konto '.$v['user']->name.' <'.$v['user']->email.'>, '.$v['grund'],
                        ])->all())
                        ->bulkToggleable()
                        ->required(),
                ])
                ->modalSubmitActionLabel('Zuordnen')
                ->action(fn (array $data) => $this->zuordnen(array_map('intval', (array) ($data['personen'] ?? [])))),
            CreateAction::make(),
        ];
    }

    /**
     * Angehakte Vorschläge verknüpfen; jeder wird beim Speichern noch einmal
     * geprüft (Eintrag ohne Konto, Vorschlag noch eindeutig).
     *
     * @param  list<int>  $ids
     */
    private function zuordnen(array $ids): void
    {
        abort_unless(PersonResource::canCreate(), 403);
        $vorschlag = new KontoVorschlag;
        $n = 0;
        foreach (Person::query()->whereKey($ids)->get() as $person) {
            if (($v = $vorschlag->fuer($person)) === null) {
                continue;
            }
            $person->forceFill(['user_id' => $v['user']->id])->save();
            AuditLogger::log('person.konto:zugeordnet', Filament::auth()->user(), Person::class, $person->id, request());
            $n++;
        }

        Notification::make()->success()->title($n === 1 ? '1 Eintrag zugeordnet' : "{$n} Einträge zugeordnet")->send();
    }
}
