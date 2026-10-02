<?php

namespace Tests\Feature\Migration;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Einmaliger Umzug der Testumgebung von SQLite nach MariaDB
 * (php artisan db:copy-from-sqlite). Der eigentliche Kopiertest läuft nur,
 * wenn die Tests gegen MariaDB laufen (in der CI zusätzlich zu SQLite).
 */
class CopySqliteToMariaDbTest extends TestCase
{
    // Kein RefreshDatabase: TRUNCATE beendet in MariaDB jede offene Transaktion.
    use DatabaseMigrations;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir().'/dpsg-copy-test-'.uniqid().'.sqlite';
    }

    protected function tearDown(): void
    {
        File::delete($this->file);
        parent::tearDown();
    }

    #[TestDox('db:check meldet, ob die Datenbank erreichbar ist (Prüfung im Deploy)')]
    public function test_db_check_reports_reachable_database(): void
    {
        $this->artisan('db:check')->expectsOutputToContain('Datenbank erreichbar')->assertSuccessful();

        config(['database.connections.kaputt' => ['driver' => 'mariadb', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
        $previous = config('database.default');
        config(['database.default' => 'kaputt']);
        $this->artisan('db:check')->expectsOutputToContain('Datenbank nicht erreichbar')->assertFailed();
        config(['database.default' => $previous]);
    }

    #[TestDox('Kopieren verweigert, wenn das Ziel keine MariaDB ist')]
    public function test_refuses_non_mariadb_target(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Nur sinnvoll, wenn die Tests auf SQLite laufen.');
        }
        touch($this->file);

        $this->artisan('db:copy-from-sqlite', ['--path' => $this->file, '--force' => true])
            ->expectsOutputToContain('Ziel muss MariaDB/MySQL sein')
            ->assertFailed();
    }

    #[TestDox('Alle Inhalte werden mit IDs übernommen; Cache und Migrationsliste bleiben')]
    public function test_copies_all_rows_with_ids(): void
    {
        $source = $this->sourceDatabase();
        $source->table('users')->insert(['id' => 41, 'name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $source->table('posts')->insert([
            'id' => 77, 'title' => 'Alter Beitrag', 'slug' => 'alter-beitrag', 'teaser' => str_repeat('ä', 290),
            'body' => '<p>'.str_repeat('Text ', 20000).'</p>', 'status' => 'draft', 'author_id' => 41,
            'import_issues' => json_encode(['Bild prüfen']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('users')->insert(['name' => 'Wird ersetzt', 'email' => 'weg@example.org', 'status' => 'active']);
        $migrations = DB::table('migrations')->count();

        $this->artisan('db:copy-from-sqlite', ['--path' => $this->file, '--force' => true])->assertSuccessful();

        $this->assertSame(['buero@example.org'], DB::table('users')->pluck('email')->all());
        $post = DB::table('posts')->where('id', 77)->first();
        $this->assertSame(41, (int) $post->author_id);
        $this->assertSame(290, mb_strlen($post->teaser));
        $this->assertSame($source->table('posts')->value('body'), $post->body);
        $this->assertSame($source->table('pages')->count(), DB::table('pages')->count());
        $this->assertSame($migrations, DB::table('migrations')->count());

        // Neue Datensätze bekommen IDs hinter den übernommenen.
        $this->assertGreaterThan(77, DB::table('posts')->insertGetId([
            'title' => 'Neu', 'slug' => 'neu', 'teaser' => 'Neu', 'body' => '', 'status' => 'draft',
        ]));
    }

    #[TestDox('Zu lange Werte werden vorab gemeldet, ohne etwas zu verändern')]
    public function test_reports_too_long_values_before_touching_anything(): void
    {
        $source = $this->sourceDatabase();
        $source->table('users')->insert(['name' => str_repeat('x', 300), 'email' => 'lang@example.org', 'status' => 'active']);
        DB::table('users')->insert(['name' => 'Bleibt', 'email' => 'bleibt@example.org', 'status' => 'active']);

        $this->artisan('db:copy-from-sqlite', ['--path' => $this->file, '--force' => true])
            ->expectsOutputToContain('users.name: bis zu 300 Zeichen, erlaubt 255')
            ->assertFailed();

        $this->assertSame(['bleibt@example.org'], DB::table('users')->pluck('email')->all());
    }

    /** Legt eine migrierte SQLite-Quelldatei an (wie auf dem Server). */
    private function sourceDatabase(): Connection
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Läuft nur gegen MariaDB (DB_CONNECTION=mariadb).');
        }

        touch($this->file);
        config(['database.connections.copy_test_source' => [
            'driver' => 'sqlite', 'database' => $this->file, 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Artisan::call('migrate', ['--database' => 'copy_test_source', '--force' => true]);

        return DB::connection('copy_test_source');
    }
}
