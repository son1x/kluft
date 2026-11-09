<?php

namespace App\Console\Commands;

use App\Support\AuditLogger;
use App\Support\Wartung;
use Illuminate\Console\Command;

/**
 * Wartungsmodus von Hand ohne Backend (App\Support\Wartung), auch über
 * Actions → Server-Prüfung. `php artisan down` bleibt für Deploy und Update.
 */
class WartungCommand extends Command
{
    protected $signature = 'kluft:wartung
        {aktion=status : an, aus oder status}
        {--text= : Text auf der Wartungsseite}
        {--nur-admins : Backend nur für Admins (Redaktionsstopp)}';

    protected $description = 'Wartungsmodus der Website ein- oder ausschalten (Besucher*innen sehen die Wartungsseite)';

    public function handle(): int
    {
        $aktion = (string) $this->argument('aktion');
        if (! in_array($aktion, ['an', 'aus', 'status'], true)) {
            $this->error('Bitte „an“, „aus“ oder „status“ angeben.');

            return self::INVALID;
        }

        if ($aktion === 'an') {
            $alt = Wartung::stand();
            Wartung::an($this->option('text') ?: ($alt['text'] !== Wartung::TEXT ? $alt['text'] : null), $alt['bis'], (bool) $this->option('nur-admins'));
            AuditLogger::log('wartung.an');
        } elseif ($aktion === 'aus') {
            Wartung::aus();
            AuditLogger::log('wartung.aus');
        }

        $s = Wartung::stand();
        $this->line('Wartungsmodus: '.($s['aktiv'] ? 'an seit '.$s['seit']?->timezone(config('app.timezone'))->format('d.m.Y H:i') : 'aus'));
        if ($s['aktiv']) {
            $this->line('Backend: '.($s['nur_admins'] ? 'nur Admins' : 'alle Angemeldeten'));
            $this->line('Text: '.$s['text']);
        }

        return self::SUCCESS;
    }
}
