<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Beraten-Online (Pflichtenheft 6.3): Inhalte von www.beraten-online.de
 * (WordPress) als Sektion „BeratungsPower & Coaching“ – Startseite der
 * Sektion, BeratungsPower, Coaching, Stöbern & Schmökern. Eigene Grafiken
 * und Klappkarten kommen in die Mediathek; Gruppenfoto (Personen) und
 * Cartoons (Rechte der Zeichnerin klären) bewusst nicht. Kontaktthema
 * „Beratung & Coaching“, Weiterleitungen der alten Adressen. Läuft nur
 * einmal (Slugs) und nicht in Tests.
 */
return new class extends Migration
{
    private const SOURCE = 'database/content/beraten-online/';

    private const SECTION = 'beratung';

    public function up(): void
    {
        if (app()->runningUnitTests() || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $powerCover = $this->media('DPSG-Trier-Beratungspower-titelbild.jpg', 'image/jpeg', 'Grafik „Let’s make a Better Place – Wir beraten euch!“ mit DPSG-Lilie, BP BeratungsPower', [1600, 900]);
        $coachingCover = $this->media('DPSG-Trier-Coaching-titelbild.jpg', 'image/jpeg', 'Grafik „Coaching bei der DPSG – Das ist DEINE persönliche Begleitung“ mit drei gezeichneten Pfadfinder*innen in Kluft', [1600, 900]);
        $kartePower = $this->media('DPSG-Klappkarte-Beraten.pdf', 'application/pdf', 'Klappkarte BeratungsPower (PDF)');
        $karteCoaching = $this->media('DPSG-Klappkarte-Coaching.pdf', 'application/pdf', 'Klappkarte Coaching (PDF)');
        $file = fn (?int $id) => $id ? '/uploads/'.DB::table('media')->where('id', $id)->value('path') : '#';

        // Kontaktthema; Nachrichten gehen an die Ansprechpersonen der Sektion.
        DB::table('contact_topics')->insertOrIgnore([
            'label' => 'Beratung & Coaching', 'slug' => self::SECTION, 'section' => self::SECTION,
            'sort' => 35, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('people')->where('name', 'Marc Basenach')->whereNull('section')->update(['section' => self::SECTION]);
        $kontakt = '/'.config('dpsg.contact_page').'?thema='.self::SECTION.'#kontaktformular';
        $cta = '<h2>Neugierig geworden?</h2><p><strong>Dann melde dich!</strong> Schreib uns über das <a href="'.$kontakt.'">Kontaktformular (Thema „Beratung &amp; Coaching“)</a>. Alle Anfragen behandeln wir vertraulich.</p>';

        $parent = DB::table('pages')->where('slug', config('dpsg.sections_page'))->value('id');
        $pages = [
            [
                'slug' => 'beratung', 'parent_id' => $parent, 'show_in_nav' => true, 'nav_sort' => 40,
                'title' => 'BeratungsPower & Coaching',
                'seo_title' => 'BeratungsPower & Coaching – Beratung für Ehrenamtliche in der DPSG Trier',
                'seo_description' => 'Ehrenamtliche Beratung und Coaching für Leitungsrunden, Vorstände und Teams in der DPSG Trier – vertraulich, lösungsorientiert, von erfahrenen Pfadfinder*innen.',
                'body' => <<<'HTML'
                    <p><strong>Das ist EURE/DEINE persönliche Begleitung, damit DPSG Zukunft hat.</strong></p>
                    <p>Wir sind ein Pool von Leuten, die ziel- und lösungsorientiert ehrenamtlich coachen und beraten. Das haben wir in Aus- und Weiterbildungen speziell für die Bedürfnisse selbstorganisierter ehrenamtlicher Jugendverbands- und Erwachsenenarbeit gelernt. Wir alle können auf ein langjähriges und vielfältiges ehrenamtliches Engagement in ganz unterschiedlichen Funktionen zurückgreifen. Wir behandeln alle Beratungs- und Coachingprozesse vertraulich.</p>
                    <ul>
                    <li><a href="/beratungspower"><strong>BeratungsPower</strong></a> – für Teams, Leitungsrunden, Vorstände und Arbeitskreise</li>
                    <li><a href="/coaching"><strong>Coaching</strong></a> – für dich persönlich in deinem Leitungsamt</li>
                    <li><a href="/stoebern-und-schmoekern"><strong>Stöbern &amp; Schmökern</strong></a> – wie alles begann, unser Ansatz, Zitate und Geschichten</li>
                    </ul>
                    HTML.$cta,
            ],
            [
                'slug' => 'beratungspower', 'nav_sort' => 10, 'cover_media_id' => $powerCover,
                'title' => 'BeratungsPower',
                'seo_description' => 'Beratung für Leitungsrunden, Vorstände, Teams und Arbeitskreise in der DPSG Trier: Arbeit reflektieren, Konflikte klären, neue Perspektiven finden.',
                'body' => <<<'HTML'
                    <p><strong>Ihr seid engagiert in der DPSG:</strong></p>
                    <ul><li>als Einzelperson oder Team</li><li>als Leitungsrunde oder Vorstand</li><li>in Arbeitsgruppen oder -kreisen</li><li>oder oder oder …</li></ul>
                    <p><strong>Ihr wollt:</strong></p>
                    <ul><li>eure Arbeit reflektieren</li><li>neue Perspektiven finden</li><li>Krisen und Konflikte klären</li><li>euer Image gestalten</li><li>eure Leitungsrunde effektiver gestalten</li><li>neue Mitarbeiter*innen gewinnen</li><li>eure pfadfinderische Identität miteinander klären</li><li>oder etwas ganz anderes …</li></ul>
                    <p><strong>Dabei können wir Berater*innen euch unterstützen, weil wir</strong></p>
                    <ul><li>langjährige Erfahrung in der DPSG als Gruppenleitung, Stavo, Bezirksleitung und/oder auf diözesaner Ebene haben</li><li>speziell ausgebildet sind für die Beratung Ehrenamtlicher</li><li>mit euch passende Wege und Lösungen finden</li><li>über ein umfangreiches methodisches Handwerkszeug verfügen</li></ul>
                    HTML.$cta.'<p>Zum Weitergeben: <a href="'.$file($kartePower).'">Klappkarte BeratungsPower (PDF)</a></p>',
            ],
            [
                'slug' => 'coaching', 'nav_sort' => 20, 'cover_media_id' => $coachingCover,
                'title' => 'Coaching',
                'seo_description' => 'Persönliches Coaching für Vorstände und Gruppenleitungen in der DPSG Trier – passgenau für dich und dein Leitungsamt, vertraulich.',
                'body' => <<<'HTML'
                    <p><strong>Coaching?</strong> Weil du Verantwortung übernommen hast! Ehrenamtlich! Freiwillig! Als Vorstand oder in der Jugendgruppenleitung! Unser Coaching ist ein Angebot für dich – egal, ob frisch ins Amt gewählt oder schon lange dabei.</p>
                    <p><strong>Coaching</strong> ist passgenau auf dich, deine Bedürfnisse und die Herausforderungen deines Leitungsamtes bei der DPSG zugeschnitten. Wir unterstützen dich in individuellen Gesprächen bei deinen Fragen und Anliegen.</p>
                    <p><strong>Coaching</strong> hat Respekt vor dir und deinem Tun! Es stärkt, ermutigt und regt an, dich mit dir und deinem Amt zu beschäftigen.</p>
                    HTML.$cta.'<p>Zum Weitergeben: <a href="'.$file($karteCoaching).'">Klappkarte Coaching (PDF)</a></p>',
            ],
            [
                'slug' => 'stoebern-und-schmoekern', 'nav_sort' => 30,
                'title' => 'Stöbern & Schmökern',
                'seo_description' => 'Hintergrund zu BeratungsPower & Coaching der DPSG Trier: wie alles begann, unser Ansatz, typische Fragen, Zitate und Geschichten.',
                'body' => <<<'HTML'
                    <h2>Über uns</h2>
                    <p>Wir sind ein Pool von Leuten, die ziel- und lösungsorientiert ehrenamtlich coachen und beraten. Das haben wir in Aus- und Weiterbildungen speziell für die Bedürfnisse selbstorganisierter ehrenamtlicher Jugendverbands- und Erwachsenenarbeit gelernt. Wir alle können auf ein langjähriges und vielfältiges ehrenamtliches Engagement in ganz unterschiedlichen Funktionen zurückgreifen. Wir behandeln alle Beratungs- und Coachingprozesse vertraulich.</p>
                    <h2>Wie alles begann</h2>
                    <p>Im Jahr 2000 hat der damalige Diözesanvorstand der DPSG Trier mit „Beraten gestaltet Zukunft“ für ehrenamtliche Leitungskräfte ein unterstützendes Instrument zum Gestalten von Veränderungen und Lösen von Fragen aus dem Pfadfinderalltag entwickelt. Diese Initiative sollte ehrenamtlich getragen professionellen Anforderungen genügen. Zu diesem Zeitpunkt gab es kein vergleichbares Konzept im deutschsprachigen Raum.</p>
                    <h2>Unser Ansatz</h2>
                    <p>Für uns ist Beratung und Coaching ein gemeinsamer Prozess, bei dem es oft gelingt, neue, meist überraschende Lösungen zu finden, andere Pfade zu entdecken, Perspektiven zu verändern und auch schwere Herausforderungen zu meistern.</p>
                    <p>Eine unserer Ideen ist es, dass die „Ratsuchenden“ selbst Fachleute für sich und ihre Situation sind. Sie wissen, worum es geht. Wir begleiten und unterstützen sie beim Finden von Lösungen und Zielen, beim Erarbeiten neuer Perspektiven. Es gibt kein Schema F für uns: Jede Beratung und jedes Coaching wird auf die jeweiligen Situationen und Personen und deren Fragestellungen und Herausforderungen hin gestaltet.</p>
                    <p>Unser Angebot ist hilfreich für Einzelne, Teams und Vorstände und vor allem auch für Leitungsrunden, Projekt- und Arbeitsgruppen.</p>
                    <h3>Wir beraten und coachen zum Beispiel, wenn …</h3>
                    <ul>
                    <li>es um die Zusammenarbeit von Ehrenamtlichen und Hauptberuflichen geht</li>
                    <li>Verändern oder Entscheiden angesagt ist</li>
                    <li>das Miteinander zum Thema wird, wie bei Konflikten und Spannungen</li>
                    <li>Einschränkungen und ungute Rahmenbedingungen das Ehrenamt beeinträchtigen</li>
                    <li>neue Ressourcen gebraucht werden, um Herausforderungen zu meistern</li>
                    <li>es um ein verbindliches Miteinander geht</li>
                    <li>die Zukunft des Stammes aktiv gestaltet werden soll</li>
                    </ul>
                    <h3>Fragen und Themen aus Beratungs- und Coachingprozessen – eine kleine Auswahl</h3>
                    <ul>
                    <li>Was mach ich bloß mit diesen Kindern/mit dieser Leitungsrunde?</li>
                    <li>Was kann ich tun, damit meine Freude bleibt bzw. wiederkommt?</li>
                    <li>Wie/Wodurch gelingt es mir, auf mich selbst zu achten bei der Fülle der Erwartungen an mich?</li>
                    <li>Vorstandsein ist ein Leben zwischen „Zepter und Krückstock“.</li>
                    <li>Balance finden zwischen Ehrenamt, Beruf, Familie, Freunden …</li>
                    <li>Gestalten wollen und können: Leitungsrunden mit mehr Verbindlichkeit, Delegieren von Aufgaben und Verantwortung</li>
                    <li>Ich entscheide, NEIN zu sagen oder JA.</li>
                    </ul>
                    <h2>Perspektiven wechseln</h2>
                    <p>Ehrenamtlich Tätige sind engagiert, motiviert und bemüht, ihre Aufgaben zeitlich, menschlich und wirtschaftlich unter einen Hut zu bekommen, um nicht in die Gefahr der Selbstausbeutung zu gelangen. Voraussetzung für eine Beratung ist die Bereitschaft, neue Erfahrungen machen zu wollen und zu neuen Impulsen und Sichtweisen zu gelangen. Sind euch diese oder ähnliche Situationen bekannt oder gar vertraut?</p>
                    <ul>
                    <li><p>Es finden viel mehr Nebengespräche statt als Klärungen in der Runde: Eigentlich müssten wir … in der Leitungsrunde mal Klartext reden. Aber wer macht den Anfang?</p><p><strong>Perspektive:</strong> Sprachlosigkeit überwinden … Gesprächsfähigkeit fördern. Faires Streiten lernen!</p></li>
                    <li><p>Alles ist festgefahren, nichts ändert sich und keiner traut sich, das auszusprechen. Warum auch, es läuft ja: Ein System (Stamm), das keine Fragen mehr hat, hat auch bald keine Perspektiven mehr!</p><p><strong>Perspektive:</strong> Identität fördern und stiften – Perspektiven schaffen!</p></li>
                    <li><p>Die Leitung abgeben, nach so langer Zeit. Und was kommt danach? Niemals geht man so ganz … einmal Pfadfinder*in, immer Pfadfinder*in!</p><p><strong>Perspektive:</strong> Generationenwechsel … möglich machen!</p></li>
                    <li><p>Jede*r kocht sein Süppchen, jede*r tut, was er kann. Reicht das? Unser System (Stamm) ist so gut wie das Leben in unseren Gruppen.</p><p><strong>Perspektive:</strong> Die Potenziale von Leitungsteams entwickeln und fördern!</p></li>
                    <li><p>Bei uns läuft alles super. Wir brauchen keine Beratung. Hoffentlich bleibt das noch lange so … Ein System (Stamm), das auch in Zukunft erfolgreich sein will, stellt jetzt seine Weichen!</p><p><strong>Perspektive:</strong> Entdeckt, was in euch steckt: Potenziale fördern und weitere hinzugewinnen!</p></li>
                    <li><p>Schon wieder Terminkalender, wer übernimmt was, warum immer nur dieselben? Früher hatten wir auch mal Zeit, gemeinsam etwas zu unternehmen, einen gemütlichen Abend zu gestalten. Heute geht es nur noch um Pflichten … Typisch pfadfinderischer Jahresablauf, immer dasselbe und immer mehr davon …</p><p><strong>Perspektive:</strong> Bewahren und/oder verändern – Akzente setzen mit erreichbaren Zielen und schwungvollen Ideen.</p></li>
                    <li><p>Alltag in der Leitungsrunde. Keine Zeit für … dafür immer nur …</p><p><strong>Perspektive:</strong> Ziele finden, Projekte planen, Schwerpunkte setzen!</p></li>
                    <li><p>Pfadfinder*in sein gibt mir so viel. Das mache ich schon vom Wölfling an. Aber manchmal … Traditionen sind uns wichtig! – und welche?</p><p><strong>Perspektive:</strong> Zeitgemäßes Pfadfinden – mit Hintergrund!</p></li>
                    <li><p>Pfadfinderimage – na und? Wir sind anders! Oder vielleicht doch nicht?</p><p><strong>Perspektive:</strong> Selbst- und Fremdbilder. Wirkungen, Auswirkungen oder wieso auf einmal alle bei uns mitmachen wollen …</p></li>
                    </ul>
                    <h3>Ein paar Fragen zur Anregung</h3>
                    <ul>
                    <li>Über welche Stichworte bist du gestolpert? Regten sie dich kurz zum Nachdenken an?</li>
                    <li>Würden andere aus deiner Leitungsrunde bei ähnlichen Stichworten nachdenklich werden – und bei welchen anderen vielleicht auch noch?</li>
                    <li>Wenn du aus deiner Situation einen Satz als „Perspektive“ formulieren würdest, wie würde dieser lauten? Welche Sätze würden andere von euch notieren?</li>
                    <li>Wäre es vielleicht an der Zeit, über einen oder mehrere dieser Sätze nachzudenken und darüber zu sprechen?</li>
                    </ul>
                    <p>Auf einer Skala von 0 bis 10 (0 = überhaupt nicht, 10 = in jedem Fall): <strong>Ab 5 nehmt Kontakt mit uns auf.</strong></p>
                    <h2>Zitate und Geschichten</h2>
                    <blockquote><p>„Hart am Abgrund ist Rückschritt Fortschritt.“</p><p>– Hans-Georg Meinung</p></blockquote>
                    <blockquote><p>„Der Pessimist sieht die Schwierigkeiten jeder Gelegenheit, der Optimist sieht die Gelegenheiten in jeder Schwierigkeit.“</p><p>– L. P. Jacks</p></blockquote>
                    <blockquote><p>„Wer ein Problem lösen will, muss sich vom Problem lösen.“</p><p>– Paul Watzlawick</p></blockquote>
                    <blockquote><p>„Reden über Probleme schafft Probleme. Reden über Lösungen schafft Lösungen.“</p><p>– Steve de Shazer</p></blockquote>
                    <blockquote><p>„Die Wahrheit ist die Erfindung eines Lügners.“</p><p>– Heinz von Foerster</p></blockquote>
                    <h3>Das Pferd, das den Weg findet</h3>
                    <p>Auf meinem Nachhauseweg von der Schule sah ich einmal ein gezäumtes, aber herrenloses Pferd an unserer Gruppe vorbei in einen Bauernhof galoppieren, wo es nach der Tränke suchte. Das Pferd war ganz nass geschwitzt. Und da der Bauer es nicht kannte, drängten wir es in die Ecke. Ich sprang auf den Pferderücken. Da es ja gezäumt war, ergriff ich die Zügel und rief: „Hüh-Hott!“ und lenkte es auf die Straße. Ich war mir sicher, das Pferd würde die richtige Richtung einschlagen. Ich selbst wusste nicht, welches die richtige Richtung war. Das Pferd trabte und galoppierte die Straße entlang. Ab und zu vergaß es, dass es sich auf dem Weg befand, und wandte sich seitwärts in die Felder. Dann zog ich immer ein wenig am Zügel und brachte ihm in Erinnerung, dass es sich eigentlich an den Weg halten sollte. Und vier Meilen nach dem Punkt, an dem ich aufgesessen hatte, bog es schließlich in einen Bauernhof ein und der Bauer sagte: „So, ist der Gaul also wieder zurückgekommen. Wo hast du ihn gefunden?“ Ich antwortete: „Ungefähr vier Meilen von hier.“ – „Woher wusstest du denn, dass er hierher gehört?“ Ich erwiderte: „Ich wusste es nicht, das Pferd wusste es. Ich brauchte eigentlich nur seine Aufmerksamkeit auf die Straße zu lenken.“</p>
                    <p>– Milton H. Erickson</p>
                    HTML.$cta,
            ],
        ];

        foreach ($pages as $page) {
            if (DB::table('pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }
            DB::table('pages')->insert([
                'parent_id' => null, 'show_in_nav' => false, 'seo_title' => null, 'cover_media_id' => null,
                ...$page,
                'section' => self::SECTION,
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Alte Adressen von beraten-online.de (BeratungsPower und Coaching
        // haben dieselben Slugs; Schrägstrich am Ende leitet Laravel um).
        foreach ([
            '/stoebern-schmoekern-fuer-powerinteressierte' => '/stoebern-und-schmoekern',
            '/datenschutzerklaerung' => '/datenschutz',
        ] as $from => $to) {
            DB::table('redirects')->insertOrIgnore([
                'from_path' => $from, 'to_path' => $to, 'status_code' => 301, 'source' => 'manuell',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Weiterleitungen sind dauerhaft gecacht; direkte Inserts leeren ihn nicht.
        Cache::forget(Redirect::CACHE_KEY);
    }

    /**
     * Datei aus database/content in die Mediathek übernehmen (einmalig).
     *
     * @param  array{0: int, 1: int}|null  $size
     */
    private function media(string $file, string $mime, string $alt, ?array $size = null): ?int
    {
        $path = 'media/2026/beraten-online-'.strtolower($file);
        if ($id = DB::table('media')->where('path', $path)->value('id')) {
            return (int) $id;
        }

        $source = base_path(self::SOURCE.$file);
        if (! is_file($source)) {
            return null;
        }

        Storage::disk('public')->put($path, (string) file_get_contents($source));

        return (int) DB::table('media')->insertGetId([
            'path' => $path,
            'mime' => $mime,
            'size' => filesize($source),
            'alt_text' => $alt,
            'credit' => 'DPSG Diözesanverband Trier, BeratungsPower & Coaching',
            'rights_confirmed' => true,
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Rein inhaltlich; die Seiten werden im Backend gepflegt.
    }
};
