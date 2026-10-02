<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * KI-Kennzeichnung und Bildnachweis, Pflichtenheft F-CMS-14.
 */
class AiLabelTest extends TestCase
{
    use RefreshDatabase;

    private function blogPost(array $attrs = []): Post
    {
        return Post::query()->create([
            'title' => 'Lagerbericht', 'slug' => 'lager', 'teaser' => 't', 'body' => '<p>b</p>',
            'status' => 'published', 'published_at' => now()->subHour(), ...$attrs,
        ]);
    }

    #[TestDox('KI-Hinweistexte entsprechen dem CD-Leitfaden')]
    public function test_notice_texts_follow_the_cd_guide(): void
    {
        $author = User::query()->create(['name' => 'Lisa Leiterin', 'email' => 'lisa@dpsg-trier.de', 'status' => 'active']);

        $this->assertNull($this->blogPost(['ai_level' => 'none'])->aiNotice());
        $this->assertNull($this->blogPost(['slug' => 'b', 'ai_level' => 'assist', 'ai_tool' => 'DeepL'])->aiNotice());
        $this->assertSame('Bild wurde mithilfe des KI-Tools Midjourney generiert.',
            $this->blogPost(['slug' => 'c', 'ai_level' => 'full', 'ai_tool' => 'Midjourney', 'ai_content' => 'Bild'])->aiNotice());
        $this->assertSame('Lisa Leiterin in Zusammenarbeit mit dem KI-Tool ChatGPT.',
            $this->blogPost(['slug' => 'd', 'ai_level' => 'partial', 'ai_tool' => 'ChatGPT', 'author_id' => $author->id])->aiNotice());
        $this->assertSame('Max Muster in Zusammenarbeit mit dem KI-Tool Claude.',
            $this->blogPost(['slug' => 'e', 'ai_level' => 'partial', 'ai_tool' => 'Claude', 'ai_person' => 'Max Muster', 'author_id' => $author->id])->aiNotice());
    }

    #[TestDox('Beitragsseite zeigt Bildnachweis und KI-Hinweis')]
    public function test_post_page_shows_credit_and_notice(): void
    {
        $media = Media::query()->create(['path' => 'media/x.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'alt_text' => 'Zeltlager', 'credit' => 'Foto: Jana Beispiel']);
        $this->blogPost(['cover_media_id' => $media->id, 'ai_level' => 'full', 'ai_tool' => 'ChatGPT', 'ai_content' => 'Text']);

        $this->get('/aktuelles/lager')->assertOk()
            ->assertSee('alt="Zeltlager"', false)
            ->assertSee('Foto: Jana Beispiel')
            ->assertDontSee('Foto: Foto:')
            ->assertSee('Text wurde mithilfe des KI-Tools ChatGPT generiert.');

        $this->blogPost(['slug' => 'ohne', 'ai_level' => 'assist']);
        $this->get('/aktuelles/ohne')->assertOk()->assertDontSee('KI-Hinweis');
    }

    #[TestDox('Bei KI-Einsatz muss das verwendete Werkzeug angegeben werden')]
    public function test_editor_requires_tool_when_ai_was_used(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user);

        $data = ['title' => 'Neu', 'slug' => 'neu', 'scope' => 'general', 'status' => 'draft', 'teaser' => 't', 'body' => '<p>b</p>'];

        Livewire::test(CreatePost::class)
            ->fillForm([...$data, 'ai_level' => 'full'])
            ->call('create')
            ->assertHasFormErrors(['ai_tool']);

        Livewire::test(CreatePost::class)
            ->fillForm([...$data, 'ai_level' => 'partial', 'ai_tool' => 'ChatGPT'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Büro in Zusammenarbeit mit dem KI-Tool ChatGPT.', Post::query()->where('slug', 'neu')->sole()->aiNotice());
    }
}
