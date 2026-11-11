<?php

namespace App\Console\Commands;

use App\Mail\KontoInaktivMail;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Ungenutzte Backend-Konten (docs/datenschutz-ueberblick.md): einen Monat
 * vor Ablauf von `dpsg.konten_sperren_monate` ohne Anmeldung warnen, danach
 * sperren. Lokale Notfall-Admins sind ausgenommen.
 */
class InaktiveKontenCommand extends Command
{
    protected $signature = 'konten:inaktive';

    protected $description = 'Warnt vor und sperrt Backend-Konten, die lange nicht genutzt wurden';

    public function handle(): int
    {
        $monate = (int) config('dpsg.konten_sperren_monate');
        if ($monate <= 0) {
            $this->info('Ausgeschaltet.');

            return self::SUCCESS;
        }
        $gewarnt = 0;
        $gesperrt = 0;

        User::query()->where('status', 'active')->where('is_local_admin', false)->each(function (User $user) use ($monate, &$gewarnt, &$gesperrt) {
            $zuletzt = $user->last_login_at ?? $user->created_at;
            if ($zuletzt === null || $zuletzt->gt(now()->subMonths($monate - 1))) {
                return;
            }
            $sperre = $zuletzt->copy()->addMonths($monate);

            // Erst sperren, wenn die Vorwarnung mindestens 14 Tage her ist.
            if ($sperre->isPast() && $user->inaktiv_gewarnt_at?->lte(now()->subDays(14))) {
                $user->forceFill(['status' => 'disabled'])->save();
                AuditLogger::log('konto.inaktiv_gesperrt', null, 'user', $user->id);
                $this->mail($user, true, now());
                $gesperrt++;

                return;
            }
            if ($user->inaktiv_gewarnt_at === null) {
                $user->forceFill(['inaktiv_gewarnt_at' => now()])->saveQuietly();
                AuditLogger::log('konto.vorwarnung', null, 'user', $user->id);
                $this->mail($user, false, $sperre->max(now()->addDays(14)));
                $gewarnt++;
            }
        });

        $this->info("Vorgewarnt: {$gewarnt}, gesperrt: {$gesperrt}.");

        return self::SUCCESS;
    }

    private function mail(User $user, bool $gesperrt, Carbon $datum): void
    {
        if (filled($user->email)) {
            Mail::to($user->email)->queue(new KontoInaktivMail($user->name, $gesperrt, $datum->format('d.m.Y')));
        }
    }
}
