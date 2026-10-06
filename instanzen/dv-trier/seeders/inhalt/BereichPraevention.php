<?php

namespace Instanzen\DvTrier\Seeders\Inhalt;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bereich Prävention unter „Diözesanverband“ (Rückmeldung Lisa): kein
 * ehrenamtlicher Fachbereich, sondern ein Thema der Hauptberuflichen.
 * Startseite, Thema im Kontaktformular und Axel Hemgesberg als
 * Ansprechperson. Legt nur an, was fehlt; ändert keine gepflegten Inhalte.
 */
class BereichPraevention extends Seeder
{
    public const SEITE = [
        'slug' => 'praevention', 'title' => 'Prävention', 'nav_sort' => 55, 'section' => 'praevention',
        'seo_description' => 'Sichere Orte für Kinder und Jugendliche: Schutzkonzept, Präventionsschulung und Hilfe bei sexualisierter Gewalt',
    ];

    public const ANSPRECHPERSON = 'Axel Hemgesberg';

    /** Frühere Fassung des Satzes zum Führungszeugnis (für die Korrektur per Migration). */
    public const FUEHRUNGSZEUGNIS_ALT = 'Leitende legen ein erweitertes Führungszeugnis vor. Die nötige Bestätigung für das Bürgeramt gibt es beim Diözesanbüro.';

    public const FUEHRUNGSZEUGNIS_NEU = 'Leitende legen ein erweitertes Führungszeugnis vor. Die Bestätigung der ehrenamtlichen Tätigkeit für den Antrag beim Bürgeramt erstellen sie in der Regel selbst über ihren NaMi-Zugang.';

    public const BODY = <<<'HTML'
        <p>Pfadfinden lebt von Vertrauen. Kinder und Jugendliche sollen sich bei uns sicher fühlen – in der Gruppenstunde, auf dem Lager und bei jeder Veranstaltung. Deshalb nehmen wir den Schutz vor sexualisierter Gewalt ernst: Wir achten Grenzen, sprechen Unsicherheiten an und wissen, was im Ernstfall zu tun ist.</p>
        <h2>Was wir dafür tun</h2>
        <ul>
        <li><strong>Schutzkonzept:</strong> Der Diözesanverband arbeitet nach einem institutionellen Schutzkonzept auf Grundlage der Präventionsordnung des Bistums Trier. Stämme und Bezirke unterstützen wir dabei, ihr eigenes Schutzkonzept zu entwickeln und aktuell zu halten.</li>
        <li><strong>Präventionsschulung:</strong> Wer bei uns mit Kindern und Jugendlichen arbeitet, nimmt an einer Präventionsschulung teil und frischt sie regelmäßig auf. Termine findest du unter <a href="/veranstaltungen">Veranstaltungen</a>.</li>
        <li><strong>Erweitertes Führungszeugnis:</strong> Leitende legen ein erweitertes Führungszeugnis vor. Die Bestätigung der ehrenamtlichen Tätigkeit für den Antrag beim Bürgeramt erstellen sie in der Regel selbst über ihren NaMi-Zugang.</li>
        <li><strong>Verhaltenskodex:</strong> Leitende verpflichten sich auf einen gemeinsamen Verhaltenskodex für den Umgang mit Kindern und Jugendlichen.</li>
        </ul>
        <h2>Wenn dir etwas passiert ist oder du dir Sorgen machst</h2>
        <p>Du bist nicht allein, und es ist richtig, darüber zu sprechen – auch wenn du dir nicht sicher bist. Melde dich bei unserer Ansprechperson (unten) oder bei einer unabhängigen Stelle:</p>
        <ul>
        <li><a href="https://www.bistum-trier.de/hilfe-soziales/hilfe-bei-sexualisierter-gewalt/intervention/">Ansprechpersonen des Bistums Trier</a> bei Verdacht auf sexualisierte Gewalt</li>
        <li><a href="https://www.praevention.bistum-trier.de/">Fachstelle Prävention im Bistum Trier</a></li>
        <li><strong>Hilfetelefon Sexueller Missbrauch</strong> (bundesweit, kostenfrei und anonym): <a href="tel:+498002255530">0800 22 55 530</a></li>
        <li><strong>Nummer gegen Kummer</strong> für Kinder und Jugendliche: <a href="tel:116111">116 111</a></li>
        <li><a href="https://dpsg.de/de/verbandsleben/themen/kinder-und-jugendschutz/aufarbeitung">Aufarbeitung in der DPSG</a> (Bundesebene)</li>
        </ul>
        <p>In akuter Gefahr: Polizei <a href="tel:110">110</a>.</p>
        HTML;

    public function run(): void
    {
        $dv = DB::table('pages')->where('slug', 'dioezesanverband')->value('id');
        if ($dv && ! DB::table('pages')->where('slug', self::SEITE['slug'])->exists()) {
            DB::table('pages')->insert([
                ...self::SEITE,
                'body' => self::BODY,
                'parent_id' => $dv,
                'show_in_nav' => true,
                'status' => 'published',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ansprechperson: nur zuordnen, wenn noch kein Bereich eingetragen ist.
        DB::table('people')->where('name', self::ANSPRECHPERSON)->whereNull('section')
            ->update(['section' => 'praevention', 'updated_at' => now()]);

        if (DB::table('contact_topics')->exists() && ! DB::table('contact_topics')->where('slug', 'praevention')->exists()) {
            DB::table('contact_topics')->insert([
                'label' => 'Prävention und Schutz', 'slug' => 'praevention', 'section' => 'praevention',
                'stage' => null, 'email' => null, 'sort' => (int) DB::table('contact_topics')->max('sort') + 10, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
