<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;

/**
 * Legt den Notfall-Super-Admin (Break-Glass) an, s. Pflichtenheft 5.2.
 * DB-basiert statt hardcodiert; separater, nicht verlinkter Login-Pfad
 * (/notfall-login, s. App\Http\Controllers\Auth\BreakGlassAuthController).
 *
 * Bewusst ein interaktives Artisan-Kommando statt eines Seeders/einer
 * Migration, damit das Passwort nie in Code oder Backups landet, die
 * versehentlich committet werden könnten.
 */
class CreateSuperAdminCommand extends Command
{
    protected $signature = 'cms:create-superadmin';

    protected $description = 'Legt den lokalen Notfall-Super-Admin-Account an (Break-Glass, Pflichtenheft 5.2)';

    public function handle(): int
    {
        $this->warn('Break-Glass-Account: nur für den Notfall bei Entra-ID-Ausfall. Zugangsdaten gehören in einen Passwortmanager mit Vier-Augen-Zugriff.');

        if (User::query()->where('is_local_admin', true)->exists()) {
            $this->error('Es existiert bereits ein lokaler Notfall-Super-Admin. Für einen weiteren bitte diesen Befehl anpassen oder den bestehenden Account zurücksetzen.');

            return self::FAILURE;
        }

        $name = $this->ask('Name (z. B. "Notfall-Admin")', 'Notfall-Admin');
        $email = $this->ask('E-Mail-Adresse (erhält die Alarmmail bei jeder Anmeldung)');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);
        if ($validator->fails()) {
            $this->error('Ungültige E-Mail-Adresse.');

            return self::FAILURE;
        }

        $password = $this->secret('Passwort (mind. 16 Zeichen)');
        $passwordConfirm = $this->secret('Passwort wiederholen');

        if ($password !== $passwordConfirm) {
            $this->error('Die Passwörter stimmen nicht überein.');

            return self::FAILURE;
        }

        if (mb_strlen((string) $password) < 16) {
            $this->error('Das Passwort muss mindestens 16 Zeichen lang sein.');

            return self::FAILURE;
        }

        $google2fa = new Google2FA;
        $totpSecret = $google2fa->generateSecretKey();

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'status' => 'active',
            'is_local_admin' => true,
            'password' => Hash::make($password),
            'totp_secret' => $totpSecret,
        ]);

        $user->roleAssignments()->create([
            'role' => 'superadmin',
            'scope_type' => 'global',
        ]);

        $this->newLine();
        $this->info('Notfall-Super-Admin angelegt.');
        $this->newLine();
        $this->line('TOTP-Secret (in eine Authenticator-App eintragen, z. B. manuell als Base32-Secret):');
        $this->line("  {$totpSecret}");
        $this->newLine();
        $this->warn('Dieses Secret wird nur jetzt angezeigt. Zugangsdaten + Secret jetzt in den Passwortmanager übernehmen.');
        $this->line('Login-Pfad: /notfall-login');

        return self::SUCCESS;
    }
}
