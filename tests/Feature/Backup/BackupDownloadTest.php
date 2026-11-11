<?php

namespace Tests\Feature\Backup;

use App\Filament\Pages\Backups;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Backup\BackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Backup herunterladen (verschlüsselt, nur Admins, signierte Adresse) und
 * ohne Website entschlüsseln (scripts/backup-entschluesseln.php).
 */
class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORT = 'ein-langes-test-passwort-123';

    private const DATEI = 'backup-2026-11-11-020000.dpsgbak';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/dpsg-download-test-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        config(['dpsg.backup.path' => $this->dir, 'dpsg.backup.password' => self::PASSWORT, 'dpsg.backup.webdav_url' => null]);
        file_put_contents($this->dir.'/inhalt.zip', 'PK-Testinhalt '.str_repeat('x', 70000));
        app(BackupService::class)->encryptFile($this->dir.'/inhalt.zip', $this->dir.'/'.self::DATEI, self::PASSWORT);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => $role.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Admin: Aktion führt zur signierten Adresse, Datei kommt verschlüsselt, Download protokolliert')]
    public function test_download(): void
    {
        $this->actingAs($this->user('admin'));

        Livewire::test(Backups::class)
            ->callAction('herunterladen', ['datei' => self::DATEI, 'sicher' => true])
            ->assertRedirectContains('/admin/backups/download/'.self::DATEI);

        $url = URL::temporarySignedRoute('backup.download', now()->addMinutes(5), ['datei' => self::DATEI]);
        $antwort = $this->get($url)->assertOk()->assertDownload(self::DATEI);
        $this->assertStringStartsWith('DPSGBAK1', file_get_contents($antwort->baseResponse->getFile()->getPathname()));
        $this->assertSame(1, AuditLog::query()->where('action', 'backup.download:'.self::DATEI)->count());

        // Ohne Bestätigung kein Download.
        Livewire::test(Backups::class)
            ->callAction('herunterladen', ['datei' => self::DATEI, 'sicher' => false])
            ->assertHasActionErrors(['sicher']);
    }

    #[TestDox('Ohne Signatur, ohne Admin oder mit fremdem Pfad: kein Download')]
    public function test_schutz(): void
    {
        $this->actingAs($this->user('verwaltung'));
        $url = URL::temporarySignedRoute('backup.download', now()->addMinutes(5), ['datei' => self::DATEI]);
        $this->get($url)->assertForbidden();

        $this->actingAs($this->user('admin'));
        $this->get('/admin/backups/download/'.self::DATEI)->assertForbidden();
        $this->get(URL::temporarySignedRoute('backup.download', now()->addMinutes(5), ['datei' => 'backup-fehlt.dpsgbak']))->assertNotFound();
        $this->get('/admin/backups/download/..%2F..%2F.env')->assertNotFound();

        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    #[TestDox('Skript entschlüsselt ohne Website; mit falschem Passwort nicht')]
    public function test_skript(): void
    {
        $quelle = $this->dir.'/'.self::DATEI;
        $skript = escapeshellarg(base_path('scripts/backup-entschluesseln.php'));

        exec('BACKUP_PASSWORD=falsch '.PHP_BINARY.' '.$skript.' '.escapeshellarg($quelle).' '.escapeshellarg($this->dir.'/falsch.zip').' 2>&1', $ausgabe, $code);
        $this->assertNotSame(0, $code);
        $this->assertFileDoesNotExist($this->dir.'/falsch.zip');

        exec('BACKUP_PASSWORD='.self::PASSWORT.' '.PHP_BINARY.' '.$skript.' '.escapeshellarg($quelle).' '.escapeshellarg($this->dir.'/ergebnis.zip').' 2>&1', $ausgabe, $code);
        $this->assertSame(0, $code, implode("\n", $ausgabe));
        $this->assertFileEquals($this->dir.'/inhalt.zip', $this->dir.'/ergebnis.zip');
    }
}
