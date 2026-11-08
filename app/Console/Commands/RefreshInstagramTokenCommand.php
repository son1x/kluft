<?php

namespace App\Console\Commands;

use App\Models\InstagramKonto;
use App\Models\User;
use App\Notifications\InstagramTokenProblemNotification;
use App\Services\Instagram\InstagramException;
use App\Services\Instagram\InstagramFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Verlängert die Instagram-Long-Lived-Tokens aller Instagram-Konten, die
 * eingeblendet werden oder zum Teilen dienen (F-IG-04, Scheduler
 * wöchentlich). Instagram erlaubt das erst, wenn das Token mindestens 24 h
 * alt ist. Schlägt es fehl, bekommen die Admins eine Mail.
 */
class RefreshInstagramTokenCommand extends Command
{
    protected $signature = 'instagram:refresh-token {--force : Auch erneuern, wenn das Token noch lange gilt}';

    protected $description = 'Erneuert die Zugangstokens der Instagram-Konten';

    public function handle(InstagramFeed $feed): int
    {
        $konten = InstagramKonto::query()->where(fn ($q) => $q->where('aktiv', true)->orWhere('teilen', true))->orderBy('id')->get();
        if ($konten->isEmpty()) {
            $this->info('Kein Instagram-Konto eingerichtet.');

            return self::SUCCESS;
        }

        $fehler = false;
        foreach ($konten as $konto) {
            try {
                $this->info($konto->name.': '.($feed->tokenErneuern($konto, (bool) $this->option('force')) ? 'Token erneuert.' : 'noch nicht fällig.'));
            } catch (InstagramException $e) {
                $konto->forceFill(['fehler' => mb_substr('Token-Erneuerung: '.$e->getMessage(), 0, 500)])->save();
                $this->notifyAdmins($konto, $e->getMessage());
                $this->error($konto->name.': '.$e->getMessage());
                $fehler = true;
            }
        }

        return $fehler ? self::FAILURE : self::SUCCESS;
    }

    private function notifyAdmins(InstagramKonto $konto, string $error): void
    {
        $admins = User::query()
            ->where('status', 'active')
            ->whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['admin', 'superadmin']))
            ->get();

        try {
            Notification::send($admins, new InstagramTokenProblemNotification($error, $konto->token_ablauf?->format('d.m.Y'), $konto->name, $konto->teilen));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
