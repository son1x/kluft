<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anmeldung zur Wö- und Biber-Weihnachtspostkartenaktion 2026 (Vorlage der
 * Wölflings- und Biberstufe als Word-Dokument). Angelegt als Veranstaltung
 * mit Anmeldung bis 23.11.2026; die Fragen, die es im Baukasten nicht gibt,
 * stehen als eigene Felder darin. Danach im Backend bearbeitbar; läuft die
 * Migration erneut, bleibt eine vorhandene Veranstaltung unberührt.
 */
return new class extends Migration
{
    private const SLUG = 'weihnachtspostkartenaktion-2026';

    /** In Tests nur auf ausdrücklichen Wunsch (andere Tests erwarten keine Veranstaltungen). */
    public bool $auchInTests = false;

    public function up(): void
    {
        if ((app()->runningUnitTests() && ! $this->auchInTests) || DB::table('events')->where('slug', self::SLUG)->exists()) {
            return;
        }

        $jetzt = now();
        DB::table('events')->insert([
            'title' => 'Wö- und Biber-Weihnachtspostkartenaktion 🎄',
            'slug' => self::SLUG,
            'teaser' => 'Eure Wölflinge und Biber gestalten Weihnachtskarten für einen anderen Stamm im Diözesanverband, auf Wunsch auch ein kurzes Video oder eine Karte für einen Stamm in Bolivien. Anmeldung für Stämme bis 23. November 2026.',
            'description' => implode('', [
                '<p>Liebe Stammesleitung,</p>',
                '<p>in diesem Jahr möchten wir mit unseren Wölflingen und Bibern in der Weihnachtszeit anderen Menschen eine Freude machen. Im Rahmen unserer Weihnachtsaktion könnt ihr mit eurem Stamm eine Weihnachtskarte für einen Stamm im Diözesanverband gestalten.</p>',
                '<p>Außerdem besteht die Möglichkeit, ein kurzes Video oder eine Karte an einen Stamm in Bolivien zu schicken.</p>',
                '<p><strong>Meldet euch bis zum 23.11.2026 mit dem Formular unten an.</strong> Vor- und Nachname sowie E-Mail-Adresse sind die der Ansprechperson eures Stammes.</p>',
                '<h2>Die Aktionen</h2>',
                '<ul>',
                '<li><strong>Weihnachtskarte gestalten:</strong> Ihr gestaltet eine (oder mehrere) Weihnachtskarte(n) und schickt sie an einen anderen Stamm im Diözesanverband.</li>',
                '<li><strong>Video aufnehmen oder Weihnachtskarte gestalten (Bolivien):</strong> Ihr nehmt ein kurzes Video (ca. 1–3 Minuten) auf, z. B. mit Weihnachtsgrüßen, einem Lied oder einem kleinen Spiel, oder erstellt eine Weihnachtskarte für einen Stamm in Bolivien.</li>',
                '</ul>',
                '<p>Beides ist natürlich auch möglich. Eine Vorlage für die Weihnachtskarte bekommt ihr von uns, per Post oder zum selbst Ausdrucken.</p>',
                '<h2>Hinweise</h2>',
                '<ul>',
                '<li><strong>Videos:</strong> Die Übermittlung erfolgt nach der Anmeldung über das Hochladen in der DPSG DV Trier Cloud. Ladet sie bitte bis zum <strong>08.12.2026</strong> hoch.</li>',
                '<li><strong>Postkarten:</strong> Schickt sie bitte so, dass sie spätestens am <strong>04.12.2026</strong> bei uns im Diözesanbüro sind: DPSG-Diözesanverband Trier, Weberbach 71, 54290 Trier.</li>',
                '</ul>',
            ]),
            'starts_at' => '2026-11-24 00:00:00',
            'ends_at' => '2026-12-24 00:00:00',
            'location' => 'In euren Gruppenstunden',
            'stage' => 'woelflinge',
            'target_group' => 'Stämme mit Wölflingen und Bibern (Anmeldung durch die Stammesleitung)',
            'contact_name' => 'DPSG-Diözesanverband Trier',
            'contact_email' => 'info@dpsg-trier.de',
            'reg_opens_at' => null,
            'reg_closes_at' => '2026-11-23 23:59:00',
            'capacity' => null,
            'waitlist_enabled' => false,
            'adults_only' => true,
            'status' => 'open',
            'form_fields' => json_encode([
                'stamm' => 'required',
                'phone' => 'optional',
                'eigene' => [
                    ['schluessel' => 'eigen_anzahlwb', 'label' => 'Anzahl der Wölflinge und Biber', 'typ' => 'zahl', 'pflicht' => true, 'hinweis' => 'Ungefähr reicht. So wissen wir, wie viele Kartenvorlagen ihr braucht.'],
                    ['schluessel' => 'eigen_adresse', 'label' => 'Adresse für die Post (Straße und Hausnummer, PLZ und Ort)', 'typ' => 'textarea', 'pflicht' => true, 'hinweis' => 'Hierhin kommen die Karten eines anderen Stammes und, wenn ihr das wählt, die Kartenvorlage.'],
                    ['schluessel' => 'eigen_aktion', 'label' => 'Auswahl der Aktion', 'typ' => 'mehrfach', 'pflicht' => true,
                        'optionen' => "Weihnachtskarte für einen Stamm im Diözesanverband gestalten\nVideo aufnehmen oder Weihnachtskarte gestalten für einen Stamm in Bolivien",
                        'hinweis' => 'Bitte Zutreffendes ankreuzen. Beides ist natürlich auch möglich!'],
                    ['schluessel' => 'eigen_vorlage', 'label' => 'Versand der Kartenvorlage', 'typ' => 'auswahl', 'pflicht' => true,
                        'optionen' => "Per Post an die Adresse oben\nIch drucke die Weihnachtskarte selbst aus",
                        'hinweis' => 'Ihr erhaltet von uns eine Vorlage für die Weihnachtskarte. Wie möchtet ihr sie bekommen?'],
                    ['schluessel' => 'eigen_fragen', 'label' => 'Rückfragen & Anmerkungen', 'typ' => 'textarea', 'pflicht' => false, 'hinweis' => 'Habt ihr Fragen, Ideen oder Anmerkungen zur Aktion?'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'retention_days' => 60,
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ]);
    }

    public function down(): void
    {
        // Inhalt bleibt; im Backend löschen.
    }
};
