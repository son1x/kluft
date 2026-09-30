<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen + Geltungsbereich (Scope), s. Pflichtenheft 5.3/5.4/8.2.
 * Eine Person kann mehrere Zuweisungen haben (z. B. Stufenadmin Wölflinge
 * UND Autorin in der Sektion Bolivien) – daher eigene Tabelle statt einer
 * einzelnen Rollenspalte auf users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', [
                'autor',
                'stufenadmin',
                'sektionsadmin',
                'vorstand',
                'verwaltung',
                'admin',
                'superadmin',
            ]);
            $table->enum('scope_type', ['global', 'section', 'stage', 'own']);
            $table->string('scope_key')->nullable()->comment('z. B. "biber", "bolivien" – leer bei scope_type=global/own');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role', 'scope_type', 'scope_key'], 'role_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
