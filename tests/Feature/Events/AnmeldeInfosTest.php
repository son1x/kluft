<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Mail\AnmeldungInfoMail;
use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventRegistrations;
use App\Support\AnmeldeInfos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Info-Mails zu Anmeldungen an Sammeladresse und verantwortliche Person.
 */
class AnmeldeInfosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['dpsg.contact.email' => 'info@example.org']);
    }

    private function user(string $email, string $role = 'admin'): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $user->fresh();
    }

    /** @param  array<string, mixed>  $attrs */
    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'starts_at' => now()->addMonth(), 'status' => 'open',
            'adults_only' => true, 'form_fields' => ['stamm' => 'optional'], ...$attrs,
        ]);
    }

    private function anmeldenUndBestaetigen(Event $event, string $email = 'kim@example.org'): EventRegistration
    {
        $this->post("/veranstaltungen/{$event->slug}/anmeldung", ['first_name' => 'Kim', 'last_name' => 'Leitung', 'email' => $email, 'stamm' => 'Stamm Sankt Georg', 'consent' => '1'])
            ->assertSessionHasNoErrors();
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $m) use (&$token, $email) {
            if ($m->kind === 'doi' && $m->hasTo($email)) {
                $token = $m->token;
            }

            return true;
        });
        app(EventRegistrations::class)->confirm((string) $token);

        return EventRegistration::query()->where('email', $email)->sole();
    }

    #[TestDox('Standard: Sammeladresse und wer die Veranstaltung angelegt hat bekommen eine Info nach der Bestätigung, nicht schon bei der Anmeldung')]
    public function test_standard(): void
    {
        $ersteller = $this->user('ref@example.org');
        $event = $this->event(['created_by' => $ersteller->id]);
        $this->assertTrue($event->fresh()->info_sammeladresse);

        $this->post('/veranstaltungen/woelflingstag/anmeldung', ['first_name' => 'Kim', 'last_name' => 'L', 'email' => 'x@example.org', 'consent' => '1']);
        Mail::assertNotQueued(AnmeldungInfoMail::class);

        $r = $this->anmeldenUndBestaetigen($event);
        Mail::assertQueued(AnmeldungInfoMail::class, fn (AnmeldungInfoMail $m) => $m->hasTo('info@example.org') && $m->art === 'confirmed');
        Mail::assertQueued(AnmeldungInfoMail::class, fn (AnmeldungInfoMail $m) => $m->hasTo('ref@example.org'));
        Mail::assertQueued(AnmeldungInfoMail::class, 2);

        $html = (new AnmeldungInfoMail($r, 'confirmed'))->render();
        $this->assertStringContainsString('Kim Leitung', $html);
        $this->assertStringContainsString('Stamm Sankt Georg', $html);
        $this->assertStringContainsString('1 bestätigte Anmeldungen', $html);

        app(EventRegistrations::class)->cancel($r);
        Mail::assertQueued(AnmeldungInfoMail::class, fn (AnmeldungInfoMail $m) => $m->art === 'cancelled' && $m->hasTo('info@example.org'));
    }

    #[TestDox('Schalter aus: keine Info; eigene verantwortliche Adresse statt Ersteller*in; gleiche Adressen nur einmal')]
    public function test_schalter(): void
    {
        $ersteller = $this->user('ref@example.org');
        $this->anmeldenUndBestaetigen($this->event(['created_by' => $ersteller->id, 'info_sammeladresse' => false, 'info_verantwortlich' => false]));
        Mail::assertNotQueued(AnmeldungInfoMail::class);

        $event = $this->event(['slug' => 'zwei', 'created_by' => $ersteller->id, 'verantwortlich_email' => 'Lisa@Example.org']);
        $this->assertSame(['info@example.org', 'lisa@example.org'], AnmeldeInfos::empfaenger($event));
        $event->update(['verantwortlich_email' => 'info@example.org']);
        $this->assertSame(['info@example.org'], AnmeldeInfos::empfaenger($event->fresh()));
    }

    #[TestDox('Backend: Sammeladresse nur für die Verwaltung einstellbar; neue Veranstaltung mit der eigenen Adresse als verantwortlich')]
    public function test_backend(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('admin@example.org'));

        Livewire::test(ListEvents::class)
            ->callAction('sammeladresse', ['adresse' => 'Anmeldungen@Example.org'])
            ->assertNotified('Gespeichert');
        $this->assertSame('anmeldungen@example.org', AnmeldeInfos::sammeladresse());
        Livewire::test(ListEvents::class)->callAction('sammeladresse', ['adresse' => '']);
        $this->assertSame('info@example.org', AnmeldeInfos::sammeladresse());

        Livewire::test(CreateEvent::class)
            ->assertFormSet(['info_sammeladresse' => true, 'info_verantwortlich' => true, 'verantwortlich_email' => 'admin@example.org'])
            ->fillForm(['title' => 'Neu', 'slug' => 'neu', 'starts_at' => now()->addMonth(), 'status' => 'open', 'retention_days' => 60])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('admin@example.org', Event::query()->sole()->verantwortlich_email);

        $this->actingAs($this->user('autor@example.org', 'stufenadmin'));
        Livewire::test(ListEvents::class)->assertActionHidden('sammeladresse');
    }

    #[TestDox('DV Trier: Weihnachtspostkartenaktion verantwortet Lisa Alm (Dienstadresse aus Leitung & Büro)')]
    public function test_weihnachtsaktion(): void
    {
        config(['dpsg.person_email_domain' => 'dpsg-trier.de', 'dpsg.person_email_schema' => null]);
        $event = Event::query()->where('slug', 'weihnachtspostkartenaktion-2026')->first()
            ?? $this->event(['slug' => 'weihnachtspostkartenaktion-2026']);
        $event->update(['verantwortlich_email' => null]);

        (require base_path('instanzen/dv-trier/migrations/2026_11_10_090100_weihnachtspostkartenaktion_verantwortlich.php'))->up();

        $event->refresh();
        $this->assertSame('l.alm@dpsg-trier.de', $event->verantwortlich_email);
        $this->assertSame(['info@example.org', 'l.alm@dpsg-trier.de'], AnmeldeInfos::empfaenger($event));
    }
}
