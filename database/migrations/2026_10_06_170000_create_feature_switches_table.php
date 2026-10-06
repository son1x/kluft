<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funktionen im Backend an- und abschalten (Verwaltung → Funktionen). Ohne
 * Eintrag gilt der Standard der Instanz (instanzen/<key>/config.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_switches', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->boolean('enabled');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_switches');
    }
};
