<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweise auf der Startseite (Kasten unter dem Titelbild), z. B. zur
 * Aufarbeitung sexualisierter Gewalt oder „Büro geschlossen“.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startseiten_hinweise', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->text('text')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('link_text', 80)->nullable();
            $table->string('farbe', 20)->default('rot');
            $table->boolean('aktiv')->default(true);
            $table->date('ab')->nullable();
            $table->date('bis')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startseiten_hinweise');
    }
};
