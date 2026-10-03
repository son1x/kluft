<?php

namespace Tests\Feature\Privacy;

use App\Models\AuditLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Audit-Log: Aufbewahrung 12 Monate (Pflichtenheft 8.2, 11.4).
 */
class AuditPruneTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('audit:prune löscht Einträge älter als 12 Monate und ist täglich eingeplant')]
    public function test_prunes_old_entries(): void
    {
        AuditLog::query()->create(['action' => 'alt', 'created_at' => now()->subMonths(12)->subDay()]);
        AuditLog::query()->create(['action' => 'grenze', 'created_at' => now()->subMonths(12)->addDay()]);
        AuditLog::query()->create(['action' => 'neu', 'created_at' => now()]);

        $this->artisan('audit:prune')->expectsOutputToContain('Gelöscht: 1')->assertSuccessful();
        $this->assertSame(['grenze', 'neu'], AuditLog::query()->orderBy('id')->pluck('action')->all());

        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");
        $this->assertStringContainsString('audit:prune', $commands);
    }
}
