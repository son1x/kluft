<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Models\User;
use App\Notifications\RolesGrantedNotification;
use App\Support\AuditLogger;
use App\Support\KontoVorschlag;
use App\Support\RoleCatalog;
use App\Support\RollenVorschlag;
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
            // Ansprechpersonen und Stufenreferent*innen mit Konto: passende Rolle für ihren Bereich.
            Action::make('rollenVorschlaege')
                ->label('Rollen-Vorschläge prüfen')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->visible(fn () => PersonResource::canCreate() && $this->rollenVorschlaege() !== [])
                ->modalHeading('Rollen vergeben')
                ->modalDescription('Diese Konten gehören zu Ansprechpersonen bzw. Stufenreferent*innen, haben aber noch keine Rolle für ihren Bereich. Mit der Rolle pflegen sie Seiten, Beiträge und Veranstaltungen des Bereichs. Vergeben wird nur, was du anhakst; die Person bekommt wie sonst eine Mail.')
                ->schema(fn () => [
                    CheckboxList::make('rollen')->label('Vorschläge')
                        ->options($this->rollenVorschlaege())
                        ->bulkToggleable()
                        ->required(),
                ])
                ->modalSubmitActionLabel('Vergeben')
                ->action(fn (array $data) => $this->rollenVergeben(array_map('strval', (array) ($data['rollen'] ?? [])))),
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

    /**
     * Offene Rollen-Vorschläge, die die angemeldete Person vergeben darf.
     *
     * @return array<string, string> "user_id#rolle|scope_type|scope_key" => Beschriftung
     */
    private function rollenVorschlaege(): array
    {
        $actor = Filament::auth()->user();
        if (! $actor instanceof User) {
            return [];
        }
        $erlaubt = RoleCatalog::grantableBy($actor);

        return (new RollenVorschlag)->alle()
            ->filter(fn (array $v) => isset($erlaubt[$v['key']]))
            ->map(fn (array $v) => $v['user']->name.' <'.$v['user']->email.'> → '.$v['label'].' ('.$v['grund'].')')
            ->all();
    }

    /**
     * Angehakte Vorschläge vergeben; jeder wird noch einmal geprüft.
     * Die Rolle schreibt selbst ins Audit-Log (RoleAssignment).
     *
     * @param  list<string>  $auswahl
     */
    private function rollenVergeben(array $auswahl): void
    {
        abort_unless(PersonResource::canCreate(), 403);
        $offen = $this->rollenVorschlaege();
        $actor = Filament::auth()->user();
        $neu = [];
        foreach ($auswahl as $wert) {
            if (! isset($offen[$wert])) {
                continue;
            }
            [$userId, $key] = explode('#', $wert, 2);
            $user = User::query()->find((int) $userId);
            if (! $user instanceof User) {
                continue;
            }
            $user->roleAssignments()->create(RoleCatalog::decode($key));
            $neu[$user->id][] = $key;
        }

        foreach ($neu as $userId => $keys) {
            User::query()->find($userId)?->notify(new RolesGrantedNotification($keys, false, $actor instanceof User ? $actor->name : null));
        }

        $n = count($neu, COUNT_RECURSIVE) - count($neu);
        Notification::make()->success()->title($n === 1 ? '1 Rolle vergeben' : "{$n} Rollen vergeben")->send();
    }
}
