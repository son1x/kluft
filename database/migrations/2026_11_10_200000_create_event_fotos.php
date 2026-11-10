<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos zur Veranstaltung mit Vier-Augen-Freigabe: bis zur Freigabe privat
 * gespeichert (path), danach als Medium öffentlich (media_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('path')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('alt_text', 300)->nullable();
            $table->string('credit', 150)->nullable();
            $table->string('status', 15)->default('eingereicht')->index();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('hochgeladen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('freigegeben_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('freigegeben_am')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_fotos');
    }
};
