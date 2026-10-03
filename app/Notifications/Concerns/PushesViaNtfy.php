<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Channels\NtfyChannel;

/**
 * Zusätzlich als Push (ntfy), wenn die Person das Ereignis abonniert hat.
 */
trait PushesViaNtfy
{
    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    protected function withPush(object $notifiable, string $event, array $channels = ['mail']): array
    {
        if ($notifiable instanceof User && $notifiable->wantsPush($event)) {
            $channels[] = NtfyChannel::class;
        }

        return $channels;
    }
}
