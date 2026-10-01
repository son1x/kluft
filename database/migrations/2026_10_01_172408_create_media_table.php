<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mediathek, s. Pflichtenheft 6.1 F-CMS-04 und 8.2 „media“.
 * Pflichtfeld alt_text (Barrierefreiheit) und credit (Bildnachweis,
 * CD-Leitfaden-Pflicht) werden im Filament-Formular erzwungen, nicht nur
 * hier in der DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('alt_text');
            $table->string('credit')->comment('Bildnachweis/Urheber:in, CD-Leitfaden Abschnitt 9');
            $table->boolean('rights_confirmed')->default(false)->comment('„Einwilligung liegt vor“ bei Personenfotos, s. F-CMS-14');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
