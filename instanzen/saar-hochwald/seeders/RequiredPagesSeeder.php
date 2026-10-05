<?php

namespace Instanzen\SaarHochwald\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pflegbare Pflichtseiten (Impressum, Datenschutz, Kontakt). Inhalt
 * übernommen von der bisherigen statischen Bezirksseite (Release 1.0) und
 * an das CMS angepasst (selbst gehostete Schriften und Karte, Kontakt-
 * formular, Veranstaltungsanmeldung). Seiten werden als "published"
 * angelegt, damit Navigation und Footer-Links nicht ins Leere laufen.
 */
class RequiredPagesSeeder extends Seeder
{
    public const IMPRESSUM_BODY = <<<'HTML'
        <h2>Angaben gemäß § 5 DDG</h2>
        <p>DPSG Bezirk Saar-Hochwald<br>
        Sebastian Hafner (Bezirksvorsitzender)<br>
        Donatusstraße 34a<br>
        66740 Saarlouis</p>
        <h2>Vertreten durch</h2>
        <p>Der DPSG Bezirk Saar-Hochwald ist ein nicht eingetragener Verein und wird durch den Bezirksvorstand vertreten, gesetzlich vertretungsberechtigt ist der Bezirksvorsitzende Sebastian Hafner. Ein Eintrag im Vereinsregister besteht nicht.</p>
        <h2>Kontakt</h2>
        <p>E-Mail: <a href="mailto:vorstand@dpsg-saar-hochwald.de">vorstand@dpsg-saar-hochwald.de</a><br>
        Telefon: 0173 479 59 59</p>
        <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
        <p>Sebastian Hafner, Anschrift wie oben</p>
        <h2>Zugehörigkeit zum Verband</h2>
        <p>Der DPSG Bezirk Saar-Hochwald ist Teil der Deutschen Pfadfinderschaft Sankt Georg (DPSG), Diözesanverband Trier. Bundesweite Informationen finden sich unter <a href="https://www.dpsg.de" target="_blank" rel="noopener">www.dpsg.de</a>, diözesane Informationen unter <a href="https://www.dpsg-trier.de" target="_blank" rel="noopener">www.dpsg-trier.de</a>.</p>
        <h2>EU-Streitschlichtung</h2>
        <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>. Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>
        <h2>Haftung für Inhalte</h2>
        <p>Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen.</p>
        <h2>Haftung für Links</h2>
        <p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter verantwortlich.</p>
        <h2>Bildnachweise</h2>
        <p>Stufen-Maskottchen und Stufenlogos: offizielles Bildmaterial der DPSG, Quelle dpsg.de bzw. rover.de. Bezirkslogo: DPSG Bezirk Saar-Hochwald. Angaben zu weiterem Bildmaterial stehen jeweils am Bild.</p>
        HTML;

    public const DATENSCHUTZ_BODY = <<<'HTML'
        <p><strong>Hinweis:</strong> Diese Datenschutzerklärung orientiert sich an der Erklärung des DPSG-Diözesanverbands Trier, zu dem der Bezirk Saar-Hochwald gehört, und ist an die eigene Website angepasst.</p>
        <h2>1. Verantwortlicher</h2>
        <p>Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
        DPSG Bezirk Saar-Hochwald<br>
        Sebastian Hafner (Bezirksvorsitzender)<br>
        Donatusstraße 34a, 66740 Saarlouis<br>
        E-Mail: <a href="mailto:vorstand@dpsg-saar-hochwald.de">vorstand@dpsg-saar-hochwald.de</a><br>
        Telefon: 0173 479 59 59</p>
        <h2>2. Kirchliche*r Datenschutzbeauftragte*r</h2>
        <p>Als Teil der Deutschen Pfadfinderschaft Sankt Georg im Bistum Trier ist für den Bezirk Saar-Hochwald zuständig:<br>
        Betrieblicher Datenschutz, Bistum Trier<br>
        Mustorstraße 2, 54290 Trier<br>
        Telefon: 0651 7105-148</p>
        <h2>3. Rechtsgrundlage</h2>
        <p>Als kirchliche Einrichtung unterliegt diese Website nicht der DSGVO, sondern dem <strong>Gesetz über den Kirchlichen Datenschutz (KDG)</strong> in der vom Bischof von Trier in Kraft gesetzten Fassung sowie ergänzend dem Digitale-Dienste-Gesetz (DDG).</p>
        <h2>4. Allgemeines zur Datenverarbeitung</h2>
        <p>Personenbezogene Daten werden auf dieser Website weit überwiegend auf freiwilliger Basis erhoben, etwa wenn du uns über das Kontaktformular oder per E-Mail erreichst oder dich zu einer Veranstaltung anmeldest. Eine Verarbeitung erfolgt nur, soweit dies zur Bereitstellung einer funktionsfähigen Website erforderlich ist oder eine Einwilligung bzw. rechtliche Erlaubnis nach dem KDG vorliegt.</p>
        <h2>5. Hosting und Server-Logfiles</h2>
        <p>Diese Website wird bei der netcup GmbH (Karlsruhe) gehostet. Beim Aufruf erhebt der Hosting-Anbieter automatisch Informationen in Server-Logfiles, die dein Browser übermittelt (z. B. IP-Adresse, Browsertyp und -version, verwendetes Betriebssystem, aufgerufene Seite, Datum und Uhrzeit des Zugriffs, verweisende Website). Diese Daten dienen der Gewährleistung eines störungsfreien Betriebs und der Sicherheit der Website und werden spätestens nach 7 Tagen gelöscht.</p>
        <h2>6. Cookies und Einwilligung</h2>
        <p>Diese Website verwendet ohne Einwilligung nur technisch notwendige Cookies (z. B. für Formulare und den Redaktionsbereich). Externe Inhalte wie eingebettete Videos werden erst nach deiner Zustimmung geladen (Zwei-Klick-Lösung); du kannst deine Auswahl jederzeit über „Datenschutz-Einstellungen“ im Footer ändern.</p>
        <h2>7. Schriftarten und Karte</h2>
        <p>Schriftarten und die Karte der Stämme liegen auf unserem eigenen Webspace. Beim Anzeigen wird keine Verbindung zu externen Font- oder Kartenanbietern hergestellt. Kartendaten: © OpenStreetMap-Mitwirkende (ODbL).</p>
        <h2>8. Kontaktaufnahme</h2>
        <p>Wenn du uns per E-Mail oder über das Kontaktformular kontaktierst, werden deine Angaben (Name, E-Mail-Adresse, Nachricht) zur Bearbeitung deiner Anfrage per E-Mail an die zuständige Person weitergeleitet. Die Nachricht wird nicht in der Website gespeichert. Wir geben diese Daten nicht ohne deine Einwilligung weiter.</p>
        <h2>9. Anmeldung zu Veranstaltungen</h2>
        <p>Für die Anmeldung zu Veranstaltungen verarbeiten wir die im Formular abgefragten Angaben ausschließlich zur Organisation der Veranstaltung. Bestätigungs-E-Mails versenden wir über den Dienstleister Brevo (Sendinblue GmbH, Berlin) als Auftragsverarbeiter. Die Daten werden nach Ende der Veranstaltung innerhalb der im Anmeldeformular genannten Frist gelöscht, unbestätigte Anmeldungen nach 48 Stunden.</p>
        <h2>10. Newsletter</h2>
        <p>Für den Newsletter verarbeiten wir deine E-Mail-Adresse und optional deinen Vornamen ausschließlich für den Versand. Die Anmeldung erfolgt per Double-Opt-In: Du bekommst eine E-Mail mit Bestätigungslink und wirst erst danach eingetragen. Verwaltet und versendet wird der Verteiler beim Dienstleister Brevo (Sendinblue GmbH, Berlin) als Auftragsverarbeiter; auf unserer Website wird die Adresse nicht gespeichert. Abmelden kannst du dich jederzeit über den Link in jeder Ausgabe oder per E-Mail an die oben genannte Adresse.</p>
        <h2>11. Deine Rechte nach dem KDG</h2>
        <ul>
        <li>Recht auf Widerruf einer erteilten Einwilligung (§ 8 KDG)</li>
        <li>Recht auf Auskunft über deine gespeicherten Daten (§ 17 KDG)</li>
        <li>Recht auf Berichtigung, Löschung oder Einschränkung der Verarbeitung (§§ 18–20 KDG)</li>
        <li>Recht auf Datenübertragbarkeit (§ 22 KDG)</li>
        <li>Widerspruchsrecht gegen die Verarbeitung (§ 23 KDG)</li>
        <li>Recht auf Beschwerde bei der zuständigen Aufsichtsbehörde</li>
        </ul>
        <p>Wende dich für die Ausübung dieser Rechte an die oben genannte Kontaktadresse.</p>
        <h2>12. Beschwerderecht bei der Aufsichtsbehörde</h2>
        <p>Als kirchliche Stelle unterliegen wir nicht der Aufsicht der staatlichen Landesdatenschutzbehörden, sondern der:<br>
        Überdiözesane Aufsichtsstelle im Datenschutz<br>
        Roßmarkt 23, 60311 Frankfurt am Main<br>
        Telefon: 069 58 99 755-10<br>
        E-Mail: <a href="mailto:info@kdsz-ffm.de">info@kdsz-ffm.de</a></p>
        HTML;

    public function run(): void
    {
        $pages = [
            [
                'slug' => 'impressum',
                'title' => 'Impressum',
                'body' => self::IMPRESSUM_BODY,
            ],
            [
                'slug' => 'datenschutz',
                'title' => 'Datenschutzerklärung',
                'body' => self::DATENSCHUTZ_BODY,
            ],
            [
                'slug' => 'kontakt',
                'title' => 'Kontakt',
                'body' => <<<'HTML'
                    <p>Fragen zum Bezirk, zu einem Stamm oder zum Mitmachen? Schreib uns – wir melden uns zurück oder vermitteln dich weiter.</p>
                    <h2>Bezirksleitung</h2>
                    <p>DPSG Bezirk Saar-Hochwald<br>
                    Donatusstraße 34a<br>
                    66740 Saarlouis</p>
                    <p>Telefon: <a href="tel:+491734795959">0173 479 59 59</a> (Bezirksvorsitzender)<br>
                    E-Mail: <a href="mailto:bezirksleitung@dpsg-saar-hochwald.de">bezirksleitung@dpsg-saar-hochwald.de</a></p>
                    <h2>Kontakt zu einem Stamm vor Ort</h2>
                    <p>Alle Stämme des Bezirks mit Kontakt und Karte findest du unter <a href="/staemme">Unsere Stämme</a>. Das ehrenamtliche Leitungsteam stellt sich unter <a href="/bezirksleitung">Bezirksleitung</a> vor.</p>
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
                ]
            );
        }
    }
}
