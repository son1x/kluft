<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Personen der Diözesanleitung und des Diözesanbüros mit ihren
 * Selbstvorstellungen, übernommen von dpsg-trier.de (dort veröffentlicht),
 * und das Gruppenfoto mit markierten Personen. Die bisherigen Namenslisten
 * im Seitentext entfallen, sofern der Text noch unverändert ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // In Tests nicht: kopiert Dateien und würde andere Tests verfälschen.
        if (app()->runningUnitTests()) {
            return;
        }

        if (DB::table('people')->exists() || ! DB::table('pages')->where('slug', 'dioezesanleitung')->exists()) {
            return;
        }

        $now = now();
        $ids = [];
        $sort = 0;

        foreach ($this->people() as [$gremium, $subgroup, $name, $role, $stage, $bio]) {
            $ids[$name] = DB::table('people')->insertGetId([
                'gremium' => $gremium,
                'subgroup' => $subgroup,
                'name' => $name,
                'role' => $role,
                'stage' => $stage,
                'bio' => $bio,
                'consent' => true,
                'active' => true,
                'sort' => $sort += 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Gruppenfoto (dpsg-trier.de, 2026) in die Mediathek übernehmen.
        $source = database_path('seed-assets/dl-gruppenfoto.jpg');
        if (File::exists($source)) {
            File::ensureDirectoryExists(public_path('uploads/media'));
            File::copy($source, public_path('uploads/media/dl-gruppenfoto.jpg'));

            $media = Media::query()->create([
                'path' => 'media/dl-gruppenfoto.jpg',
                'mime' => 'image/jpeg',
                'size' => File::size($source),
                'alt_text' => 'Gruppenfoto der Diözesanleitung mit den Bildungsreferent*innen vor der Wand „DPSG Friskirchen“, alle in DPSG-Pullovern',
                'credit' => 'DPSG Diözesanverband Trier',
                'rights_confirmed' => true,
            ]);

            $photoId = DB::table('team_photos')->insertGetId([
                'gremium' => 'leitung',
                'media_id' => $media->id,
                'title' => 'Unsere Diözesanleitung',
                'note' => 'Auf dem Foto fehlen Silas Weiß (Jungpfadfinderstufe) sowie Heike Franz und Lisa Tinanoff (Wölflingsstufe).',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Gesichtsmitte in Prozent, von links nach rechts.
            $spots = [
                'Axel Hemgesberg' => [8.9, 40.8],
                'Till Mang' => [18.2, 42.1],
                'Tobias Jost' => [26.2, 45.6],
                'Julia Lehn' => [33.5, 47.6],
                'Lisa Alm' => [41.2, 45.7],
                'Bianca Boltze' => [48.8, 45.1],
                'Marc Basenach' => [56.0, 43.0],
                'Christopher Klein' => [63.5, 44.7],
                'Felix Monzen' => [71.7, 40.6],
                'Jannis Odrosek' => [79.3, 44.1],
                'Peter Zillgen' => [87.2, 42.5],
            ];
            foreach ($spots as $name => [$x, $y]) {
                DB::table('team_photo_spots')->insert(['team_photo_id' => $photoId, 'person_id' => $ids[$name], 'x' => $x, 'y' => $y]);
            }
        }

        $this->replaceListIfUnchanged('dioezesanleitung', '[TODO: Zusammensetzung, Namen und Funktionen', <<<'HTML'
            <p>Der DPSG Diözesanverband Trier wird ehrenamtlich geleitet. Zur Diözesanleitung gehören der Diözesanvorstand sowie Referent*innen für die Stufen und Fachbereiche. Sie vertreten den Verband, setzen die Beschlüsse der Diözesanversammlung um und gestalten die inhaltliche Arbeit im ganzen Bistum Trier.</p>
            <p>Hauptberuflich unterstützt wird die Diözesanleitung vom <a href="/dioezesanbuero">Diözesanbüro</a> mit den Bildungsreferent*innen.</p>
            <p>Kontakt zur Diözesanleitung: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
            <h2>Struktur des Diözesanverbands</h2>
            <p>Der Diözesanverband Trier gliedert sich in 10 Bezirke im gesamten Bistum Trier, von der Mosel über Eifel und Hunsrück bis ins Saarland, mit mehr als 60 Stämmen und Siedlungen und über 5.000 Mitgliedern. Alle Bezirke und Stämme mit Kontakt und Karte findest du unter <a href="/bezirke-staemme">Bezirke &amp; Stämme</a>.</p>
            HTML);

        $this->replaceListIfUnchanged('dioezesanbuero', '[TODO: Namen, Funktionen und Kontaktdaten', <<<'HTML'
            <p>Das Diözesanbüro ist die hauptberufliche Unterstützung des Verbands: Bildungsreferent*innen und Verwaltung begleiten die Ehrenamtlichen in Bezirken, Stämmen und Arbeitskreisen – bei Ausbildung, Veranstaltungen und Organisation. Geleitet wird der Verband von der ehrenamtlichen <a href="/dioezesanleitung">Diözesanleitung</a>.</p>
            <h2>Anschrift</h2>
            <p>DPSG Diözesanverband Trier<br>
            Diözesanbüro<br>
            Weberbach 71<br>
            54290 Trier</p>
            <p>Telefon: <a href="tel:+4965146298290">0651 4 629 829 0</a><br>
            E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
            HTML);
    }

    private function replaceListIfUnchanged(string $slug, string $marker, string $body): void
    {
        $page = DB::table('pages')->where('slug', $slug)->first();
        if ($page && str_contains((string) $page->body, $marker)) {
            DB::table('pages')->where('id', $page->id)->update(['body' => $body, 'updated_at' => now()]);
        }
    }

    /**
     * @return list<array{string, string, string, string, ?string, ?string}>
     */
    private function people(): array
    {
        return [
            ['leitung', 'Diözesanvorstand', 'Till Mang', 'Diözesanvorsitzender', null,
                "Ich bin seit November 2025 Teil des Diözesanvorstands.\n\nIch bin am liebsten draußen unterwegs – in der Natur finde ich Ausgleich, Energie und oft auch die besten Ideen. Die Pfadfinderarbeit bedeutet für mich genau das: Gemeinschaft erleben, Abenteuer teilen und dabei persönlich wachsen.\n\nMeine besondere Leidenschaft liegt in der Ausbildung. Es begeistert mich, Menschen auf ihrem Weg zu begleiten, sie zu stärken und ihnen Werkzeuge mitzugeben, mit denen sie selbst Verantwortung übernehmen und andere inspirieren können. Gute Ausbildung ist für mich der Schlüssel zu einem lebendigen und zukunftsfähigen Verband.\n\nAls Teil des Vorstands möchte ich die DPSG aktiv mitgestalten und weiterentwickeln. Mir ist wichtig, Bewährtes zu bewahren und gleichzeitig neue Impulse zu setzen, damit unser Verband auch in Zukunft attraktiv, vielfältig und stark bleibt.\n\nIch freue mich darauf, gemeinsam mit euch Ideen zu entwickeln, Herausforderungen anzupacken und unsere Vision von Pfadfinden weiter voranzubringen. Gut Pfad!"],
            ['leitung', 'Diözesanvorstand', 'Peter Zillgen', 'Diözesankurat', null,
                'Ich bin seit November 2016 euer Diözesankurat. Das heißt, ich bin mit euch zusammen zuständig dafür, Spiritualität und Kirchlichkeit der DPSG zu entdecken und zu pflegen. Als Kurat, d. h. als Seelsorger, versuche ich, „Brückenbauer“ zwischen den verschiedenen Ebenen zu sein, und es ist mir ein Anliegen, dass wir als Georgspfadfinder auch unsere christliche Sendung mitten in die Welt hineintragen. Ich freue mich jederzeit, von euch zu hören und habe immer ein offenes Ohr für euch.'],
            ['leitung', 'Vorstandsreferenten', 'Felix Monzen', 'Vorstandsreferent (Region Mitte)', null,
                'Ich bin Felix und seit vielen Jahren als Pfadfinder im Stamm Feldkirchen aktiv. Seit November 2025 bin ich als Referent des Vorstandes in der Diözesanleitung tätig. Mein Schwerpunkt liegt dabei auf der Vertretung der Diözesanleitung in der Region Mitte.'],
            ['leitung', 'Vorstandsreferenten', 'Christopher Klein', 'Vorstandsreferent', null,
                'Ich bin Christopher und seit vielen Jahren als Pfadfinder im Stamm Feldkirchen aktiv. Seit November 2025 bin ich als Referent des Vorstandes in der Diözesanleitung tätig. Mein Schwerpunkt liegt dabei auf der Unterstützung des Diözesanvorstandes, ob als Vertreter bei BDKJ-Themen, als Unterstützer der Stufenarbeit oder als Teil von Leitungsteams für Großveranstaltungen.'],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Heike Franz', 'Referentin der Wölflingsstufe', 'woelflinge', null],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Lisa Tinanoff', 'Referentin der Wölflingsstufe', 'woelflinge', null],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Silas Weiß', 'Referent der Jungpfadfinderstufe', 'jupfi',
                'Ich bin als Jungpfadfinderstufenreferent auf Diözesanebene aktiv. Dort vertrete ich die Jufistufe, bringe neue Ideen ein und arbeite daran, die Jungpfadfinderarbeit weiterzuentwickeln. Besonders wichtig ist mir, gemeinsam im Team kreative und zeitgemäße Angebote auf die Beine zu stellen, die Spaß machen und etwas bewegen.'],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Tobias Jost', 'Referent der Roverstufe', 'rover',
                "Ich bin Tobias und seit 2023 Roverreferent im DV Trier. In dieser Rolle kümmere ich mich um alles rund um die Roverstufe.\n\nIch bin überzeugt, dass die besten Gespräche nachts am Feuer entstehen – und wenn ich gerade keine Veranstaltungen mit dem Rover-DAK organisiere oder neue Ideen mit dem Roverboard entwickle, findet man mich vermutlich irgendwo draußen, eben dort, wo Pfadfinden Spaß macht."],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Bianca Boltze', 'Referentin Inklusion / Ich-Du-Wir', null,
                'Inklusion ist mehr als „mit dem Rollstuhl ins Lager“. Gemeinsam mit meinem Arbeitskreis bin ich dafür zuständig, das Thema in die Diözese einzubringen und durch Workshops, Schulungen und Aktionen mit Leben zu füllen. Wir möchten erreichen, dass Pfadfinden in der Diözese Trier für jeden möglich wird!'],
            ['leitung', 'Referent*innen für Stufen und Fachbereiche', 'Jannis Odrosek', 'Referent der Bolivienpartnerschaft', null,
                "Seit 2025 bin ich Referent der Bolivienpartnerschaft – eine Aufgabe, die mir sehr am Herzen liegt. Schon lange begeistere ich mich für die Partnerschaft und die Möglichkeit, Menschen und Kulturen miteinander zu verbinden.\n\nEin entscheidender Moment für mich war die Begegnungsreise nach Bolivien im Jahr 2024. Die Zeit vor Ort, die Begegnungen mit den Menschen und die Einblicke in ihren Alltag haben mich tief beeindruckt. Diese Erfahrungen haben meinen Wunsch bestärkt, mich noch intensiver für die Partnerschaft einzusetzen.\n\nIn meiner Rolle möchte ich dazu beitragen, die Bolivienpartnerschaft weiter zu stärken, neue Menschen dafür zu begeistern und den interkulturellen Austausch lebendig zu gestalten."],
            ['buero', 'Bildungsreferent*innen', 'Marc Basenach', 'Geschäftsführender Bildungsreferent', null,
                "Als Geschäftsführender Bildungsreferent begleite ich die Diözesanleitung seit 2014. Ein zentraler Bestandteil meiner Tätigkeit ist die Begleitung des Projekts BeratungsPower, in dem wir systemische Berater*innen ausbilden, die ihre Beratungsfähigkeiten dann dem Verband zugutekommen lassen.\n\nIn meiner Rolle als Geschäftsführer begleite ich unsere Funktionsgremien. Bei Fragen zu Finanzen, Versicherung und rechtlichen Herausforderungen könnt ihr euch gerne an mich wenden. Außerdem bin ich ausgebildeter Multiplikator für die Prävention sexualisierter Gewalt im Bistum Trier und begleite den Arbeitskreis International sowie die Partnerschaft mit Bolivien."],
            ['buero', 'Bildungsreferent*innen', 'Axel Hemgesberg', 'Bildungsreferent', null,
                'Als Pfadfinder bin ich unterwegs – in Gebiete, die noch niemand kennt. Ich habe das Thema Prävention sexualisierter Gewalt auf meiner Agenda, engagiere mich in der Diözesanleitung, begleite und unterstütze Stämme und Bezirke (besonders im Saarland), plane Bildungs- und Schulungsangebote, unterstütze bei Bedarf die Roverstufe, organisiere verschiedenste Veranstaltungen mit und wirke in diözesanen Arbeitskreisen des BDKJ und der Jugendpastoral des Bistums mit.'],
            ['buero', 'Bildungsreferent*innen', 'Lisa Alm', 'Bildungsreferentin', null,
                "Als Bildungsreferentin begleite und unterstütze ich Pfadfinder*innen in unterschiedlichen Arbeitsfeldern. Ein Schwerpunkt meiner Arbeit liegt in der Wölflings- und Jungpfadfinderstufe, in der Kinder und Jugendliche Gemeinschaft erleben, Selbstvertrauen entwickeln und schrittweise Verantwortung übernehmen können.\n\nIn der Ausbildung von Gruppenleiter*innen sowie bei „Fit für’s Lager“ ist mir besonders wichtig, dass pfadfinderische Grundlagen praxisnah vermittelt werden. Mit dem Arbeitsfeld Ökologie und dem Spirituellen Hike verbinde ich pfadfinderische Werte mit bewusster Naturerfahrung und spiritueller Reflexion. Darüber hinaus begleite ich Bezirke bei Entwicklungsprozessen, Vernetzung und inhaltlicher Ausrichtung."],
            ['buero', 'Bildungsreferent*innen', 'Julia Lehn', 'Bildungsreferentin', null,
                'Ich arbeite seit 2024 als Bildungsreferentin für den Diözesanverband, vorher war ich viele Jahre als Referentin der Jungpfadfinderstufe ehrenamtlich in der Diözesanleitung aktiv. Meine Arbeitsfelder sind die Unterstützung des Vorstands, politisches Handeln und politische Bildung, die Unterstützung des AK Ich-Du-Wir (Inklusion), Öffentlichkeitsarbeit, das Projekt Zeitzeug*innen, die Vertretung im RDP Rheinland-Pfalz sowie die Pfadfinderstufe.'],
            ['buero', 'Verwaltung', 'Miriam Dobner', 'Verwaltung', null, null],
        ];
    }

    public function down(): void
    {
        //
    }
};
