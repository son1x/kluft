<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Alle vergebbaren Rollen-Zuweisungen (Rolle + Scope) und wer welche
 * vergeben darf, s. Pflichtenheft 5.3–5.6 (F-USR-01/05).
 *
 * Eine Zuweisung wird als Schlüssel "rolle|scope_type|scope_key" kodiert,
 * damit sie im Backend als eine einzige Auswahl erscheint.
 */
class RoleCatalog
{
    /**
     * @return array<string, string> Schlüssel => Beschriftung
     */
    public static function sections(): array
    {
        return collect(config('dpsg.sections'))->map(fn (array $section) => $section['label'])->all();
    }

    /**
     * @return array<string, string> Schlüssel => Beschriftung
     */
    public static function all(): array
    {
        $stages = collect(config('dpsg.stages'))->map(fn (array $stage) => $stage['label']);
        $options = [];

        foreach ($stages as $key => $label) {
            $options["stufenadmin|stage|{$key}"] = "Stufenadmin – {$label}";
        }
        foreach (self::sections() as $key => $label) {
            $options["sektionsadmin|section|{$key}"] = "Sektionsadmin – {$label}";
        }
        // Lesen die Leitungen Veranstaltungen nur, gibt es das Schreiben je Bereich einzeln.
        if (self::leitungLiestVeranstaltungen()) {
            foreach ($stages as $key => $label) {
                $options["veranstaltungen|stage|{$key}"] = "Veranstaltungen bearbeiten – {$label}";
            }
            foreach (self::sections() as $key => $label) {
                $options["veranstaltungen|section|{$key}"] = "Veranstaltungen bearbeiten – {$label}";
            }
        }

        $options['autor|own|'] = 'Autor:in (ohne Zuordnung)';
        foreach ($stages as $key => $label) {
            $options["autor|own|{$key}"] = "Autor:in – {$label}";
        }
        foreach (self::sections() as $key => $label) {
            $options["autor|own|{$key}"] = "Autor:in – {$label}";
        }

        $options['vorstand|global|'] = 'Vorstand';
        if (config('dpsg.site.level') !== 'Stamm') {
            $options['bildungsreferent|global|'] = 'Bildungsreferent*in';
        }
        $options['verwaltung|global|'] = 'Verwaltung';
        if (Features::verfuegbar(Features::MITGLIEDSBEITRAEGE)) {
            $options['kasse|global|'] = 'Kasse (Mitgliedsbeiträge)';
        }
        if (Features::verfuegbar(Features::SAMMELBESTELLUNG)) {
            $options['sammelbestellung|global|'] = 'Sammelbestellung (Rüsthaus)';
        }
        if (WhatsAppMode::enabled()) {
            $options['whatsapp|global|'] = 'WhatsApp-Newsletter-Admin';
        }
        if (Features::enabled(Features::MATERIALVERLEIH)) {
            $options['material|global|'] = 'Materialwart*in';
        }
        // Eigene Rollen aus der Rechte-Matrix (docs/rollen.md).
        foreach (Rechte::eigene() as $schluessel => $rolle) {
            if ($rolle->geltung === 'bereich') {
                foreach ($stages as $key => $label) {
                    $options["{$schluessel}|stage|{$key}"] = "{$rolle->name} – {$label}";
                }
                foreach (self::sections() as $key => $label) {
                    $options["{$schluessel}|section|{$key}"] = "{$rolle->name} – {$label}";
                }
            } else {
                $options["{$schluessel}|global|"] = $rolle->name;
            }
        }
        $options['admin|global|'] = 'Admin (Technik)';
        $options['superadmin|global|'] = 'Super-Admin';

        return $options;
    }

    /**
     * Auswahl gruppiert für Select-Felder: Rollen für die ganze Website
     * zuerst, dann Bereiche, Autor:innen, eigene Rollen, Technik.
     *
     * @param  array<string, string>  $optionen  Schlüssel => Beschriftung (z. B. aus grantableBy())
     * @return array<string, array<string, string>>
     */
    public static function gruppiert(array $optionen): array
    {
        $gruppen = ['Ganze Website' => [], 'Leitung je Stufe/Sektion' => [], 'Veranstaltungen je Bereich' => [],
            'Autor:innen' => [], 'Eigene Rollen' => [], 'Technik' => []];
        foreach ($optionen as $key => $label) {
            $role = self::decode($key)['role'];
            $gruppe = match (true) {
                in_array($role, ['admin', 'superadmin'], true) => 'Technik',
                Rechte::istEigen($role) => 'Eigene Rollen',
                in_array($role, ['stufenadmin', 'sektionsadmin'], true) => 'Leitung je Stufe/Sektion',
                $role === 'veranstaltungen' => 'Veranstaltungen je Bereich',
                $role === 'autor' => 'Autor:innen',
                default => 'Ganze Website',
            };
            $gruppen[$gruppe][$key] = $label;
        }

        return array_filter($gruppen);
    }

    /**
     * Zweistufige Auswahl (erst Rolle, dann Bereich) aus kombinierten
     * Schlüsseln „rolle|typ|bereich“. Rollen in der Reihenfolge von
     * gruppiert(); Bereiche je Rolle nach Stufen und Sektionen gruppiert.
     * Rollen nur für die ganze Website haben keine Bereiche.
     *
     * @param  array<string, string>  $optionen
     * @return array{rollen: array<string, string>, bereiche: array<string, array<string, array<string, string>>>}
     */
    public static function rollenAuswahl(array $optionen): array
    {
        $stufen = array_keys((array) config('dpsg.stages'));
        $rollen = [];
        $bereiche = [];
        foreach (array_merge(...array_values(self::gruppiert($optionen))) as $key => $label) {
            ['role' => $role, 'scope_type' => $typ, 'scope_key' => $bereich] = self::decode($key);
            if ($typ === 'global') {
                $rollen[$role] = $label;

                continue;
            }
            $rollen[$role] ??= trim(Str::before(Str::before($label, ' – '), ' ('));
            $gruppe = match (true) {
                $bereich === null => 'Allgemein',
                $typ === 'stage' || in_array($bereich, $stufen, true) => 'Stufen',
                default => 'Sektionen',
            };
            $bereiche[$role][$gruppe][$typ.'|'.$bereich] = $bereich === null ? 'ohne Zuordnung' : Str::after($label, ' – ');
        }

        return ['rollen' => $rollen, 'bereiche' => $bereiche];
    }

    /** Kombinierter Schlüssel aus den beiden Feldern der Auswahl. */
    public static function ausAuswahl(?string $rolle, ?string $bereich): string
    {
        return $rolle.'|'.(filled($bereich) ? $bereich : 'global|');
    }

    /**
     * Zuweisungen, die $actor vergeben bzw. entziehen darf.
     *
     * @return array<string, string>
     */
    public static function grantableBy(User $actor): array
    {
        $all = self::all();

        if ($actor->isSuperAdmin()) {
            return $all;
        }

        if ($actor->isAdmin()) {
            unset($all['superadmin|global|']);

            return $all;
        }

        if ($actor->darf('benutzer')) {
            unset($all['admin|global|'], $all['superadmin|global|']);

            return $all;
        }

        // „Autor:innen freischalten“ für die ganze Website (z. B. Bildungsreferent*innen):
        // alle Bereiche, auch ohne Zuordnung. Je Bereich (Stufen-/Sektionsadmins):
        // nur Autor:innen im eigenen Bereich (5.5).
        $keys = self::delegatedScopeKeys($actor);
        if ($actor->darfUeberall('autoren')) {
            $keys[] = '';
        }

        return collect($all)
            ->filter(fn ($label, string $key) => in_array($key, array_map(
                fn (string $scopeKey) => "autor|own|{$scopeKey}",
                $keys
            ), true))
            ->all();
    }

    /**
     * Scope-Schlüssel, in denen $actor delegieren darf (leer für Rollen
     * ohne Delegationsrecht).
     *
     * @return list<string>
     */
    public static function delegatedScopeKeys(User $actor): array
    {
        // „Autor:innen freischalten“ für die ganze Website: alle Bereiche.
        if ($actor->darfUeberall('autoren') && ! $actor->darf('benutzer')) {
            return array_values(array_unique([...array_keys((array) config('dpsg.stages')), ...array_keys(self::sections())]));
        }

        return array_values(array_unique([...$actor->bereicheFuer('autoren', 'stage'), ...$actor->bereicheFuer('autoren', 'section')]));
    }

    public static function canManageUsersGlobally(User $actor): bool
    {
        return $actor->darf('benutzer');
    }

    public static function encode(RoleAssignment|array $assignment): string
    {
        $assignment = $assignment instanceof RoleAssignment ? $assignment->only(['role', 'scope_type', 'scope_key']) : $assignment;

        return "{$assignment['role']}|{$assignment['scope_type']}|".($assignment['scope_key'] ?? '');
    }

    /**
     * @return array{role: string, scope_type: string, scope_key: ?string}
     */
    public static function decode(string $key): array
    {
        [$role, $scopeType, $scopeKey] = array_pad(explode('|', $key, 3), 3, '');

        return ['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey === '' ? null : $scopeKey];
    }

    /**
     * Rolle mit kurzer Erklärung für Mails an die betroffene Person.
     */
    public static function describe(string $key): string
    {
        $what = self::erklaerung(self::decode($key)['role']);

        return (self::all()[$key] ?? $key).($what ? " ({$what})" : '');
    }

    /** Was eine Rolle darf, in einem Satz (Mail bei neuen Rechten, „Meine Rollen und Rechte“). */
    public static function erklaerung(string $role): ?string
    {
        if (Rechte::istEigen($role)) {
            $rolle = Rechte::eigene()[$role] ?? null;

            return $rolle ? ($rolle->beschreibung ?: collect($rolle->rechte)->map(fn (string $r) => Rechte::KATALOG[$r]['label'] ?? $r)->implode(', ')) : null;
        }

        return [
            'autor' => 'eigene Beiträge schreiben und bearbeiten',
            'stufenadmin' => 'Stufenseite, Beiträge und Veranstaltungen der Stufe pflegen, Autor:innen freischalten',
            'sektionsadmin' => 'Seiten, Beiträge und Veranstaltungen des Bereichs pflegen, Autor:innen freischalten',
            'material' => 'Material und Reservierungen im Materialverleih pflegen',
            'veranstaltungen' => 'Veranstaltungen des Bereichs anlegen und bearbeiten, Teilnehmende verwalten',
            'bildungsreferent' => 'alle Seiten, Beiträge und Veranstaltungen samt Teilnehmenden, Nachrichten, Autor:innen freischalten',
            'vorstand' => 'Vorstandsseiten pflegen, Beiträge freigeben',
            'verwaltung' => 'alle Inhalte und Veranstaltungen, Benutzer und Datenschutz-Anfragen',
            'kasse' => 'Mitgliedsbeiträge: offene und bezahlte Beiträge, Abgleich mit NaMi und der Rechnung des Bundesverbands',
            'sammelbestellung' => 'Rüsthaus-Sammelbestellung: Artikel freigeben und sperren, Runden, Bestellungen prüfen, Sammelliste',
            'whatsapp' => 'WhatsApp-Newsletter: Aussendungen, Vorlagen, Abonnent:innen und Verbindung',
            'admin' => 'Technik: Integrationen und Einstellungen',
            'superadmin' => 'Notfallzugang',
        ][$role] ?? null;
    }

    /** Lesen Stufen- und Sektionsadmins Veranstaltungen nur (config dpsg.rechte)? */
    public static function leitungLiestVeranstaltungen(): bool
    {
        return config('dpsg.rechte.leitung_veranstaltungen', 'bearbeiten') === 'lesen';
    }

    /**
     * Rollen, bei deren Vergabe die Technik-Admins informiert werden.
     */
    public static function isPrivileged(string $key): bool
    {
        $role = self::decode($key)['role'];
        if (Rechte::istEigen($role)) {
            return Rechte::sensibel(Rechte::fuerRolle($role));
        }

        return in_array($role, ['verwaltung', 'bildungsreferent', 'kasse', 'whatsapp', 'admin', 'superadmin'], true);
    }

    public static function label(RoleAssignment $assignment): string
    {
        return self::all()[self::encode($assignment)] ?? self::encode($assignment);
    }
}
