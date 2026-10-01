<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\User;

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

        $options['autor|own|'] = 'Autor:in (ohne Zuordnung)';
        foreach ($stages as $key => $label) {
            $options["autor|own|{$key}"] = "Autor:in – {$label}";
        }
        foreach (self::sections() as $key => $label) {
            $options["autor|own|{$key}"] = "Autor:in – {$label}";
        }

        $options['vorstand|global|'] = 'Vorstand';
        $options['verwaltung|global|'] = 'Verwaltung';
        $options['admin|global|'] = 'Admin (Technik)';
        $options['superadmin|global|'] = 'Super-Admin';

        return $options;
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

        if ($actor->isVerwaltung()) {
            unset($all['admin|global|'], $all['superadmin|global|']);

            return $all;
        }

        // Stufen-/Sektionsadmins: nur Autor:innen im eigenen Scope (5.5).
        return collect($all)
            ->filter(fn ($label, string $key) => in_array($key, array_map(
                fn (string $scopeKey) => "autor|own|{$scopeKey}",
                self::delegatedScopeKeys($actor)
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
        return $actor->roleAssignments
            ->whereIn('role', ['stufenadmin', 'sektionsadmin'])
            ->pluck('scope_key')
            ->filter()
            ->values()
            ->all();
    }

    public static function canManageUsersGlobally(User $actor): bool
    {
        return $actor->isVerwaltung();
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

    public static function label(RoleAssignment $assignment): string
    {
        return self::all()[self::encode($assignment)] ?? self::encode($assignment);
    }
}
