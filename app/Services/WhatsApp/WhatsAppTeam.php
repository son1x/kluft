<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Notifications\WhatsAppNotice;
use App\Support\WhatsAppAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Wer im Team Hinweise zum WhatsApp-Newsletter bekommt.
 */
class WhatsAppTeam
{
    /**
     * Störungen an WhatsApp-Admins und Admins – je Titel höchstens alle 6 Stunden.
     */
    public function alert(string $title, string $body, bool $urgent = true): void
    {
        if (! Cache::add('whatsapp-alert|'.md5($title), 1, now()->addHours(6))) {
            return;
        }

        $recipients = $this->users()->filter(fn (User $u) => $u->isWhatsAppAdmin());
        Notification::send($recipients, new WhatsAppNotice($title, $body, url('/admin'), $urgent));
    }

    /** An alle, die freigeben dürfen – außer der auslösenden Person. */
    public function notifyApprovers(string $title, string $body, string $link, ?User $except = null): void
    {
        $recipients = $this->users()
            ->filter(fn (User $u) => WhatsAppAccess::manage($u))
            ->reject(fn (User $u) => $except !== null && $u->is($except));
        Notification::send($recipients, new WhatsAppNotice($title, $body, $link));
    }

    /**
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        return User::query()->where('status', 'active')->with('roleAssignments')->get();
    }
}
