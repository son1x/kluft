<?php

namespace Tests\Feature\Content;

use App\Filament\Widgets\ErsteSchritte;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Dashboard „Erste Schritte“: erklärt Autor:innen den Weg zum
 * veröffentlichten Beitrag, Freigebenden zusätzlich die wartenden Beiträge.
 */
class ErsteSchritteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, string $role, string $scopeType = 'global', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    private function beitrag(array $attrs): Post
    {
        return Post::query()->create(['title' => 'Lager', 'slug' => 'lager-'.uniqid(), 'teaser' => 'x', 'body' => '<p>x</p>', 'scope' => 'general', 'status' => 'draft', ...$attrs]);
    }

    #[TestDox('Autor:in: Weg über „Zur Prüfung“, eigene Entwürfe, keine Freigabe-Liste; auf dem Dashboard zu sehen')]
    public function test_autorin(): void
    {
        $autorin = $this->user('autorin@example.org', 'autor', 'own');
        $this->beitrag(['author_id' => $autorin->id]);
        $this->beitrag(['status' => 'review']);
        $this->actingAs($autorin);

        Livewire::test(ErsteSchritte::class)
            ->assertSee('So kommt dein Beitrag online')
            ->assertSee('Zur Prüfung geben')
            ->assertSee('1 Entwurf von dir')
            ->assertSee('Fotos: was gilt')
            ->assertDontSee('auf deine Freigabe');

        // Auf dem Dashboard registriert (Widgets laden dort nachträglich).
        $this->get('/admin')->assertOk();
        $this->assertContains(ErsteSchritte::class, Filament::getCurrentPanel()->getWidgets());
    }

    #[TestDox('Wer freigeben darf, sieht wartende Beiträge seines Bereichs und den eigenen Bereich')]
    public function test_freigebende(): void
    {
        // Stufenadmins bearbeiten Veranstaltungen (Standard; im DV lesen sie nur, s. RollenModellTest).
        config(['dpsg.rechte.leitung_veranstaltungen' => 'bearbeiten']);
        [$stufe, $andere] = array_map('strval', array_slice(array_keys((array) config('dpsg.stages')), 0, 2));
        $this->beitrag(['status' => 'review', 'scope' => 'stage', 'stage' => $stufe]);
        $this->beitrag(['status' => 'review', 'scope' => 'stage', 'stage' => $andere]);
        $this->actingAs($this->user('stufe@example.org', 'stufenadmin', 'stage', $stufe));

        Livewire::test(ErsteSchritte::class)
            ->assertSee('1 Beitrag wartet auf deine Freigabe.')
            ->assertSee('Du darfst Beiträge selbst freigeben.')
            ->assertSee('Seiten pflegen')
            ->assertSee('Veranstaltung anlegen')
            ->assertSee('Autor:innen freischalten');
    }

    #[TestDox('Ohne Rolle kein Kasten')]
    public function test_ohne_rolle(): void
    {
        $this->actingAs(User::query()->create(['name' => 'Neu', 'email' => 'neu@example.org', 'status' => 'active']));

        $this->assertFalse(ErsteSchritte::canView());
    }
}
