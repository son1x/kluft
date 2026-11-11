<?php

namespace Tests\Feature\Users;

use App\Filament\Pages\PrivacyRequestsPage;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Auslagen;
use App\Support\Features;
use App\Support\Praevention;
use App\Support\RoleCatalog;
use App\Support\Zahlungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Rechte-Modell (docs/rollen.md): DL liest Veranstaltungen ihres Bereichs,
 * „Veranstaltungen bearbeiten“ schreibt, Bildungsreferent*innen machen das
 * Tagesgeschäft ohne Kasse, Prävention, Datenschutz und Rollenvergabe.
 */
class RollenModellTest extends TestCase
{
    use RefreshDatabase;

    private Event $woe;

    private Event $rover;

    private EventRegistration $kim;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        config(['dpsg.rechte.leitung_veranstaltungen' => 'lesen']);
        $this->woe = Event::query()->create(['title' => 'Wölflingstag', 'slug' => 'wt', 'stage' => 'woelflinge', 'starts_at' => now()->addMonth(), 'status' => 'open', 'adults_only' => true]);
        $this->rover = Event::query()->create(['title' => 'Roverrunde', 'slug' => 'rr', 'stage' => 'rover', 'starts_at' => now()->addMonth(), 'status' => 'open', 'adults_only' => true]);
        $this->kim = $this->woe->registrations()->create(['status' => 'pending', 'first_name' => 'Kim', 'last_name' => 'Muster', 'email' => 'kim@example.org', 'consent_text_version' => 'v1', 'consent_at' => now()]);
    }

    /** @param  list<array{0: string, 1: string, 2: ?string}>  $rollen */
    private function user(string $email, array $rollen): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        foreach ($rollen as [$role, $type, $key]) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => $type, 'scope_key' => $key]);
        }

        return $user->fresh();
    }

    #[TestDox('DL (Stufenadmin) liest Veranstaltungen und Teilnehmende des eigenen Bereichs, schreibt aber nichts; Texte weiter wie bisher')]
    public function test_dl_liest(): void
    {
        $dl = $this->user('dl@example.org', [['stufenadmin', 'stage', 'woelflinge']]);
        $this->actingAs($dl);

        $this->assertTrue($dl->can('view', $this->woe));
        $this->assertTrue($dl->can('viewParticipants', $this->woe));
        $this->assertFalse($dl->can('manageParticipants', $this->woe));
        $this->assertFalse($dl->can('update', $this->woe));
        $this->assertFalse($dl->can('create', Event::class));
        $this->assertFalse($dl->can('view', $this->rover));
        $this->assertTrue(PageResource::canCreate());

        $this->get(EventResource::getUrl('view', ['record' => $this->woe]))->assertOk()->assertSee('Teilnehmende');
        $this->get(EventResource::getUrl('edit', ['record' => $this->woe]))->assertForbidden();
        $this->get(EventResource::getUrl('create'))->assertForbidden();

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->woe, 'pageClass' => ViewEvent::class])
            ->assertCanSeeTableRecords([$this->kim])
            ->assertTableActionVisible('details', $this->kim)
            ->assertTableActionHidden('confirmManually', $this->kim)
            ->assertTableActionHidden('nachricht')
            ->assertTableActionHidden('export')
            ->assertTableActionHidden('liste')
            ->assertTableActionHidden('addParticipant')
            ->assertTableBulkActionHidden('confirmManuallyBulk');
        $this->assertSame('pending', $this->kim->fresh()->status);
    }

    #[TestDox('„Veranstaltungen bearbeiten – Bereich“ erlaubt Anlegen, Bearbeiten und Teilnehmende verwalten im Bereich')]
    public function test_veranstaltungen_bearbeiten(): void
    {
        $this->assertArrayHasKey('veranstaltungen|stage|woelflinge', RoleCatalog::all());
        $dl = $this->user('dl@example.org', [['stufenadmin', 'stage', 'woelflinge'], ['veranstaltungen', 'stage', 'woelflinge']]);
        $this->actingAs($dl);

        $this->assertTrue($dl->can('create', Event::class));
        $this->assertTrue($dl->can('update', $this->woe));
        $this->assertTrue($dl->can('manageParticipants', $this->woe));
        $this->assertFalse($dl->can('update', $this->rover));
        $this->get(EventResource::getUrl('edit', ['record' => $this->woe]))->assertOk();
        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->woe, 'pageClass' => EditEvent::class])
            ->assertTableActionVisible('confirmManually', $this->kim);
    }

    #[TestDox('Andere Websites (Standard „bearbeiten“): Stufenadmins bearbeiten Veranstaltungen wie bisher; die Extra-Rolle gibt es dort nicht')]
    public function test_standard_bearbeiten(): void
    {
        config(['dpsg.rechte.leitung_veranstaltungen' => 'bearbeiten']);
        $dl = $this->user('dl@example.org', [['stufenadmin', 'stage', 'woelflinge']]);

        $this->assertTrue($dl->can('create', Event::class));
        $this->assertTrue($dl->can('manageParticipants', $this->woe));
        $this->assertArrayNotHasKey('veranstaltungen|stage|woelflinge', RoleCatalog::all());
    }

    #[TestDox('Bildungsreferent*in: alle Seiten, Beiträge, Veranstaltungen samt Teilnehmenden; Autor:innen freischalten – keine Kasse, Prävention, Datenschutz, andere Rollen')]
    public function test_bildungsreferentin(): void
    {
        if (config('dpsg.site.level') === 'Stamm') {
            $this->markTestSkipped('Bildungsreferent*innen gibt es nur bei DV und Bezirk.');
        }
        Features::setzen(Features::ZAHLUNGEN, true);
        Features::setzen(Features::AUSLAGEN, true);
        Features::setzen(Features::PRAEVENTION, true);
        $br = $this->user('br@example.org', [['bildungsreferent', 'global', null]]);
        $this->actingAs($br);

        $this->assertArrayHasKey('bildungsreferent|global|', RoleCatalog::all());
        $this->assertTrue(RoleCatalog::isPrivileged('bildungsreferent|global|'));
        $this->assertTrue($br->can('create', Event::class));
        $this->assertTrue($br->can('manageParticipants', $this->rover));
        $this->assertTrue(PageResource::canCreate());
        $this->get(PostResource::getUrl())->assertOk();
        $this->get(EventResource::getUrl('edit', ['record' => $this->woe]))->assertOk();

        $this->assertFalse(Zahlungen::zugriff($br));
        $this->assertFalse(Auslagen::kasse($br));
        $this->assertFalse(Praevention::zugriff($br));
        $this->get(PrivacyRequestsPage::getUrl())->assertForbidden();

        $this->get(UserResource::getUrl())->assertOk();
        $vergebbar = array_keys(RoleCatalog::grantableBy($br));
        $this->assertContains('autor|own|', $vergebbar);
        $this->assertContains('autor|own|woelflinge', $vergebbar);
        $this->assertNotContains('stufenadmin|stage|woelflinge', $vergebbar);
        $this->assertNotContains('verwaltung|global|', $vergebbar);
        $this->assertNotContains('bildungsreferent|global|', $vergebbar);
    }

    #[TestDox('Verwaltung bleibt vollständig: Kasse im DV, Prävention, Datenschutz, alle Rollen außer Admin')]
    public function test_verwaltung(): void
    {
        Features::setzen(Features::ZAHLUNGEN, true);
        Features::setzen(Features::PRAEVENTION, true);
        $v = $this->user('buero@example.org', [['verwaltung', 'global', null]]);
        $this->actingAs($v);

        $this->assertTrue($v->isRedaktion());
        $this->assertTrue(Praevention::zugriff($v));
        $this->get(PrivacyRequestsPage::getUrl())->assertOk();
        $this->assertContains('stufenadmin|stage|woelflinge', array_keys(RoleCatalog::grantableBy($v)));
        if (config('dpsg.site.level') !== 'Stamm') {
            $this->assertTrue(Zahlungen::zugriff($v));
            $this->assertContains('bildungsreferent|global|', array_keys(RoleCatalog::grantableBy($v)));
        }
    }
}
