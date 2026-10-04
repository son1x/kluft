<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neue Rolle „whatsapp“ (WhatsApp-Newsletter-Admin): Rollen als Text statt
 * Enum, damit neue Rollen keine Tabellenänderung mehr brauchen. Welche
 * Rollen gültig sind, prüft App\Support\RoleCatalog beim Vergeben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->string('role', 30)->change();
        });
    }

    public function down(): void
    {
        // Bewusst nicht zurück auf das Enum: vorhandene neue Rollen gingen verloren.
    }
};
