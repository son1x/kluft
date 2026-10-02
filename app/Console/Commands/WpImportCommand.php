<?php

namespace App\Console\Commands;

use App\Services\WordPress\WordPressImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * WordPress-Migration (Pflichtenheft F-MIG-01): Beiträge der alten
 * Website als Entwürfe übernehmen. Wiederholbar; mit --dry-run ohne
 * Änderungen.
 */
class WpImportCommand extends Command
{
    protected $signature = 'wp:import
        {--since= : Nur Beiträge ab diesem Datum (YYYY-MM-DD); Standard: letzte 3 Jahre}
        {--limit= : Höchstens so viele Beiträge}
        {--source= : WordPress-Adresse (Standard aus config dpsg.wp_import.source)}
        {--dry-run : Nur prüfen und berichten, nichts speichern oder herunterladen}';

    protected $description = 'Beiträge aus WordPress als Entwürfe importieren (mit Medien und 301-Weiterleitungen)';

    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))
            : now()->subYears((int) config('dpsg.wp_import.since_years', 3));
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? 'Testlauf (nichts wird gespeichert)' : 'Import').' ab '.$since->toDateString().' …');

        $run = (new WordPressImporter($this->option('source') ?: null))->run(
            $since,
            $dryRun,
            $this->option('limit') ? (int) $this->option('limit') : null,
            fn (string $title) => $this->line('  · '.$title),
        );

        $this->newLine();
        $this->table(['Ergebnis', 'Anzahl'], collect($run->counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        $problems = collect($run->log)->whereIn('action', ['fehler', 'übersprungen']);
        foreach ($problems as $entry) {
            $this->warn("{$entry['action']}: {$entry['title']} – {$entry['message']}");
        }

        $this->info("Bericht #{$run->id} im Backend unter „WordPress-Import“.");

        return ($run->counts['fehler'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
