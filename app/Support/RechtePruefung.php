<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jährliche Prüfung: Wer hat sensible Rechte (Teilnehmende, Kasse,
 * Prävention, Datenschutz, Benutzer, Admin)? Abschluss mit Datum und Person
 * (docs/datenschutz-ueberblick.md).
 */
class RechtePruefung
{
    public const SCHLUESSEL = 'rechte_geprueft_am';

    /** Erinnerung so viele Monate nach der letzten Prüfung. */
    public const MONATE = 12;

    /** Hinweis, wenn so viele Monate keine Anmeldung. */
    public const INAKTIV_MONATE = 6;

    public static function zugriff(?User $user): bool
    {
        return $user instanceof User && $user->darf('benutzer');
    }

    /** Ist die Rolle sensibel (Admin oder ein sensibles Recht)? */
    public static function sensibleRolle(string $role): bool
    {
        return in_array($role, ['admin', 'superadmin'], true) || Rechte::sensibel(Rechte::fuerRolle($role));
    }

    /**
     * Aktive Konten mit sensiblen Rollen, je mit den betreffenden Zuweisungen.
     *
     * @return Collection<int, array{user: User, rollen: list<array{name: string, gueltig_bis: ?Carbon}>, rechte: list<string>, hinweise: list<string>}>
     */
    public static function personen(): Collection
    {
        $namen = RoleCatalog::all();

        return User::query()->where('status', 'active')->with('roleAssignments')->orderBy('name')->get()
            ->map(function (User $user) use ($namen) {
                $zuweisungen = $user->roleAssignments->filter(fn (RoleAssignment $a) => self::sensibleRolle($a->role))->values();
                if ($zuweisungen->isEmpty()) {
                    return null;
                }
                $rechte = $zuweisungen->flatMap(fn (RoleAssignment $a) => in_array($a->role, ['admin', 'superadmin'], true)
                    ? ['Alles (Admin)']
                    : collect(Rechte::fuerRolle($a->role))->filter(fn (string $r) => Rechte::KATALOG[$r]['sensibel'] ?? false)
                        ->map(fn (string $r) => Rechte::KATALOG[$r]['label']))->unique()->values()->all();

                $hinweise = [];
                if ($user->last_login_at === null || $user->last_login_at->lt(now()->subMonths(self::INAKTIV_MONATE))) {
                    $hinweise[] = $user->last_login_at ? 'seit über '.self::INAKTIV_MONATE.' Monaten nicht angemeldet' : 'noch nie angemeldet';
                }
                if ($zuweisungen->contains(fn (RoleAssignment $a) => $a->gueltig_bis === null && ! in_array($a->role, ['admin', 'superadmin'], true))) {
                    $hinweise[] = 'ohne „gültig bis“';
                }

                return [
                    'user' => $user,
                    'rollen' => $zuweisungen->map(fn (RoleAssignment $a) => [
                        'name' => $namen[RoleCatalog::encode($a)] ?? $a->role,
                        'gueltig_bis' => $a->gueltig_bis,
                    ])->all(),
                    'rechte' => $rechte,
                    'hinweise' => $hinweise,
                ];
            })->filter()->values();
    }

    /** @return array{am: Carbon, von: ?string}|null */
    public static function letzte(): ?array
    {
        $eintrag = SiteSetting::query()->with('updatedBy')->find(self::SCHLUESSEL);

        return $eintrag ? ['am' => Carbon::parse($eintrag->value), 'von' => $eintrag->updatedBy?->name] : null;
    }

    public static function faellig(): bool
    {
        $letzte = self::letzte();

        return $letzte === null || $letzte['am']->lt(now()->subMonths(self::MONATE));
    }

    public static function abschliessen(User $user): void
    {
        $anzahl = self::personen()->count();
        SiteSetting::query()->updateOrCreate(['key' => self::SCHLUESSEL], ['value' => now()->toDateString(), 'updated_by' => $user->id]);
        AuditLogger::log("rechte.geprueft:{$anzahl}", $user);
    }
}
