<?php

namespace Tests\Feature\Praevention;

use App\Filament\Resources\Praevention\Pages\CreatePraeventionNachweis;
use App\Filament\Resources\Praevention\Pages\ListPraeventionNachweise;
use App\Filament\Resources\Praevention\PraeventionResource;
use App\Mail\PraeventionErinnerungMail;
use App\Models\Person;
use App\Models\PraeventionNachweis;
use App\Models\User;
use App\Services\PrivacyRequests;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Präventions-Nachweise der Leitenden (docs/praevention.md).
 */
class PraeventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::PRAEVENTION, true);
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, string $role): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $role === 'stufenadmin' ? 'stage' : 'global', 'scope_key' => $role === 'stufenadmin' ? 'biber' : null]);

        return $user->fresh();
    }

    /** @param  array<string, mixed>  $attrs */
    private function nachweis(array $attrs): PraeventionNachweis
    {
        return PraeventionNachweis::query()->create(['name' => 'Kim', 'email' => 'kim@example.org', 'art' => 'efz', ...$attrs]);
    }

    #[TestDox('Zugriff nur für Vorstand und Verwaltung, nur mit eingeschalteter Funktion')]
    public function test_zugriff(): void
    {
        $this->actingAs($this->user('vorstand@example.org', 'vorstand'))->get(PraeventionResource::getUrl())->assertOk();
        $this->actingAs($this->user('buero@example.org', 'verwaltung'))->get(PraeventionResource::getUrl())->assertOk();
        $this->actingAs($this->user('biber@example.org', 'stufenadmin'))->get(PraeventionResource::getUrl())->assertForbidden();

        Features::setzen(Features::PRAEVENTION, false);
        $this->actingAs(User::query()->where('email', 'vorstand@example.org')->sole())->get(PraeventionResource::getUrl())->assertForbidden();
    }

    #[TestDox('Eintragen: Person übernehmen, „gültig bis“ wird mit 5 Jahren vorbelegt; Liste mit Ampel')]
    public function test_eintragen(): void
    {
        $this->travelTo('2026-11-10 10:00');
        $vorstand = $this->user('vorstand@example.org', 'vorstand');
        $person = Person::query()->create(['gremium' => 'dl', 'name' => 'Lea Leitung', 'email' => 'Lea@Example.org', 'active' => true]);
        $this->actingAs($vorstand);

        Livewire::test(CreatePraeventionNachweis::class)
            ->fillForm(['person_id' => $person->id])
            ->assertFormSet(['name' => 'Lea Leitung', 'email' => 'Lea@Example.org'])
            ->fillForm(['art' => 'efz', 'datum' => '2026-11-01'])
            ->assertFormSet(['gueltig_bis' => '2031-11-01'])
            ->call('create')->assertHasNoFormErrors();
        $n = PraeventionNachweis::query()->sole();
        $this->assertSame(['lea@example.org', $vorstand->id, '2031-11-01'], [$n->email, $n->erfasst_von, $n->gueltig_bis->toDateString()]);

        $this->nachweis(['art' => 'schulung', 'bezeichnung' => 'Basisschulung', 'gueltig_bis' => '2027-01-15']);
        $this->nachweis(['name' => 'Ben', 'art' => 'juleica', 'gueltig_bis' => '2026-10-01']);
        Livewire::test(ListPraeventionNachweise::class)
            ->assertSee('Noch 66 Tage')->assertSee('Abgelaufen')->assertSee('Gültig')->assertSee('Basisschulung')
            ->filterTable('status', 'handeln')->assertCountTableRecords(2);
        $this->assertSame('2', PraeventionResource::getNavigationBadge());
    }

    #[TestDox('Erinnerungen 90 und 30 Tage vorher an die Person und als Übersicht an den Vorstand, je Stufe einmal; ein Jahr nach Ablauf gelöscht')]
    public function test_erinnerungen(): void
    {
        Mail::fake();
        $this->travelTo('2026-11-10 08:30');
        $vorstand = $this->user('vorstand@example.org', 'vorstand');
        $kim = $this->nachweis(['gueltig_bis' => '2027-02-01']); // 83 Tage
        $ohne = $this->nachweis(['name' => 'Tom', 'email' => null, 'art' => 'schulung', 'gueltig_bis' => '2026-12-01']); // 21 Tage
        $this->nachweis(['name' => 'Spät', 'gueltig_bis' => '2027-06-01']);
        $alt = $this->nachweis(['name' => 'Alt', 'gueltig_bis' => '2025-11-01']);

        $this->artisan('praevention:erinnerungen')->expectsOutput('2 Erinnerungen, 1 gelöscht.')->assertSuccessful();
        Mail::assertQueued(PraeventionErinnerungMail::class, fn ($m) => $m->hasTo('kim@example.org') && ! $m->uebersicht);
        Mail::assertQueued(PraeventionErinnerungMail::class, fn ($m) => $m->hasTo($vorstand->email) && $m->uebersicht && count($m->eintraege) === 2);
        Mail::assertQueued(PraeventionErinnerungMail::class, 2);
        $this->assertSame([90, 30], [$kim->fresh()->erinnert_stufe, $ohne->fresh()->erinnert_stufe]);
        $this->assertNull($alt->fresh());

        // Am nächsten Tag nichts Neues; bei 30 Tagen die zweite Erinnerung.
        $this->travelTo('2026-11-11 08:30');
        $this->artisan('praevention:erinnerungen')->expectsOutput('0 Erinnerungen, 0 gelöscht.');
        $this->travelTo('2027-01-02 08:30');
        $this->artisan('praevention:erinnerungen')->expectsOutput('1 Erinnerungen, 0 gelöscht.');

        // Neues Datum: Erinnerungen von vorn.
        $kim->refresh()->update(['gueltig_bis' => '2032-01-01']);
        $this->assertNull($kim->erinnert_stufe);

        $html = (new PraeventionErinnerungMail([['name' => 'Kim', 'art' => 'Führungszeugnis', 'bezeichnung' => null, 'bis' => '01.02.2027', 'tage' => 30]]))->render();
        $this->assertStringContainsString('dein erweitertes Führungszeugnis', $html);
        $this->assertStringContainsString('01.02.2027', $html);
    }

    #[TestDox('Datenschutzauskunft und Löschung erfassen die Nachweise zur Adresse')]
    public function test_datenschutz(): void
    {
        $this->nachweis(['gueltig_bis' => '2030-01-01']);
        $privacy = app(PrivacyRequests::class);
        $this->assertCount(1, $privacy->find('KIM@example.org')['praevention']);
        $this->assertSame(1, $privacy->delete('kim@example.org')['praevention']);
        $this->assertSame(0, PraeventionNachweis::query()->count());
    }
}
