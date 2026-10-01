<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pflegbare Pflichtseiten (Pflichtenheft 6.1 F-CMS-08). Inhalte sind
 * bewusst Platzhalter mit [TODO]-Markierungen statt erfundener Angaben –
 * echte Angaben (Anschrift, Vertretungsberechtigte etc.) müssen von der
 * Verwaltung vor Veröffentlichung eingetragen werden. Seiten werden
 * trotzdem als "published" angelegt, damit die Navigation/Footer-Links
 * nicht ins Leere laufen; der Platzhaltertext macht den Bearbeitungs-
 * bedarf im Frontend sichtbar.
 */
class RequiredPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'impressum',
                'title' => 'Impressum',
                'body' => <<<'HTML'
                    <p><strong>[TODO: Von der Verwaltung zu vervollständigen – siehe § 5 DDG (ehem. TMG).]</strong></p>
                    <h2>Angaben gemäß § 5 DDG</h2>
                    <p>DPSG Diözesanverband Trier<br>
                    [TODO: Straße, Hausnummer]<br>
                    [TODO: PLZ, Ort]</p>
                    <h2>Vertreten durch</h2>
                    <p>[TODO: Name(n) der Vertretungsberechtigten laut Satzung]</p>
                    <h2>Kontakt</h2>
                    <p>Telefon: [TODO]<br>E-Mail: [TODO]</p>
                    <h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
                    <p>[TODO]</p>
                    HTML,
            ],
            [
                'slug' => 'datenschutz',
                'title' => 'Datenschutzerklärung',
                'body' => <<<'HTML'
                    <p><strong>[TODO: Von der Verwaltung mit dem/der Datenschutzbeauftragten abzustimmen –
                    siehe Pflichtenheft Abschnitt 9. Dieser Platzhalter ersetzt keine Rechtsberatung.]</strong></p>
                    <h2>Verantwortlicher</h2>
                    <p>[TODO: Name, Anschrift, Kontakt des Verantwortlichen]</p>
                    <h2>Welche Daten wir verarbeiten</h2>
                    <p>[TODO: Veranstaltungsanmeldungen, Newsletter (Georgsbote über Brevo), Redaktions-Login (Entra ID) –
                    je Verarbeitung Zweck, Rechtsgrundlage und Speicherdauer ergänzen.]</p>
                    <h2>Cookies und Consent</h2>
                    <p>Diese Website verwendet nur technisch notwendige Cookies ohne Einwilligung. Optionale Kategorien
                    (Statistik, externe Inhalte) werden erst nach deiner Zustimmung aktiv; du kannst deine Auswahl jederzeit
                    über "Datenschutz-Einstellungen" im Footer ändern.</p>
                    <h2>Deine Rechte</h2>
                    <p>[TODO: Auskunft, Berichtigung, Löschung, Widerspruch – Kontaktweg ergänzen.]</p>
                    HTML,
            ],
            [
                'slug' => 'kontakt',
                'title' => 'Kontakt',
                'body' => <<<'HTML'
                    <p><strong>[TODO: Von der Verwaltung zu vervollständigen. Ein Kontaktformular folgt in einer
                    späteren Ausbaustufe (F-CMS-09).]</strong></p>
                    <h2>DPSG Diözesanverband Trier</h2>
                    <p>[TODO: Anschrift]</p>
                    <p>Telefon: [TODO]<br>E-Mail: [TODO]</p>
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
