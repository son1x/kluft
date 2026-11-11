<?php

namespace App\Support;

use App\Models\Person;
use App\Models\User;

/**
 * Personensuche für Formularfelder (z. B. „Verantwortlich“ und
 * „Kontaktperson“ bei Veranstaltungen): aktive Benutzerkonten und Personen
 * der Website. Treffer-Schlüssel: „u:<id>“, „p:<id>“.
 */
class PersonenSuche
{
    /**
     * @return array<string, string> Schlüssel => „Name – E-Mail (Quelle)“
     */
    public static function suchen(string $suche, ?User $user): array
    {
        $suche = trim(mb_substr($suche, 0, 64));
        if (mb_strlen($suche) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $suche).'%';
        $treffer = [];
        $gesehen = [];

        foreach (User::query()->where('status', 'active')->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'email']) as $u) {
            $email = mb_strtolower((string) $u->email);
            $gesehen[$email] = true;
            $treffer['u:'.$u->id] = $u->name.' – '.$email.' (Benutzer)';
        }

        foreach (Person::query()->where('active', true)->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'email']) as $p) {
            $email = mb_strtolower((string) ($p->email ?: Person::suggestedEmail($p->name)));
            if ($email === '' || isset($gesehen[$email])) {
                continue;
            }
            $gesehen[$email] = true;
            $treffer['p:'.$p->id] = $p->name.' – '.$email.' (Person)';
        }

        return $treffer;
    }

    /**
     * Name und E-Mail zu einem Treffer-Schlüssel.
     *
     * @return array{name: string, email: string}|null
     */
    public static function finden(?string $schluessel, ?User $user): ?array
    {
        [$quelle, $id] = array_pad(explode(':', (string) $schluessel, 2), 2, '');

        return match ($quelle) {
            'u' => ($u = User::query()->where('status', 'active')->find((int) $id)) ? ['name' => $u->name, 'email' => mb_strtolower((string) $u->email)] : null,
            'p' => ($p = Person::query()->where('active', true)->find((int) $id)) && ($p->email || Person::suggestedEmail($p->name))
                ? ['name' => $p->name, 'email' => mb_strtolower((string) ($p->email ?: Person::suggestedEmail($p->name)))] : null,
            default => null,
        };
    }

    public static function label(?string $schluessel, ?User $user): ?string
    {
        $p = self::finden($schluessel, $user);

        return $p ? $p['name'].' – '.$p['email'] : null;
    }
}
