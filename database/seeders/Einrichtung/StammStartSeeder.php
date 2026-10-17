<?php

namespace Database\Seeders\Einrichtung;

use App\Models\Gruppierung;
use App\Models\Page;
use App\Support\Ausbau;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Startinhalt einer Stammes-Website aus dem Einrichtungsassistenten
 * (config dpsg.einrichtung): Startseite, Stufen mit Platz für die
 * Gruppenstunden, Stammesvorstand, Kontakt mit Themen, Impressum und
 * Datenschutz als Vorlage, der Stamm selbst als Gruppierung. Legt nur an,
 * was fehlt – im Backend Gepflegtes bleibt.
 */
class StammStartSeeder extends Seeder
{
    private const STUFEN_TEXTE = [
        'biber' => 'Bei den Bibern lernen Kinder ab etwa vier Jahren das Abenteuer Pfadfinden kennen: gemeinsam spielen, draußen sein und die Welt mit neugierigen Augen entdecken.',
        'woelflinge' => 'Wölflinge sind Forscher*innen, Entdecker*innen und Kundschafter*innen. In der Meute erleben Kinder von etwa sechs bis zehn Jahren Gemeinschaft, Spiel und erste Abenteuer.',
        'jupfi' => 'Jungpfadfinder*innen erobern Stück für Stück die Welt: im Trupp planen sie Aktionen, Hajks und Lager zunehmend selbst.',
        'pfadi' => 'Pfadfinder*innen nehmen ihr Leben selbst in die Hand: Sie entscheiden gemeinsam, was sie anpacken, und übernehmen Verantwortung im Trupp.',
        'rover' => 'Rover treffen Entscheidungen für ihre eigene Zukunft: Sie planen eigene Projekte und Fahrten und probieren aus, was in ihnen steckt.',
    ];

    public function run(): void
    {
        $a = (array) config('dpsg.einrichtung', []);
        $stamm = (string) ($a['stamm'] ?? 'Beispiel');
        $ort = (string) ($a['ort'] ?? '');
        $stufen = array_values(array_intersect(array_keys((array) config('dpsg.stages')), (array) ($a['stufen'] ?? array_keys((array) config('dpsg.stages')))));

        $this->seite('start', 'Pfadfinden'.($ort !== '' ? ' in '.$ort : ' beim Stamm '.$stamm), [
            'seo_title' => (string) config('dpsg.site.name'),
            'seo_description' => 'Deutsche Pfadfinderschaft Sankt Georg – Stamm '.$stamm,
            'body' => '<h2>Willkommen beim Stamm '.e($stamm).'</h2>'
                .'<p>Wir sind der Stamm '.e($stamm).' der Deutschen Pfadfinderschaft Sankt Georg (DPSG)'.($ort !== '' ? ' in '.e($ort) : '').'. Bei uns erleben Kinder und Jugendliche Gemeinschaft, Abenteuer und Natur – in wöchentlichen Gruppenstunden, auf Fahrten und im Zeltlager.</p>'
                // Visitenkarte (Ausbaustufe 1): noch keine Gruppenseiten zum Verlinken.
                .((int) ($a['ausbau'] ?? Ausbau::VOLL) >= Ausbau::GRUPPEN
                    ? '<p>Welche Gruppen es gibt und wann sie sich treffen, steht bei den <a href="/stufen">Stufen</a>. Komm einfach mal vorbei – sag uns vorher über das <a href="/kontakt">Kontaktformular</a> kurz Bescheid.</p>'
                    : '<p>Wann sich welche Gruppe trifft, erfährst du bei uns: Schreib uns über das <a href="/kontakt">Kontaktformular</a> – wir melden uns.</p>'),
        ]);

        $this->seite('stufen', 'Unsere Gruppen', [
            'show_in_nav' => true,
            'nav_sort' => 10,
            'seo_description' => 'Gruppenstunden für Kinder und Jugendliche',
            'body' => '<p>Die DPSG begleitet Kinder und Jugendliche in altersgerechten Stufen. Hier findest du unsere Gruppen und wann sie sich treffen.</p>',
        ]);
        $zeiten = (array) ($a['gruppenstunden'] ?? []);
        $orte = (array) ($a['gruppenstunden_orte'] ?? []);
        $adresse = implode(', ', array_filter([trim((string) ($a['strasse'] ?? '')), trim((string) ($a['plz_ort'] ?? ''))]));
        foreach ($stufen as $i => $key) {
            $stage = (array) config("dpsg.stages.{$key}");
            // Eigener Ort der Gruppe (z. B. Pfarrheim), sonst die Adresse des Stamms.
            $ortZeile = filled($orte[$key] ?? null) ? (string) $orte[$key] : $adresse;
            $this->seite('stufe-'.$key, (string) $stage['label'], [
                'parent' => 'stufen',
                'stage' => $key,
                'show_in_nav' => true,
                'nav_sort' => ($i + 1) * 10,
                // Kein eigener SEO-Titel: Der Standard enthält das Alter, das der Stamm anpassen kann.
                'body' => '<p>'.e(self::STUFEN_TEXTE[$key] ?? '').'</p>'
                    .'<h2>Gruppenstunde</h2>'
                    .(filled($zeiten[$key] ?? null)
                        ? '<p><strong>'.e($zeiten[$key]).'</strong>'.($ortZeile !== '' ? '<br>'.e($ortZeile) : '').'</p>'
                            .'<p>Komm einfach mal vorbei – sag uns vorher über das <a href="/kontakt">Kontaktformular</a> kurz Bescheid.</p>'
                        : '<p>Die Zeiten und den Ort der Gruppenstunde tragen wir hier bald ein. Bis dahin frag einfach über das <a href="/kontakt">Kontaktformular</a> nach – wir melden uns.</p>'),
            ]);
        }

        $vorstand = trim((string) ($a['vorstand'] ?? ''));
        $this->seite('stammesvorstand', 'Stammesvorstand', [
            'show_in_nav' => true,
            'nav_sort' => 30,
            'seo_description' => 'Wer den Stamm leitet und wie du uns erreichst',
            'body' => '<p>Der Stammesvorstand leitet den Stamm '.e($stamm).($vorstand !== '' ? ': '.e($vorstand) : '').'. Er ist Ansprechpartner für Eltern, Leiter*innen und alle, die mitmachen möchten.</p>'
                .'<p>Schreib uns über das <a href="/kontakt">Kontaktformular</a> oder an <a href="mailto:'.e((string) config('dpsg.contact.email')).'">'.e((string) config('dpsg.contact.email')).'</a>.</p>',
        ]);

        $this->seite('kontakt', 'Kontakt', [
            'body' => '<p>Fragen zum Stamm, zu einer Gruppe oder zum Schnuppern? Schreib uns – wir melden uns.</p>',
        ]);
        $this->seite('impressum', 'Impressum', ['body' => $this->impressum($a)]);
        $this->seite('datenschutz', 'Datenschutzerklärung', ['body' => $this->datenschutz($a)]);

        $this->kontaktthemen($stufen);

        $gruppierung = Gruppierung::query()->firstOrCreate(
            ['slug' => Str::slug('stamm-'.$stamm) ?: 'stamm'],
            [
                'name' => $stamm,
                'typ' => 'stamm',
                'nummer' => filled($a['nummer'] ?? null) ? (string) $a['nummer'] : null,
                'ort' => $ort !== '' ? $ort : null,
                'kontakt_email' => config('dpsg.contact.email'),
                'website_url' => 'https://'.config('dpsg.site.domain'),
                'active' => true,
                'sort' => 10,
            ],
        );
        if ($gruppierung->wasRecentlyCreated) {
            $gruppierung->syncStufen($stufen);
        }
    }

    /**
     * @param  array<string, mixed>  $werte
     */
    private function seite(string $slug, string $titel, array $werte): void
    {
        Page::query()->firstOrCreate(['slug' => $slug], [
            'parent_id' => isset($werte['parent']) ? Page::query()->where('slug', $werte['parent'])->value('id') : null,
            'title' => $titel,
            'body' => $werte['body'],
            'status' => 'published',
            'published_at' => now(),
            'stage' => $werte['stage'] ?? null,
            'seo_title' => $werte['seo_title'] ?? null,
            'seo_description' => $werte['seo_description'] ?? null,
            'show_in_nav' => $werte['show_in_nav'] ?? false,
            'nav_sort' => $werte['nav_sort'] ?? 0,
        ]);
    }

    /**
     * @param  list<string>  $stufen
     */
    private function kontaktthemen(array $stufen): void
    {
        if (DB::table('contact_topics')->exists()) {
            return;
        }
        $themen = [
            ['label' => 'Allgemeine Frage an den Stammesvorstand', 'slug' => 'allgemein'],
            ['label' => 'Schnupperstunde / Mitmachen', 'slug' => 'mitmachen'],
        ];
        foreach ($stufen as $key) {
            $themen[] = ['label' => 'Gruppe: '.config("dpsg.stages.{$key}.label"), 'slug' => $key, 'stage' => $key];
        }
        $themen[] = ['label' => 'Website (Fehler, Hinweise)', 'slug' => 'website'];

        foreach ($themen as $i => $thema) {
            DB::table('contact_topics')->insert([
                'stage' => null, 'section' => null, 'email' => null, ...$thema,
                'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $a */
    private function impressum(array $a): string
    {
        $stamm = e((string) ($a['stamm'] ?? ''));
        $vorstand = trim((string) ($a['vorstand'] ?? '')) ?: '[Bitte ergänzen: Namen des Stammesvorstands]';
        $anschrift = trim(e((string) ($a['strasse'] ?? '')).'<br>'.e((string) ($a['plz_ort'] ?? '')), '<br>') ?: '[Bitte ergänzen: Anschrift]';
        $email = e((string) config('dpsg.contact.email'));
        $telefon = filled($a['telefon'] ?? null) ? '<br>Telefon: '.e((string) $a['telefon']) : '';
        $verband = filled($a['dioezese'] ?? null) ? ', Diözesanverband '.e((string) $a['dioezese']) : '';

        return <<<HTML
            <h2>Angaben gemäß § 5 DDG</h2>
            <p>DPSG Stamm {$stamm}<br>{$anschrift}</p>
            <h2>Vertreten durch</h2>
            <p>Der Stamm ist ein nicht eingetragener Verein und wird durch den Stammesvorstand vertreten: {$vorstand}.</p>
            <h2>Kontakt</h2>
            <p>E-Mail: <a href="mailto:{$email}">{$email}</a>{$telefon}</p>
            <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
            <p>{$vorstand}, Anschrift wie oben</p>
            <h2>Zugehörigkeit zum Verband</h2>
            <p>Der Stamm {$stamm} ist Teil der Deutschen Pfadfinderschaft Sankt Georg (DPSG){$verband}. Bundesweite Informationen: <a href="https://www.dpsg.de" target="_blank" rel="noopener">www.dpsg.de</a>.</p>
            <h2>Haftung für Inhalte und Links</h2>
            <p>Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten verantwortlich. Für die Inhalte verlinkter externer Seiten ist stets der jeweilige Anbieter verantwortlich.</p>
            <h2>Bildnachweise</h2>
            <p>Stufen-Maskottchen und Stufenlogos: offizielles Bildmaterial der DPSG, Quelle dpsg.de bzw. rover.de. Angaben zu weiterem Bildmaterial stehen jeweils am Bild.</p>
            HTML;
    }

    /** @param array<string, mixed> $a */
    private function datenschutz(array $a): string
    {
        $stamm = e((string) ($a['stamm'] ?? ''));
        $vorstand = trim((string) ($a['vorstand'] ?? '')) ?: '[Bitte ergänzen: Namen des Stammesvorstands]';
        $anschrift = trim(e((string) ($a['strasse'] ?? '')).', '.e((string) ($a['plz_ort'] ?? '')), ', ') ?: '[Bitte ergänzen: Anschrift]';
        $email = e((string) config('dpsg.contact.email'));

        return <<<HTML
            <p><strong>Hinweis für den Stamm:</strong> Diese Datenschutzerklärung ist eine Vorlage. Bitte mit der/dem Datenschutzbeauftragten des Bistums bzw. Diözesanverbands abstimmen und die markierten Stellen ergänzen.</p>
            <h2>1. Verantwortlicher</h2>
            <p>DPSG Stamm {$stamm}, vertreten durch den Stammesvorstand ({$vorstand}), {$anschrift}, E-Mail: <a href="mailto:{$email}">{$email}</a></p>
            <h2>2. Kirchliche*r Datenschutzbeauftragte*r</h2>
            <p>[Bitte ergänzen: zuständige*r Datenschutzbeauftragte*r des Bistums]</p>
            <h2>3. Rechtsgrundlage</h2>
            <p>Als kirchliche Einrichtung unterliegt diese Website dem Gesetz über den Kirchlichen Datenschutz (KDG) sowie ergänzend dem Digitale-Dienste-Gesetz (DDG).</p>
            <h2>4. Hosting und Server-Logfiles</h2>
            <p>Beim Aufruf der Website speichert unser Webhosting-Anbieter [Bitte ergänzen: Name des Anbieters] automatisch technische Daten (z. B. IP-Adresse, Browser, aufgerufene Seite, Zeitpunkt). Sie dienen dem sicheren Betrieb und werden nach kurzer Zeit gelöscht.</p>
            <h2>5. Cookies</h2>
            <p>Ohne Einwilligung setzen wir nur technisch notwendige Cookies. Externe Inhalte wie Videos werden erst nach deiner Zustimmung geladen.</p>
            <h2>6. Kontaktformular und E-Mail</h2>
            <p>Deine Angaben im Kontaktformular werden per E-Mail an die zuständige Person weitergeleitet und nicht in der Website gespeichert.</p>
            <h2>7. Anmeldung zu Veranstaltungen</h2>
            <p>Die Angaben im Anmeldeformular verarbeiten wir nur zur Organisation der Veranstaltung und löschen sie nach der im Formular genannten Frist; unbestätigte Anmeldungen nach 48 Stunden.</p>
            <h2>8. Deine Rechte nach dem KDG</h2>
            <p>Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch sowie auf Widerruf einer Einwilligung. Wende dich dazu an die oben genannte Adresse. Beschwerden richtest du an die Katholische Datenschutzaufsicht, die für dein Bistum zuständig ist.</p>
            HTML;
    }
}
