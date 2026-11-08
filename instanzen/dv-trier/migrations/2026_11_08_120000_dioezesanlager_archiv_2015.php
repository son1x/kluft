<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rubrik „Diözesanlager“: Archiv Gast>>FREUNDschaft 2015 (Ferschweiler).
 * Grundlage: Ausschreibung, „Infos rund um das Lager“ (Endfassung
 * 25.06.2015), Lagerregeln und Belegungsstatistik aus dem Aktenplan
 * (08.11.2026). Keine Namen oder Kontaktdaten des damaligen Teams, keine
 * Anmeldelisten. Nur dv-trier, nach dem Startinhalt; vorhandene Slugs
 * bleiben unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('dpsg.instanz') !== 'dv-trier' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }
        if (DB::table('pages')->where('slug', 'gastfreundschaft')->exists()) {
            return;
        }

        $now = now();
        DB::table('pages')->insert([
            'slug' => 'gastfreundschaft',
            'title' => 'Gast>>FREUNDschaft 2015',
            'body' => $this->text(),
            'seo_description' => 'Das Diözesanzeltlager des DPSG-Diözesanverbands Trier vom 24. Juli bis 2. August 2015 in Ferschweiler, Motto „Gast>>FREUNDschaft“.',
            'section' => 'gastfreundschaft',
            'status' => 'published',
            'published_at' => $now,
            'show_in_nav' => false,
            'nav_sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function text(): string
    {
        return <<<'HTML'
<p>Vom 24. Juli bis 2. August 2015, in der ersten Woche der Sommerferien, lud die Diözesanleitung nach „Schongse“ (2004) und „futurscout“ (2011) wieder zu einem großen Diözesanlager ein: dem <strong>DiözesanzeltLager 2015</strong> auf dem Zeltplatz in Ferschweiler in der Eifel. Das Motto: <strong>Gast&gt;&gt;FREUNDschaft</strong>.</p>
<h2>Die Idee</h2>
<p>Die Stämme schlugen auf dem Platz ihr eigenes Stammeslager auf – ob für zwei, drei Tage oder die ganze Zeit, entschied jeder Stamm selbst. Gekocht wurde in Eigenregie. Dazu kamen zwei zentrale Programmtage für alle. Im Mittelpunkt standen Begegnung und gemeinsames Tun: Aus Gästen sollten Freund*innen werden.</p>
<p>Ferschweiler kannten viele schon: Hier fand 2007 auch Scouting100 statt, das Fest zu 100 Jahren Pfadfinden.</p>
<h2>Programm</h2>
<figure><table><tbody>
<tr><td>Sonntag, 26. Juli</td><td>Austausch zur Bistumssynode am Vormittag, 18 Uhr Gottesdienst, danach Begrüßung aller Teilnehmenden und Campfire</td></tr>
<tr><td>Montag, 27. Juli</td><td><strong>Stufentag:</strong> Schnitzeljagd für die Wölflinge, das Stationenspiel „Julympic“ für die Jungpfadfinder, grüne Küche und Wellness für die Pfadfinder, ein Erlebnisort für die Rover</td></tr>
<tr><td>Dienstag, 28. Juli</td><td><strong>Aktionstag:</strong> Die Stämme zeigen sich gegenseitig ihre Workshops</td></tr>
<tr><td>Mittwoch, 29. Juli</td><td>Band am Abend im Café „Jurta Nigra“</td></tr>
</tbody></table></figure>
<p>An den beiden Programmtagen gab es ein gemeinsames Abendessen für alle, vorbereitet von den Küchenteams der Stämme.</p>
<h2>Rund ums Lager</h2>
<ul>
<li><strong>Lagerheft</strong> mit Infos, Lagerregeln und einer „FreundeSeite“ jedes Stammes – nach dem Vorbild eines Freundebuchs, damit sich schon zu Beginn ein buntes Bild der Teilnehmenden zeigt</li>
<li><strong>Einkaufsservice</strong> für Frischwaren an drei Tagen und ein <strong>Kühlwagen</strong> auf dem Platz</li>
<li><strong>Fortbildung für die Küchenteams</strong> der Stämme mit neuen Ideen für die Lagerküche</li>
<li>Spielmobil des DRK, Sanitätsdienst, Ladestation für Handys und ein Shuttle mit Fahrzeugen des Diözesanverbands</li>
<li>Für alle einen DiLa-Aufnäher</li>
</ul>
<p>Laut Belegungsliste waren am Stufentag, dem 27. Juli, 622 Personen auf dem Platz.</p>
<h2>Lagerregeln</h2>
<p>Solidarisches und rücksichtsvolles Verhalten sowie die Sorge für Ordnung und Sauberkeit waren selbstverständlich. Dazu kamen unter anderem:</p>
<ul>
<li>Kein „Bannerklau“ während des Lagers</li>
<li>Nachtruhe auf dem Lagergelände von 24 bis 7 Uhr</li>
<li>Feuer nur in den mitgebrachten Feuerschalen, Brennholz wurde gestellt – keine Äste und Sträucher abreißen</li>
<li>Müll trennen, Spülwasser nur an den Abwasserstationen</li>
<li>Vorsicht an der kleinen Schlucht direkt neben dem Gelände</li>
</ul>
<h2>Vorbereitung</h2>
<p>Eine Vorbereitungsgruppe des Diözesanverbands plante das Lager von November 2014 bis Juli 2015 in Treffen und Telefonkonferenzen. Die Stämme meldeten sich bis Oktober 2014 zurück, ob und mit wie vielen sie dabei sein wollten. Für Stufentag und Aktionstag gab es Zuschüsse über den Diözesanverband.</p>
HTML;
    }

    public function down(): void
    {
        // Inhalt bleibt stehen; im Backend änderbar.
    }
};
