<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Ergebnis der automatischen Tests der ausgelieferten Version. Der Bericht
 * entsteht im Deploy-Workflow (.github/scripts/test-report.php) und liegt
 * als resources/data/test-report.json neben dem Code.
 */
class TestReportController extends Controller
{
    public const AREAS = [
        'Auth' => 'Anmeldung & Zugriffsrechte',
        'Users' => 'Benutzerverwaltung',
        'Content' => 'Seiten, Beiträge & Sicherheit',
        'People' => 'Personenvorstellung',
        'Gruppierungen' => 'Bezirke & Stämme',
        'Calendar' => 'Kalender',
        'Events' => 'Veranstaltungen & Anmeldung',
        'Georgsbote' => 'Georgsbote & Newsletter',
        'Sections' => 'Bolivienpartnerschaft',
        'Instagram' => 'Instagram',
        'Backup' => 'Backups',
    ];

    public function __invoke(): View
    {
        // Pfad überschreibbar für Tests; config/dpsg.php muss ohne Laravel
        // lesbar bleiben (der Deploy-Workflow liest daraus die Kartendaten).
        $file = config('dpsg.test_report') ?? resource_path('data/test-report.json');
        $report = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        $areas = [];
        foreach ($report['areas'] ?? [] as $key => $classes) {
            $tests = collect($classes)->flatMap(fn (array $c) => $c['tests']);
            $areas[$key] = [
                'label' => self::AREAS[$key] ?? $key,
                'classes' => $classes,
                'total' => $tests->count(),
                'passed' => $tests->where('status', 'passed')->count(),
            ];
        }
        // Reihenfolge wie in AREAS, Unbekanntes hinten.
        $order = array_flip(array_keys(self::AREAS));
        uksort($areas, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));

        return view('roadmap-tests', ['report' => $report, 'areas' => $areas]);
    }
}
