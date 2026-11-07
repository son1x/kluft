<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Mediathek (Pflichtenheft 5.4 „Medien hochladen“): Autor, Stufen- und
 * Sektionsadmin ändern und löschen nur eigene Uploads, Vorstand und
 * Verwaltung alle.
 */
class MediaPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
    }

    private function user(string $role, string $scopeType = 'own', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => $role.'@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    private function media(?User $von, string $name = 'datei'): Media
    {
        // PDF statt Bild: keine WebP-Varianten nötig.
        Storage::disk('public')->put("media/{$name}.pdf", '%PDF-1.4');

        return Media::query()->create([
            'path' => "media/{$name}.pdf", 'mime' => 'application/pdf', 'size' => 8,
            'alt_text' => 'Test', 'credit' => 'DPSG', 'uploaded_by' => $von?->id,
        ]);
    }

    #[TestDox('Autor*innen sehen und laden hoch, ändern und löschen aber nur eigene Medien')]
    public function test_autor_nur_eigene_medien(): void
    {
        $autor = $this->user('autor');
        $andere = $this->user('stufenadmin', 'stage', 'woelflinge');
        $eigene = $this->media($autor, 'eigene');
        $fremde = $this->media($andere, 'fremde');
        $importiert = $this->media(null, 'import');

        $this->assertTrue($autor->can('viewAny', Media::class));
        $this->assertTrue($autor->can('create', Media::class));
        $this->assertTrue($autor->can('update', $eigene));
        $this->assertTrue($autor->can('delete', $eigene));
        $this->assertFalse($autor->can('update', $fremde));
        $this->assertFalse($autor->can('delete', $fremde));
        $this->assertFalse($autor->can('delete', $importiert));
        $this->assertFalse($autor->can('deleteAny', Media::class));

        $this->actingAs($autor);
        $this->get('/admin/media')->assertOk();
        $this->get("/admin/media/{$eigene->id}/edit")->assertOk();
        $this->get("/admin/media/{$fremde->id}/edit")->assertForbidden();

        Livewire::test(ListMedia::class)
            ->assertTableActionVisible('edit', $eigene)
            ->assertTableActionHidden('edit', $fremde)
            ->assertTableBulkActionHidden('delete');
    }

    #[TestDox('Stufen- und Sektionsadmins ändern ebenfalls nur eigene Medien')]
    public function test_stufenadmin_nur_eigene_medien(): void
    {
        $stufenadmin = $this->user('stufenadmin', 'stage', 'woelflinge');
        $sektionsadmin = $this->user('sektionsadmin', 'section', 'bolivien');
        $autor = $this->user('autor');
        $vomAutor = $this->media($autor);

        $this->assertFalse($stufenadmin->can('delete', $vomAutor));
        $this->assertFalse($sektionsadmin->can('update', $vomAutor));
        $this->assertTrue($stufenadmin->can('delete', $this->media($stufenadmin, 'eigene')));
    }

    #[TestDox('Vorstand, Verwaltung und Admins ändern und löschen alle Medien')]
    public function test_volle_rollen_alle_medien(): void
    {
        $fremde = $this->media($this->user('autor'));
        $importiert = $this->media(null, 'import');

        foreach (['vorstand', 'verwaltung', 'admin'] as $rolle) {
            $user = $this->user($rolle, 'global');
            $this->assertTrue($user->can('update', $fremde), $rolle);
            $this->assertTrue($user->can('delete', $importiert), $rolle);
            $this->assertTrue($user->can('deleteAny', Media::class), $rolle);
        }

        $this->actingAs($this->user('superadmin', 'global'));
        Livewire::test(ListMedia::class)->assertTableBulkActionVisible('delete');
    }
}
