<?php

namespace App\Support;

use App\Models\Page;

/**
 * Leitergewinnung auf Stammes-Websites: eine eigene Seite „Leiter*in werden“
 * im Menü statt eines Aufrufs auf jeder Stufenseite. Es ist eine normale
 * Seite – Text im Backend anpassen; wer sie nicht will, setzt sie auf
 * Entwurf oder nimmt sie aus dem Menü.
 */
class Leitergewinnung
{
    public const SLUG = 'leiter-werden';

    public const TITEL = 'Leiter*in werden';

    public static function aufStammesebene(): bool
    {
        return config('dpsg.site.level') === 'Stamm';
    }

    /** Adresse der veröffentlichten Seite (nur Stammes-Websites), sonst null. */
    public static function url(): ?string
    {
        if (! self::aufStammesebene() || ! Page::query()->published()->where('slug', self::SLUG)->exists()) {
            return null;
        }

        return url('/'.self::SLUG);
    }

    /** Vorschlag für den Seitentext; der Stamm passt ihn im Backend an. */
    public static function text(): string
    {
        $mail = (string) config('dpsg.contact.email');

        return '<p>Gruppenstunden, Fahrten und Lager gibt es bei uns nur, weil Ehrenamtliche sie vorbereiten. Wir freuen uns über alle, die mitmachen möchten – ob du selbst Pfadfinder*in warst oder ganz neu dazukommst.</p>'
            .'<h2>Was dich erwartet</h2>'
            .'<ul><li>Ein Leitungsteam, mit dem du gemeinsam eine Gruppe begleitest – du bist nicht allein.</li>'
            .'<li>Gruppenstunden, Aktionen, Fahrten und Lager, die ihr selbst gestaltet.</li>'
            .'<li>Die Leitungsrunde des Stammes: Austausch, Planung und Gemeinschaft mit allen Leitenden.</li></ul>'
            .'<h2>Ausbildung</h2>'
            .'<p>Die DPSG bildet ihre Leiter*innen aus – in Kursen von Bezirk und Diözese, dazu gehört eine Präventionsschulung. Wer Kinder und Jugendliche leitet, legt außerdem ein erweitertes Führungszeugnis vor. Wir begleiten dich dabei.</p>'
            .'<h2>Interesse?</h2>'
            .'<p>Melde dich beim <a href="/stammesvorstand">Stammesvorstand</a> – über das <a href="/kontakt">Kontaktformular</a>'
            .($mail !== '' ? ' oder an <a href="mailto:'.e($mail).'">'.e($mail).'</a>' : '')
            .'. Gern kannst du auch erst einmal bei einer Gruppenstunde reinschnuppern.</p>';
    }
}
