<?php

namespace Tests\Feature\Instanzen;

use App\Filament\Pages\Stufen;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\StageAge;
use App\Models\User;
use App\Support\StufenAlter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Verwaltung → Stufen: Altersangaben im Rahmen des DPSG-Standards anpassen.
 */
class StufenAlterTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): User
    {
        $user = User::query()->create(['name' => 'Test '.$role, 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    #[TestDox('Nur Admins; Wölflinge 7 bis 10 statt 6 bis 10 erscheint auf der Website; Rückkehr zum Standard entfernt den Eintrag')]
    public function test_alter_anpassen(): void
    {
        $standard = StufenAlter::standard('woelflinge');
        $this->login('verwaltung');
        $this->get(Stufen::getUrl())->assertForbidden();

        $admin = $this->login('admin');
        $this->get(Stufen::getUrl())->assertOk()->assertSee('DPSG-Standard: '.StufenAlter::text($standard['from'], $standard['to']));

        $neu = $standard['from'] + 1;
        Livewire::test(Stufen::class)->set('data.alter.woelflinge.from', $neu)->call('save')->assertNotified();

        $this->assertSame($neu, config('dpsg.stages.woelflinge.from'));
        $this->assertSame(StufenAlter::text($neu, $standard['to']), config('dpsg.stages.woelflinge.age'));
        $this->assertSame($admin->id, StageAge::query()->find('woelflinge')?->updated_by);
        $this->assertTrue(AuditLog::query()->where('action', 'stufe.alter:woelflinge')->exists());

        Page::query()->create(['title' => 'Wölflinge', 'slug' => 'stufe-woelflinge', 'stage' => 'woelflinge', 'body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->get('/stufe-woelflinge')->assertOk()->assertSee(StufenAlter::text($neu, $standard['to']));

        Livewire::test(Stufen::class)->assertSet('data.alter.woelflinge.from', $neu)
            ->set('data.alter.woelflinge.from', $standard['from'])->call('save');
        $this->assertNull(StageAge::query()->find('woelflinge'));
        $this->assertSame($standard['from'], config('dpsg.stages.woelflinge.from'));
    }

    #[TestDox('Nur im Rahmen: außerhalb wird abgelehnt, ungültige Einträge in der Datenbank werden ignoriert')]
    public function test_rahmen(): void
    {
        $standard = StufenAlter::standard('rover');

        $this->assertTrue(StufenAlter::gueltig('rover', $standard['from'] - 1, $standard['to'] + 1));
        $this->assertFalse(StufenAlter::gueltig('rover', $standard['from'] - 2, $standard['to']));
        $this->assertFalse(StufenAlter::gueltig('rover', $standard['from'], $standard['to'] + 2));

        try {
            StufenAlter::setzen('rover', $standard['from'] - 3, $standard['to']);
            $this->fail('Außerhalb des Rahmens muss abgelehnt werden.');
        } catch (InvalidArgumentException) {
        }
        $this->assertNull(StageAge::query()->find('rover'));

        StageAge::query()->create(['stage' => 'rover', 'from' => $standard['from'] - 3, 'to' => $standard['to']]);
        Cache::flush();
        StufenAlter::anwenden();
        $this->assertSame($standard['from'], config('dpsg.stages.rover.from'));
    }
}
