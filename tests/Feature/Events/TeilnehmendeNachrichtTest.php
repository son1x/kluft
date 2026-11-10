<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\TeilnehmendeNachrichtMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\TeilnehmendeNachrichten;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Nachricht an die Teilnehmenden und Erinnerung vor der Veranstaltung
 * (docs/plan-ehrenamt.md Nr. 1).
 */
class TeilnehmendeNachrichtTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->event = Event::query()->create(['title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'starts_at' => now()->addDays(10)->setTime(10, 0),
            'status' => 'open', 'adults_only' => true, 'contact_name' => 'Lisa', 'contact_email' => 'lisa@example.org']);
    }

    private function anmeldung(string $vorname, string $status, ?string $email = null): EventRegistration
    {
        return $this->event->registrations()->create([
            'status' => $status, 'first_name' => $vorname, 'last_name' => 'Muster', 'email' => $email ?? mb_strtolower($vorname).'@example.org',
            'consent_text_version' => 'v1', 'consent_at' => now(),
        ]);
    }

    #[TestDox('Rundmail aus dem Reiter: je Person eine Mail an die gewählten Status, Antwort an die Kontaktadresse, Protokoll; Doppelte und ohne Adresse fallen weg')]
    public function test_rundmail(): void
    {
        $this->anmeldung('Kim', 'confirmed');
        $this->anmeldung('Ben', 'waitlist');
        $this->anmeldung('Tom', 'cancelled');
        $this->anmeldung('Ohne', 'confirmed', '');
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->callTableAction('nachricht', data: ['an' => ['confirmed', 'waitlist'], 'betreff' => 'Treffpunkt geändert', 'text' => "Wir treffen uns am Bahnhof.\n<script>x</script>"])
            ->assertNotified('Nachricht an 2 Personen unterwegs.');

        Mail::assertQueued(TeilnehmendeNachrichtMail::class, 2);
        Mail::assertQueued(TeilnehmendeNachrichtMail::class, fn (TeilnehmendeNachrichtMail $m) => $m->hasTo('kim@example.org') && $m->hasReplyTo('lisa@example.org') && $m->betreff === 'Treffpunkt geändert');
        Mail::assertNotQueued(TeilnehmendeNachrichtMail::class, fn (TeilnehmendeNachrichtMail $m) => $m->hasTo('tom@example.org'));

        $html = (new TeilnehmendeNachrichtMail(EventRegistration::query()->where('first_name', 'Kim')->sole(), 'Treffpunkt geändert', "Wir treffen uns am Bahnhof.\n<script>x</script>"))->render();
        $this->assertStringContainsString('Hallo Kim', $html);
        $this->assertStringContainsString('Wir treffen uns am Bahnhof.<br', $html);
        $this->assertStringNotContainsString('<script>x', $html);
        $this->assertStringContainsString(route('events.show', 'woelflingstag'), $html);

        $nachricht = $this->event->nachrichten()->sole();
        $this->assertSame([2, ['confirmed', 'waitlist'], $user->id], [$nachricht->empfaenger, $nachricht->an, $nachricht->user_id]);
    }

    #[TestDox('Erinnerung: genau einmal, an die Bestätigten, wenn der Beginn innerhalb der Vorlaufzeit liegt; neues Datum setzt sie zurück')]
    public function test_erinnerung(): void
    {
        $this->anmeldung('Kim', 'confirmed');
        $this->anmeldung('Ben', 'waitlist');
        $this->event->update(['erinnerung_tage' => 3]);

        $this->artisan('veranstaltungen:erinnerungen')->expectsOutputToContain('0 Veranstaltung(en)')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->travelTo($this->event->starts_at->copy()->subDays(3)->setTime(9, 0));
        $this->artisan('veranstaltungen:erinnerungen')->expectsOutputToContain('1 Veranstaltung(en)')->assertSuccessful();
        Mail::assertQueued(TeilnehmendeNachrichtMail::class, 1);
        Mail::assertQueued(TeilnehmendeNachrichtMail::class, fn (TeilnehmendeNachrichtMail $m) => $m->hasTo('kim@example.org') && str_starts_with($m->betreff, 'Erinnerung: Wölflingstag') && $m->text === TeilnehmendeNachrichten::ERINNERUNG_STANDARD);
        $this->assertNotNull($this->event->fresh()->erinnerung_gesendet_am);
        $this->assertSame('erinnerung', $this->event->nachrichten()->sole()->art);

        $this->artisan('veranstaltungen:erinnerungen')->expectsOutputToContain('0 Veranstaltung(en)');

        $this->event->fresh()->update(['starts_at' => now()->addDays(20)]);
        $this->assertNull($this->event->fresh()->erinnerung_gesendet_am);
    }
}
