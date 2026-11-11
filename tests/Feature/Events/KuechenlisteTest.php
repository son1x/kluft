<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Services\KuechenlistePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Küchenliste: Essen je Tag, Verpflegung und nur Personen mit
 * Besonderheiten, ohne Kontaktdaten.
 */
class KuechenlisteTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::query()->create([
            'title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => now()->addMonth()->startOfDay(), 'ends_at' => now()->addMonth()->addDays(2), 'status' => 'open',
            'form_fields' => ['diet' => 'optional', 'tage' => 'optional', 'phone' => 'optional', 'stamm' => 'optional', 'eigene' => [
                ['schluessel' => 'eigen_allergien', 'label' => 'Allergien und Unverträglichkeiten', 'typ' => 'text', 'sensibel' => true],
                ['schluessel' => 'eigen_shirt', 'label' => 'T-Shirt-Größe', 'typ' => 'text'],
            ]],
        ]);
        $tage = array_keys($this->event->days());
        $personen = [
            ['Mia', 'Muster', ['diet' => 'alles', 'tage' => $tage, 'phone' => '0651 123', 'eigen_shirt' => 'M']],
            ['Ben', 'Beispiel', ['diet' => 'vegan', 'tage' => [$tage[0]], 'stamm' => 'Stamm Sankt Georg']],
            ['Lea', 'Lustig', ['diet' => 'alles', 'eigen_allergien' => 'Erdnüsse']],
        ];
        foreach ($personen as [$vor, $nach, $data]) {
            $this->event->registrations()->create(['status' => 'confirmed', 'confirmed_at' => now(), 'first_name' => $vor, 'last_name' => $nach,
                'email' => strtolower($vor).'@example.org', 'consent_text_version' => 'v1', 'consent_at' => now(), 'data' => $data, 'purge_at' => now()->addYear()]);
        }
        $this->event->registrations()->create(['status' => 'waitlist', 'first_name' => 'Wart', 'last_name' => 'Liste', 'email' => 'w@example.org',
            'consent_text_version' => 'v1', 'consent_at' => now(), 'data' => ['diet' => 'vegan'], 'purge_at' => now()->addYear()]);
    }

    #[TestDox('Vorauswahl: Verpflegung und Allergien, nicht T-Shirt oder Telefon')]
    public function test_felder(): void
    {
        $this->assertSame(['diet', 'eigen_allergien'], KuechenlistePdf::standardFelder($this->event));
        $this->assertArrayNotHasKey('phone', KuechenlistePdf::felder($this->event));
    }

    #[TestDox('Zahlen je Tag und Verpflegung; Besonderheiten nur mit Angabe, Namen gekürzt, ohne Kontaktdaten')]
    public function test_daten(): void
    {
        $daten = app(KuechenlistePdf::class)->daten($this->event, ['diet', 'eigen_allergien'], false);

        $this->assertSame([3, 2, 2], array_values($daten['tage']), 'Lea ohne Tage zählt die ganze Zeit; Warteliste nicht');
        $verpflegung = collect($daten['verteilung']['Verpflegung'])->map(fn (array $n) => array_values($n))->all();
        $this->assertSame(['Esse alles' => [2, 2, 2], 'Vegetarisch' => [0, 0, 0], 'Vegan' => [1, 0, 0]], $verpflegung);
        $this->assertSame(['Ben B.', 'Lea L.'], $daten['besonderheiten']->pluck('name')->all());
        $this->assertSame('Erdnüsse', $daten['besonderheiten'][1]['werte']['eigen_allergien']);

        $html = view('pdf.kuechenliste', ['event' => $this->event, 'fonts' => '', 'logo' => null, ...$daten])->render();
        $this->assertStringContainsString('Erdnüsse', $html);
        $this->assertStringContainsString('Stamm Sankt Georg', $html);
        $this->assertStringNotContainsString('0651', $html);
        $this->assertStringNotContainsString('@example.org', $html);
        $this->assertStringNotContainsString('Muster', $html);

        $this->assertStringStartsWith('%PDF', app(KuechenlistePdf::class)->pdf($this->event, ['diet', 'eigen_allergien'], true));
    }

    #[TestDox('Backend: Aktion lädt das PDF herunter und wird protokolliert; nur mit vollem Zugriff')]
    public function test_aktion(): void
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->mountTableAction('kuechenliste')
            ->assertTableActionDataSet(['felder' => ['diet', 'eigen_allergien'], 'voller_name' => false])
            ->callMountedTableAction()
            ->assertFileDownloaded('Kuechenliste-pfingstlager-'.now()->format('Y-m-d').'.pdf');
        $this->assertSame(1, AuditLog::query()->where('action', 'event.kuechenliste')->count());
    }
}
