<?php

namespace Tests\Feature\Monitoring;

use App\Models\User;
use App\Notifications\SystemProblemNotification;
use App\Support\Roadmap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Statusseite und Monitoring (Pflichtenheft 10.2).
 */
class StatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);

        return $user;
    }

    #[TestDox('Alles in Ordnung: /status zeigt die Bereiche, /status.json liefert 200')]
    public function test_healthy(): void
    {
        Cache::forever(Roadmap::HEARTBEAT_KEY, now()->timestamp);

        $this->get('/status')->assertOk()->assertSee('Systemstatus')->assertSee('Geplante Aufgaben (Cronjob)')->assertSee('Datenbank');
        $json = $this->getJson('/status.json')->assertOk()->json();
        $this->assertSame('warnung', $json['status']); // Backups noch nicht eingeschaltet
        $this->assertSame('ok', $json['checks']['cronjob']['status']);
        $this->assertSame('warnung', $json['checks']['backups']['status']);
    }

    #[TestDox('Cronjob steht oder Mails hängen: Störung, HTTP 503 und Alarmmail an Admins – höchstens alle sechs Stunden')]
    public function test_failure_alerts_admins_once(): void
    {
        Notification::fake();
        $admin = $this->admin();
        Cache::forever(Roadmap::HEARTBEAT_KEY, now()->subMinutes(30)->timestamp);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);

        $json = $this->getJson('/status.json')->assertStatus(503)->json();
        $this->assertSame('fehler', $json['checks']['cronjob']['status']);
        $this->assertSame('fehler', $json['checks']['mails']['status']);

        $this->artisan('monitor:check')->assertSuccessful();
        Notification::assertSentTo($admin, SystemProblemNotification::class, fn ($n) => count($n->problems) === 2);

        $this->artisan('monitor:check')->assertSuccessful();
        Notification::assertSentToTimes($admin, SystemProblemNotification::class, 1);
    }
}
