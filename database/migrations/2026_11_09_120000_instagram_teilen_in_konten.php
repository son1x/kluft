<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Ort für Instagram-Zugänge (docs/instagram.md): „Auf Instagram teilen“
 * nutzt jetzt das Konto aus „Instagram-Konten“ mit dem Haken „teilen“
 * statt eines eigenen Tokens unter „Integrationen“. Ein vorhandenes Token
 * wird übernommen: Gibt es das Konto schon, bekommt es Haken und Token
 * (das Token zum Teilen hat das Recht zum Veröffentlichen, das reine
 * Einblendungs-Token nicht); sonst entsteht ein Konto ohne Einblendung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_konten', function (Blueprint $table) {
            $table->boolean('teilen')->default(false)->after('aktiv');
        });

        $alt = DB::table('integration_tokens')->where('provider', 'instagram')->first();
        if ($alt === null) {
            return;
        }

        $werte = [
            'access_token' => $alt->access_token,          // bereits verschlüsselt, gleicher Schlüssel
            'token_ablauf' => $alt->expires_at,
            'token_erneuert' => $alt->refreshed_at,
            'teilen' => true,
            'updated_at' => now(),
        ];
        $vorhanden = filled($alt->account_id)
            ? DB::table('instagram_konten')->where('ig_user_id', $alt->account_id)->orderBy('id')->first()
            : null;

        if ($vorhanden) {
            DB::table('instagram_konten')->where('id', $vorhanden->id)->update($werte);
        } else {
            DB::table('instagram_konten')->insert($werte + [
                'name' => filled($alt->account_name) ? '@'.$alt->account_name : 'Instagram',
                'benutzername' => $alt->account_name,
                'ig_user_id' => $alt->account_id,
                'orte' => json_encode([]),
                'anzahl' => 8,
                'aktiv' => false,                          // nur teilen, nicht einblenden
                'fehler' => $alt->last_error,
                'created_at' => now(),
            ]);
        }

        DB::table('integration_tokens')->where('provider', 'instagram')->delete();
    }

    public function down(): void
    {
        Schema::table('instagram_konten', function (Blueprint $table) {
            $table->dropColumn('teilen');
        });
    }
};
