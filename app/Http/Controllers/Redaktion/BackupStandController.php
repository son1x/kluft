<?php

namespace App\Http\Controllers\Redaktion;

use App\Http\Controllers\Controller;
use App\Services\Backup\BackupEinspielen;
use Illuminate\Http\Response;

/**
 * Stand eines Backup-Auftrags als schlichte Seite, die sich selbst neu lädt
 * (ohne Skript, CSP). Ohne Anmeldung, nur mit dem Token aus dem Auftrag.
 */
class BackupStandController extends Controller
{
    public function __invoke(string $token): Response
    {
        $stand = BackupEinspielen::stand();
        abort_unless($stand !== null && hash_equals((string) $stand['token'], $token), 404);

        return response()->view('backup.stand', ['stand' => $stand], 200)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
