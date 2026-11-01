<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Meldung aus dem Formular „Meine Daten aktualisieren“ (NaMi-Datenabgleich).
 * Zählt erst nach der Bestätigung per Mail-Link.
 */
class Datenmeldung extends Model
{
    protected $table = 'datenmeldungen';

    protected $fillable = ['daten', 'email_hash', 'email_eltern_hash', 'token_hash', 'bestaetigt_am'];

    protected $hidden = ['daten', 'token_hash'];

    protected function casts(): array
    {
        return ['daten' => 'encrypted:array', 'bestaetigt_am' => 'datetime'];
    }

    public static function hash(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : hash('sha256', $email);
    }
}
