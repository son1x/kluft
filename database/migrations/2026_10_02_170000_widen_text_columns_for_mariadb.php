<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite prüft keine Spaltenlängen, MariaDB schon. Die Spalten werden an die
 * Formulare angepasst (Teaser bis 300, SEO-Beschreibung bis 500 Zeichen);
 * Seiten- und Beitragstexte bekommen Platz für lange Importe aus WordPress
 * (TEXT fasst in MariaDB nur 64 KB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('teaser', 500)->change();
            $table->longText('body')->change();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('seo_description', 500)->nullable()->change();
            $table->longText('body')->comment('Bereinigtes HTML (Whitelist), s. F-CMS-02')->change();
        });
    }

    public function down(): void
    {
        // Bewusst leer: Zurückkürzen würde längere Teaser/Texte abschneiden
        // bzw. in MariaDB mit einem Fehler abbrechen.
    }
};
