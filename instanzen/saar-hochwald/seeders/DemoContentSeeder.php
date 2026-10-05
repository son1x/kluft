<?php

namespace Instanzen\SaarHochwald\Seeders;

use App\Models\Page;
use App\Models\Person;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\Seeder;

/**
 * Startinhalte des Bezirks Saar-Hochwald, übernommen von der bisherigen
 * statischen Bezirksseite (Release 1.0, liegt in legacy-static-site/).
 * Stufenbeschreibungen sind paraphrasiert nach dpsg.de (wie im
 * Upstream-CMS des DV Trier). Alles lässt sich danach im Backend pflegen;
 * ein erneutes Seeden überschreibt Seiten mit gleichem Slug.
 */
class DemoContentSeeder extends Seeder
{
    public const BEZIRK_BODY = <<<'HTML'
        <p>Katholische Pfadfinder*innenarbeit zwischen Saarlouis und Perl – gemeinsam mit unseren Stämmen gestalten wir Kindern und Jugendlichen einen Ort zum Wachsen.</p>
        <h2>Was ist die DPSG?</h2>
        <p>Die Deutsche Pfadfinderschaft Sankt Georg (DPSG) ist mit über 80.000 Mitgliedern einer der größten katholischen Jugendverbände Deutschlands. Sie versteht sich als Gemeinschaft, in der Kinder und Jugendliche Verantwortung übernehmen, Gemeinschaft erleben und ihren Glauben leben – nach den Grundsätzen der internationalen Pfadfinderbewegung.</p>
        <p>Organisiert ist die DPSG in Stämmen vor Ort, die sich zu Bezirken zusammenschließen. Mehrere Bezirke bilden einen Diözesanverband – für uns der <a href="https://www.dpsg-trier.de" target="_blank" rel="noopener">Diözesanverband Trier</a>, einer von 24 Diözesanverbänden der DPSG bundesweit. Im Saarland gibt es drei Bezirke: Saar-Hochwald, Schaumberg-Blies und Saarbrücken.</p>
        <h2>Unser Bezirksgebiet</h2>
        <p>Der Bezirk Saar-Hochwald umfasst im Wesentlichen den Landkreis Saarlouis sowie angrenzende Gemeinden in Richtung Merzig-Wadern – von der Kreisstadt Saarlouis bis hinüber nach Perl an der Grenze zu Frankreich und Luxemburg. Fünf Stämme sind hier aktiv; alle mit Karte findest du unter <a href="/staemme">Unsere Stämme</a>.</p>
        <p>Getragen wird der Bezirk von seinen Stämmen, ihren ehrenamtlichen Leiter*innen und dem Bezirksvorstand, der die Arbeit vor Ort koordiniert und unterstützt – mehr dazu bei der <a href="/bezirksleitung">Bezirksleitung</a>.</p>
        HTML;

    public const LEITUNG_BODY = <<<'HTML'
        <p>Der Bezirk wird von einem ehrenamtlichen Vorstand geleitet – unterstützt von einem Kurat für die geistliche Begleitung und Fach- bzw. Stufenreferent*innen für die einzelnen Altersstufen.</p>
        <p>Kontakt zur Bezirksleitung: <a href="mailto:bezirksleitung@dpsg-saar-hochwald.de">bezirksleitung@dpsg-saar-hochwald.de</a></p>
        HTML;

    public const STAEMME_BODY = <<<'HTML'
        <p>Wo finde ich meinen Stamm? Fünf Stämme, fünf Orte – hier findest du alle Stämme im Bezirk Saar-Hochwald mit Kontakt, den Stufen, die sie anbieten, und Karte.</p>
        HTML;

    public const PRAEVENTION_BODY = <<<'HTML'
        <p>Die Sicherheit und das Wohl der uns anvertrauten Kinder und Jugendlichen stehen für uns an oberster Stelle. Das gilt für jeden Stamm und jede Gruppenstunde im Bezirk Saar-Hochwald.</p>
        <h2>Was wir tun</h2>
        <p>Alle Leiter*innen im Bezirk durchlaufen verpflichtende Präventionsschulungen zum Schutz vor sexualisierter Gewalt und verpflichten sich auf den DPSG-Verhaltenskodex. Zusätzlich legt jede Leitung ein erweitertes polizeiliches Führungszeugnis vor, das regelmäßig erneuert wird.</p>
        <ul>
        <li><strong>Präventionsschulung:</strong> Pflicht für alle Leiter*innen</li>
        <li><strong>Erweitertes Führungszeugnis:</strong> für jede ehrenamtliche Leitung</li>
        <li><strong>Verhaltenskodex:</strong> verbindlich für alle Verantwortlichen</li>
        <li><strong>Ansprechpartner*in Prävention:</strong> für Fragen und im Verdachtsfall</li>
        </ul>
        <p>Grundlage dafür ist die Präventionsordnung der <a href="https://www.dpsg.de" target="_blank" rel="noopener">DPSG</a> sowie das institutionelle Schutzkonzept des Diözesanverbands Trier, das für alle Stämme und Bezirke im Verband verbindlich ist.</p>
        <h2>Unser Schutzkonzept</h2>
        <p>Das ausführliche institutionelle Schutzkonzept des Bezirks beschreibt Präventions-, Interventions- und Beschwerdewege im Detail. Es wird hier verlinkt, sobald es vom Bezirksvorstand bereitgestellt ist; ebenso die Kontaktdaten der Präventionsfachkraft.</p>
        <h2>Im Verdachtsfall</h2>
        <p>Wenn du den Eindruck hast, dass einem Kind oder einer/einem Jugendlichen Gewalt angetan wird, melde dich – vertraulich und ohne Vorverurteilung: über das <a href="/kontakt?thema=praevention#kontaktformular">Kontaktformular</a>, bei der <a href="/bezirksleitung">Bezirksleitung</a> oder beim <a href="https://www.dpsg-trier.de" target="_blank" rel="noopener">Diözesanverband Trier</a>.</p>
        HTML;

    public function run(): void
    {
        $pages = [
            [
                // Titel = Hero-Überschrift, SEO-Beschreibung = Hero-Unterzeile.
                'slug' => 'start',
                'title' => 'Pfadfinden zwischen Saar, Mosel und Hochwald',
                'seo_title' => 'DPSG Bezirk Saar-Hochwald',
                'seo_description' => 'Katholisch, bunt und immer draußen unterwegs',
                'show_in_nav' => false,
                'body' => <<<'HTML'
                    <h2>Willkommen im Bezirk Saar-Hochwald</h2>
                    <p>Fünf Stämme, hunderte Kinder und Jugendliche, ein gemeinsames Abenteuer: Wir sind der Bezirk Saar-Hochwald der Deutschen Pfadfinderschaft Sankt Georg – katholisch, bunt und immer draußen unterwegs.</p>
                    <p>Der Bezirk ist einer von drei Bezirken des DPSG-Diözesanverbands Trier im Saarland und erstreckt sich über den Landkreis Saarlouis und Teile des Landkreises Merzig-Wadern – von der Kreisstadt Saarlouis bis nach Perl. Vom Kindergartenalter bis ins junge Erwachsenenalter begleiten wir junge Menschen mit einer Gruppenstunde pro Woche, gemeinsamen Fahrten und großen Lagern – mehr dazu bei den <a href="/stufen">Stufen</a> und <a href="/staemme">unseren Stämmen</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'der-bezirk',
                'title' => 'Der Bezirk',
                'seo_description' => 'Katholische Pfadfinder*innenarbeit zwischen Saarlouis und Perl',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => self::BEZIRK_BODY,
            ],
            [
                'slug' => 'bezirksleitung',
                'title' => 'Bezirksleitung',
                'parent' => 'der-bezirk',
                'seo_description' => 'Wer den Bezirk trägt: Vorstand, Kurat und Referent*innen',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => self::LEITUNG_BODY,
            ],
            [
                'slug' => 'staemme',
                'title' => 'Unsere Stämme',
                'parent' => 'der-bezirk',
                'seo_description' => 'Alle Stämme im Bezirk Saar-Hochwald auf der Karte',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'body' => self::STAEMME_BODY,
            ],
            [
                'slug' => 'praevention',
                'title' => 'Prävention & Schutzkonzept',
                'parent' => 'der-bezirk',
                'seo_description' => 'So schützen wir Kinder und Jugendliche – und so erreichst du uns im Verdachtsfall',
                'show_in_nav' => true,
                'nav_sort' => 30,
                'body' => self::PRAEVENTION_BODY,
            ],
            [
                'slug' => 'newsletter',
                'title' => 'Newsletter',
                'parent' => 'der-bezirk',
                'seo_description' => 'Termine und Neuigkeiten aus dem Bezirk direkt ins Postfach',
                'show_in_nav' => true,
                'nav_sort' => 40,
                'body' => <<<'HTML'
                    <p>Mit unserem Newsletter bekommst du Termine, Anmeldestarts und Neuigkeiten aus dem Bezirk Saar-Hochwald direkt in dein Postfach – für Leiter*innen, Eltern und alle, die mit uns verbunden sind.</p>
                    HTML,
            ],
            [
                'slug' => 'stufen',
                'title' => 'Die Stufen',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'body' => <<<'HTML'
                    <p>Die DPSG begleitet Kinder und Jugendliche in fünf altersgerechten Stufen – von den ersten Entdeckungen als Biber bis zu eigenverantwortlichen Projekten als Rover. Jede Stufe hat ihre eigene Pädagogik, ihre eigenen Methoden und ihre eigene Farbe.</p>
                    HTML,
            ],
            [
                'slug' => 'stufe-biber',
                'parent' => 'stufen',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'title' => 'Biber',
                'stage' => 'biber',
                'seo_title' => 'Biber (4 bis 7 Jahre) – DPSG Bezirk Saar-Hochwald',
                'body' => <<<'HTML'
                    <p>Ab dem Alter von vier Jahren können Mädchen und Jungen Mitglied in einer Bibergruppe werden. Schon vor dem Einstieg in die Wölflingsstufe lernen sie hier das Abenteuer Pfadfinden kennen. Kinder in diesem Alter gehen mit neugierigen Augen durch die Welt – in der Bibergruppe können sie das gemeinsam mit Freund*innen tun, begleitet von den Leiter*innen ihrer Gruppe.</p>
                    <p>Im Mittelpunkt stehen gemeinsames Spielen und Erleben in der Gruppe, ergänzt um erste pfadfinderische Aktionen wie ein gemeinsames Lagerfeuer.</p>
                    <p>Die Biberstufe wurde 2016 von der Bundesversammlung beschlossen und 2017 als Vorgruppe vor der Wölflingsstufe fest in der Satzung verankert.</p>
                    <ul>
                    <li>Erste Gruppe vor dem Einstieg in die Wölflingsstufe</li>
                    <li>Gemeinsames Erleben: Spielen, Lagerfeuer und kleine Abenteuer</li>
                    <li>Übergang zu den Wölflingen ab ca. 6 Jahren</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'stufe-woelflinge',
                'parent' => 'stufen',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'title' => 'Wölflinge',
                'stage' => 'woelflinge',
                'seo_title' => 'Wölflinge (6 bis 10 Jahre) – DPSG Bezirk Saar-Hochwald',
                'body' => <<<'HTML'
                    <p>Wölflinge sind Kinder im Alter von 6 bis 10 Jahren. In diesem Alter sind sie Forscher*innen, Entdecker*innen, Kundschafter*innen und Kritiker*innen – sie wollen ihre Welt auch außerhalb des Elternhauses spielerisch entdecken.</p>
                    <p>Dabei geht es um vier Entdeckungsbereiche: hinter die Dinge schauen und Unbekanntes kennenlernen, zu anderen finden und sich gegenseitig akzeptieren, zu sich selbst finden und eigene Stärken sowie Schwächen erkennen, und erfahren, dass Gott für sie da ist.</p>
                    <p>Die wichtigste Aktionsform ist dabei das Spiel. Wölflinge bestimmen mit – bei Gruppenstunden, Festen, Lagern und der Stammesversammlung.</p>
                    <ul>
                    <li>Vier Entdeckungsbereiche: Welt, andere, sich selbst, Gott</li>
                    <li>Das Spiel als wichtigste Aktionsform</li>
                    <li>Übergang zu den Jungpfadfinder*innen ab ca. 10 Jahren</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'stufe-jupfi',
                'parent' => 'stufen',
                'show_in_nav' => true,
                'nav_sort' => 30,
                'title' => 'Jungpfadfinder*innen',
                'stage' => 'jupfi',
                'seo_title' => 'Jungpfadfinder*innen (10 bis 13 Jahre) – DPSG Bezirk Saar-Hochwald',
                'body' => <<<'HTML'
                    <p>Jungpfadfinder*innen sind 10 bis 13 Jahre alt und treffen sich meist einmal wöchentlich zur Truppstunde, in Gruppen von etwa 10 bis 20 Mitgliedern.</p>
                    <p>Im Trupp erfahren sie, was es heißt, gemeinsam in einer Gruppe zu leben: sich auseinanderzusetzen, miteinander umzugehen, voneinander zu lernen und aufeinander Rücksicht zu nehmen – immer mit Spaß am gemeinsamen Erleben.</p>
                    <p>Im Mittelpunkt steht das Abenteuer: Stück für Stück erobern Jungpfadfinder*innen ihre Welt, schauen hinter die Dinge und lernen eigene Grenzen kennen. Dabei bestimmen sie das Truppleben zunehmend selbst mit und übernehmen Verantwortung dafür.</p>
                    <ul>
                    <li>Der Trupp: 10 bis 20 Mitglieder, wöchentliche Truppstunde</li>
                    <li>Schwerpunkt: die eigene Welt Stück für Stück erobern</li>
                    <li>Übergang zu den Pfadfinder*innen ab ca. 13 Jahren</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'stufe-pfadi',
                'parent' => 'stufen',
                'show_in_nav' => true,
                'nav_sort' => 40,
                'title' => 'Pfadfinder*innen',
                'stage' => 'pfadi',
                'seo_title' => 'Pfadfinder*innen (13 bis 15 Jahre) – DPSG Bezirk Saar-Hochwald',
                'body' => <<<'HTML'
                    <p>Unter dem Motto „Wagt es" ermutigt die Pfadfinderstufe dazu, das eigene Leben selbst in die Hand zu nehmen. Pfadfinder*innen machen sich gemeinsam auf den Weg, um sich persönlich weiterzuentwickeln – in Truppstunden, auf Fahrten oder ganz für sich allein.</p>
                    <p>Diese persönliche Entwicklung bleibt nicht losgelöst vom Geschehen in der Welt: Pfadfinder*innen sind aufgerufen, ihre Zeit zu nutzen, um – ganz im Sinne des Gründers der Pfadfinderbewegung, Robert Baden-Powell – die Welt ein kleines Stück besser zu hinterlassen, als sie sie vorgefunden haben.</p>
                    <ul>
                    <li>Motto „Wagt es": Mut zur eigenen Lebensgestaltung</li>
                    <li>Persönliche Weiterentwicklung in Trupp, auf Fahrt und im Alltag</li>
                    <li>Übergang zu den Rovern ab ca. 15 Jahren</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'stufe-rover',
                'parent' => 'stufen',
                'show_in_nav' => true,
                'nav_sort' => 50,
                'title' => 'Rover',
                'stage' => 'rover',
                'seo_title' => 'Rover (15 bis 20 Jahre) – DPSG Bezirk Saar-Hochwald',
                'body' => <<<'HTML'
                    <p>Rover*innen im Alter von 15 bis 20 Jahren treffen Entscheidungen für ihre eigene Zukunft. Sie packen an und entdecken die Welt mit ihren Menschen und Kulturen – häufig auf größeren Fahrten und in eigenverantwortlich geplanten Projekten.</p>
                    <p>Als älteste Stufe der DPSG stehen Rover*innen an der Schwelle zum Erwachsenwerden. Viele engagieren sich bereits als Leiter*in in jüngeren Gruppen oder übernehmen Verantwortung auf Bezirks- und Diözesanebene.</p>
                    <ul>
                    <li>Eigenverantwortung: größere Fahrten und selbst geplante Projekte</li>
                    <li>Älteste Stufe der DPSG, an der Schwelle zum Erwachsenwerden</li>
                    <li>Danach: als volljähriges Mitglied jederzeit als Leiter*in weitermachen</li>
                    </ul>
                    HTML,
            ],
        ];

        foreach ($pages as $data) {
            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'parent_id' => isset($data['parent']) ? Page::query()->where('slug', $data['parent'])->value('id') : null,
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => 'published',
                    'published_at' => now(),
                    'stage' => $data['stage'] ?? null,
                    'section' => $data['section'] ?? null,
                    'seo_title' => $data['seo_title'] ?? null,
                    'seo_description' => $data['seo_description'] ?? null,
                    'show_in_nav' => $data['show_in_nav'] ?? false,
                    'nav_sort' => $data['nav_sort'] ?? 0,
                ]
            );
        }

        $this->seedPeople();
        $this->seedRedirects();
        $this->seedPosts();
    }

    /**
     * Bezirksleitung wie auf der bisherigen Bezirksseite veröffentlicht
     * (dort mit Name, Funktion und E-Mail-Adresse). Nur anlegen, wenn die
     * Person noch nicht existiert – im Backend Gepflegtes bleibt.
     */
    private function seedPeople(): void
    {
        $people = [
            ['Sebastian Hafner', 'Bezirksvorsitzender', 'Bezirksvorstand', null, 's.hafner@dpsg-saar-hochwald.de'],
            ['Stefan Driesch', 'Bezirkskurat', 'Bezirksvorstand', null, 's.driesch@dpsg-saar-hochwald.de'],
            ['Daniel Klasen', 'Fachreferent Ausbildung', 'Referent*innen', null, 'd.klasen@dpsg-saar-hochwald.de'],
            ['Kimberly Lamprecht', 'Stufenreferentin Biber', 'Referent*innen', 'biber', 'k.lamprecht@dpsg-saar-hochwald.de'],
            ['Julia Hafner', 'Stufenreferentin Wölflinge', 'Referent*innen', 'woelflinge', 'j.hafner@dpsg-saar-hochwald.de'],
        ];

        foreach ($people as $i => [$name, $role, $subgroup, $stage, $email]) {
            Person::query()->firstOrCreate(['gremium' => 'leitung', 'name' => $name], [
                'role' => $role,
                'subgroup' => $subgroup,
                'stage' => $stage,
                'badge' => $stage,
                'email' => $email,
                'consent' => true,
                'active' => true,
                'sort' => ($i + 1) * 10,
            ]);
        }
    }

    /**
     * Adressen der bisherigen statischen Seite (Release 1.0) auf die neuen
     * Seiten umleiten, damit Lesezeichen und Suchtreffer weiter funktionieren.
     */
    private function seedRedirects(): void
    {
        $map = [
            '/index.html' => '/',
            '/der-bezirk.html' => '/der-bezirk',
            '/staemme.html' => '/staemme',
            '/termine.html' => '/veranstaltungen',
            '/kontakt.html' => '/kontakt',
            '/praevention.html' => '/praevention',
            '/impressum.html' => '/impressum',
            '/datenschutz.html' => '/datenschutz',
            '/stufen/biber' => '/stufe-biber',
            '/stufen/woelflinge' => '/stufe-woelflinge',
            '/stufen/jungpfadfinder' => '/stufe-jupfi',
            '/stufen/pfadfinder' => '/stufe-pfadi',
            '/stufen/rover' => '/stufe-rover',
            '/staemme/roden' => '/staemme',
            '/staemme/huelzweiler' => '/staemme',
            '/staemme/beaumarais' => '/staemme',
            '/staemme/rehlingen' => '/staemme',
            '/staemme/perl' => '/staemme',
        ];

        foreach ($map as $from => $to) {
            Redirect::query()->firstOrCreate(['from_path' => Redirect::normalize($from)], [
                'to_path' => $to,
                'status_code' => 301,
                'source' => 'release-1.0',
            ]);
        }
    }

    private function seedPosts(): void
    {
        Post::query()->updateOrCreate(
            ['slug' => 'neue-website'],
            [
                'title' => 'Unsere neue Website',
                'teaser' => 'Der Bezirk Saar-Hochwald hat einen neuen Webauftritt – mit eigenem Redaktionssystem, Veranstaltungsanmeldung und Karte aller Stämme.',
                'body' => '<p>Schritt für Schritt entsteht hier der neue Webauftritt des DPSG Bezirks Saar-Hochwald. Bezirksleitung und Stämme können Inhalte künftig selbst pflegen. Neu sind die Karte <a href="/staemme">unserer Stämme</a>, die Online-Anmeldung zu <a href="/veranstaltungen">Veranstaltungen</a> und der Kalender.</p>',
                'scope' => 'general',
                'status' => 'published',
                'published_at' => now(),
            ]
        );
    }
}
