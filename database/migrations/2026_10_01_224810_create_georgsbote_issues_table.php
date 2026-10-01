<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Georgsbote-Archiv, s. Pflichtenheft 6.6 und 8.2 („georgsbote_issues“).
 * PDFs liegen privat (storage/app/private) und werden nur für
 * veröffentlichte Ausgaben über /georgsbote/{jahr}/{ausgabe}.pdf
 * ausgeliefert (F-GB-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('georgsbote_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('issue_no');
            $table->string('title');
            $table->string('pdf_path');
            $table->string('cover_path')->nullable()->comment('Titelbild auf der public-Disk');
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->boolean('privacy_checked')->default(false)->comment('Personenbezug geprüft (F-GB-03)');
            $table->foreignId('privacy_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('privacy_checked_at')->nullable();
            $table->unsignedInteger('downloads')->default(0)->comment('Zähler ohne Personenbezug (F-GB-05)');
            $table->timestamps();

            $table->unique(['year', 'issue_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('georgsbote_issues');
    }
};
