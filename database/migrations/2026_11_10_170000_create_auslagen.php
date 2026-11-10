<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auslagen und Fahrtkosten erstatten (docs/auslagen.md). Bankverbindung
 * verschlüsselt: einmal am Konto, bis zur Auszahlung an der Auslage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auslagen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email');
            $table->string('anlass', 200);
            $table->date('datum');
            $table->string('art', 10);
            $table->unsignedInteger('betrag_cent')->nullable();
            $table->decimal('km', 7, 1)->nullable();
            $table->unsignedSmallInteger('km_satz_cent')->nullable();
            $table->string('strecke', 200)->nullable();
            $table->unsignedInteger('summe_cent');
            $table->json('belege')->nullable();
            $table->text('konto')->nullable();
            $table->string('status', 15)->default('eingereicht')->index();
            $table->string('grund', 500)->nullable();
            $table->foreignId('geprueft_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('geprueft_am')->nullable();
            $table->date('ausgezahlt_am')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('auslagen_konto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auslagen');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auslagen_konto');
        });
    }
};
