<?php

namespace Tests\Feature\Backup;

use App\Filament\Pages\Backups;
use App\Models\User;
use App\Services\Backup\BackupEinspielen;
use App\Services\Backup\BackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Backup einspielen (Verwaltung → Backups, backup:einspielen), z. B. Test →
 * Live beim Livegang (docs/livegang.md).
 */
class BackupEinspielenTest extends TestCase
{
    // Wie BackupTest: kein RefreshDatabase (VACUUM INTO/mysqldump außerhalb einer Transaktion).
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/dpsg-einspielen-test-'.uniqid();
        config(['dpsg.backup.path' => $this->dir, 'dpsg.backup.password' => 'ein-langes-test-passwort-123', 'dpsg.backup.webdav_url' => null]);
        // Nachlaufende Befehle (migrate, optimize:clear, media:variants) nicht wirklich ausführen.
        Process::fake(['*artisan*' => Process::result('ok')]);
        File::deleteDirectory(BackupEinspielen::ordner());
        // Nie die echte .env anfassen, auch nicht über die Befehle.
        $einspielen = app(BackupEinspielen::class);
        $einspielen->envPfad = $this->dir.'/.env';
        $this->app->instance(BackupEinspielen::class, $einspielen);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::deleteDirectory(BackupEinspielen::ordner());
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Einspielen ersetzt die Datenbank durch den Stand im Backup; Website danach nicht im Wartungsmodus; Stand mit Verlauf')]
    public function test_einspielen(): void
    {
        User::query()->create(['name' => 'Aus dem Backup', 'email' => 'backup@example.org', 'status' => 'active']);
        $datei = app(BackupService::class)->create();

        User::query()->where('email', 'backup@example.org')->delete();
        User::query()->create(['name' => 'Später', 'email' => 'spaeter@example.org', 'status' => 'active']);

        $einspielen = app(BackupEinspielen::class);
        File::put($einspielen->envPfad, "APP_NAME=Kluft\nAPP_KEY=base64:alt\n");
        $anzahlen = $einspielen->einspielen($datei, str_repeat('a', 40));

        $this->assertSame(['backup@example.org'], User::query()->pluck('email')->all());
        $this->assertSame(1, $anzahlen['users']);
        $this->assertFalse(app()->isDownForMaintenance());
        Process::assertRan(fn ($p) => str_contains(implode(' ', (array) $p->command), 'migrate'));
        $stand = BackupEinspielen::stand();
        $this->assertSame('fertig', $stand['status']);
        $this->assertStringContainsString('Datenbank wird ersetzt', implode("\n", $stand['zeilen']));
        // Ohne app.key im Backup bleibt die .env unverändert, sonst kommt der Schlüssel aus dem Backup.
        $schluessel = trim((string) @file_get_contents(storage_path('app/private/app.key')));
        $this->assertStringContainsString(str_starts_with($schluessel, 'base64:') ? 'APP_KEY='.$schluessel : 'APP_KEY=base64:alt', File::get($einspielen->envPfad));
    }

    #[TestDox('Kaputtes oder fremdes Backup: Fehler im Stand, Daten bleiben, Website wieder erreichbar')]
    public function test_fehler(): void
    {
        User::query()->create(['name' => 'Bleibt', 'email' => 'bleibt@example.org', 'status' => 'active']);
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/backup-2026-11-10-020000.dpsgbak', 'kaputt');

        try {
            app(BackupEinspielen::class)->einspielen($this->dir.'/backup-2026-11-10-020000.dpsgbak');
            $this->fail('Fehler erwartet');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Keine Backup-Datei', $e->getMessage());
        }
        $this->assertSame(['bleibt@example.org'], User::query()->pluck('email')->all());
        $this->assertSame('fehler', BackupEinspielen::stand()['status']);
        $this->assertFalse(app()->isDownForMaintenance());
    }

    #[TestDox('Backend: nur Admins; „Backup einspielen“ nur mit eingetipptem Website-Namen; Auftrag läuft im Cronjob, Stand-Seite nur mit Token')]
    public function test_backend_und_auftrag(): void
    {
        $admin = $this->admin();
        $datei = app(BackupService::class)->create();
        $andere = User::query()->create(['name' => 'Verwaltung', 'email' => 'verwaltung@example.org', 'status' => 'active']);
        $andere->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');

        $this->actingAs($andere->fresh())->get(Backups::getUrl())->assertForbidden();
        $this->actingAs($admin)->get(Backups::getUrl())->assertOk()->assertSee('Vorhandene Backups')->assertSee(basename($datei));
        $this->assertArrayHasKey(realpath($datei), app(BackupEinspielen::class)->quellen());

        Livewire::test(Backups::class)
            ->callAction('einspielen', ['datei' => realpath($datei), 'bestaetigung' => 'falsch'])
            ->assertHasActionErrors(['bestaetigung']);
        $this->assertNull(BackupEinspielen::auftrag());

        Livewire::test(Backups::class)
            ->callAction('einspielen', ['datei' => realpath($datei), 'bestaetigung' => request()->getHost()])
            ->assertHasNoActionErrors();
        $auftrag = BackupEinspielen::auftrag();
        $this->assertSame(['einspielen', realpath($datei)], [$auftrag['art'], $auftrag['datei']]);
        $this->get(route('backup.stand', $auftrag['token']))->assertOk()->assertSee('Wartet auf den Cronjob');
        $this->get(route('backup.stand', str_repeat('x', 40)))->assertNotFound();

        // Zweiter Auftrag, solange einer wartet: abgelehnt.
        Livewire::test(Backups::class)->assertActionDisabled('jetzt');

        User::query()->create(['name' => 'Später', 'email' => 'spaeter@example.org', 'status' => 'active']);
        $this->artisan('backup:auftrag')->assertSuccessful();
        $this->assertNull(BackupEinspielen::auftrag());
        $this->assertSame('fertig', BackupEinspielen::stand()['status']);
        $this->assertFalse(User::query()->where('email', 'spaeter@example.org')->exists());
        $this->get(route('backup.stand', $auftrag['token']))->assertOk()->assertSee('Fertig.')->assertSee('Zum Backend');
    }

    #[TestDox('„Backup jetzt erstellen“ als Auftrag; der Cronjob erstellt es')]
    public function test_backup_jetzt(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin());
        Livewire::test(Backups::class)->callAction('jetzt');
        $this->assertSame('backup', BackupEinspielen::auftrag()['art']);

        $this->artisan('backup:auftrag')->assertSuccessful();
        $this->assertNotNull(app(BackupService::class)->latest());
        $this->assertSame('fertig', BackupEinspielen::stand()['status']);
    }

    #[TestDox('Auf andere Website übertragen: frisches Backup, Auftrag und Stand im Ordner der Ziel-Website (z. B. Test → Live)')]
    public function test_uebertragen(): void
    {
        // Ziel-Website als Geschwister-Ordner im selben „Webspace“.
        $ziel = dirname(base_path()).'/kluft-ziel-'.uniqid();
        File::ensureDirectoryExists($ziel.'/storage/app/private');
        File::put($ziel.'/artisan', '<?php');
        File::put($ziel.'/.env', "APP_URL=https://neu.example.org\n");
        try {
            $einspielen = app(BackupEinspielen::class);
            $this->assertArrayHasKey(realpath($ziel), $einspielen->ziele());
            $this->assertArrayNotHasKey(realpath(base_path()), $einspielen->ziele());

            Filament::setCurrentPanel('admin');
            $this->actingAs($this->admin());
            Livewire::test(Backups::class)
                ->callAction('uebertragen', ['ziel' => realpath($ziel), 'bestaetigung' => 'falsch'])
                ->assertHasActionErrors(['bestaetigung']);
            Livewire::test(Backups::class)
                ->callAction('uebertragen', ['ziel' => realpath($ziel), 'bestaetigung' => basename($ziel)])
                ->assertHasNoActionErrors();

            $this->artisan('backup:auftrag')->assertSuccessful();
            $auftrag = json_decode(File::get($ziel.'/storage/app/private/einspielen/auftrag.json'), true);
            $this->assertSame('einspielen', $auftrag['art']);
            $this->assertFileExists($auftrag['datei']);
            $this->assertSame('wartet', json_decode(File::get($ziel.'/storage/app/private/einspielen/stand.json'), true)['status']);
            $stand = BackupEinspielen::stand();
            $this->assertSame('fertig', $stand['status']);
            $this->assertStringContainsString('https://neu.example.org/einspielen/stand/'.$auftrag['token'], implode(' ', $stand['zeilen']));
        } finally {
            File::deleteDirectory($ziel);
        }
    }
}
