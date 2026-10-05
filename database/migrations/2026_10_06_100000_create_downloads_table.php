<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Downloads (Methoden, Formulare, Arbeitshilfen): Dateien liegen privat
 * (Disk „local“) und werden über die Website ausgeliefert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description', 300)->nullable();
            $table->string('category', 50);
            $table->string('stage', 50)->nullable();
            $table->string('section', 50)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            // Seiten (PDF) bzw. Dateien (ZIP), sonst leer.
            $table->unsignedInteger('parts')->nullable();
            $table->timestamp('file_updated_at')->nullable();
            $table->boolean('rights_confirmed')->default(false);
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort')->default(0);
            // Zähler ohne Personenbezug.
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();

            $table->index(['published', 'category', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
