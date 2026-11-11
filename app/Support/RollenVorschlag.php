<?php

namespace App\Support;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Vorschläge, welche Rolle ein Konto für seinen Bereich braucht: Wer mit
 * einem Personen-Eintrag verknüpft ist (people.user_id) und dort als
 * Ansprechperson für einen Fachbereich steht, bekommt Sektionsadmin für
 * diesen Bereich vorgeschlagen; Stufenreferent*innen (Eintrag mit Stufe,
 * nicht im Arbeitskreis) Stufenadmin für die Stufe. Nur Vorschläge:
 * Vergeben wird erst, wenn die Verwaltung bestätigt.
 */
class RollenVorschlag
{
    /**
     * Fehlende Rollen eines Eintrags, Schlüssel wie im RoleCatalog
     * ("rolle|scope_type|scope_key") => Grund.
     *
     * @return array<string, string>
     */
    public function fuer(Person $person): array
    {
        $user = $person->user;
        if (! $user instanceof User || $user->status !== 'active' || $user->isRolle() || $user->darfUeberall('seiten')) {
            return [];
        }

        $katalog = RoleCatalog::all();
        $vorschlaege = [];
        foreach ($person->ansprechBereiche() as $bereich) {
            $vorschlaege["sektionsadmin|section|{$bereich}"] = 'Ansprechperson für '.config("dpsg.sections.{$bereich}.label", $bereich);
        }
        if ($person->stage && ! $person->inArbeitskreis()) {
            $vorschlaege["stufenadmin|stage|{$person->stage}"] = ($person->role ?: 'Stufenreferent*in').' ('.$person->gremiumLabel().')';
        }

        return collect($vorschlaege)
            ->filter(fn (string $grund, string $key) => isset($katalog[$key]))
            ->reject(fn (string $grund, string $key) => $user->hasRoleInScope(...array_values(RoleCatalog::decode($key))))
            ->all();
    }

    /**
     * Alle offenen Vorschläge, je Konto und Rolle einmal.
     *
     * @return Collection<string, array{user: User, key: string, label: string, grund: string}>
     */
    public function alle(): Collection
    {
        $katalog = RoleCatalog::all();
        $alle = [];
        foreach (Person::query()->whereNotNull('user_id')->with('user.roleAssignments')->orderBy('gremium')->orderBy('sort')->get() as $person) {
            foreach ($this->fuer($person) as $key => $grund) {
                $user = $person->user;
                if ($user instanceof User) {
                    $alle[$user->id.'#'.$key] = ['user' => $user, 'key' => $key, 'label' => $katalog[$key], 'grund' => $grund];
                }
            }
        }

        return collect($alle)->sortBy(fn (array $v) => $v['user']->name);
    }
}
