<?php

namespace App\Http\Controllers\Redaktion;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Verschlüsseltes Backup dieser Website herunterladen (nur Admins, über eine
 * signierte Adresse, 5 Minuten gültig). Die Datei bleibt verschlüsselt;
 * entschlüsseln mit `scripts/backup-entschluesseln.php` und BACKUP_PASSWORD.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, string $datei, BackupService $backups): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $pfad = self::pfad($backups, $datei);
        abort_if($pfad === null, 404);

        AuditLogger::log('backup.download:'.$datei, $user, request: $request);

        return response()->download($pfad, $datei, ['Content-Type' => 'application/octet-stream']);
    }

    /** Nur eigene Backups (keine anderen Websites im Webspace, keine Pfade). */
    public static function pfad(BackupService $backups, string $datei): ?string
    {
        if (! preg_match('/^backup-[A-Za-z0-9_.-]+\.dpsgbak$/', $datei)) {
            return null;
        }
        $verzeichnis = realpath($backups->directory());
        $pfad = $verzeichnis ? realpath($verzeichnis.'/'.$datei) : false;

        return $pfad !== false && dirname($pfad) === $verzeichnis && is_file($pfad) ? $pfad : null;
    }
}
