<?php

namespace Tests\Feature\Instanzen;

use App\Models\Page;
use App\Models\Person;
use App\Models\TeamPhoto;
use App\Models\User;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Stammes-Website Roden (KLUFT_INSTANZ=roden): Adressen nach Vorname,
 * Stammesvorstand mit Foto, Ämter.
 */
#[Group('roden')]
class RodenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--force' => true]);
    }

    #[TestDox('Leitende haben vorname@dpsg-roden.de, auch auf der Stufenseite')]
    public function test_adressen_der_leitenden(): void
    {
        $this->assertSame('gianluca@dpsg-roden.de', Person::query()->where('name', 'Gian-Luca Blasius')->firstOrFail()->publicEmail());
        $this->assertSame('michele@dpsg-roden.de', Person::query()->where('name', 'Michele Franke')->firstOrFail()->publicEmail());

        $this->get('/stufe-woelflinge')->assertOk()->assertSee('hendrik@dpsg-roden.de', false);
    }

    #[TestDox('E-Mail-Adressen öffnen in Roden ein Formular statt des Mailprogramms')]
    public function test_mailformular(): void
    {
        $this->assertTrue(Features::enabled(Features::MAILFORMULAR));
        $this->get('/stufe-woelflinge')->assertOk()->assertSee('/nachricht?an=', false)->assertDontSee('mailto:hendrik@dpsg-roden.de', false);
    }

    #[TestDox('Erstes M365-Konto wird aktiv und Super-Admin; ohne Konto passiert nichts')]
    public function test_erster_admin(): void
    {
        $migration = require base_path('instanzen/roden/migrations/2026_10_22_090000_roden_erster_admin_m365.php');
        $migration->up();
        $this->assertSame(0, User::query()->where('email', 'sebastian@dpsg-roden.de')->count());

        $user = User::query()->create(['name' => 'Sebastian', 'email' => 'Sebastian@dpsg-roden.de', 'upn' => 'sebastian@dpsg-roden.de', 'status' => 'pending']);
        $migration->up();
        $migration->up();

        $user = $user->fresh();
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame(1, $user->roleAssignments()->count());
    }

    #[TestDox('Migration: Stammesvorstand mit Foto und vorstand@, Ämter, Vorstandsadresse auf der Seite; zweiter Lauf ändert nichts')]
    public function test_migration(): void
    {
        $seite = Page::query()->where('slug', 'stammesvorstand')->firstOrFail();
        $seite->update(['body' => str_replace('vorstand@dpsg-roden.de', 'kontakt@dpsg-roden.de', $seite->body)]);

        $migration = require base_path('instanzen/roden/migrations/2026_10_19_101500_roden_stammesvorstand_und_aemter.php');
        $migration->up();
        $migration->up();

        $vorstand = Person::query()->where('gremium', 'leitung')->orderBy('sort')->get();
        $this->assertSame(['Kimberly Lamprecht', 'Flavio Russello'], $vorstand->pluck('name')->all());
        $this->assertSame(['vorstand@dpsg-roden.de'], $vorstand->map->publicEmail()->unique()->values()->all());

        $foto = TeamPhoto::query()->where('gremium', 'leitung')->sole();
        $this->assertSame('Julia Hafner', $foto->media->credit);
        $this->assertSame(2, $foto->spots()->count());

        $this->assertSame(['Kassenwart und Mitgliederbeauftragter'], Person::query()->where('name', 'Hendrik Schuler')->pluck('role')->all());
        $this->assertSame(['Rüsthaus-Bestellungen', 'Rüsthaus-Bestellungen'], Person::query()->where('name', 'Michele Franke')->pluck('role')->all());

        $this->get('/stammesvorstand')->assertOk()
            ->assertSee('Unser Stammesvorstand')
            ->assertSee('Foto: Julia Hafner')
            ->assertSee('mx-auto max-w-2xl', false)
            ->assertSee('vorstand@dpsg-roden.de', false);
        $this->assertStringNotContainsString('kontakt@dpsg-roden.de', $seite->fresh()->body);

        // Bild und Varianten wieder entfernen.
        $foto->media->delete();
        File::delete(public_path('uploads/media/stammesvorstand-roden.jpg'));
    }
}
