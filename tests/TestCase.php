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
