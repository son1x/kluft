<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Services\Backup\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Backups, Pflichtenheft 10.2 und Abnahmekriterium 10.
 */
class BackupTest extends TestCase
{
    // Kein RefreshDatabase: SQLite erlaubt VACUUM INTO nicht innerhalb einer Transaktion.
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/dpsg-backup-test-'.uniqid();
        config(['dpsg.backup.path' => $this->dir, 'dpsg.backup.password' => 'ein-langes-test-passwort-123', 'dpsg.backup.webdav_url' => null]);
        User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    #[TestDox('Backup lässt sich wiederherstellen; mit falschem Passwort nicht lesbar')]
    public function test_backup_round_trip_and_wrong_password(): void
    {
        $this->artisan('backup:run')->assertSuccessful();
        $file = app(BackupService::class)->latest();
        $this->assertNotNull($file);
        $this->assertStringNotContainsString('buero@dpsg-trier.de', file_get_contents($file), 'Backup muss verschlüsselt sein');

        $this->artisan('backup:verify')->expectsOutputToContain('Datenbank ok (1 Benutzer)')->assertSuccessful();
        $this->assertTrue(Cache::get(BackupService::LAST_VERIFY_KEY)['ok']);

        config(['dpsg.backup.password' => 'ein-ganz-anderes-passwort']);
        $this->artisan('backup:verify')->assertFailed();
        $this->assertFalse(Cache::get(BackupService::LAST_VERIFY_KEY)['ok']);
    }

    #[TestDox('Manipulierte oder abgeschnittene Backups werden erkannt')]
    public function test_tampered_or_truncated_files_are_detected(): void
    {
        $file = app(BackupService::class)->create();

        $bytes = file_get_contents($file);
        file_put_contents($file, substr($bytes, 0, -10));
        $this->assertFalse(app(BackupService::class)->verify($file)['ok']);

        $bytes[200] = $bytes[200] === 'A' ? 'B' : 'A';
        file_put_contents($file, $bytes);
        $this->assertFalse(app(BackupService::class)->verify($file)['ok']);
    }

    #[TestDox('Wiederherstellung entpackt in ein eigenes Verzeichnis statt die Live-Daten zu überschreiben')]
    public function test_restore_extracts_into_separate_directory(): void
    {
        $file = app(BackupService::class)->create();
        $target = $this->dir.'/restore';

        $this->artisan('backup:restore', ['file' => $file, '--to' => $target])->assertSuccessful();

        $this->assertFileExists($target.'/database/database.sqlite');
        $manifest = json_decode(file_get_contents($target.'/manifest.json'), true);
        $this->assertSame(1, $manifest['counts']['users']);
        $this->assertSame('sqlite', $manifest['driver']);
    }

    #[TestDox('Ohne Backup-Passwort gibt es eine klare Fehlermeldung')]
    public function test_missing_password_fails_clearly(): void
    {
        config(['dpsg.backup.password' => '']);
        $this->artisan('backup:run')->expectsOutputToContain('BACKUP_PASSWORD fehlt')->assertFailed();
    }

    #[TestDox('Backups werden 14 Tage aufbewahrt, mindestens aber drei')]
    public function test_rotation_keeps_fourteen_days_and_at_least_three(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach ([20, 16, 15, 3] as $daysAgo) {
            touch($this->dir.'/backup-'.now()->subDays($daysAgo)->format('Y-m-d').'-020000.dpsgbak');
        }

        app(BackupService::class)->create();

        $names = array_map('basename', glob($this->dir.'/backup-*.dpsgbak'));
        // Heute, vor 3 und vor 15 Tagen bleiben (die drei neuesten sind geschützt);
        // ältere als 14 Tage werden gelöscht.
        $this->assertCount(3, $names);
        $this->assertContains('backup-'.now()->subDays(15)->format('Y-m-d').'-020000.dpsgbak', $names);
        $this->assertNotContains('backup-'.now()->subDays(20)->format('Y-m-d').'-020000.dpsgbak', $names);
        $this->assertNotContains('backup-'.now()->subDays(16)->format('Y-m-d').'-020000.dpsgbak', $names);
    }

    #[TestDox('Kopie außerhalb des Webspace per WebDAV, inklusive Aufräumen dort')]
    public function test_offsite_copy_via_webdav_and_remote_rotation(): void
    {
        config(['dpsg.backup.webdav_url' => 'https://cloud.example.org/remote.php/dav/files/backup/dpsg', 'dpsg.backup.webdav_user' => 'backup', 'dpsg.backup.webdav_password' => 'app-pw']);
        $old = 'backup-'.now()->subDays(30)->format('Y-m-d').'-020000.dpsgbak';
        Http::fake([
            '*' => fn (Request $r) => $r->method() === 'PROPFIND'
                ? Http::response('<d:multistatus><d:response><d:href>/remote.php/dav/files/backup/dpsg/'.$old.'</d:href></d:response></d:multistatus>', 207)
                : Http::response('', 201),
        ]);

        app(BackupService::class)->create();

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '.dpsgbak') && $r->hasHeader('Authorization'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), $old));
        $this->assertTrue(Cache::get(BackupService::LAST_RUN_KEY)['offsite']);
    }
}
