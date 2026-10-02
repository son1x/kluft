<?php

namespace Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Übersicht der automatischen Tests unter /roadmap/tests.
 */
class TestReportPageTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Testübersicht zeigt Ergebnis, Bereiche und Testbeschreibungen')]
    public function test_page_shows_totals_areas_and_descriptions(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'report');
        file_put_contents($file, json_encode([
            'generated_at' => '2026-10-02T08:00:00+00:00',
            'commit' => 'abc1234', 'commit_message' => 'Stufenseiten', 'commit_url' => null, 'run_url' => null,
            'totals' => ['tests' => 2, 'assertions' => 7, 'failed' => 1, 'skipped' => 0, 'time' => 1.2],
            'areas' => ['Backup' => [[
                'class' => 'BackupTest', 'description' => 'Backups, Pflichtenheft 10.2.',
                'tests' => [
                    ['method' => 'test_a', 'name' => 'Backup lässt sich wiederherstellen', 'status' => 'passed', 'message' => null, 'assertions' => 5, 'time' => 0.5],
                    ['method' => 'test_b', 'name' => 'Rotation hält 14 Tage', 'status' => 'failed', 'message' => 'Erwartet 14, war 13', 'assertions' => 2, 'time' => 0.7],
                ],
            ]]],
        ]));
        config(['dpsg.test_report' => $file]);

        $this->get('/roadmap/tests')->assertOk()
            ->assertSee('1 von 2 Tests fehlgeschlagen')
            ->assertSee('Backups')
            ->assertSee('1 / 2 bestanden')
            ->assertSee('Backups, Pflichtenheft 10.2.')
            ->assertSee('Backup lässt sich wiederherstellen')
            ->assertSee('Erwartet 14, war 13')
            ->assertSee('abc1234');

        unlink($file);
    }

    #[TestDox('Ohne Testbericht erscheint ein Hinweis statt eines Fehlers')]
    public function test_page_without_report_shows_notice(): void
    {
        config(['dpsg.test_report' => sys_get_temp_dir().'/gibt-es-nicht.json']);

        $this->get('/roadmap/tests')->assertOk()->assertSee('Noch kein Testbericht vorhanden');
    }
}
