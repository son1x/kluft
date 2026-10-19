<?php

namespace App\Support;

use App\Models\ContactTopic;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * Funktion „E-Mail-Adressen öffnen ein Formular“: Statt mailto: führt ein
 * Klick auf eine Adresse zu einem Formular an genau diese Adresse. Die
 * Nachricht geht per Mail dorthin und liegt mit dem Nachrichten-Eingang
 * zusätzlich im Backend. Die Adresse steckt signiert im Link, damit das
 * Formular nur an Adressen schreibt, die die Website selbst zeigt.
 */
class Mailformular
{
    public static function aktiv(): bool
    {
        return Features::enabled(Features::MAILFORMULAR);
    }

    /** Ziel für einen Link auf diese Adresse: Formular oder mailto. */
    public static function href(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || ! self::aktiv() || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:'.$email;
        }

        return route('mailformular.zeigen', ['an' => self::schluessel($email)]);
    }

    public static function schluessel(string $email): string
    {
        $email = mb_strtolower(trim($email));

        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=').'.'.self::signatur($email);
    }

    /** Adresse aus dem Schlüssel, nur mit gültiger Signatur. */
    public static function adresse(?string $schluessel): ?string
    {
        [$teil, $sig] = array_pad(explode('.', (string) $schluessel, 2), 2, '');
        $email = (string) base64_decode(strtr($teil, '-_', '+/'), true);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! hash_equals(self::signatur($email), $sig)) {
            return null;
        }

        return $email;
    }

    /**
     * Wer hinter der Adresse steht, für die Überschrift des Formulars.
     *
     * @return array{name: string, rolle: ?string}
     */
    public static function empfaenger(string $email): array
    {
        $email = mb_strtolower($email);
        if ($person = self::personen()->get($email)) {
            return ['name' => $person->name, 'rolle' => $person->role];
        }
        if ($thema = ContactTopic::query()->active()->whereRaw('LOWER(email) = ?', [$email])->first()) {
            return ['name' => $thema->label, 'rolle' => null];
        }
        if ($email === mb_strtolower((string) config('dpsg.contact.email'))) {
            return ['name' => Site::officeLabel(), 'rolle' => (string) config('dpsg.site.name')];
        }

        return ['name' => $email, 'rolle' => null];
    }

    /**
     * mailto-Links im redaktionellen HTML auf das Formular umbiegen – nur für
     * Adressen der eigenen Domain(s) und veröffentlichte Personen-Adressen;
     * fremde Adressen bleiben mailto.
     */
    public static function html(string $html): string
    {
        if (! self::aktiv() || ! str_contains($html, 'mailto:')) {
            return $html;
        }

        return (string) preg_replace_callback('/href="mailto:([^"?]+)(\?[^"]*)?"/i', function (array $m) {
            $email = rawurldecode(html_entity_decode($m[1], ENT_QUOTES));

            return self::eigene($email) ? 'href="'.e(self::href($email)).'"' : $m[0];
        }, $html);
    }

    private static function eigene(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $domain = substr((string) strrchr($email, '@'), 1);
        $domains = array_filter(array_map(
            fn ($d) => preg_replace('/^www\./', '', mb_strtolower(trim((string) $d))),
            [config('dpsg.person_email_domain'), config('dpsg.site.domain'), substr((string) strrchr((string) config('dpsg.contact.email'), '@'), 1)],
        ));

        return in_array($domain, $domains, true) || self::personen()->has($email);
    }

    /**
     * Personen je veröffentlichter Adresse, einmal je Anfrage.
     *
     * @return Collection<string, Person>
     */
    private static function personen(): Collection
    {
        return once(fn () => Person::query()->visible()->get()
            ->filter(fn (Person $p) => filled($p->publicEmail()))
            ->keyBy(fn (Person $p) => mb_strtolower((string) $p->publicEmail())));
    }

    private static function signatur(string $email): string
    {
        return substr(hash_hmac('sha256', 'mailformular|'.$email, (string) config('app.key')), 0, 16);
    }
}
