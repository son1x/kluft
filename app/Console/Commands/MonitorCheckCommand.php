<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemProblemNotification;
use App\Support\Health\HealthChecks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Monitoring (Pflichtenheft 10.2): prüft regelmäßig den Betriebszustand und
 * schreibt den Admins bei Störungen – je Störung höchstens alle sechs
 * Stunden. Fällt der Cronjob selbst aus, meldet das der GitHub-Workflow
 * „Monitoring“ von außen.
 */
class MonitorCheckCommand extends Command
{
    public const QUIET_HOURS = 6;

    protected $signature = 'monitor:check';

    protected $description = 'Prüft den Betriebszustand und alarmiert die Admins bei Störungen';

    public function handle(HealthChecks $checks): int
    {
        $results = $checks->run();
        foreach ($results as $r) {
            $this->line(str_pad($r['status'], 8).' '.$r['label'].': '.$r['message']);
        }

        $new = collect($results)
            ->where('status', HealthChecks::FAIL)
            ->reject(fn (array $r) => Cache::has('monitor:alerted:'.$r['key']))
            ->values();
        if ($new->isEmpty()) {
            return self::SUCCESS;
        }

        $admins = User::query()->where('status', 'active')
            ->whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['admin', 'superadmin']))
            ->get();

        try {
            Notification::sendNow($admins, new SystemProblemNotification($new->map(fn ($r) => ['label' => $r['label'], 'message' => $r['message']])->all()));
            $new->each(fn (array $r) => Cache::put('monitor:alerted:'.$r['key'], true, now()->addHours(self::QUIET_HOURS)));
            $this->warn('Alarm an '.$admins->count().' Admin(s) geschickt.');
        } catch (Throwable $e) {
            report($e);
        }

        return self::SUCCESS;
    }
}
