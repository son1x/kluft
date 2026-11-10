<?php

namespace App\Console\Commands;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\RollenAblaufNotification;
use App\Support\AuditLogger;
use App\Support\RoleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Rollen mit Ablaufdatum: 30 und 7 Tage vorher ein Hinweis an die Person
 * und die Verwaltung; ist das Datum vorbei, wird die Rolle entfernt
 * (Protokoll „Recht abgelaufen“). Läuft täglich.
 */
class RollenAblaufCommand extends Command
{
    public const HINWEIS_TAGE = [30, 7];

    protected $signature = 'rollen:ablauf';

    protected $description = 'Hinweise zu bald ablaufenden Rollen senden und abgelaufene Rollen entfernen';

    public function handle(): int
    {
        $hinweise = RoleAssignment::query()->with('user')
            ->where(function ($q) {
                foreach (self::HINWEIS_TAGE as $tage) {
                    $q->orWhereDate('gueltig_bis', today()->addDays($tage));
                }
            })
            ->orderBy('gueltig_bis')->get()
            ->filter(fn (RoleAssignment $a) => $a->user?->status === 'active');

        $eintrag = fn (RoleAssignment $a) => ['person' => (string) $a->user?->name, 'rolle' => RoleCatalog::label($a), 'bis' => (string) $a->gueltig_bis?->format('d.m.Y')];

        foreach ($hinweise->groupBy('user_id') as $zuweisungen) {
            $zuweisungen->first()->user->notify(new RollenAblaufNotification($zuweisungen->map($eintrag)->values()->all()));
        }

        if ($hinweise->isNotEmpty()) {
            $verwaltung = User::query()->where('status', 'active')
                ->whereHas('roleAssignments', fn ($q) => $q->where('role', 'verwaltung'))->get();
            Notification::send($verwaltung, new RollenAblaufNotification($hinweise->map($eintrag)->values()->all(), verwaltung: true));
        }

        $abgelaufen = RoleAssignment::query()->whereDate('gueltig_bis', '<', today())->get();
        foreach ($abgelaufen as $a) {
            RoleAssignment::withoutEvents(fn () => $a->delete());
            AuditLogger::log('role.expired:'.RoleCatalog::encode($a), null, 'user', $a->user_id);
        }

        $this->info($hinweise->count().' Hinweise, '.$abgelaufen->count().' Rollen entfernt.');

        return self::SUCCESS;
    }
}
