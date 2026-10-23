<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram-Einblendung (docs/instagram.md): eigene Konten mit Zugangstoken
 * und ihre neuesten Beiträge, Bilder lokal gespiegelt (F-IG-07, kein Embed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_konten', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                      // Anzeigename, z. B. „DPSG Trier“
            $table->string('benutzername', 100)->nullable();   // ohne @, von Instagram
            $table->string('ig_user_id', 64)->nullable();
            $table->text('access_token');                      // verschlüsselt
            $table->timestamp('token_ablauf')->nullable();
            $table->timestamp('token_erneuert')->nullable();
            $table->json('orte')->nullable();                  // „startseite“, „section:<key>“
            $table->unsignedTinyInteger('anzahl')->default(8);
            $table->boolean('aktiv')->default(true);
            $table->timestamp('abgerufen_am')->nullable();
            $table->string('fehler', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_beitraege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_konto_id')->constrained('instagram_konten')->cascadeOnDelete();
            $table->string('media_id', 64);
            $table->string('art', 20);                         // IMAGE, VIDEO, CAROUSEL_ALBUM
            $table->string('permalink', 500);
            $table->text('text')->nullable();                  // Bildunterschrift, gekürzt
            $table->string('bild', 255);                       // Pfad auf der public-Disk
            $table->timestamp('veroeffentlicht_am');
            $table->timestamps();
            $table->unique(['instagram_konto_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_beitraege');
        Schema::dropIfExists('instagram_konten');
    }
};
