<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Schreibt Einträge ins Audit-Log ohne Inhalte/Personendaten
 * (Pflichtenheft 9.2 „Logging“, 8.2 `audit_logs`).
 */
class AuditLogger
{
    public static function log(
        string $action,
        ?User $user = null,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_truncated' => static::truncateIp($request?->ip()),
            'created_at' => now(),
        ]);
    }

    /**
     * Entfernt das letzte Oktett (IPv4) bzw. das Interface-Suffix (IPv6),
     * s. Pflichtenheft 9.2 „Server-Logs mit gekürzten IPs“.
     */
    public static function truncateIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4)).'::';
        }

        return null;
    }
}
