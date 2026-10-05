<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Backup\BackupService;
use App\Support\Roadmap;
use Illuminate\Contracts\View\View;

/**
 * Projektstand nach Pflichtenheft (außerhalb des CMS gepflegt in
 * resources/data/roadmap.php).
 */
class RoadmapController extends Controller
{
    public function __invoke(Roadmap $roadmap): View
    {
        $data = $roadmap->data();

        return view('roadmap', [
            'stand' => $data['stand'],
            'currentPhase' => $data['current_phase'],
            'phases' => $data['phases'],
            'acceptance' => $data['acceptance'],
            'feedback' => $data['feedback'] ?? [],
            'chapters' => $roadmap->chapters(),
            'counts' => $roadmap->counts(),
            'todos' => $roadmap->todos(),
            // Anleitung oben auf der Seite, bis Backups laufen (nur ja/nein,
            // keine Geheimnisse).
            'backup' => [
                'healthy' => $roadmap->backupsHealthy(),
                'password' => mb_strlen((string) config('dpsg.backup.password')) >= 16,
                'webdav' => filled(config('dpsg.backup.webdav_url')),
                'lastRun' => BackupService::lastRun()['at'] ?? null,
            ],
        ]);
    }
}
