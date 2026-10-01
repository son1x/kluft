<?php

namespace App\Console\Commands;

use App\Models\IntegrationToken;
use App\Models\User;
use App\Notifications\InstagramTokenProblemNotification;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Verlängert das Instagram-Long-Lived-Token (F-IG-04, Scheduler wöchentlich).
 * Instagram erlaubt das erst, wenn das Token mindestens 24 h alt ist.
 */
class RefreshInstagramTokenCommand extends Command
{
    protected $signature = 'instagram:refresh-token {--force : Auch erneuern, wenn die letzte Erneuerung weniger als 24 h her ist}';

    protected $description = 'Erneuert das Instagram-Zugangstoken';

    public function handle(InstagramClient $instagram): int
    {
        $token = $instagram->token();

        if (! $token) {
            $this->info('Instagram ist nicht verbunden.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $token->refreshed_at?->gt(now()->subDay())) {
            $this->info('Token ist jünger als 24 Stunden.');

            return self::SUCCESS;
        }

        try {
            $fresh = $instagram->refreshToken();
            $token->update([
                'access_token' => $fresh['access_token'],
                'expires_at' => $fresh['expires_in'] > 0 ? now()->addSeconds($fresh['expires_in']) : now()->addDays(60),
                'refreshed_at' => now(),
                'last_error' => null,
            ]);
            $this->info('Token erneuert.');

            return self::SUCCESS;
        } catch (InstagramException $e) {
            $token->update(['last_error' => $e->getMessage()]);
            $this->notifyAdmins($token, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function notifyAdmins(IntegrationToken $token, string $error): void
    {
        $admins = User::query()
            ->where('status', 'active')
            ->whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['admin', 'superadmin']))
            ->get();

        try {
            Notification::send($admins, new InstagramTokenProblemNotification($error, $token->expires_at?->format('d.m.Y')));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
