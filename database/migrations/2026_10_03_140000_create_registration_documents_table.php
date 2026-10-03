<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumente zu einer Anmeldung (Rechnung, Teilnahmebescheinigung …), vom
 * Diözesanbüro hochgeladen und unter „Meine Anmeldungen“ abrufbar. Die
 * Dateien liegen nicht öffentlich (storage/app/private) und werden mit der
 * Anmeldung gelöscht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title')->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_documents');
    }
};
