<?php

namespace Instanzen\DvTrier\Seeders;

use Illuminate\Database\Seeder;

/**
 * Inhalt, den bis zur Umstellung auf Kluft die Daten-Migrationen angelegt
 * haben – in derselben Reihenfolge, damit eine neue DV-Installation genau
 * den bisherigen Stand bekommt. Bestehende Installationen haben diese
 * Schritte schon hinter sich (die Migrationen sind jetzt leer).
 *
 * Läuft auch in den Tests (tests/TestCase.php), wie früher die Migrationen.
 */
class InhaltSeeder extends Seeder
{
    /** @var list<class-string<Seeder>> */
    public const SCHRITTE = [
        GruppierungenSeeder::class,                      // 2026_10_01_193056
        Inhalt\UpdateDirectoryLinksInSeededPages::class, // 2026_10_01_194205
        StaemmeSeeder::class,                            // 2026_10_01_222959
        Inhalt\SplitDioezesanleitungFromDioezesanbuero::class,
        Inhalt\UpdateGeorgsboteIntroText::class,
        Inhalt\BolivienSeiteAlsSektion::class,           // 2026_10_01_225318
        Inhalt\SetupBolivienSectionPages::class,
        Inhalt\SeedDemoEvents::class,
        Inhalt\AssignDefaultStufenToStaemme::class,
        Inhalt\SeedLeitungAndBueroPeople::class,
        Inhalt\PersonenAbzeichen::class,                 // 2026_10_02_080246
        Inhalt\UpdateBolivienLandingText::class,
        Inhalt\TidyStagePageBodies::class,
        Inhalt\MenuStufenAndFachbereiche::class,
        Inhalt\FillPersonEmailAddresses::class,
        Inhalt\MenuDioezesanverbandGeorgsbrief::class,
        Inhalt\Glossar::class,                           // 2026_10_02_150000
        Inhalt\FixTillMangRole::class,
        Inhalt\Kontaktthemen::class,                     // 2026_10_02_180000
        Inhalt\RechtstraegerPagesAndImpressum::class,
        Inhalt\ContentDioezesanversammlung2026::class,
        Inhalt\Divers2026DaysField::class,
        Inhalt\DiversEinladungMitVeranstaltung::class,   // 2026_10_03_120000
        Inhalt\DiversCoverWithJugendhaus::class,
        Inhalt\ContentBeratenOnline::class,
        Inhalt\BeratenOnlineServiceUndCartoons::class,
        Inhalt\FachbereicheSpiritualitaetAusbildungOekologie::class, // instanzen/dv-trier/migrations/2026_10_05_120000
        Inhalt\FachbereichPolitischeBildung::class, // instanzen/dv-trier/migrations/2026_10_06_090100
    ];

    public function run(): void
    {
        foreach (self::SCHRITTE as $schritt) {
            $this->container->make($schritt)->setContainer($this->container)->run();
        }
    }
}
