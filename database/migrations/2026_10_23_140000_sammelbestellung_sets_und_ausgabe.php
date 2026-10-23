<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelbestellung Phase 2 (docs/pflichtenheft-sammelbestellung.md):
 * Artikelsets (SB-38), Mitgliedsprüfung (SB-37), Erinnerung und Halten
 * beim Schließen, Rüsthaus-Rechnung (SB-45), Ausgabe (SB-43), Löschfrist (SB-71).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('beschreibung')->nullable();
            $table->foreignId('bild_artikel_id')->nullable()->constrained('shop_artikel')->nullOnDelete();
            $table->boolean('freigegeben')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_set_teile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('shop_sets')->cascadeOnDelete();
            $table->foreignId('artikel_id')->constrained('shop_artikel')->cascadeOnDelete();
            $table->unsignedTinyInteger('menge')->default(1);
            $table->boolean('optional')->default(false);
            $table->boolean('vorgewaehlt')->default(true)->comment('optionale Teile: anfangs angehakt');
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::table('shop_positionen', function (Blueprint $table) {
            $table->foreignId('set_id')->nullable()->after('artikel_id')->constrained('shop_sets')->nullOnDelete();
            $table->string('set_gruppe', 16)->nullable()->after('set_id')->comment('Teile eines bestellten Sets');
            $table->timestamp('ausgegeben_am')->nullable();
            $table->foreignId('ausgegeben_von')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ausgabe_vermerk', 160)->nullable();
        });

        Schema::table('shop_bestellungen', function (Blueprint $table) {
            $table->string('mitgliedschaft', 20)->nullable()->comment('ja | nein | unklar | bestaetigt');
            $table->timestamp('mitgliedschaft_am')->nullable();
            $table->boolean('gehalten')->default(false)->comment('beim Schließen trotz offener Zahlung nicht stornieren');
            $table->timestamp('erinnert_am')->nullable();
            $table->timestamp('anonymisiert_am')->nullable();
        });

        Schema::table('shop_runden', function (Blueprint $table) {
            $table->unsignedInteger('rechnung_cent')->nullable()->comment('Rüsthaus-Rechnung nach Provisionsabzug');
            $table->unsignedInteger('versand_cent')->nullable();
            $table->date('rechnung_am')->nullable();
            $table->timestamp('bestellt_am')->nullable();
            $table->timestamp('geliefert_am')->nullable();
            $table->timestamp('erledigt_am')->nullable();
            $table->timestamp('erinnert_am')->nullable()->comment('Hinweis an die Sammelbestellung am Bestelldatum');
        });
    }

    public function down(): void
    {
        Schema::table('shop_runden', fn (Blueprint $t) => $t->dropColumn(['rechnung_cent', 'versand_cent', 'rechnung_am', 'bestellt_am', 'geliefert_am', 'erledigt_am', 'erinnert_am']));
        Schema::table('shop_bestellungen', fn (Blueprint $t) => $t->dropColumn(['mitgliedschaft', 'mitgliedschaft_am', 'gehalten', 'erinnert_am', 'anonymisiert_am']));
        Schema::table('shop_positionen', function (Blueprint $t) {
            $t->dropConstrainedForeignId('set_id');
            $t->dropConstrainedForeignId('ausgegeben_von');
            $t->dropColumn(['set_gruppe', 'ausgegeben_am', 'ausgabe_vermerk']);
        });
        Schema::dropIfExists('shop_set_teile');
        Schema::dropIfExists('shop_sets');
    }
};
