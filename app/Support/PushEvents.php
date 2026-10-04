<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ereignisse, die Personen im Backend als Push-Nachricht (ntfy) abonnieren
 * können – jeweils nur, wenn ihre Rechte dazu passen.
 */
class PushEvents
{
    public const ANMELDUNGEN = 'anmeldungen';

    public const BEITRAEGE_FREIGABE = 'beitraege_freigabe';

    public const BEITRAEGE_NEU = 'beitraege_neu';

    public const RECHTE = 'rechte';

    public const BENUTZER = 'benutzer';

    public const SYSTEM = 'system';

    public const WHATSAPP = 'whatsapp';

    public const LABELS = [
        self::ANMELDUNGEN => 'Anmeldungen zu meinen Veranstaltungen (neu, Warteliste, Storno)',
        self::BEITRAEGE_FREIGABE => 'Beiträge, die auf meine Freigabe warten',
        self::BEITRAEGE_NEU => 'Neu veröffentlichte Beiträge',
        self::RECHTE => 'Neue Rechte für mich',
        self::BENUTZER => 'Konten zur Freischaltung und weitreichende Rechtevergaben',
        self::SYSTEM => 'Störungen der Website (Monitoring)',
        self::WHATSAPP => 'WhatsApp-Newsletter: Aussendungen zur Freigabe und Störungen',
    ];

    /**
     * @return array<string, string>
     */
    public static function availableFor(User $user): array
    {
        $allowed = [
            self::ANMELDUNGEN => $user->isVerwaltung() || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin'),
            self::BEITRAEGE_FREIGABE => Gate::forUser($user)->allows('publish', Post::class),
            self::BEITRAEGE_NEU => $user->roleAssignments->isNotEmpty(),
            self::RECHTE => true,
            self::BENUTZER => $user->isVerwaltung(),
            self::SYSTEM => $user->isAdmin(),
            self::WHATSAPP => WhatsAppAccess::manage($user),
        ];

        return array_filter(self::LABELS, fn (string $key) => $allowed[$key], ARRAY_FILTER_USE_KEY);
    }
}
