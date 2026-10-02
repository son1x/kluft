<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Einmaliger Umzug von SQLite in die Standard-Datenbank (MariaDB/MySQL).
 *
 * Voraussetzung: php artisan migrate ist auf dem Ziel gelaufen, das Schema
 * ist also identisch. Der Befehl leert jede Tabelle im Ziel und kopiert alle
 * Zeilen mit ihren IDs aus der SQLite-Datei; Cache, Sitzungen, Warteschlange
 * und die Migrationsliste bleiben unberührt. Zum Schluss werden die
 * Zeilenzahlen verglichen.
 */
class DbCopyFromSqliteCommand extends Command
{
    protected $signature = 'db:copy-from-sqlite
        {--path= : Pfad zur SQLite-Datei (Standard: database/database.sqlite)}
        {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Kopiert alle Inhalte aus der SQLite-Datei in die konfigurierte MariaDB';

    /** Tabellen, die nicht kopiert werden (flüchtig oder vom Ziel selbst verwaltet). */
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    private const CHUNK = 200;

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('database.sqlite');
        if (! is_file($path)) {
            $this->error("SQLite-Datei nicht gefunden: {$path}");

            return self::FAILURE;
        }

        $target = DB::connection();
        if (! in_array($target->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Ziel muss MariaDB/MySQL sein, ist aber '.$target->getDriverName().'.');

            return self::FAILURE;
        }

        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        $source = DB::connection('sqlite_source');

        $sourceTables = collect($source->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
            ->pluck('name');
        $targetTables = collect(Schema::getTableListing(schemaQualified: false));

        $tables = $sourceTables->intersect($targetTables)->diff(self::SKIP)->sort()->values();
        $missing = $sourceTables->diff($targetTables)->diff(self::SKIP);
        if ($missing->isNotEmpty()) {
            $this->warn('Nur in SQLite vorhanden (nicht kopiert): '.$missing->implode(', '));
        }

        $tooLong = $this->findTooLongValues($source, $tables->all());
        if ($tooLong !== []) {
            $this->error('Diese Werte passen nicht in die Zielspalten – erst das Schema anpassen (Migration), dann erneut starten:');
            foreach ($tooLong as $problem) {
                $this->line('  '.$problem);
            }

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Alle '.$tables->count().' Tabellen im Ziel ('.$target->getDatabaseName().') werden geleert und neu befüllt. Fortfahren?')) {
            return self::FAILURE;
        }

        $rows = [];
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                $columns = array_values(array_intersect(
                    Schema::connection('sqlite_source')->getColumnListing($table),
                    Schema::getColumnListing($table),
                ));

                $target->table($table)->truncate();

                $order = in_array('id', $columns, true) ? 'id' : $columns[0];
                $copied = 0;
                $source->table($table)->select($columns)->orderBy($order)
                    ->chunk(self::CHUNK, function ($chunk) use ($target, $table, &$copied) {
                        $target->table($table)->insert($chunk->map(fn ($row) => (array) $row)->all());
                        $copied += $chunk->count();
                    });

                $rows[] = [$table, $source->table($table)->count(), $copied, $target->table($table)->count()];
            }
        } catch (QueryException $e) {
            // Nur die Fehlermeldung ausgeben, nicht die (personenbezogenen) Zeilen aus dem SQL.
            $this->error('Abbruch bei Tabelle '.($table ?? '?').': '.Str::before($e->getPrevious()?->getMessage() ?? $e->getMessage(), ' (Connection'));

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->table(['Tabelle', 'SQLite', 'kopiert', 'Ziel'], $rows);

        $mismatch = array_filter($rows, fn ($row) => $row[1] !== $row[3]);
        if ($mismatch !== []) {
            $this->error('Zeilenzahlen weichen ab: '.implode(', ', array_column($mismatch, 0)));

            return self::FAILURE;
        }

        $this->info('Fertig: '.array_sum(array_column($rows, 1)).' Zeilen in '.count($rows).' Tabellen übernommen.');

        return self::SUCCESS;
    }

    /**
     * SQLite prüft keine Längen, MariaDB im Strict-Modus schon. Vorab alle
     * Werte suchen, die abgelehnt würden, damit nichts halb kopiert wird.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function findTooLongValues(ConnectionInterface $source, array $tables): array
    {
        $problems = [];
        foreach ($tables as $table) {
            foreach (Schema::getColumns($table) as $column) {
                [$limit, $unit] = match ($column['type_name']) {
                    'varchar', 'char' => [(int) Str::between($column['type'], '(', ')'), 'chars'],
                    'tinytext' => [255, 'bytes'],
                    'text' => [65535, 'bytes'],
                    'mediumtext' => [16777215, 'bytes'],
                    default => [null, null],
                };
                if (! $limit || ! Schema::connection('sqlite_source')->hasColumn($table, $column['name'])) {
                    continue;
                }

                $name = '"'.str_replace('"', '""', $column['name']).'"';
                $expression = $unit === 'bytes' ? "length(cast({$name} as blob))" : "length({$name})";
                $max = (int) $source->table($table)->selectRaw("max({$expression}) as m")->value('m');
                if ($max > $limit) {
                    $problems[] = "{$table}.{$column['name']}: bis zu {$max} ".($unit === 'bytes' ? 'Bytes' : 'Zeichen').", erlaubt {$limit}";
                }
            }
        }

        return $problems;
    }
}
