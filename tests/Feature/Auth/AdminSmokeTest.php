<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Jede Übersichts- und Anlegen-Seite im Backend lädt fehlerfrei
 * (fängt z. B. falsch benannte Closure-Parameter in Tabellen ab).
 */
class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Jede Übersichts-, Anlegen- und Sonderseite im Backend lädt ohne Fehler')]
    public function test_every_resource_index_and_create_page_renders(): void
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $admin = User::query()->create(['name' => 'Super', 'email' => 'super@dpsg-trier.de', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'superadmin', 'scope_type' => 'global']);
        $this->actingAs($admin);

        $this->get('/admin')->assertOk();
        $checked = 0;

        foreach ($panel->getResources() as $resource) {
            foreach (['index', 'create'] as $page) {
                if (! $resource::hasPage($page)) {
                    continue;
                }
                $url = $resource::getUrl($page);
                $this->get($url)->assertOk();
                $checked++;
            }
        }

        foreach ($panel->getPages() as $page) {
            if ($page::canAccess()) {
                $this->get($page::getUrl())->assertOk();
                $checked++;
            }
        }

        $this->assertGreaterThan(15, $checked);
    }
}
