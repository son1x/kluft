<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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
        ]);
    }
}
