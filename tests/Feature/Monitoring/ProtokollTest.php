<?php

namespace Tests\Feature\Monitoring;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\AuditText;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Verwaltung → Protokoll: Audit-Log lesen (nur Admins).
 */
class ProtokollTest extends TestCase
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

    #[TestDox('Lesbare Texte für die Aktionen; Unbekanntes bleibt unverändert')]
    public function test_texts(): void
    {
        $this->assertSame('Anmeldung mit Microsoft 365', AuditText::text('login.entra'));
        $this->assertSame('Funktion ausgeschaltet: Download-Seite', AuditText::text('feature.aus:'.Features::DOWNLOADS));
        $this->assertSame('Kontostatus geändert: gesperrt', AuditText::text('user.status:disabled'));
        $this->assertSame('Datenschutz: Daten gelöscht (2 Anmeldungen, 1 Personen, 0 Mails)', AuditText::text('privacy.delete:2+1+0'));
        $this->assertSame('Kontaktformular gesendet (Thema: bolivien)', AuditText::text('kontakt:gesendet:bolivien'));
        $this->assertSame('Newsletter-Anmeldung angefragt', AuditText::text('newsletter.doi:angefragt:abc123'));
        $this->assertStringStartsWith('Recht vergeben: ', AuditText::text('role.granted:verwaltung|global|'));
        $this->assertSame('etwas.neues', AuditText::text('etwas.neues'));
        $this->assertSame('veranstaltungen', AuditText::bereich('registration.manual'));
    }

    #[TestDox('Nur Admins; Liste mit Person, Text, betroffenem Datensatz; Filter nach Bereich und Person; nichts bearbeitbar')]
    public function test_page(): void
    {
        $this->login('verwaltung');
        $this->get(AuditLogResource::getUrl())->assertForbidden();

        $admin = $this->login('admin');
        $event = Event::query()->create(['title' => 'Wölflingstag', 'slug' => 'wt', 'status' => 'open', 'starts_at' => now()->addMonth()]);
        $andere = User::query()->create(['name' => 'Bea Büro', 'email' => 'bea@example.org', 'status' => 'active']);
        AuditLogger::log('event.export', $andere, Event::class, $event->id);
        AuditLogger::log('feature.aus:'.Features::PUSH, $admin);
        AuditLogger::log('registrations.purge:3+1');
        AuditLogger::log('event.member_check', $andere, Event::class, 999999);

        $this->get(AuditLogResource::getUrl())->assertOk()
            ->assertSee('Teilnehmendenliste exportiert')
            ->assertSee('Veranstaltung: Wölflingstag')
            ->assertSee('Funktion ausgeschaltet: Push-Nachrichten (ntfy)')
            ->assertSee('Automatisch gelöscht: 3 unbestätigte und 1 abgelaufene Anmeldungen')
            ->assertSee('System / Website')
            ->assertSee('Veranstaltung #999999 (gelöscht)');

        $export = AuditLog::query()->where('action', 'event.export')->sole();
        $feature = AuditLog::query()->where('action', 'like', 'feature.%')->sole();
        Livewire::test(ListAuditLogs::class)
            ->filterTable('bereich', 'funktionen')
            ->assertCanSeeTableRecords([$feature])->assertCanNotSeeTableRecords([$export]);
        Livewire::test(ListAuditLogs::class)
            ->filterTable('user_id', $andere->id)
            ->assertCanSeeTableRecords([$export])->assertCanNotSeeTableRecords([$feature]);

        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse($admin->can('delete', $export));
        $this->assertFalse($admin->can('update', $export));
    }
}
