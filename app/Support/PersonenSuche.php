<?php

namespace App\Support;

use App\Models\Person;
use App\Models\User;
use App\Services\DirectoryUnavailable;
use App\Services\EntraDirectory;

/**
 * Personensuche für Formularfelder (z. B. „Verantwortlich“ und
 * „Kontaktperson“ bei Veranstaltungen): aktive Benutzerkonten, Personen der
 * Website und – nur für Admins mit verbundener Verzeichnissuche – das
 * M365-Verzeichnis. Treffer-Schlüssel: „u:<id>“, „p:<id>“, „m:<Entra-ID>“.
 */
class PersonenSuche
{
    public const TREFFER = 'personensuche.m365';

    /** Darf die Person im M365-Verzeichnis suchen (nur Admins)? */
    public static function m365Erlaubt(?User $user): bool
    {
        return $user instanceof User && $user->isAdmin();
    }

    public static function m365Verbunden(?User $user): bool
    {
        return self::m365Erlaubt($user) && app(EntraDirectory::class)->connected();
    }

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

        if (self::m365Verbunden($user)) {
            try {
                $m365 = [];
                foreach (app(EntraDirectory::class)->search($suche) as $m) {
                    $m365[$m['id']] = $m;
                    if (! isset($gesehen[$m['email']])) {
                        $gesehen[$m['email']] = true;
                        $treffer['m:'.$m['id']] = $m['name'].' – '.$m['email'].' (M365)';
                    }
                }
                // Für die Übernahme merken (nur in der Sitzung).
                session()->put(self::TREFFER, $m365);
            } catch (DirectoryUnavailable) {
                // Ohne Verzeichnis weiter mit den lokalen Treffern.
            }
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
            'm' => self::m365Erlaubt($user) && is_array($m = session(self::TREFFER.'.'.$id)) ? ['name' => $m['name'], 'email' => $m['email']] : null,
            default => null,
        };
    }

    public static function label(?string $schluessel, ?User $user): ?string
    {
        $p = self::finden($schluessel, $user);

        return $p ? $p['name'].' – '.$p['email'] : null;
    }
}
