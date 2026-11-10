<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\FotosRelationManager;
use App\Models\Event;
use App\Models\EventFoto;
use App\Models\Media;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Fotos zur Veranstaltung mit Vier-Augen-Freigabe.
 */
class EventFotosTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $this->event = Event::query()->create(['title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => now()->subWeek(), 'status' => 'open', 'adults_only' => true]);
    }

    private function user(string $email, string $role = 'verwaltung'): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $role === 'autor' ? 'own' : 'global']);

        return $user->fresh();
    }

    private function rm(): Testable
    {
        return Livewire::test(FotosRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    #[TestDox('Hochladen mit Einwilligung: privat gespeichert, nicht öffentlich, Vorschau nur für Berechtigte')]
    public function test_hochladen(): void
    {
        $kim = $this->user('kim@example.org');
        $this->actingAs($kim);

        $this->rm()->callTableAction('hochladen', data: ['dateien' => [UploadedFile::fake()->image('a.jpg', 800, 600)], 'credit' => 'Kim'])
            ->assertHasTableActionErrors(['einwilligung']);
        $this->rm()->callTableAction('hochladen', data: ['dateien' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.jpg', 800, 600)], 'credit' => 'Kim', 'einwilligung' => true])
            ->assertHasNoTableActionErrors();

        $fotos = EventFoto::query()->orderBy('sort')->get();
        $this->assertSame(['eingereicht', 'eingereicht'], $fotos->pluck('status')->all());
        Storage::disk('local')->assertExists($fotos[0]->path);
        $this->assertSame(0, Media::query()->count());
        $this->assertSame('2', FotosRelationManager::getBadge($this->event, EditEvent::class));

        $this->get(route('event-fotos.vorschau', $fotos[0]))->assertOk();
        $this->actingAs($this->user('autor@example.org', 'autor'))->get(route('event-fotos.vorschau', $fotos[0]))->assertNotFound();
        $this->get('/veranstaltungen/pfingstlager')->assertDontSee('fotos-heading', false);
    }

    #[TestDox('Vier-Augen: die hochladende Person kann nicht freigeben, eine zweite schon; dann öffentlich als Galerie, ausblendbar')]
    public function test_freigabe(): void
    {
        $kim = $this->user('kim@example.org');
        $ben = $this->user('ben@example.org');
        Storage::disk('local')->put('event-fotos/a.jpg', UploadedFile::fake()->image('a.jpg', 800, 600)->getContent());
        $foto = $this->event->fotos()->create(['path' => 'event-fotos/a.jpg', 'credit' => 'Kim', 'alt_text' => 'Floß am See', 'hochgeladen_von' => $kim->id]);

        $this->actingAs($kim);
        $this->rm()->assertTableActionDisabled('freigeben', $foto)
            ->callTableBulkAction('alleFreigeben', [$foto])->assertNotified();
        $this->assertSame('eingereicht', $foto->fresh()->status);

        $this->actingAs($ben);
        $this->rm()->assertTableActionEnabled('freigeben', $foto)->callTableAction('freigeben', $foto);
        $foto->refresh();
        $this->assertSame(['freigegeben', null, $ben->id], [$foto->status, $foto->path, $foto->freigegeben_von]);
        Storage::disk('local')->assertMissing('event-fotos/a.jpg');
        Storage::disk('public')->assertExists($foto->media->path);
        $this->assertSame(['Floß am See', 'Kim', true], [$foto->media->alt_text, $foto->media->credit, $foto->media->rights_confirmed]);

        auth()->logout();
        $this->get('/veranstaltungen/pfingstlager')->assertSee('fotos-heading', false)->assertSee('alt="Floß am See"', false)->assertSee('Fotos: Kim');

        $this->actingAs($ben);
        $this->rm()->callTableAction('ausblenden', $foto);
        auth()->logout();
        $this->get('/veranstaltungen/pfingstlager')->assertDontSee('fotos-heading', false);

        // Löschen entfernt auch das Medium.
        $foto->fresh()->delete();
        $this->assertSame(0, Media::query()->count());
    }
}
