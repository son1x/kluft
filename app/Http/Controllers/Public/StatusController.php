<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Health\HealthChecks;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Statusseite (/status) und maschinenlesbarer Status (/api/status) für
 * externe Überwachung: HTTP 503, sobald ein Bereich „fehler“ meldet.
 * Ergebnisse werden eine Minute zwischengespeichert.
 */
class StatusController extends Controller
{
    public function show(HealthChecks $checks): View
    {
        $results = $this->results($checks);

        return view('status', ['results' => $results, 'overall' => HealthChecks::overall($results), 'checkedAt' => now()]);
    }

    public function json(HealthChecks $checks): JsonResponse
    {
        $results = $this->results($checks);
        $overall = HealthChecks::overall($results);

        return response()->json([
            'status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'checks' => collect($results)->mapWithKeys(fn (array $r) => [$r['key'] => ['status' => $r['status'], 'message' => $r['message']]]),
        ], $overall === HealthChecks::FAIL ? 503 : 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return list<array{key: string, label: string, status: string, message: string}>
     */
    private function results(HealthChecks $checks): array
    {
        return Cache::remember('status:results', 60, fn () => $checks->run());
    }
}
