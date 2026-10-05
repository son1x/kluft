<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

/**
 * Fachbereich Politische Bildung (mit Facharbeitskreis): Startseite unter
 * „Fachbereiche“ und Thema im Kontaktformular, wie bei Spiritualität,
 * Ausbildung und Ökologie (Rückmeldung Lisa).
 */
class FachbereichPolitischeBildung extends FachbereicheSpiritualitaetAusbildungOekologie
{
    public const SEITEN = [
        ['slug' => 'politische-bildung', 'title' => 'Politische Bildung', 'nav_sort' => 70,
            'seo_description' => 'Haltung zeigen: Vielfalt, Kinderrechte, Demokratie – gegen Ausgrenzung und rechte Hetze'],
    ];

    public const BODY = <<<'HTML'
        <p>Pfadfinden ist politisch: Wir setzen uns ein für eine gerechte und vielfältige Gesellschaft, in der alle Kinder und Jugendlichen gut aufwachsen können. Der Fachbereich Politische Bildung greift Themen auf, die junge Menschen und unsere Gruppen bewegen, zum Beispiel Vielfalt, Kinderarmut, Kinderrechte und Demokratie, und bezieht klar Stellung gegen Rassismus und rechte Hetze, etwa mit „Kein Bock auf Nazis“.</p>
        <p>Er bietet Material, Methoden und Aktionen für Gruppenstunden und Lager und unterstützt Stämme, Bezirke und Leiter*innen, sich einzumischen.</p>
        HTML;

    public static function body(string $slug): string
    {
        return self::BODY;
    }
}
