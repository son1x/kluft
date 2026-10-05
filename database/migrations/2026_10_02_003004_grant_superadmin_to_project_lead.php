<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Früher: Super-Admin-Rechte für das M365-Konto der Projektleitung des DV.
 * Entfällt in Kluft: Neue Installationen richten den ersten Zugang mit
 * „php artisan cms:create-superadmin“ ein (README, „Neue Gliederung
 * einrichten“). Die leere Migration bleibt, weil bestehende Installationen
 * sie schon ausgeführt haben.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
