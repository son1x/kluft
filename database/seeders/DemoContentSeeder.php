<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Beispielhafter Startcontent, damit das CMS nicht leer wirkt, bevor echte
 * redaktionelle Inhalte gepflegt werden. Stufenbeschreibungen sind
 * paraphrasiert nach den Angaben der DPSG-Bundeshomepage (dpsg.de),
 * Diözesanbüro-Angaben nach dpsg-trier.de. Kein Ersatz für die eigentliche
 * Altcontent-Migration (F-MIG, bewusst zuletzt), sondern Demo-Material, das
 * jederzeit durch echte Redaktion überschrieben werden kann/soll.
 */
class DemoContentSeeder extends Seeder
{
    public const LEITUNG_BODY = <<<'HTML'
        <p>Der DPSG Diözesanverband Trier wird ehrenamtlich geleitet. Zur Diözesanleitung gehören der Diözesanvorstand sowie Referent*innen für die Stufen und Fachbereiche. Sie vertreten den Verband, setzen die Beschlüsse der Diözesanversammlung um und gestalten die inhaltliche Arbeit im ganzen Bistum Trier.</p>
        <p>Hauptberuflich unterstützt wird die Diözesanleitung vom <a href="/dioezesanbuero">Diözesanbüro</a> mit den Bildungsreferent*innen.</p>
        <p>[TODO: Zusammensetzung, Namen und Funktionen vor Veröffentlichung von der Diözesanleitung bestätigen lassen.]</p>
        <h2>Diözesanvorstand</h2>
        <ul>
        <li><strong>Diözesanvorstand:</strong> Till Mang</li>
        <li><strong>Diözesankurat:</strong> Peter Zillgen</li>
        </ul>
        <h2>Vorstandsreferenten</h2>
        <ul>
        <li><strong>Vorstandsreferent (Region Mitte):</strong> Felix Monzen</li>
        <li><strong>Vorstandsreferent:</strong> Christopher Klein</li>
        </ul>
        <h2>Referent*innen für Stufen und Fachbereiche</h2>
        <ul>
        <li><strong>Wölflingsstufe:</strong> Heike Franz &amp; Lisa Tinanoff</li>
        <li><strong>Jungpfadfinderstufe:</strong> Silas Weiß</li>
        <li><strong>Roverstufe:</strong> Tobias Jost</li>
        <li><strong>Inklusion / Ich-Du-Wir:</strong> Bianca Boltze</li>
        <li><strong>Bolivienpartnerschaft:</strong> Jannis Odrosek</li>
        </ul>
        <p>Kontakt zur Diözesanleitung: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
        <h2>Struktur des Diözesanverbands</h2>
        <p>Der Diözesanverband Trier gliedert sich in 10 Bezirke im gesamten Bistum Trier, von der Mosel über Eifel und Hunsrück bis ins Saarland, mit mehr als 60 Stämmen und Siedlungen und über 5.000 Mitgliedern. Alle Bezirke und Stämme mit Kontakt und Karte findest du unter <a href="/bezirke-staemme">Bezirke &amp; Stämme</a>.</p>
        HTML;

    public const DV_BODY = <<<'HTML'
        <p>Die Deutsche Pfadfinderschaft Sankt Georg (DPSG) ist organisiert in Stämmen vor Ort, die sich zu Bezirken zusammenschließen. Mehrere Bezirke bilden einen Diözesanverband – für das Bistum Trier ist das der Diözesanverband Trier, mit 10 Bezirken von der Mosel über Eifel und Hunsrück bis ins Saarland.</p>
        HTML;

    public const GEORGSBOTE_BODY = <<<'HTML'
        <p>Der Georgsbote ist die Zeitschrift des DPSG Diözesanverbands Trier mit Berichten aus Stufen, Bezirken und Stämmen. Hier findest du alle Ausgaben als PDF. Kurz und regelmäßig informiert dich unser Newsletter, der Georgsbrief.</p>
        HTML;

    public const GEORGSBRIEF_BODY = <<<'HTML'
        <p>Der Georgsbrief ist der Newsletter der DPSG Trier: Termine, Neuigkeiten und Infos aus dem Diözesanverband – direkt in dein Postfach.</p>
        HTML;

    public const STAEMME_BODY = <<<'HTML'
        <p>Wo finde ich meinen Stamm? Hier findest du alle Bezirke und Stämme im Bistum Trier mit Kontakt und den Stufen, die sie anbieten.</p>
        HTML;

    public const FACHBEREICHE_BODY = <<<'HTML'
        <p>Neben den Stufen gibt es im Diözesanverband Fachbereiche. Sie kümmern sich um Themen, die alle Altersstufen betreffen, und unterstützen Stämme, Bezirke und Leiter*innen mit Ideen, Material und Aktionen.</p>
        HTML;

    public const GERECHTIGKEIT_BODY = <<<'HTML'
        <p>Der Fachbereich Internationale Gerechtigkeit beschäftigt sich mit globalen Zusammenhängen: Wie leben Menschen in anderen Teilen der Welt, was hat unser Alltag damit zu tun, und wie können wir als Pfadfinder*innen zu einer gerechteren Welt beitragen?</p>
        <p>Dazu gehören Themen wie fairer Handel, Begegnungen über Grenzen hinweg und Aktionen, die Gruppen in ihren Gruppenstunden aufgreifen können.</p>
        HTML;

    public const INKLUSION_BODY = <<<'HTML'
        <p>Pfadfinden ist für alle da – mit und ohne Behinderung. Der Fachbereich Inklusion („Ich-Du-Wir“) unterstützt Stämme und Leiter*innen dabei, Gruppenstunden, Lager und Aktionen so zu gestalten, dass alle mitmachen können.</p>
        <p>Bei Fragen rund um Inklusion in deiner Gruppe hilft der Fachbereich gern weiter.</p>
        HTML;

    public const SPENDEN_BODY = <<<'HTML'
        <p>Mit deiner Spende unterstützt du die Projekte unserer Partner*innen in Bolivien. Die Partnerschaft lebt von Begegnung und Austausch – und von Menschen, die sie mittragen.</p>
        <h2>Bankverbindung</h2>
        <p>[TODO: Kontoinhaber, IBAN, BIC und Verwendungszweck durch die Bolivien-Verantwortlichen ergänzen.]</p>
        <h2>Spendenbescheinigung</h2>
        <p>[TODO: Hinweis zur Zuwendungsbestätigung ergänzen.]</p>
        <p>Fragen zur Partnerschaft und zu Spenden: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
        HTML;

    public const BUERO_BODY = <<<'HTML'
        <p>Das Diözesanbüro ist die hauptberufliche Unterstützung des Verbands: Bildungsreferent*innen und Verwaltung begleiten die Ehrenamtlichen in Bezirken, Stämmen und Arbeitskreisen – bei Ausbildung, Veranstaltungen und Organisation. Geleitet wird der Verband von der ehrenamtlichen <a href="/dioezesanleitung">Diözesanleitung</a>.</p>
        <h2>Anschrift</h2>
        <p>DPSG Diözesanverband Trier<br>
        Diözesanbüro<br>
        Weberbach 71<br>
        54290 Trier</p>
        <p>Telefon: <a href="tel:+4965146298290">0651 4 629 829 0</a><br>
        E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
        <h2>Team im Diözesanbüro</h2>
        <p>[TODO: Namen, Funktionen und Kontaktdaten vor Veröffentlichung bestätigen lassen.]</p>
        <ul>
        <li><strong>Geschäftsführender Bildungsreferent:</strong> Marc Basenach</li>
        <li><strong>Bildungsreferent:</strong> Axel Hemgesberg</li>
        <li><strong>Bildungsreferentin:</strong> Lisa Alm</li>
        <li><strong>Bildungsreferentin:</strong> Julia Lehn</li>
        <li><strong>Verwaltung:</strong> Miriam Dobner</li>
        </ul>
        HTML;

    public function run(): void
    {
        $pages = [
            [
                // Titel = Hero-Überschrift, SEO-Beschreibung = Hero-Unterzeile.
                'slug' => 'start',
                'title' => 'Pfadfinden im Bistum Trier',
                'seo_title' => 'DPSG Diözesanverband Trier',
                'seo_description' => 'Katholische Pfadfinder*innen von der Mosel bis ins Saarland',
                'show_in_nav' => false,
                'body' => <<<'HTML'
                    <h2>Willkommen beim Diözesanverband Trier</h2>
                    <p>Der DPSG Diözesanverband Trier ist die katholische Pfadfinder*innenarbeit im gesamten Bistum Trier – von der Mosel über Eifel und Hunsrück bis ins Saarland. Gemeinsam mit unseren Bezirken und Stämmen gestalten wir Kindern und Jugendlichen einen Ort zum Wachsen.</p>
                    <p>In 10 Bezirken engagieren sich mehr als 5.000 Mitglieder in über 60 Stämmen und Siedlungen. Vom neugierigen Entdecken als Biber bis zu eigenverantwortlichen Projekten als Rover begleiten wir junge Menschen stufengerecht auf ihrem Weg – mehr dazu bei den <a href="/stufen">Stufen</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'fachbereiche',
                'title' => 'Fachbereiche',
                'show_in_nav' => true,
                'nav_sort' => 30,
                'body' => self::FACHBEREICHE_BODY,
            ],
            [
                'slug' => 'bolivien',
                'title' => 'Bolivienpartnerschaft',
                'section' => 'bolivien',
                'parent' => 'fachbereiche',
                'seo_description' => 'Partnerschaft mit Pfadfinder*innen in Bolivien',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => <<<'HTML'
                    <p>Eingebettet in die Bolivienpartnerschaft des Bistums Trier pflegt der Diözesanverband Kontakte zu Pfadfinder*innen in Bolivien. Begegnungen, gemeinsame Projekte und der Austausch über Ländergrenzen hinweg machen Pfadfinden als weltweite Bewegung erlebbar.</p>
                    <p>[TODO: Aktuelle Projekte, Ansprechperson und Spendenmöglichkeiten ergänzen.]</p>
                    HTML,
            ],
            [
                'slug' => 'internationale-gerechtigkeit',
                'title' => 'Internationale Gerechtigkeit',
                'section' => 'internationale-gerechtigkeit',
                'parent' => 'fachbereiche',
                'seo_description' => 'Global denken, gemeinsam handeln',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'body' => self::GERECHTIGKEIT_BODY,
            ],
            [
                'slug' => 'inklusion',
                'title' => 'Inklusion',
                'section' => 'inklusion',
                'parent' => 'fachbereiche',
                'seo_description' => 'Pfadfinden für alle – Ich, Du, Wir',
                'show_in_nav' => true,
                'nav_sort' => 30,
                'body' => self::INKLUSION_BODY,
            ],
            [
                'slug' => 'dioezesanverband',
                'title' => 'Diözesanverband',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => self::DV_BODY,
            ],
            [
                'slug' => 'georgsbote',
                'title' => 'Georgsbote',
                'parent' => 'dioezesanverband',
                'seo_description' => 'Die Zeitschrift des Diözesanverbands – alle Ausgaben als PDF',
                'show_in_nav' => true,
                'nav_sort' => 30,
                'body' => self::GEORGSBOTE_BODY,
            ],
            [
                'slug' => 'georgsbrief',
                'title' => 'Georgsbrief',
                'parent' => 'dioezesanverband',
                'seo_description' => 'Der Newsletter der DPSG Trier',
                'show_in_nav' => true,
                'nav_sort' => 40,
                'body' => self::GEORGSBRIEF_BODY,
            ],
            [
                'slug' => 'bezirke-staemme',
                'title' => 'Bezirke & Stämme',
                'parent' => 'dioezesanverband',
                'seo_description' => 'Alle Bezirke und Stämme im Bistum Trier auf der Karte',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'body' => self::STAEMME_BODY,
            ],
            [
                'slug' => 'dioezesanleitung',
                'title' => 'Diözesanleitung',
                'parent' => 'dioezesanverband',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => self::LEITUNG_BODY,
            ],
            [
                'slug' => 'dioezesanbuero',
                'title' => 'Diözesanbüro',
                'parent' => 'kontakt',
                'show_in_nav' => false,
                'body' => self::BUERO_BODY,
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
                'seo_title' => 'Biber (4 bis 7 Jahre) – DPSG Diözesanverband Trier',
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
                'seo_title' => 'Wölflinge (6 bis 10 Jahre) – DPSG Diözesanverband Trier',
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
                'seo_title' => 'Jungpfadfinder*innen (10 bis 13 Jahre) – DPSG Diözesanverband Trier',
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
                'seo_title' => 'Pfadfinder*innen (13 bis 15 Jahre) – DPSG Diözesanverband Trier',
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
                'seo_title' => 'Rover (15 bis 20 Jahre) – DPSG Diözesanverband Trier',
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

        Page::query()->updateOrCreate(['slug' => 'bolivien-spenden'], [
            'parent_id' => Page::query()->where('slug', 'bolivien')->value('id'),
            'section' => 'bolivien',
            'title' => 'Spenden',
            'body' => self::SPENDEN_BODY,
            'status' => 'published',
            'published_at' => now(),
            'nav_sort' => 90,
        ]);

        $this->seedPosts();
    }

    private function seedPosts(): void
    {
        $posts = [
            [
                'slug' => 'neue-website-im-aufbau',
                'title' => 'Unsere neue Website ist im Aufbau',
                'teaser' => 'Der Diözesanverband bekommt einen neuen Webauftritt – mit eigenem Redaktionssystem für Stufen, Bezirke und das Diözesanbüro.',
                'body' => '<p>Schritt für Schritt entsteht hier der neue Webauftritt des DPSG Diözesanverbands Trier. Stufen, Arbeitskreise und das Diözesanbüro können Inhalte künftig selbst pflegen. Neu ist das Verzeichnis aller <a href="/bezirke-staemme">Bezirke und Stämme</a> mit Karte; Veranstaltungen und der Georgsbote folgen.</p>',
                'days_ago' => 0,
            ],
            [
                'slug' => 'fuenf-stufen-im-ueberblick',
                'title' => 'Fünf Stufen im Überblick',
                'teaser' => 'Von den Bibern bis zu den Rover*innen: Jede Altersstufe hat ihre eigene Pädagogik, ihre eigenen Methoden und ihre eigene Farbe.',
                'body' => '<p>Auf den neuen <a href="/stufen">Stufenseiten</a> stellen wir Biber, Wölflinge, Jungpfadfinder*innen, Pfadfinder*innen und Rover vor – mit Altersspanne und den Schwerpunkten der jeweiligen Stufe.</p>',
                'days_ago' => 1,
            ],
            [
                'slug' => 'so-erreichst-du-das-dioezesanbuero',
                'title' => 'So erreichst du das Diözesanbüro',
                'teaser' => 'Fragen zum Verband, zu einem Bezirk oder zum Mitmachen? Das Diözesanbüro in der Weberbach in Trier hilft weiter.',
                'body' => '<p>Alle Kontaktdaten und das Leitungsteam findest du auf der Seite <a href="/dioezesanbuero">Diözesanbüro</a>.</p>',
                'days_ago' => 2,
            ],
        ];

        foreach ($posts as $data) {
            Post::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'teaser' => $data['teaser'],
                    'body' => $data['body'],
                    'scope' => 'general',
                    'status' => 'published',
                    'published_at' => now()->subDays($data['days_ago']),
                ]
            );
        }
    }
}
