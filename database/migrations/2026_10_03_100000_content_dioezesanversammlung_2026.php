<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Inhalt: Einladung zur 91. Diözesanversammlung (20.–22.11.2026) als
 * Beitrag unter „Aktuelles“ und als Veranstaltung mit Online-Anmeldung.
 * Einladung, Ablaufplan und Beschluss zur Stimmendelegation kommen als
 * PDF in die Mediathek. Läuft nur einmal (Slugs) und nicht in Tests.
 */
return new class extends Migration
{
    private const SOURCE = 'database/content/divers-2026/';

    private const EVENT_SLUG = 'dioezesanversammlung-2026';

    private const POST_SLUG = 'einladung-zur-91-dioezesanversammlung';

    public function up(): void
    {
        if (app()->runningUnitTests() || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $einladung = $this->media('einladung-91-dioezesanversammlung.pdf', 'application/pdf', 'Einladung zur 91. Diözesanversammlung (PDF)');
        $ablauf = $this->media('ablaufplan-dioezesanversammlung-2026.pdf', 'application/pdf', 'Ablaufplan der Diözesanversammlung 2026 (PDF)');
        $delegation = $this->media('beschluss-stimmendelegation.pdf', 'application/pdf', 'Beschluss zur Stimmendelegation bei unbesetzten Vorstandsämtern (PDF)');
        $cover = $this->media('divers-2026-titelbild.jpg', 'image/jpeg', 'Rundes Logo: 91. Diözesanversammlung 2026 im Jugendhaus in Trier – Weltkugel mit Megafon und zwei Jugendlichen im Gespräch', [1600, 900]);

        $url = fn (?int $id) => $id ? '/uploads/'.DB::table('media')->where('id', $id)->value('path') : '#';
        $event = '/veranstaltungen/'.self::EVENT_SLUG;

        if (! DB::table('events')->where('slug', self::EVENT_SLUG)->exists()) {
            DB::table('events')->insert([
                'title' => '91. Diözesanversammlung',
                'slug' => self::EVENT_SLUG,
                'teaser' => 'Die Versammlung des Diözesanverbands im Jugend- und Gästehaus Bistum Trier: Berichte, Anträge, Wahlen, Workshops und ein gemeinsames Wochenende.',
                'description' => <<<HTML
                    <p>Wir laden euch herzlich zur 91. Diözesanversammlung des DPSG Diözesanverbands Trier ins Jugend- und Gästehaus Bistum Trier ein. Ihr könnt bereits am Freitagabend ab 17 Uhr anreisen; mit der Versammlung beginnen wir am Samstag um 9 Uhr, am Sonntag enden wir mit einem Gottesdienst um 11.30 Uhr.</p>
                    <h3>Ablauf</h3>
                    <p><strong>Freitag, 20. November</strong></p>
                    <ul>
                    <li>ab 17.00 Uhr: Ankunft und Anmeldung</li>
                    <li>ab 18.00 Uhr: gemeinsames Abendessen</li>
                    <li>ab 19.30 Uhr: gemütlicher Abend</li>
                    <li>20.00 Uhr: Mitgliederversammlung</li>
                    <li>20.00 Uhr: „Im Dschungel der DiVers“ – Infoveranstaltung zur anstehenden Versammlung</li>
                    </ul>
                    <p><strong>Samstag, 21. November</strong></p>
                    <ul>
                    <li>ab 7.30 Uhr: Frühstück</li>
                    <li>9.00 Uhr: gemeinsamer Beginn in der Hauskapelle, Eröffnung der Diözesanversammlung (Teil I)</li>
                    <li>12.30 Uhr: Mittagessen</li>
                    <li>14.00 Uhr: Workshops zu queeren Lebenswelten und zum Leitenden-Event 2027 sowie die Führung „Blick hinter den Dom“</li>
                    <li>16.00 Uhr: Diözesanversammlung (Teil II)</li>
                    <li>18.30 Uhr: festliches Abendessen</li>
                    <li>ab 20.00 Uhr: gemütliches Beisammensein und Kegelturnier</li>
                    </ul>
                    <p><strong>Sonntag, 22. November</strong></p>
                    <ul>
                    <li>ab 7.30 Uhr: Frühstück</li>
                    <li>9.00 Uhr: Start in den Tag</li>
                    <li>9.15 Uhr: Diözesanversammlung (Teil III)</li>
                    <li>11.30 Uhr: Gottesdienst in der Hauskapelle und Ende der 91. Diözesanversammlung, im Anschluss Mitgliederversammlung</li>
                    <li>12.30 Uhr: Mittagessen und Abreise</li>
                    </ul>
                    <h3>Tagesordnung</h3>
                    <ol>
                    <li>Eröffnung der 91. Diözesanversammlung</li>
                    <li>Feststellung der Beschlussfähigkeit</li>
                    <li>Diskussion des Arbeitsberichts der DL</li>
                    <li>Bericht des Landesamts St. Georg e. V.</li>
                    <li>Entlastung des Vorstands</li>
                    <li>Anträge</li>
                    <li>Wahlen</li>
                    <li>Verschiedenes</li>
                    <li>Reflexion der Versammlung</li>
                    <li>Ende der 91. Diözesanversammlung</li>
                    </ol>
                    <p>Anträge können bis zum 23. Oktober 2026 per E-Mail an <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a> eingereicht werden. Später eingehende Anträge können als Initiativanträge gestellt werden.</p>
                    <h3>Delegierte</h3>
                    <p>Vakante Vorstandsämter können durch Delegierte besetzt werden, die bei eurer Bezirksversammlung für ein Jahr gewählt werden. Sie vertreten euren Bezirk mit vollem Stimmrecht. Teilt uns im Protokoll eurer Bezirksversammlung mit, wer gewählt wurde – ohne diese offizielle Info im Diözesanbüro kann die Stimme nicht berücksichtigt werden. Mehr dazu im <a href="{$url($delegation)}">Beschluss zur Stimmendelegation (PDF)</a>.</p>
                    <h3>Anfahrt</h3>
                    <p>Parkplätze gibt es direkt beim Jugendhaus; die Einfahrt mit der Schranke erreicht ihr über die Rahnenstraße. Bildet gerne Fahrgemeinschaften. Der Bahnhof liegt etwa 15 Gehminuten entfernt; am einfachsten erreicht ihr das Gelände über die Weberbach 71 und folgt dann der Beschilderung. Mit dem Bus: Haltestelle Stadtbibliothek oder Basilika.</p>
                    <h3>Verpflegung</h3>
                    <p>Das ganze Wochenende gibt es vegetarische Verpflegung, Tagungsgetränke und Softdrinks. Als Dankeschön laden wir euch am Samstagabend zu einem festlichen Buffet und anschließend zu alkoholfreien Cocktails ein. Auch der Weltladen ist wieder dabei – bringt gerne etwas Kleingeld mit.</p>
                    <p>Weitere Informationen bekommen alle Angemeldeten etwa zwei Wochen vor Beginn per E-Mail.</p>
                    <p><a href="{$url($einladung)}">Einladung (PDF)</a> · <a href="{$url($ablauf)}">Ablaufplan (PDF)</a></p>
                    HTML,
                'starts_at' => '2026-11-20 17:00:00',
                'ends_at' => '2026-11-22 13:30:00',
                'location' => 'Jugend- und Gästehaus Bistum Trier',
                'stage' => null,
                'section' => null,
                'target_group' => 'Mitglieder der Diözesanversammlung, Delegierte und Gäste',
                'cover_media_id' => $cover,
                'contact_name' => 'Diözesanbüro',
                'contact_email' => 'info@dpsg-trier.de',
                'cost_info' => null,
                'reg_opens_at' => null,
                'reg_closes_at' => '2026-11-08 23:59:00',
                'capacity' => null,
                'waitlist_enabled' => false,
                'status' => 'open',
                'form_fields' => json_encode(['birth_year' => 'required', 'stamm' => 'required', 'gremium' => 'required', 'uebernachtung' => 'required', 'diet' => 'optional', 'comment' => 'optional']),
                'retention_days' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('posts')->where('slug', self::POST_SLUG)->exists()) {
            DB::table('posts')->insert([
                'title' => 'Einladung zur 91. Diözesanversammlung',
                'slug' => self::POST_SLUG,
                'scope' => 'general',
                'teaser' => 'Vom 20. bis 22. November 2026 treffen wir uns zur 91. Diözesanversammlung im Jugend- und Gästehaus Bistum Trier. Anmeldeschluss ist der 8. November, Anträge bitte bis zum 23. Oktober.',
                'body' => <<<HTML
                    <p>Liebe Pfadfinder*innen,</p>
                    <p>hiermit laden wir euch zur diesjährigen Diözesanversammlung des DPSG Diözesanverbands Trier vom <strong>20. bis 22. November 2026</strong> ins Jugend- und Gästehaus Bistum Trier ein.</p>
                    <p>Ihr könnt bereits am Freitagabend ab 17 Uhr gemütlich anreisen, Abendessen gibt es ab 18 Uhr. Mit der Versammlung beginnen wir am Samstag um 9 Uhr, am Sonntag enden wir mit einem Gottesdienst um 11.30 Uhr. Neben Berichten, Anträgen und Wahlen erwarten euch Workshops zu queeren Lebenswelten und zum Leitenden-Event 2027, die Führung „Blick hinter den Dom“, ein festliches Buffet am Samstagabend und ein Kegelturnier.</p>
                    <p><a href="{$event}"><strong>Hier geht’s zur Anmeldung</strong></a> – Anmeldeschluss ist der 8. November 2026.</p>
                    <h2>Anträge</h2>
                    <p>Anträge können noch fristgerecht bis zum <strong>23. Oktober 2026</strong> per E-Mail an <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a> eingereicht werden. Später eingehende Anträge können als Initiativanträge gestellt werden.</p>
                    <h2>Tagesordnung</h2>
                    <ol>
                    <li>Eröffnung der 91. Diözesanversammlung</li>
                    <li>Feststellung der Beschlussfähigkeit</li>
                    <li>Diskussion des Arbeitsberichts der DL</li>
                    <li>Bericht des Landesamts St. Georg e. V.</li>
                    <li>Entlastung des Vorstands</li>
                    <li>Anträge</li>
                    <li>Wahlen</li>
                    <li>Verschiedenes</li>
                    <li>Reflexion der Versammlung</li>
                    <li>Ende der 91. Diözesanversammlung</li>
                    </ol>
                    <h2>Delegierte für unbesetzte Vorstandsämter</h2>
                    <p>Seit letztem Jahr können vakante Stellen in den Vorständen durch Delegierte besetzt werden, die bei eurer Bezirksversammlung für ein Jahr gewählt werden. Sie vertreten euren Bezirk bei der Diözesanversammlung mit vollem Stimmrecht – nehmt diese Möglichkeit gerne wahr. Teilt uns im Protokoll eurer Bezirksversammlung mit, wer gewählt wurde, und leitet die Einladung gerne an die Delegierten weiter. Wichtig: Ohne diese offizielle Info im Diözesanbüro kann die Stimme nicht berücksichtigt werden. Alle Details stehen im <a href="{$url($delegation)}">Beschluss zur Stimmendelegation (PDF)</a>.</p>
                    <h2>Anfahrt und Verpflegung</h2>
                    <p>Parkplätze gibt es direkt beim Jugendhaus (Einfahrt über die Rahnenstraße), vom Bahnhof sind es etwa 15 Minuten zu Fuß über die Weberbach 71. Das ganze Wochenende gibt es vegetarische Verpflegung; der Weltladen ist auch wieder dabei.</p>
                    <p>Alle Infos zum Nachlesen: <a href="{$url($einladung)}">Einladung (PDF)</a> und <a href="{$url($ablauf)}">Ablaufplan (PDF)</a>.</p>
                    <p>Wir freuen uns auf euch!<br>
                    Liebe Grüße und Gut Pfad!<br>
                    Till Mang (Diözesanvorsitzender) und Peter Zillgen (Diözesankurat)</p>
                    HTML,
                'cover_media_id' => $cover,
                'status' => 'published',
                'published_at' => now(),
                'ai_level' => 'assist',
                'ai_tool' => 'Claude',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Link aus der PDF-Einladung (alte Website) auf die neue Anmeldung.
        DB::table('redirects')->insertOrIgnore([
            'from_path' => '/anmeldung-zur-dioezesanversammlung-2026', 'to_path' => $event,
            'status_code' => 301, 'source' => 'manuell', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Datei aus database/content in die Mediathek übernehmen (einmalig).
     *
     * @param  array{0: int, 1: int}|null  $size
     */
    private function media(string $file, string $mime, string $alt, ?array $size = null): ?int
    {
        $path = 'media/2026/'.$file;
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
            'credit' => 'DPSG Diözesanverband Trier',
            'rights_confirmed' => true,
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Rein inhaltlich; Beitrag und Veranstaltung werden im Backend gepflegt.
    }
};
