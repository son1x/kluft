<?php

namespace App\Console\Commands;

use App\Models\InstagramKonto;
use App\Services\Instagram\InstagramFeed;
use Illuminate\Console\Command;

/**
 * Instagram-Einblendung: neueste Beiträge aller aktiven Konten holen
 * (stündlich über den Scheduler, docs/instagram.md).
 */
class InstagramFeedCommand extends Command
{
    protected $signature = 'instagram:feed';

    protected $description = 'Holt die neuesten Beiträge der eingeblendeten Instagram-Konten';

    public function handle(InstagramFeed $feed): int
    {
        foreach (InstagramKonto::query()->where('aktiv', true)->get() as $konto) {
            $anzahl = $feed->abrufen($konto);
            $this->line($konto->name.': '.($konto->fehler ?? $anzahl.' Beiträge'));
        }

        return self::SUCCESS;
    }
}
