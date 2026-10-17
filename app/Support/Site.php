<?php

namespace App\Support;

/**
 * Wortformen der Gliederung, die diese Instanz betreibt (config dpsg.site).
 * Die Formen der Anlaufstelle hängen am Artikel: „zum Diözesanbüro“,
 * aber „zur Bezirksleitung“.
 */
class Site
{
    private const OFFICE_FORMS = [
        'das' => ['nom' => 'das', 'dat' => 'dem', 'zu' => 'zum', 'bei' => 'beim', 'in' => 'im', 'an' => 'ans'],
        // „im Stammesvorstand melden“ klingt schief, daher „beim“.
        'der' => ['nom' => 'der', 'dat' => 'dem', 'zu' => 'zum', 'bei' => 'beim', 'in' => 'beim', 'an' => 'an den'],
        // „in der Bezirksleitung melden“ klingt schief, daher „bei der“.
        'die' => ['nom' => 'die', 'dat' => 'der', 'zu' => 'zur', 'bei' => 'bei der', 'in' => 'bei der', 'an' => 'an die'],
    ];

    /**
     * Anlaufstelle mit Präposition/Artikel, z. B. office('zu') = „zum Diözesanbüro“.
     *
     * @param  'nom'|'dat'|'zu'|'bei'|'in'|'an'  $form
     */
    public static function office(string $form, bool $capitalize = false): string
    {
        $text = self::officePrefix($form).' '.self::officeLabel();

        return $capitalize ? mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1) : $text;
    }

    /**
     * Nur Präposition/Artikel, z. B. für „im <a>Diözesanbüro</a>“.
     *
     * @param  'nom'|'dat'|'zu'|'bei'|'in'|'an'  $form
     */
    public static function officePrefix(string $form): string
    {
        return self::OFFICE_FORMS[config('dpsg.site.office.article', 'das')][$form];
    }

    public static function officeLabel(): string
    {
        return (string) config('dpsg.site.office.label');
    }

    public static function officeUrl(): string
    {
        // Visitenkarte (Ausbaustufe 1): Die Seite des Vorstands gibt es noch nicht.
        if (! Ausbau::gruppen()) {
            return url('/'.config('dpsg.contact_page'));
        }

        return url('/'.config('dpsg.site.office.page'));
    }

    /** Kürzel des Stufen-Arbeitskreises („DAK“) oder „Arbeitskreis“. */
    public static function akShort(): string
    {
        return (string) (config('dpsg.site.ak_short') ?: 'Arbeitskreis');
    }

    /** Arbeitskreis mit Artikel im Nominativ, z. B. „Der Diözesanarbeitskreis“, „Das Leitungsteam“. */
    public static function akNominativ(): string
    {
        $artikel = ['der' => 'Der', 'das' => 'Das', 'die' => 'Die'][(string) config('dpsg.site.ak_article', 'der')] ?? 'Der';

        return $artikel.' '.(string) config('dpsg.site.ak');
    }
}
