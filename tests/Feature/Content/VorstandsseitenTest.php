<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Pflichtenheft 5.4: Der Vorstand pflegt die Vorstandsseiten, also die
 * Seite des Gremiums „leitung“ (config dpsg.gremien.leitung.page) und ihre
 * Unterseiten. Andere Seiten, Anlegen und Löschen bleiben bei der Verwaltung.
 */
class VorstandsseitenTest extends TestCase
{
    use RefreshDatabase;

    private Page $vorstandsseite;

    private Page $unterseite;

    private Page $andere;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Page::query()->delete();
        $slug = (string) config('dpsg.gremien.leitung.page');
        $seite = fn (array $a) => Page::query()->create(['body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()->subDay(), ...$a]);
        $this->vorstandsseite = $seite(['slug' => $slug, 'title' => 'Vorstand']);
        $this->unterseite = $seite(['slug' => 'vorstand-termine', 'title' => 'Termine des Vorstands', 'parent_id' => $this->vorstandsseite->id]);
        $this->andere = $seite(['slug' => 'impressum', 'title' => 'Impressum']);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => "{$role}@example.org", 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Vorstand darf Vorstandsseite und Unterseiten bearbeiten, andere Seiten nicht; Anlegen und Löschen nicht')]
    public function test_rechte(): void
    {
        $vorstand = $this->user('vorstand');

        $this->assertSame([$this->vorstandsseite->id, $this->unterseite->id], Page::vorstandsseitenIds());
        $this->assertTrue($vorstand->can('update', $this->vorstandsseite));
        $this->assertTrue($vorstand->can('update', $this->unterseite));
        $this->assertFalse($vorstand->can('update', $this->andere));
        $this->assertFalse($vorstand->can('delete', $this->vorstandsseite));
        $this->assertFalse($vorstand->can('create', Page::class));

        // Autor:innen bleiben außen vor.
        $this->assertFalse($this->user('autor')->can('update', $this->vorstandsseite));
    }

    #[TestDox('Vorstand sieht in der Seitenliste nur die Vorstandsseiten und kann sie veröffentlicht speichern; Slug und übergeordnete Seite bleiben fest')]
    public function test_bearbeiten(): void
    {
        $this->actingAs($this->user('vorstand'));

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$this->vorstandsseite, $this->unterseite])
            ->assertCanNotSeeTableRecords([$this->andere]);

        Livewire::test(EditPage::class, ['record' => $this->vorstandsseite->getRouteKey()])
            ->assertFormFieldIsDisabled('slug')
            ->assertFormFieldIsDisabled('parent_id')
            ->fillForm(['title' => 'Unser Vorstand', 'body' => '<p>Neu</p>', 'status' => 'published', 'slug' => 'anders'])
            ->call('save')
            ->assertHasNoFormErrors();

        $seite = $this->vorstandsseite->fresh();
        $this->assertSame(['Unser Vorstand', 'published', (string) config('dpsg.gremien.leitung.page')], [$seite->title, $seite->status, $seite->slug]);

        // Fremde Seiten sind für den Vorstand nicht auffindbar.
        $this->get(EditPage::getUrl(['record' => $this->andere]))->assertNotFound();
    }
}
