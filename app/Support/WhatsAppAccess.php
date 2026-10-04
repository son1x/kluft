<?php

namespace App\Support;

use App\Models\User;

/**
 * Wer darf was im WhatsApp-Newsletter (docs/whatsapp_newsletter.md, 7.1)?
 */
class WhatsAppAccess
{
    /** Aussendungen entwerfen. */
    public static function draft(?User $user): bool
    {
        return $user !== null && ($user->isWhatsAppAdmin() || $user->isVerwaltung() || $user->hasRole('vorstand'));
    }

    /** Freigeben, versenden, Vorlagen und Abonnent:innen verwalten. */
    public static function manage(?User $user): bool
    {
        return $user !== null && ($user->isWhatsAppAdmin() || $user->isVerwaltung());
    }

    /** Verbindung zu Meta (Zugangsdaten, Nummer, Webhook). */
    public static function connect(?User $user): bool
    {
        return $user !== null && $user->isWhatsAppAdmin();
    }
}
