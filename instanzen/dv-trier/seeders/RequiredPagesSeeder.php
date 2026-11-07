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

    /** Datenschutzerklärung (instanzen/dv-trier/dateien/datenschutz.html, abgestimmte Fassung von dpsg-trier.de, ergänzt). */
    public static function datenschutz(): string
    {
        return (string) file_get_contents(base_path('instanzen/dv-trier/dateien/datenschutz.html'));
    }

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
                'body' => self::datenschutz(),
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
