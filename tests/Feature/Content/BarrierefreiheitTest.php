<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use App\Support\Barrierefreiheit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Hinweise zur Barrierefreiheit beim Speichern.
 */
class BarrierefreiheitTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Prüfung: Bilder ohne Alt-Text, „hier“-Links, Überschriften, lange Sätze; sauberer Text ohne Hinweise')]
    public function test_pruefen(): void
    {
        $this->assertSame([], Barrierefreiheit::pruefen('<h2>Programm</h2><p>Wir treffen uns um 10 Uhr. <a href="/anmeldung">Zur Anmeldung</a></p><img src="a.jpg" alt="Wölflinge am Feuer"><img src="d.svg" alt="" role="presentation"><h3>Essen</h3>'));
        $this->assertSame([], Barrierefreiheit::pruefen(null));

        $h = Barrierefreiheit::pruefen('<h1>Doppelt</h1><h2>A</h2><h4>B</h4><p><img src="a.jpg"><img src="b.jpg" alt=" "> Mehr Infos <a href="/x">hier</a> und <a href="/y">Weiterlesen</a>.</p><p>'.str_repeat('Wort ', 35).'Ende.</p>');
        $this->assertCount(5, $h);
        $this->assertStringContainsString('2 Bilder haben keinen Alternativtext', $h[0]);
        $this->assertStringContainsString('„hier“, „Weiterlesen“', $h[1]);
        $this->assertStringContainsString('Überschrift 1', $h[2]);
        $this->assertStringContainsString('Nach Überschrift 2 folgt Überschrift 4', $h[3]);
        $this->assertStringContainsString('Ein Satz hat mehr als 30 Wörter', $h[4]);
    }

    #[TestDox('Beitrag speichern: Hinweise als Mitteilung, gespeichert wird trotzdem')]
    public function test_speichern(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());
        $post = Post::query()->create(['title' => 'Bericht', 'slug' => 'bericht', 'status' => 'draft', 'teaser' => 'Kurz', 'body' => '<p>x</p>', 'author_id' => $user->id]);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['body' => '<p>Infos gibt es <a href="https://example.org">hier</a>.</p>'])
            ->call('save')->assertHasNoFormErrors()
            ->assertNotified('Hinweise zur Barrierefreiheit');
        $this->assertStringContainsString('hier', (string) $post->fresh()->body);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->fillForm(['body' => '<p>Infos gibt es im <a href="https://example.org">Anmeldeformular</a>.</p>'])
            ->call('save')->assertNotNotified('Hinweise zur Barrierefreiheit');
    }
}
