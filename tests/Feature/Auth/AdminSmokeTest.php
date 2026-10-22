<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\GlossaryTerms\GlossaryTermResource;
use App\Filament\Resources\GlossaryTerms\Pages\CreateGlossaryTerm;
use App\Filament\Resources\GlossaryTerms\Pages\EditGlossaryTerm;
use App\Models\GlossaryTerm;
use App\Models\User;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        // WhatsApp-Seiten und Kasse sind standardmäßig ausgeschaltet; hier mitprüfen
        // (die Kasse gibt es nur auf Stammes-Websites, s. BeitraegeTest).
        config(['whatsapp.mode' => 'api']);
        Features::setzen(Features::MITGLIEDSBEITRAEGE, true);
        Features::setzen(Features::NACHRICHTEN, true);
        $kasse = Features::enabled(Features::MITGLIEDSBEITRAEGE);
        $this->get('/admin')->assertOk();
        $checked = 0;

        foreach ($panel->getResources() as $resource) {
            foreach (['index', 'create'] as $page) {
                // Bereiche nur für eine andere Ebene (z. B. Gruppenstunden nur im Stamm) überspringen.
                if (! $resource::hasPage($page) || (! $kasse && $resource::getNavigationGroup() === 'Kasse') || ! $resource::canAccess()) {
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

    #[TestDox('Nach Anlegen und Speichern geht es zurück zur Übersicht')]
    public function test_create_and_save_return_to_the_list(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user);
        $index = GlossaryTermResource::getUrl('index');

        Livewire::test(CreateGlossaryTerm::class)
            ->fillForm(['term' => 'DAK', 'explanation' => 'Diözesanarbeitskreis'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect($index);

        Livewire::test(EditGlossaryTerm::class, ['record' => GlossaryTerm::query()->sole()->getRouteKey()])
            ->fillForm(['explanation' => 'Arbeitskreis einer Stufe'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect($index);
    }
}
