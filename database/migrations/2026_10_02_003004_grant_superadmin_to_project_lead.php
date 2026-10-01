<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projektleitung (M365-Konto) erhält Super-Admin-Rechte – auf Wunsch der
 * Projektleitung, da es auf dem Server keinen direkten DB-Zugriff gibt.
 * Das Konto wird bei der ersten Microsoft-Anmeldung über die E-Mail-Adresse
 * verknüpft. Trägt der Notfall-Account dieselbe Adresse, bekommt er eine
 * eigene, damit Notfall-Zugang und M365-Login getrennt bleiben (5.2).
 */
return new class extends Migration
{
    private const EMAIL = 's.hafner@dpsg-trier.de';

    // In Tests nur, wenn ausdrücklich gewünscht (sonst verfälscht der
    // zusätzliche Benutzer andere Tests).
    public bool $runInTests = false;

    public function up(): void
    {
        if (app()->runningUnitTests() && ! $this->runInTests) {
            return;
        }

        $now = now();

        // Notfall-Account mit derselben Adresse freistellen.
        $breakGlass = DB::table('users')->where('is_local_admin', true)->whereRaw('lower(email) = ?', [self::EMAIL])->first();
        if ($breakGlass) {
            $newEmail = 'notfall@dpsg-trier.de';
            if (DB::table('users')->whereRaw('lower(email) = ?', [$newEmail])->exists()) {
                $newEmail = "notfall-{$breakGlass->id}@dpsg-trier.de";
            }
            DB::table('users')->where('id', $breakGlass->id)->update([
                'email' => $newEmail, 'upn' => null, 'entra_oid' => null, 'updated_at' => $now,
            ]);
        }

        $user = DB::table('users')
            ->where('is_local_admin', false)
            ->where(fn ($q) => $q->whereRaw('lower(email) = ?', [self::EMAIL])->orWhereRaw('lower(upn) = ?', [self::EMAIL]))
            ->first();

        if ($user) {
            DB::table('users')->where('id', $user->id)->update(['status' => 'active', 'updated_at' => $now]);
            $userId = $user->id;
        } else {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Sebastian Hafner',
                'email' => self::EMAIL,
                'upn' => self::EMAIL,
                'status' => 'active',
                'is_local_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hasRole = DB::table('role_assignments')
            ->where('user_id', $userId)->where('role', 'superadmin')->where('scope_type', 'global')->exists();

        if (! $hasRole) {
            DB::table('role_assignments')->insert([
                'user_id' => $userId, 'role' => 'superadmin', 'scope_type' => 'global', 'scope_key' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'role.granted:superadmin|global|',
            'subject_type' => 'user',
            'subject_id' => $userId,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        //
    }
};
