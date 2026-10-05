<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** RefreshDatabase seedet damit den Grundbestand der Instanz (config dpsg.seeders.basis). */
    protected $seed = false;

    /** @var class-string|null */
    protected $seeder = null;

    /**
     * Tests einer bestimmten Instanz tragen deren Schlüssel als Gruppe
     * (#[Group('dv-trier')]) und laufen nur, wenn KLUFT_INSTANZ passt.
     */
    protected function setUp(): void
    {
        $instanz = getenv('KLUFT_INSTANZ') ?: 'dv-trier';
        foreach ($this->groups() as $group) {
            if ($group !== $instanz && is_file(dirname(__DIR__).'/instanzen/'.$group.'/config.php')) {
                $this->markTestSkipped("Nur für die Instanz {$group} (KLUFT_INSTANZ={$instanz}).");
            }
        }

        parent::setUp();
    }

    /**
     * Früher legten Daten-Migrationen den Grundbestand an (z. B. Bezirke und
     * Stämme des DV); jetzt kommt er aus dem Seeder der aktiven Instanz.
     */
    protected function setUpTraits()
    {
        $this->seeder = config('dpsg.seeders.basis');
        $this->seed = filled($this->seeder);

        return parent::setUpTraits();
    }
}
