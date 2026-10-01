<?php

namespace Database\Seeders;

use App\Models\Page;
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
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'start',
                'title' => 'DPSG Diözesanverband Trier',
                'show_in_nav' => false,
                'body' => <<<'HTML'
                    <p>Der DPSG Diözesanverband Trier ist die katholische Pfadfinder*innenarbeit im gesamten Bistum Trier – von der Mosel bis ins Saarland. Gemeinsam mit unseren Bezirken und Stämmen gestalten wir Kindern und Jugendlichen einen Ort zum Wachsen.</p>
                    <h2>Fünf Stufen, ein Weg</h2>
                    <p>Vom neugierigen Entdecken als Biber bis zu eigenverantwortlichen Projekten als Rover begleiten wir junge Menschen stufengerecht auf ihrem Weg. Mehr dazu auf der <a href="/stufen">Stufen-Seite</a>.</p>
                    <h2>Fragen oder Kontakt?</h2>
                    <p>Das <a href="/dioezesanbuero">Diözesanbüro</a> ist deine zentrale Anlaufstelle für alles rund um den Diözesanverband.</p>
                    HTML,
            ],
            [
                'slug' => 'dioezesanbuero',
                'title' => 'Diözesanbüro',
                'show_in_nav' => true,
                'nav_sort' => 10,
                'body' => <<<'HTML'
                    <p>Das Diözesanbüro ist die zentrale Anlaufstelle des DPSG Diözesanverbands Trier – für Mitglieder, Bezirke, Stämme und alle, die mehr über uns erfahren möchten.</p>
                    <h2>Anschrift</h2>
                    <p>DPSG Diözesanverband Trier<br>
                    Diözesanbüro<br>
                    Weberbach 71<br>
                    54290 Trier</p>
                    <p>Telefon: <a href="tel:+4965146298290">0651 4 629 829 0</a><br>
                    E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
                    <h2>Die Diözesanleitung</h2>
                    <p>Der Diözesanverband wird von einer ehrenamtlichen Diözesanleitung geführt – unterstützt von Kurat*in, Fach- und Stufenreferent*innen sowie dem Diözesanbüro. [TODO: Namen, Rollen und Kontaktdaten vor Veröffentlichung durch die Verwaltung bestätigen lassen.]</p>
                    <ul>
                    <li><strong>Diözesanvorstand:</strong> Till Mang</li>
                    <li><strong>Diözesankurat:</strong> Peter Zillgen</li>
                    <li><strong>Vorstandsreferent (Region Mitte):</strong> Felix Monzen</li>
                    <li><strong>Vorstandsreferent:</strong> Christopher Klein</li>
                    <li><strong>Geschäftsführender Bildungsreferent:</strong> Marc Basenach</li>
                    <li><strong>Bildungsreferent:</strong> Axel Hemgesberg</li>
                    <li><strong>Bildungsreferentin:</strong> Lisa Alm</li>
                    <li><strong>Bildungsreferentin:</strong> Julia Lehn</li>
                    <li><strong>Referentinnen Wölflingstufe:</strong> Heike Franz &amp; Lisa Tinanoff</li>
                    <li><strong>Referent Jungpfadfinderstufe:</strong> Silas Weiß</li>
                    <li><strong>Referent Roverstufe:</strong> Tobias Jost</li>
                    <li><strong>Referentin Inklusion / Ich-Du-Wir:</strong> Bianca Boltze</li>
                    <li><strong>Referent Bolivienpartnerschaft:</strong> Jannis Odrosek</li>
                    <li><strong>Verwaltung, Diözesanbüro:</strong> Miriam Dobner</li>
                    </ul>
                    <h2>Struktur des Diözesanverbands</h2>
                    <p>Der Diözesanverband Trier gliedert sich in 10 Bezirke im gesamten Bistum Trier, von der Mosel über Eifel und Hunsrück bis ins Saarland, mit mehr als 60 Stämmen und Siedlungen und über 5.000 Mitgliedern. Ein eigenes Bezirke- und Stämme-Verzeichnis mit Kartenansicht ist in Vorbereitung (Pflichtenheft 6.11).</p>
                    HTML,
            ],
            [
                'slug' => 'stufen',
                'title' => 'Die Stufen',
                'show_in_nav' => true,
                'nav_sort' => 20,
                'body' => <<<'HTML'
                    <p>Die DPSG begleitet Kinder und Jugendliche in fünf altersgerechten Stufen – von den ersten Entdeckungen als Biber bis zu eigenverantwortlichen Projekten als Rover. Jede Stufe hat ihre eigene Pädagogik, ihre eigenen Methoden und ihre eigene Farbe.</p>
                    <ul>
                    <li><strong><a href="/stufe-biber">Biber</a></strong> (4 bis 7 Jahre) – neugierig durch die Welt gehen</li>
                    <li><strong><a href="/stufe-woelflinge">Wölflinge</a></strong> (6 bis 10 Jahre) – Forscher*innen, Entdecker*innen und Kundschafter*innen</li>
                    <li><strong><a href="/stufe-jupfi">Jungpfadfinder*innen</a></strong> (10 bis 13 Jahre) – Stück für Stück die Welt erobern</li>
                    <li><strong><a href="/stufe-pfadi">Pfadfinder*innen</a></strong> (13 bis 15 Jahre) – das eigene Leben selbst in die Hand nehmen</li>
                    <li><strong><a href="/stufe-rover">Rover</a></strong> (15 bis 20 Jahre) – Entscheidungen für die eigene Zukunft treffen</li>
                    </ul>
                    HTML,
            ],
            [
                'slug' => 'stufe-biber',
                'title' => 'Biber',
                'stage' => 'biber',
                'seo_title' => 'Biber (4 bis 7 Jahre) – DPSG Diözesanverband Trier',
                'body' => <<<'HTML'
                    <p><strong>4 bis 7 Jahre</strong> – neugierig durch die Welt gehen</p>
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
                'title' => 'Wölflinge',
                'stage' => 'woelflinge',
                'seo_title' => 'Wölflinge (6 bis 10 Jahre) – DPSG Diözesanverband Trier',
                'body' => <<<'HTML'
                    <p><strong>6 bis 10 Jahre</strong> – Forscher*innen, Entdecker*innen und Kundschafter*innen</p>
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
                'title' => 'Jungpfadfinder*innen',
                'stage' => 'jupfi',
                'seo_title' => 'Jungpfadfinder*innen (10 bis 13 Jahre) – DPSG Diözesanverband Trier',
                'body' => <<<'HTML'
                    <p><strong>10 bis 13 Jahre</strong> – Stück für Stück die Welt erobern</p>
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
                'title' => 'Pfadfinder*innen',
                'stage' => 'pfadi',
                'seo_title' => 'Pfadfinder*innen (13 bis 15 Jahre) – DPSG Diözesanverband Trier',
                'body' => <<<'HTML'
                    <p><strong>13 bis 15 Jahre</strong> – das eigene Leben selbst in die Hand nehmen</p>
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
                'title' => 'Rover',
                'stage' => 'rover',
                'seo_title' => 'Rover (15 bis 20 Jahre) – DPSG Diözesanverband Trier',
                'body' => <<<'HTML'
                    <p><strong>15 bis 20 Jahre</strong> – Entscheidungen für die eigene Zukunft treffen</p>
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
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => 'published',
                    'published_at' => now(),
                    'stage' => $data['stage'] ?? null,
                    'seo_title' => $data['seo_title'] ?? null,
                    'show_in_nav' => $data['show_in_nav'] ?? false,
                    'nav_sort' => $data['nav_sort'] ?? 0,
                ]
            );
        }
    }
}
