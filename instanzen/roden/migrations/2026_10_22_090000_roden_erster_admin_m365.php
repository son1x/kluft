<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roden: Erstes Microsoft-365-Konto freischalten. Es wurde bei der ersten
 * Anmeldung mit Status „wartet auf Freischaltung“ angelegt (403), und ohne
 * funktionierenden Notfall- oder Code-Login konnte niemand eine Rolle geben.
 * Setzt das Konto aktiv und gibt ihm die Rolle Super-Admin – nur, wenn es
 * das Konto schon gibt. Weitere Rollen vergibt man danach im Backend.
 */
return new class extends Migration
{
    private const ADRESSE = 'sebastian@dpsg-roden.de';

    public function up(): void
    {
        if (config('dpsg.instanz') !== 'roden') {
            return;
        }

        $user = DB::table('users')
            ->whereRaw('lower(email) = ?', [self::ADRESSE])
            ->orWhereRaw('lower(upn) = ?', [self::ADRESSE])
            ->first();
        if (! $user) {
            return;
        }

        DB::table('users')->where('id', $user->id)->where('status', 'pending')->update(['status' => 'active', 'updated_at' => now()]);

        $vorhanden = DB::table('role_assignments')
            ->where('user_id', $user->id)->where('role', 'superadmin')->where('scope_type', 'global')->exists();
        if (! $vorhanden) {
            DB::table('role_assignments')->insert([
                'user_id' => $user->id, 'role' => 'superadmin', 'scope_type' => 'global', 'scope_key' => null,
                'granted_by' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Rollen bleiben; im Backend unter „Benutzer“ änderbar.
    }
};
