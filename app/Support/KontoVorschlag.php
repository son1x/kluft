<?php

namespace App\Support;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Vorschläge, welches Konto zu einem Personen-Eintrag gehört (Mein Konto →
 * „Mein Eintrag auf der Website“). Nur Vorschläge: Verknüpft wird erst,
 * wenn die Verwaltung bestätigt.
 *
 * Vorrang hat die E-Mail-Adresse, die auf der Website steht (eingetragen
 * oder nach dem Muster der Instanz gebildet), verglichen mit E-Mail und
 * Microsoft-365-Anmeldung (UPN) aktiver Konten. Ohne Treffer zählt der
 * gleiche Name, z. B. für Arbeitskreis-Mitglieder ohne eigene Adresse.
 * Vorgeschlagen wird nur ein eindeutiges Konto.
 */
class KontoVorschlag
{
    public const EMAIL = 'gleiche E-Mail';

    public const NAME = 'gleicher Name';

    /** @var Collection<int, User>|null */
    private ?Collection $konten = null;

    /**
     * @return array{user: User, grund: string}|null
     */
    public function fuer(Person $person): ?array
    {
        if ($person->user_id !== null) {
            return null;
        }

        $adresse = self::norm($person->email) ?? self::norm($person->publicEmail());
        if ($adresse !== null) {
            $treffer = $this->konten()->filter(fn (User $u) => in_array($adresse, [self::norm($u->email), self::norm($u->upn)], true));
            if ($treffer->count() === 1) {
                return ['user' => $treffer->first(), 'grund' => self::EMAIL];
            }
            if ($treffer->count() > 1) {
                return null;
            }
        }

        $name = self::norm($person->name);
        $treffer = $name === null ? collect() : $this->konten()->filter(fn (User $u) => self::norm($u->name) === $name);

        return $treffer->count() === 1 ? ['user' => $treffer->first(), 'grund' => self::NAME] : null;
    }

    /**
     * Alle Einträge ohne Konto mit eindeutigem Vorschlag.
     *
     * @return Collection<int, array{person: Person, user: User, grund: string}>
     */
    public function alle(): Collection
    {
        return Person::query()->whereNull('user_id')->orderBy('gremium')->orderBy('sort')->get()
            ->map(fn (Person $person) => ($v = $this->fuer($person)) ? ['person' => $person, ...$v] : null)
            ->filter()
            ->values();
    }

    /**
     * Aktive Konten (ohne Rollen-Zugänge wie „Stammesvorstand“, die keine Person sind).
     *
     * @return Collection<int, User>
     */
    private function konten(): Collection
    {
        return $this->konten ??= User::query()->where('status', 'active')->get()->reject(fn (User $u) => $u->isRolle())->values();
    }

    private static function norm(?string $wert): ?string
    {
        $wert = mb_strtolower(trim((string) $wert));

        return $wert === '' ? null : $wert;
    }
}
