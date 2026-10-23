<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelbestellung beim Rüsthaus (docs/pflichtenheft-sammelbestellung.md,
 * Phase 1): freigegebene Artikel mit Rüsthaus-Cache, Sperrliste, Runden,
 * Bestellungen und Positionen. Bezahlt wird über den Baustein „Zahlungen“.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_artikel', function (Blueprint $table) {
            $table->id();
            $table->string('nummer', 40)->unique()->comment('Rüsthaus-Artikelnummer (Hauptvariante)');
            $table->string('name', 160);
            $table->text('beschreibung')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('rh_kategorie', 80)->nullable()->comment('erster Pfadteil der Rüsthaus-Adresse, z. B. zelte');
            $table->string('kategorie', 80)->nullable()->comment('Kategorie im eigenen Shop');
            $table->foreignId('bild_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('preis_cent')->default(0);
            $table->boolean('freigegeben')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamp('abgerufen_am')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_varianten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained('shop_artikel')->cascadeOnDelete();
            $table->string('nummer', 40);
            $table->string('bezeichnung', 120)->nullable();
            $table->unsignedInteger('preis_cent');
            $table->boolean('aktiv')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['artikel_id', 'nummer']);
        });

        Schema::create('shop_sperren', function (Blueprint $table) {
            $table->id();
            $table->string('typ', 20)->comment('nummer, praefix oder kategorie');
            $table->string('wert', 80);
            $table->string('notiz', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('shop_runden', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->date('bestelldatum')->comment('zugleich Zahlungsfrist');
            $table->string('status', 20)->default('offen');
            $table->timestamp('geschlossen_am')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_bestellungen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('runde_id')->constrained('shop_runden')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('telefon', 40)->nullable();
            $table->string('rolle', 20)->comment('mitglied oder erziehungsberechtigt');
            $table->string('abholung', 120)->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamp('bestaetigt_am')->nullable();
            $table->timestamp('storniert_am')->nullable();
            $table->boolean('test')->default(false)->comment('im Testbetrieb bestellt, löschbar');
            $table->timestamps();
        });

        Schema::create('shop_positionen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bestellung_id')->constrained('shop_bestellungen')->cascadeOnDelete();
            $table->foreignId('artikel_id')->nullable()->constrained('shop_artikel')->nullOnDelete();
            $table->string('nummer', 40)->nullable()->comment('Rüsthaus-Artikelnummer der Variante');
            $table->string('bezeichnung', 160);
            $table->string('groesse', 120)->nullable();
            $table->unsignedSmallInteger('menge')->default(1);
            $table->unsignedInteger('preis_cent')->nullable()->comment('je Stück; bei freien Artikeln erst nach Bestätigung sicher');
            $table->string('mitglied', 120)->comment('für wen');
            $table->string('stufe', 20)->nullable();
            $table->boolean('frei')->default(false);
            $table->boolean('bestaetigt')->default(true);
            $table->foreignId('zahlung_id')->nullable()->constrained('zahlungen')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_positionen');
        Schema::dropIfExists('shop_bestellungen');
        Schema::dropIfExists('shop_runden');
        Schema::dropIfExists('shop_sperren');
        Schema::dropIfExists('shop_varianten');
        Schema::dropIfExists('shop_artikel');
    }
};
