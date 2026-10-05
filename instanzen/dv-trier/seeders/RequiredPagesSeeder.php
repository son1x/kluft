<?php

namespace Instanzen\DvTrier\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pflegbare Pflichtseiten (Pflichtenheft 6.1 F-CMS-08). Inhalt orientiert
 * sich an den bisherigen Angaben von dpsg-trier.de (Anschrift, Kontakt,
 * KDG-Grundlagen), verbleibende [TODO]-Markierungen betreffen Angaben, die
 * die Verwaltung vor Veröffentlichung fachlich bestätigen oder ergänzen
 * muss (z. B. aktuell vertretungsberechtigte Person, Hosting-Anbieter).
 * Seiten werden trotzdem als "published" angelegt, damit Navigation und
 * Footer-Links nicht ins Leere laufen.
 */
class RequiredPagesSeeder extends Seeder
{
    /** Impressum; die Migration 2026_10_03_090000 setzt es auf bestehenden Installationen. */
    public const IMPRESSUM_BODY = <<<'HTML'
        <p><strong>Hinweis:</strong> Dieses Impressum orientiert sich an den Angaben von dpsg-trier.de und ist vor Veröffentlichung von der Verwaltung zu prüfen.</p>
        <h2>Angaben gemäß § 5 DDG</h2>
        <p>DPSG Diözesanverband Trier<br>
        Rechtsträger: Landesamt St. Georg e. V.<br>
        Weberbach 71<br>
        54290 Trier</p>
        <h2>Vertreten durch</h2>
        <p>Den Vorstand des Landesamts St. Georg e. V. [TODO: Namen der vertretungsberechtigten Vorstandsmitglieder ergänzen.]<br>
        Geschäftsführer: Marc Basenach</p>
        <h2>Registereintrag</h2>
        <p>[TODO: Registergericht und Vereinsregisternummer des Landesamts St. Georg e. V. ergänzen.]</p>
        <h2>Kontakt</h2>
        <p>Telefon: 0651 4 629 829 0<br>
        E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
        <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
        <p>Marc Basenach, Geschäftsführender Bildungsreferent<br>
        Weberbach 71, 54290 Trier<br>
        E-Mail: <a href="mailto:m.basenach@dpsg-trier.de">m.basenach@dpsg-trier.de</a></p>
        <h2>Zugehörigkeit zum Verband</h2>
        <p>Der DPSG Diözesanverband Trier ist Teil der Deutschen Pfadfinderschaft Sankt Georg (DPSG). Bundesweite Informationen unter <a href="https://www.dpsg.de" target="_blank" rel="noopener">www.dpsg.de</a>, Informationen zum Bistum Trier unter <a href="https://www.bistum-trier.de" target="_blank" rel="noopener">www.bistum-trier.de</a>. Zu Rechtsträger, Stiftung und Förderverein siehe <a href="/rechtstraeger">Rechtsträger</a>.</p>
        <h2>EU-Streitschlichtung</h2>
        <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>. Wir sind nicht verpflichtet und nicht bereit, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>
        <h2>Haftung für Inhalte</h2>
        <p>Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen.</p>
        <h2>Haftung für Links</h2>
        <p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter verantwortlich.</p>
        <h2>Bildnachweise</h2>
        <p>Stufen-Maskottchen und Verbandslogo: offizielles Bildmaterial der DPSG, Quelle dpsg.de. [TODO: Angaben zu eigenem Bildmaterial ergänzen, sobald verwendet.]</p>
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
                'body' => <<<'HTML'
                    <p><strong>Hinweis:</strong> Diese Datenschutzerklärung orientiert sich an den Angaben von dpsg-trier.de, ersetzt keine Rechtsberatung und ist vor Veröffentlichung mit der/dem Datenschutzbeauftragten abzustimmen.</p>
                    <h2>1. Verantwortlicher</h2>
                    <p>Verantwortlich für die Datenverarbeitung auf dieser Website ist:<br>
                    DPSG Diözesanverband Trier<br>
                    Weberbach 71, 54290 Trier<br>
                    E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a><br>
                    Telefon: 0651 4 629 829 0</p>
                    <h2>2. Kirchliche*r Datenschutzbeauftragte*r</h2>
                    <p>Als kirchliche Einrichtung im Bistum Trier ist für den Diözesanverband Trier zuständig:<br>
                    Betrieblicher Datenschutz, Bistum Trier<br>
                    Mustorstraße 2, 54290 Trier<br>
                    Telefon: 0651 7105-148</p>
                    <h2>3. Rechtsgrundlage</h2>
                    <p>Als kirchliche Einrichtung unterliegt diese Website nicht der DSGVO, sondern dem <strong>Gesetz über den Kirchlichen Datenschutz (KDG)</strong> in der vom Bischof von Trier in Kraft gesetzten Fassung sowie ergänzend dem Digitale-Dienste-Gesetz (DDG).</p>
                    <h2>4. Allgemeines zur Datenverarbeitung</h2>
                    <p>Personenbezogene Daten werden auf dieser Website weit überwiegend auf freiwilliger Basis erhoben, etwa wenn du uns über das Kontaktformular oder per E-Mail erreichst. Eine Verarbeitung erfolgt nur, soweit dies zur Bereitstellung einer funktionsfähigen Website erforderlich ist oder eine Einwilligung bzw. rechtliche Erlaubnis nach dem KDG vorliegt.</p>
                    <h2>5. Hosting und Server-Logfiles</h2>
                    <p>[TODO: Hosting-Anbieter ergänzen.] Beim Aufruf erhebt der Hosting-Anbieter automatisch Informationen in Server-Logfiles, die dein Browser übermittelt (z. B. IP-Adresse, Browsertyp und -version, verwendetes Betriebssystem, aufgerufene Seite, Datum und Uhrzeit des Zugriffs, verweisende Website). Diese Daten dienen der Gewährleistung eines störungsfreien Betriebs und der Sicherheit der Website und werden spätestens nach 7 Tagen gelöscht.</p>
                    <h2>6. Cookies und Consent</h2>
                    <p>Diese Website verwendet nur technisch notwendige Cookies ohne Einwilligung. Optionale Kategorien (Statistik, externe Inhalte) werden erst nach deiner Zustimmung aktiv; du kannst deine Auswahl jederzeit über "Datenschutz-Einstellungen" im Footer ändern.</p>
                    <h2>7. Schriftarten</h2>
                    <p>Diese Website bindet die Schriftarten Carlito und Arvo selbst gehostet ein. Es findet dabei keine Verbindung zu externen Font-Anbietern statt.</p>
                    <h2>8. Kontaktaufnahme</h2>
                    <p>Wenn du uns per E-Mail oder über das Kontaktformular kontaktierst, werden deine Angaben (Name, E-Mail-Adresse, Nachricht) zur Bearbeitung deiner Anfrage gespeichert. Diese Daten geben wir nicht ohne deine Einwilligung weiter und löschen sie, sobald sie für die Beantwortung nicht mehr benötigt werden.</p>
                    <h2>9. Georgsbote-Newsletter</h2>
                    <p>Für den Versand des Georgsboten (Newsletter) werden die dafür angegebenen Daten (z. B. E-Mail-Adresse) ausschließlich zu diesem Zweck verwendet und nicht an Dritte weitergegeben. Eine Abmeldung ist jederzeit möglich. [TODO: Details zum Versanddienst (Brevo) und zur Auftragsverarbeitung ergänzen.]</p>
                    <h2>10. Deine Rechte nach dem KDG</h2>
                    <ul>
                    <li>Recht auf Widerruf einer erteilten Einwilligung (§ 8 KDG)</li>
                    <li>Recht auf Auskunft über deine gespeicherten Daten (§ 17 KDG)</li>
                    <li>Recht auf Berichtigung, Löschung oder Einschränkung der Verarbeitung (§§ 18–20 KDG)</li>
                    <li>Recht auf Datenübertragbarkeit (§ 22 KDG)</li>
                    <li>Widerspruchsrecht gegen die Verarbeitung (§ 23 KDG)</li>
                    <li>Recht auf Beschwerde bei der zuständigen Aufsichtsbehörde</li>
                    </ul>
                    <p>Wende dich für die Ausübung dieser Rechte an die oben genannte Kontaktadresse.</p>
                    <h2>11. Beschwerderecht bei der Aufsichtsbehörde</h2>
                    <p>Als kirchliche Stelle unterliegen wir nicht der Aufsicht der staatlichen Landesdatenschutzbehörden, sondern der:<br>
                    Überdiözesane Aufsichtsstelle im Datenschutz<br>
                    Roßmarkt 23, 60311 Frankfurt am Main<br>
                    Telefon: 069 58 99 755-10<br>
                    E-Mail: <a href="mailto:info@kdsz-ffm.de">info@kdsz-ffm.de</a></p>
                    HTML,
            ],
            [
                'slug' => 'kontakt',
                'title' => 'Kontakt',
                'body' => <<<'HTML'
                    <p>Fragen zum Diözesanverband, zu einem Bezirk oder zum Mitmachen? Schreib uns – wir melden uns zurück oder vermitteln dich weiter.</p>
                    <h2>Diözesanbüro</h2>
                    <p>DPSG Diözesanverband Trier<br>
                    Weberbach 71<br>
                    54290 Trier</p>
                    <p>Telefon: <a href="tel:+4965146298290">0651 4 629 829 0</a><br>
                    E-Mail: <a href="mailto:info@dpsg-trier.de">info@dpsg-trier.de</a></p>
                    <h2>Kontakt zu einem Bezirk vor Ort</h2>
                    <p>Alle Bezirke und Stämme mit Kontakt und Karte findest du unter <a href="/bezirke-staemme">Bezirke &amp; Stämme</a>. Die hauptberuflichen Ansprechpersonen findest du im <a href="/dioezesanbuero">Diözesanbüro</a>, das ehrenamtliche Leitungsteam unter <a href="/dioezesanleitung">Diözesanleitung</a>.</p>
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
