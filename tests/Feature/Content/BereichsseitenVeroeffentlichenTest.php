<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Stufen- und Fachbereichsleitungen veröffentlichen die Seiten ihres
 * Bereichs selbst (Entscheidung 09.11.2026, wie bei Beiträgen). Vorher
 * fehlte ihnen der Status „Veröffentlicht“, und eine veröffentlichte Seite
 * ließ sich nicht speichern.
 */
class BereichsseitenVeroeffentlichenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function stufenadmin(string $stufe): User
    {
        $user = User::query()->create(['name' => 'Stufe', 'email' => 'stufe@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => $stufe]);

        return $user->fresh();
    }

    #[TestDox('Stufenleitung speichert ihre veröffentlichte Seite, ändert sie sofort und legt eine neue veröffentlicht an')]
    public function test_stufenleitung_veroeffentlicht(): void
    {
        $stufe = (string) array_key_first(config('dpsg.stages'));
        $seite = Page::query()->create(['slug' => 'stufen-info', 'title' => 'Info', 'body' => '<p>alt</p>', 'stage' => $stufe, 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->actingAs($this->stufenadmin($stufe));

        Livewire::test(EditPage::class, ['record' => $seite->getRouteKey()])
            ->fillForm(['body' => '<p>neu</p>'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['published', '<p>neu</p>'], [$seite->fresh()->status, $seite->fresh()->body]);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Neue Seite', 'slug' => 'neue-stufen-seite', 'stage' => $stufe, 'body' => '<p>x</p>', 'status' => 'published'])
            ->call('create')
            ->assertHasNoFormErrors();
        $neu = Page::query()->where('slug', 'neue-stufen-seite')->sole();
        $this->assertSame('published', $neu->status);
        $this->assertNotNull($neu->published_at);
    }

    #[TestDox('Fremde Seiten bleiben für die Stufenleitung unerreichbar')]
    public function test_fremde_seite(): void
    {
        $stufen = array_keys(config('dpsg.stages'));
        $fremd = Page::query()->create(['slug' => 'fremde-seite', 'title' => 'Fremd', 'body' => '<p>x</p>', 'stage' => $stufen[1], 'status' => 'published', 'published_at' => now()->subDay()]);
        $user = $this->stufenadmin($stufen[0]);

        $this->assertFalse($user->can('update', $fremd));
        $this->actingAs($user)->get(EditPage::getUrl(['record' => $fremd]))->assertNotFound();
    }
}
