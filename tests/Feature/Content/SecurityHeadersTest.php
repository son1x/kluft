<?php

namespace Tests\Feature\Content;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security-Header (Pflichtenheft 10.1) und keine externen Avatare (F-CON-02).
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_send_strict_headers_without_inline_scripts(): void
    {
        config(['app.url' => 'https://test.dpsg-trier.de']);
        $response = $this->get('/kalender')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src [^;]*'self'/", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src [^;]*'unsafe-inline'/", $csp);

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_backend_allows_inline_scripts_and_uses_local_avatars(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Sebastian Hafner', 'email' => 's.hafner@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        $response = $this->actingAs($user)->get('/admin')->assertOk();

        $this->assertMatchesRegularExpression("/script-src [^;]*'unsafe-inline'/", $response->headers->get('Content-Security-Policy'));
        $response->assertDontSee('ui-avatars.com');
        $this->assertStringStartsWith('data:image/svg+xml;base64,', Filament::getUserAvatarUrl($user));
        $this->assertStringContainsString('>SH<', base64_decode(substr(Filament::getUserAvatarUrl($user), 26)));
    }
}
