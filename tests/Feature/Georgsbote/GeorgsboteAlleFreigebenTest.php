<?php

namespace Tests\Feature\Georgsbote;

use App\Models\AuditLog;
use App\Models\GeorgsboteIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Inhalts-Migration dv-trier: alle Georgsboten und Georgsbriefe mit PDF
 * geprüft und veröffentlicht (Auftrag Projektleitung 09.11.2026).
 */
class GeorgsboteAlleFreigebenTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Ausgaben mit PDF werden geprüft und veröffentlicht, „Sichtbar ab“ bleibt, ohne PDF bleibt alles, mit Protokoll')]
    public function test_migration(): void
    {
        $bote = GeorgsboteIssue::query()->create(['art' => GeorgsboteIssue::BOTE, 'year' => 2010, 'issue_no' => 1, 'title' => 'Georgsbote 1/2010', 'pdf_path' => 'georgsbote/2010-1.pdf']);
        $brief = GeorgsboteIssue::query()->create(['art' => GeorgsboteIssue::BRIEF, 'year' => 2020, 'issue_no' => 3, 'title' => 'Georgsbrief März 2020', 'pdf_path' => 'georgsbrief/2020-03.pdf', 'published_at' => now()->addWeek()]);
        $ohnePdf = GeorgsboteIssue::query()->create(['art' => GeorgsboteIssue::BOTE, 'year' => 2011, 'issue_no' => 1, 'title' => 'Georgsbote 1/2011', 'pdf_path' => '']);

        (require base_path('instanzen/dv-trier/migrations/2026_11_09_140000_georgsbote_alle_veroeffentlichen.php'))->up();

        foreach ([$bote, $brief] as $issue) {
            $issue->refresh();
            $this->assertTrue($issue->privacy_checked);
            $this->assertNotNull($issue->privacy_checked_at);
            $this->assertTrue($issue->published);
        }
        $this->assertTrue($brief->published_at->isFuture());
        $this->assertSame([$bote->id], GeorgsboteIssue::query()->published()->pluck('id')->all());
        $this->assertFalse($ohnePdf->fresh()->published);
        $this->assertSame(1, AuditLog::query()->where('action', 'georgsbote.alle_freigegeben')->count());
    }
}
