<?php

namespace Tests\Feature\Content;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Autoren-Workflow und Scope-Rechte (Pflichtenheft 5.4, 6.1 F-CMS-10/11,
 * Abnahmekriterien 11/12).
 */
class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, ?string $scopeType = null, ?string $scopeKey = null): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'@dpsg-trier.de',
            'status' => 'active',
        ]);

        $user->roleAssignments()->create([
            'role' => $role,
            'scope_type' => $scopeType ?? 'own',
            'scope_key' => $scopeKey,
        ]);

        return $user->fresh();
    }

    #[TestDox('Autor*innen bearbeiten nur eigene Beiträge')]
    public function test_autor_can_only_edit_own_posts(): void
    {
        $autor = $this->makeUser('autor');
        $otherAutor = User::query()->create([
            'name' => 'Anderer Autor', 'email' => 'anderer-autor@dpsg-trier.de', 'status' => 'active',
        ]);

        $ownPost = Post::query()->create([
            'title' => 'Eigener Beitrag', 'slug' => 'eigener-beitrag',
            'teaser' => 't', 'body' => 'b', 'status' => 'draft', 'author_id' => $autor->id,
        ]);

        $foreignPost = Post::query()->create([
            'title' => 'Fremder Beitrag', 'slug' => 'fremder-beitrag',
            'teaser' => 't', 'body' => 'b', 'status' => 'draft', 'author_id' => $otherAutor->id,
        ]);

        $this->assertTrue($autor->can('update', $ownPost));
        $this->assertFalse($autor->can('update', $foreignPost));
    }

    #[TestDox('Stufenadmins verwalten nur Beiträge der eigenen Stufe')]
    public function test_stufenadmin_can_manage_posts_of_their_own_stage_only(): void
    {
        $stufenadminBiber = $this->makeUser('stufenadmin', 'stage', 'biber');

        $biberPost = Post::query()->create([
            'title' => 'Biber-News', 'slug' => 'biber-news', 'stage' => 'biber',
            'teaser' => 't', 'body' => 'b', 'status' => 'draft',
        ]);

        $woelflingePost = Post::query()->create([
            'title' => 'Wölflinge-News', 'slug' => 'woelflinge-news', 'stage' => 'woelflinge',
            'teaser' => 't', 'body' => 'b', 'status' => 'draft',
        ]);

        $this->assertTrue($stufenadminBiber->can('update', $biberPost));
        $this->assertFalse($stufenadminBiber->can('update', $woelflingePost));
    }

    #[TestDox('Nur berechtigte Rollen dürfen direkt veröffentlichen')]
    public function test_only_scoped_roles_may_publish_directly(): void
    {
        $autor = $this->makeUser('autor');
        $stufenadmin = $this->makeUser('stufenadmin', 'stage', 'biber');
        $verwaltung = $this->makeUser('verwaltung', 'global');

        $this->assertFalse($autor->can('publish', Post::class));
        $this->assertTrue($stufenadmin->can('publish', Post::class));
        $this->assertTrue($verwaltung->can('publish', Post::class));
    }

    #[TestDox('Verwaltung darf alle Beiträge bearbeiten')]
    public function test_verwaltung_can_manage_any_post(): void
    {
        $verwaltung = $this->makeUser('verwaltung', 'global');
        $author = User::query()->create([
            'name' => 'Ein Autor', 'email' => 'ein-autor@dpsg-trier.de', 'status' => 'active',
        ]);

        $post = Post::query()->create([
            'title' => 'Irgendein Beitrag', 'slug' => 'irgendein-beitrag',
            'teaser' => 't', 'body' => 'b', 'status' => 'draft', 'author_id' => $author->id,
        ]);

        $this->assertTrue($verwaltung->can('update', $post));
        $this->assertTrue($verwaltung->can('delete', $post));
    }
}
