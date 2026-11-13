<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teilnahmeliste in wählbaren Modellen (App\Support\Teilnahmelisten): Modell,
 * Veranstalter für den Kopf der Liste und die Angabe, ob die Betreuung für
 * Teilnehmende mit Behinderung ist (Vorlage Landesjugendring RLP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->string('teilnahmeliste_modell', 40)->nullable();
            $t->string('veranstalter', 150)->nullable();
            $t->boolean('liste_mit_behinderung')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['teilnahmeliste_modell', 'veranstalter', 'liste_mit_behinderung']));
    }
};
