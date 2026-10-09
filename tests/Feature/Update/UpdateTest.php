<?php

namespace Tests\Feature\Update;

use App\Filament\Pages\Updates;
use App\Models\User;
use App\Notifications\UpdateNotification;
use App\Services\Backup\BackupService;
use App\Services\Update\Release;
use App\Services\Update\UpdateFehler;
use App\Services\Update\Updater;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Updates für eigenständige Installationen (docs/updates.md): signierte
 * Release-Beschreibung, Download mit Prüfsumme, Backup, Dateitausch mit
 * Rücknahme, Backend-Seite.
 */
class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://updates.example.org/kluft/manifest.json';

    private string $geheim;

    private string $arbeit;

    /** @var array<string, string> Antworten des Update-Servers je Adresse */
    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $paar = sodium_crypto_sign_keypair();
        $this->geheim = sodium_crypto_sign_secretkey($paar);
        $this->arbeit = sys_get_temp_dir().'/kluft-update-test-'.bin2hex(random_bytes(4));
        config(['kluft.updates' => [
            'mode' => 'manual',
            'manifest_url' => self::URL,
            'public_key' => base64_encode(sodium_crypto_sign_publickey($paar)),
            'path' => $this->arbeit,
        ]]);
        Http::fake(fn (Request $r) => array_key_exists($r->url(), $this->server)
            ? Http::response($this->server[$r->url()])
            : Http::response('', 404));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->arbeit);
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Technik', 'email' => 'technik@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    /** Paket mit gültiger Grundstruktur; liefert den ZIP-Inhalt. */
    private function paket(string $version, array $extra = []): string
    {
        $datei = tempnam(sys_get_temp_dir(), 'kluft').'.zip';
        $zip = new ZipArchive;
        $zip->open($datei, ZipArchive::CREATE);
        foreach (['VERSION' => $version."\n", 'artisan' => '<?php', 'vendor/autoload.php' => '<?php', 'app/Neu.php' => '<?php', ...$extra] as $name => $inhalt) {
            $zip->addFromString($name, $inhalt);
        }
        $zip->close();
        $inhalt = (string) file_get_contents($datei);
        unlink($datei);

        return $inhalt;
    }

    private function veroeffentlichen(string $version, string $paket, ?string $sha = null, bool $falscheSignatur = false): void
    {
        $manifest = json_encode([
            'name' => 'kluft', 'version' => $version, 'revision' => 'abc1234', 'php' => '8.3', 'auto' => true,
            'package' => ['url' => 'kluft-'.$version.'.zip', 'sha256' => $sha ?? hash('sha256', $paket), 'size' => strlen($paket)],
            'changes' => ['Gruppenstunden als Baustein', 'Fehler behoben'],
        ]);
        $sig = sodium_crypto_sign_detached($falscheSignatur ? $manifest.' ' : $manifest, $this->geheim);
        $this->server = [
            self::URL => (string) $manifest,
            self::URL.'.sig' => base64_encode($sig),
            'https://updates.example.org/kluft/kluft-'.$version.'.zip' => $paket,
        ];
    }

    #[TestDox('Prüfen: neueres signiertes Release wird erkannt, Admins bekommen einmal Bescheid')]
    public function test_pruefen(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'));

        $release = app(Updater::class)->pruefen();
        $this->assertInstanceOf(Release::class, $release);
        $this->assertSame('9.1.0', $release->version);
        $this->assertSame('https://updates.example.org/kluft/kluft-9.1.0.zip', $release->url);
        $this->assertSame(['Gruppenstunden als Baustein', 'Fehler behoben'], $release->aenderungen);
        $this->assertSame('9.1.0', Updater::bekannt()?->version);

        app(Updater::class)->pruefen();
        Notification::assertSentToTimes($admin, UpdateNotification::class, 1);
    }

    #[TestDox('Gleiche oder ältere Version: nichts verfügbar')]
    public function test_nicht_neuer(): void
    {
        $this->veroeffentlichen('0.1.0', $this->paket('0.1.0'));

        $this->assertNull(app(Updater::class)->pruefen());
        $this->assertNull(Updater::bekannt());
    }

    #[TestDox('Ungültige Signatur oder fehlender Schlüssel: kein Release, auch keine Anzeige')]
    public function test_signatur(): void
    {
        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'), falscheSignatur: true);
        try {
            app(Updater::class)->pruefen();
            $this->fail('Ungültige Signatur akzeptiert');
        } catch (UpdateFehler $e) {
            $this->assertStringContainsString('Signatur', $e->getMessage());
        }
        $this->assertNull(Updater::bekannt());

        config(['kluft.updates.public_key' => null]);
        $this->expectException(UpdateFehler::class);
        app(Updater::class)->pruefen();
    }

    #[TestDox('Installieren: Download geprüft, Backup, Wartungsmodus, Tausch, Migrationen, wieder online, Abschluss im neuen Code')]
    public function test_installieren(): void
    {
        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'));
        $this->mock(BackupService::class)->shouldReceive('create')->once()->andReturn('/backups/kluft-backup.zip.enc');
        Process::fake();

        $release = app(Updater::class)->pruefen();
        $protokoll = app(Updater::class)->installieren($release, $this->admin());

        $befehle = [];
        Process::assertRan(function (PendingProcess $p) use (&$befehle) {
            $befehle[] = implode(' ', array_slice((array) $p->command, 1));

            return true;
        });
        $this->assertSame('artisan down --retry=60 --no-interaction', $befehle[0]);
        $this->assertStringStartsWith($this->arbeit.'/austausch.php tauschen '.base_path().' '.$this->arbeit.'/neu-9.1.0 '.$this->arbeit.'/rueck-', $befehle[1]);
        $this->assertSame([
            'artisan migrate --force --no-interaction',
            'artisan kluft:assets --no-interaction',
            'artisan optimize:clear --no-interaction',
            'artisan up --no-interaction',
            'artisan kluft:update --abschluss --no-interaction',
        ], array_slice($befehle, 2));

        $this->assertFileExists($this->arbeit.'/austausch.php');
        $this->assertFileDoesNotExist($this->arbeit.'/kluft-9.1.0.zip');
        $this->assertStringContainsString('Paket geprüft', implode("\n", $protokoll));
        $lauf = Updater::letzterLauf();
        $this->assertTrue($lauf['ok']);
        $this->assertSame('9.1.0', $lauf['version']);
        $this->assertFalse($lauf['gemeldet']);
    }

    #[TestDox('Migration schlägt fehl: Dateien zurückgetauscht, wieder online, Admins bekommen den Fehler')]
    public function test_ruecknahme(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'));
        $this->mock(BackupService::class)->shouldReceive('create')->once()->andReturn('/backups/b.zip.enc');
        Process::fake(fn (PendingProcess $p) => in_array('migrate', (array) $p->command, true)
            ? Process::result(errorOutput: 'SQLSTATE: kaputt', exitCode: 1)
            : Process::result());

        $release = app(Updater::class)->pruefen();
        try {
            app(Updater::class)->installieren($release, $admin);
            $this->fail('Fehler nicht gemeldet');
        } catch (UpdateFehler $e) {
            $this->assertStringContainsString('vorherige Version ist wiederhergestellt', $e->getMessage());
        }

        Process::assertRan(fn (PendingProcess $p) => in_array('zurueck', (array) $p->command, true));
        Process::assertRan(fn (PendingProcess $p) => in_array('up', (array) $p->command, true));
        $this->assertFalse(Updater::letzterLauf()['ok']);
        Notification::assertSentTo($admin, UpdateNotification::class, fn (UpdateNotification $n) => $n->art === 'fehlgeschlagen');
    }

    #[TestDox('Falsche Prüfsumme, unvollständiges Paket, fehlgeschlagenes Backup: nichts wird angefasst')]
    public function test_abbrueche_vor_dem_tausch(): void
    {
        Process::fake();
        $this->mock(BackupService::class)->shouldReceive('create')->andThrow(new RuntimeException('BACKUP_PASSWORD fehlt'));

        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'), sha: str_repeat('a', 64));
        $this->assertFehler('Prüfsumme');

        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0', ['../boese.php' => 'x']));
        $this->assertFehler('unzulässigen Pfad');

        $this->veroeffentlichen('9.1.0', $this->paket('9.2.0'));
        $this->assertFehler('passt nicht zur Version');

        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'));
        $this->assertFehler('Backup fehlgeschlagen');

        Process::assertNothingRan();
    }

    private function assertFehler(string $text): void
    {
        try {
            app(Updater::class)->installieren(app(Updater::class)->pruefen());
            $this->fail('Kein Fehler: '.$text);
        } catch (UpdateFehler $e) {
            $this->assertStringContainsString($text, $e->getMessage());
        }
    }

    #[TestDox('Tauschskript: Programm getauscht, Daten der Installation bleiben, Rücknahme stellt alles wieder her')]
    public function test_austausch_skript(): void
    {
        $inst = $this->arbeit.'/installation';
        $rel = $this->arbeit.'/release';
        $rueck = $this->arbeit.'/rueck';
        $dateien = [
            "$inst/.env" => 'APP_KEY=geheim', "$inst/storage/app/x.txt" => 'daten', "$inst/app/Alt.php" => 'alt',
            "$inst/vendor/autoload.php" => 'alt', "$inst/database/database.sqlite" => 'db', "$inst/database/migrations/alt.php" => 'alt',
            "$inst/public/uploads/u.jpg" => 'bild', "$inst/public/index.php" => 'alt', "$inst/instanzen/meinstamm/config.php" => 'stamm',
            "$rel/artisan" => 'neu', "$rel/VERSION" => '9.1.0', "$rel/vendor/autoload.php" => 'neu', "$rel/app/Neu.php" => 'neu',
            "$rel/database/migrations/neu.php" => 'neu', "$rel/public/index.php" => 'neu',
        ];
        foreach ($dateien as $pfad => $inhalt) {
            File::ensureDirectoryExists(dirname($pfad));
            File::put($pfad, $inhalt);
        }
        $skript = resource_path('update/austausch.php');

        $lauf = Process::run([PHP_BINARY, $skript, 'tauschen', $inst, $rel, $rueck]);
        $this->assertTrue($lauf->successful(), $lauf->output());
        $this->assertFileExists("$inst/app/Neu.php");
        $this->assertFileDoesNotExist("$inst/app/Alt.php");
        $this->assertSame('neu', File::get("$inst/vendor/autoload.php"));
        $this->assertSame('neu', File::get("$inst/public/index.php"));
        $this->assertFileExists("$inst/database/migrations/neu.php");
        $this->assertFileDoesNotExist("$inst/database/migrations/alt.php");
        foreach (['.env' => 'APP_KEY=geheim', 'storage/app/x.txt' => 'daten', 'database/database.sqlite' => 'db', 'public/uploads/u.jpg' => 'bild', 'instanzen/meinstamm/config.php' => 'stamm'] as $pfad => $inhalt) {
            $this->assertSame($inhalt, File::get("$inst/$pfad"), $pfad);
        }

        $lauf = Process::run([PHP_BINARY, $skript, 'zurueck', $inst, $rueck]);
        $this->assertTrue($lauf->successful(), $lauf->output());
        $this->assertFileExists("$inst/app/Alt.php");
        $this->assertFileDoesNotExist("$inst/app/Neu.php");
        $this->assertFileDoesNotExist("$inst/artisan");
        $this->assertSame('alt', File::get("$inst/vendor/autoload.php"));
        $this->assertSame('alt', File::get("$inst/public/index.php"));
        $this->assertFileExists("$inst/database/migrations/alt.php");
        $this->assertSame('db', File::get("$inst/database/database.sqlite"));
        $this->assertSame('APP_KEY=geheim', File::get("$inst/.env"));
    }

    #[TestDox('Backend: nur Admins; „aktualisieren“ fordert das Update für den Cronjob an; Modus „aus“ zeigt nur die Version')]
    public function test_backend_seite(): void
    {
        $this->veroeffentlichen('9.1.0', $this->paket('9.1.0'));
        app(Updater::class)->pruefen();
        $admin = $this->admin();

        $this->actingAs($admin)->get(Updates::getUrl())->assertOk()
            ->assertSee('Verfügbar: Kluft 9.1.0')->assertSee('Gruppenstunden als Baustein');
        Livewire::test(Updates::class)->callAction('installieren');
        $this->assertSame('9.1.0', Cache::get(Updater::CACHE_ANGEFORDERT)['version']);
        $this->actingAs($admin)->get(Updates::getUrl())->assertSee('Update angefordert');

        $autor = User::query()->create(['name' => 'A', 'email' => 'a@example.org', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own', 'scope_key' => null]);
        $this->actingAs($autor->fresh())->get(Updates::getUrl())->assertForbidden();

        config(['kluft.updates.mode' => 'off']);
        $this->actingAs($admin)->get(Updates::getUrl())->assertOk()->assertSee('wird anders aktualisiert')->assertDontSee('Verfügbar: Kluft');
    }

    #[TestDox('Befehl: Modus „aus“ tut nichts; „--angefordert“ ohne Anfrage tut nichts')]
    public function test_befehl(): void
    {
        config(['kluft.updates.mode' => 'off']);
        $this->artisan('kluft:update --installieren')->expectsOutputToContain('abgeschaltet')->assertSuccessful();

        config(['kluft.updates.mode' => 'manual']);
        $this->artisan('kluft:update --angefordert')->assertSuccessful();
        Http::assertNothingSent();
    }
}
