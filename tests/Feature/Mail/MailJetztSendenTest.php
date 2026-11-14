<?php

namespace Tests\Feature\Mail;

use App\Filament\Pages\MailQueue;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * E-Mail-Versand: „Jetzt senden“ arbeitet die Warteschlange sofort ab, ohne den Cronjob.
 */
class MailJetztSendenTest extends TestCase
{
    use RefreshDatabase;

    private function nutzer(string $rolle): User
    {
        $u = User::query()->create(['name' => $rolle, 'email' => $rolle.'@example.org', 'status' => 'active']);
        $u->roleAssignments()->create(['role' => $rolle, 'scope_type' => 'global']);

        return $u->fresh();
    }

    #[TestDox('Knopf erscheint nur bei wartenden Mails; Klick arbeitet sie ab, meldet das Ergebnis und protokolliert')]
    public function test_jetzt_senden(): void
    {
        Filament::setCurrentPanel('admin');
        config(['queue.default' => 'database']);
        $verwaltung = $this->nutzer('verwaltung');

        Livewire::actingAs($verwaltung)->test(MailQueue::class)->assertDontSee('Jetzt senden');

        dispatch(function () {
            Cache::put('mail-jetzt-test-1', 'ok');
        });
        dispatch(function () {
            Cache::put('mail-jetzt-test-2', 'ok');
        });
        $this->assertSame(2, DB::table('jobs')->count());

        Livewire::actingAs($verwaltung)->test(MailQueue::class)
            ->assertSee('Jetzt senden (2)')
            ->call('jetztSenden')
            ->assertNotified('Alle 2 wartenden Mails sind abgearbeitet.');

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(['ok', 'ok'], [Cache::get('mail-jetzt-test-1'), Cache::get('mail-jetzt-test-2')]);
        $this->assertTrue(AuditLog::query()->where('action', 'mail.jetzt_senden:2')->exists());
    }

    #[TestDox('Ohne wartende Mails meldet der Klick das')]
    public function test_nichts_wartet(): void
    {
        Filament::setCurrentPanel('admin');
        config(['queue.default' => 'database']);

        Livewire::actingAs($this->nutzer('verwaltung'))->test(MailQueue::class)->call('jetztSenden')->assertNotified('Es warten keine Mails.');
    }

    #[TestDox('Ohne Berechtigung (Autor*in) kein Zugriff')]
    public function test_zugriff(): void
    {
        Filament::setCurrentPanel('admin');
        config(['queue.default' => 'database']);

        Livewire::actingAs($this->nutzer('autor'))->test(MailQueue::class)->assertForbidden();
    }
}
