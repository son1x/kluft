<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rubrik „Diözesanlager“: Archive Schongse 2004, futurscout 2011 und
 * Scoutgames 2020 (Material aus dem Aktenplan, 07.11.2026). Nur Inhalte,
 * keine Namen oder Kontaktdaten der damaligen Teams. Nur dv-trier, nach dem
 * Startinhalt; vorhandene Slugs bleiben unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('dpsg.instanz') !== 'dv-trier' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $now = now();
        foreach ($this->seiten() as $s) {
            if (DB::table('pages')->where('slug', $s['slug'])->exists()) {
                continue;
            }
            DB::table('pages')->insert([
                'slug' => $s['slug'],
                'title' => $s['title'],
                'body' => $s['body'],
                'seo_description' => $s['seo_description'],
                'section' => $s['section'],
                'status' => 'published',
                'published_at' => $now,
                'show_in_nav' => false,
                'nav_sort' => $s['nav_sort'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return list<array{slug: string, title: string, section: string, seo_description: string, body: string, nav_sort?: int}>
     */
    private function seiten(): array
    {
        return [
            [
                'slug' => 'schongse',
                'section' => 'schongse',
                'title' => 'Schongse 2004',
                'seo_description' => 'Das Pfingstlager des DPSG-Diözesanverbands Trier vom 28. bis 31. Mai 2004 in Rhens am Rhein, Motto „Das Weltdorf“.',
                'body' => <<<'HTML'
<p>Über Pfingsten, vom 28. bis 31. Mai 2004, trafen sich die Stämme des Diözesanverbands Trier auf dem Jugendzeltplatz in Rhens am Rhein. Das Motto: <strong>Das Weltdorf</strong>.</p>
<h2>Die Idee</h2>
<p>Die erste Idee entstand auf der Diözesanversammlung 2001: „Mal alle Stämme unserer Diözese zusammen bekommen. Die großen und kleinen Pfadis im Diözesanverband Trier lernen sich kennen. Das wär’ was!“ 2002 beschloss die Diözesanversammlung das Lager, im September 2002 traf sich das Team zum ersten Mal und legte das Thema fest.</p>
<blockquote><p>Es sollte etwas mit Internationalität zu tun haben – mit Kulturen aus der ganzen Welt. Das spiegelt am besten die Vielfalt der Diözese wider. Wenn sich ein Saarländer mit einem Westerwälder unterhält, ist das schon, wie wenn sich zwei Menschen aus verschiedenen Ländern unterhalten. Alle zusammen auf einem Zeltplatz, in einem Dorf – das ergibt das Weltdorf Schongse.</p></blockquote>
<p>Die Stämme lebten in fünf Kontinenten und stellten 41 von über 180 Ländern vor. Am Samstag ging es auf „Weltreise“ zu Workshops in den Kontinenten, am Sonntag gab es Stufenprogramm, Gottesdienst und das Weltfest mit zwei Live-Bands.</p>
<h2>In Zahlen</h2>
<ul>
<li>41 Stämme aus 9 Bezirken, über 1.300 Pfadfinderinnen und Pfadfinder (Stand Ende April 2004)</li>
<li>rund 60.000 m² Lagerfläche und ein Großzelt mit 1.000 m²</li>
<li>der entfernteste Stamm kam aus Gersweiler, mehr als 200 km Luftlinie; der nächste aus Koblenz, weniger als 10 km</li>
<li>Teilnahmebeitrag 40 €, für Geschwister 30 €</li>
<li>Gesamtvolumen rund 74.000 €, etwa 15 % davon über Sponsoren – mit positiver Bilanz am Ende</li>
</ul>
<p>Beim Anmeldestand im März 2004 waren dabei:</p>
<figure><table><thead><tr><th>Gruppe</th><th>Personen</th></tr></thead><tbody>
<tr><td>Wölflinge</td><td>286</td></tr>
<tr><td>Jungpfadfinder*innen</td><td>252</td></tr>
<tr><td>Pfadfinder*innen</td><td>171</td></tr>
<tr><td>Rover</td><td>88</td></tr>
<tr><td>Leitende</td><td>241</td></tr>
<tr><td>Helfende</td><td>64</td></tr>
</tbody></table><figcaption>Anmeldungen nach Stufen, Stand 12. März 2004</figcaption></figure>
<h2>Vorbereitung</h2>
<p>Vorbereitet wurde das Lager von einem Leitungsteam, vier Arbeitskreisen (Inhalte, Organisation, Finanzen, Öffentlichkeitsarbeit) und Verantwortlichen für die Kontinente. Diese Aufteilung hat sich bewährt: Auch futurscout 2011 wurde so vorbereitet – wieder in Rhens.</p>
HTML,
            ],
            [
                'slug' => 'schongse-programm',
                'section' => 'schongse',
                'nav_sort' => 10,
                'title' => 'Programm',
                'seo_description' => 'Tagesplan des Pfingstlagers Schongse 2004: Kontinentetag, Stufentag, Weltfest.',
                'body' => <<<'HTML'
<p>So stand das Programm im Flyer vom April 2004.</p>
<h2>Freitag, 28. Mai</h2>
<figure><table><tbody><tr><td>ab 19:00</td><td>Abendessen</td></tr><tr><td>21:00</td><td>Begrüßung</td></tr><tr><td>ab 22:30</td><td>Freizeit</td></tr></tbody></table></figure>
<h2>Samstag, 29. Mai – Kontinentetag</h2>
<figure><table><tbody><tr><td>08:00–09:00</td><td>Frühstück</td></tr><tr><td>09:30</td><td>Gemeinsamer Beginn in den Kontinenten</td></tr><tr><td>10:30–13:00</td><td>Weltreise und Workshops</td></tr><tr><td>13:00–15:00</td><td>Pause (Lunchpakete)</td></tr><tr><td>15:15–17:45</td><td>Weltreise und Workshops</td></tr><tr><td>18:30</td><td>Warmes Abendessen</td></tr><tr><td>20:00</td><td>Kontinentefest</td></tr></tbody></table></figure>
<h2>Sonntag, 30. Mai – Stufentag und Weltfest</h2>
<figure><table><tbody><tr><td>08:00–09:00</td><td>Frühstück</td></tr><tr><td>10:00–15:00</td><td>Stufenprogramm (Lunchpakete)</td></tr><tr><td>15:00–17:00</td><td>Freizeit: Spiele, Besuche, Café International</td></tr><tr><td>17:00</td><td>Gottesdienst</td></tr><tr><td>18:30</td><td>Warmes Abendessen</td></tr><tr><td>20:00</td><td>Weltfest</td></tr><tr><td>ab 21:00</td><td>Live-Bands</td></tr></tbody></table></figure>
<h2>Montag, 31. Mai</h2>
<figure><table><tbody><tr><td>08:00–09:00</td><td>Frühstück</td></tr><tr><td>11:00</td><td>Gemeinsame Reflexion</td></tr><tr><td>11:30</td><td>Abtakt</td></tr><tr><td>12:00</td><td>Warmes Mittagessen</td></tr></tbody></table></figure>
HTML,
            ],
            [
                'slug' => 'futurscout',
                'section' => 'futurscout',
                'title' => 'futurscout 2011',
                'seo_description' => 'Das Pfingstlager des DPSG-Diözesanverbands Trier vom 10. bis 13. Juni 2011 in Rhens: abheben zu neuen Planeten.',
                'body' => <<<'HTML'
<p>Über Pfingsten, vom 10. bis 13. Juni 2011, landeten über 1.000 Pfadfinderinnen und Pfadfinder auf dem Jugendzeltplatz „Am Pfaffenwäldchen“ in Rhens – wie schon bei Schongse 2004. Das Motto: <strong>abheben zu neuen Planeten</strong>.</p>
<h2>Wie alles begann</h2>
<p>Die Diözesanversammlung 2007 beschloss, ab 2011 alle vier Jahre ein diözesanes Lager durchzuführen. Eine Projektgruppe fragte 2008 per Fragebogen alle Leitenden, was sie sich wünschen, und bekam 81 Rückmeldungen. Im November 2008 legte die Diözesanversammlung die Grundpfeiler fest:</p>
<ol>
<li>Es gibt ein Pfingstlager.</li>
<li>Es ist ein Stammeslager, an dem alle Stufen teilnehmen können.</li>
<li>Es findet auf dem Zeltplatz in Rhens statt.</li>
<li>Eingekauft wird zentral, mit gesunder und ökologischer Ernährung.</li>
<li>Neben gemeinsamen Programmpunkten gibt es freie Zeiten für die Stämme.</li>
</ol>
<p>Beim Motto setzte sich die Idee „Futurama“ durch – wegen des Urheberrechts und um pfadfinderisch zu bleiben, wurde daraus „futurscout“.</p>
<h2>Die Idee</h2>
<blockquote><p>Kurz gesagt, wir wollen den Pfadfinder der Zukunft mit seinem Raumschiff zu neuen Welten starten lassen.</p></blockquote>
<p>Die Welt ändert sich, und mit ihr die Pfadfinderei. Was macht uns als Pfadfinder stark für die Zukunft? Jeder Stamm wurde zum eigenen <strong>Planeten</strong> mit Namen, Kluft, Deko und Vierzeiler. Die Stämme eines Bezirks lebten im <strong>Bezirksdorf</strong>, einem Sonnensystem. Zusammen bildeten alle eine <strong>Galaxie</strong>.</p>
<p>Vier Themen zogen sich durch das Lager:</p>
<ul>
<li><strong>Pfadfinderische Identität:</strong> den eigenen „Wunschplaneten“ entdecken, auf dem man so Pfadfinder sein kann, wie man es in Zukunft sein will</li>
<li><strong>Lagerbauten:</strong> futuristisch-galaktisch</li>
<li><strong>Internationale Gerechtigkeit:</strong> intergalaktische Fairness zwischen den neuen Welten</li>
<li><strong>Ökologie:</strong> abheben und unterwegs sein mit größter ökologischer Sorgfalt</li>
</ul>
<h2>Programm</h2>
<figure><table><tbody>
<tr><td>Freitag</td><td>Ankunft, Check-in und Eröffnung mit der „Ankunft des futurscout“</td></tr>
<tr><td>Samstag</td><td>Workshops den ganzen Tag (10–17 Uhr), angeboten von den Stämmen selbst</td></tr>
<tr><td>Sonntag</td><td>Gottesdienst und großes Abschlussfest</td></tr>
<tr><td>Montag</td><td>Abtakt und Abreise</td></tr>
</tbody></table></figure>
<h2>Vorbereitung</h2>
<p>Wie bei Schongse 2004 arbeiteten vier Arbeitskreise: Inhalt, Organisation, Öffentlichkeitsarbeit sowie Sponsoring und Finanzen. Die Vorbereitungswochenenden hießen „Sternenreisen“ – im September 2009 in Hochscheid, im Januar 2010 im Diözesanzentrum Heddert und im Juli 2010 auf dem Lagerplatz in Rhens, wo es auch um das Logo ging. Der Teilnahmebeitrag war für Familien gestaffelt.</p>
HTML,
            ],
            [
                'slug' => 'scoutgames',
                'section' => 'scoutgames',
                'title' => 'Scoutgames 2020',
                'seo_description' => 'Digitale Scoutgames des DPSG-Diözesanverbands Trier am Pfingstsamstag, 30. Mai 2020: Impulse, Spiele und Lagerfeuerrunde im Netz.',
                'body' => <<<'HTML'
<p>Pfingsten 2020 fand im Netz statt: Am Pfingstsamstag, 30. Mai 2020, lud der Diözesanverband zu den <strong>digitalen Scoutgames</strong> ein – mit Impulsen, Spielen und einer Lagerfeuerrunde per Videokonferenz.</p>
<blockquote><p>Denn Pfadfinder spielen (immer noch) gern.</p></blockquote>
<h2>Programm</h2>
<figure><table><tbody>
<tr><td>10:00</td><td>Morgenrunde</td></tr>
<tr><td>11:00</td><td>Werwolf (über einen Messenger)</td></tr>
<tr><td>11:00</td><td>Spielerallye</td></tr>
<tr><td>11:00 und 15:00</td><td>Escape Room</td></tr>
<tr><td>14:00</td><td>Quiz</td></tr>
<tr><td>14:00</td><td>Rollenspiel</td></tr>
<tr><td>18:00</td><td>Abschlussrunde</td></tr>
</tbody></table><figcaption>Ablauf laut Plakat</figcaption></figure>
<p>Stämme konnten eigene Spiele anbieten.</p>
HTML,
            ],
        ];
    }

    public function down(): void
    {
        // Inhalte bleiben stehen; im Backend änderbar.
    }
};
