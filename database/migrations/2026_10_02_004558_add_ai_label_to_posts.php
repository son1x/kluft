<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KI-Kennzeichnung je Beitrag nach CD-Leitfaden Abschnitt 13
 * (Pflichtenheft F-CMS-14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('ai_level')->default('none'); // none|assist|partial|full
            $table->string('ai_tool')->nullable();
            $table->string('ai_content')->nullable(); // bei "full": Text, Bild, Video, …
            $table->string('ai_person')->nullable(); // bei "partial": erstellende Person
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['ai_level', 'ai_tool', 'ai_content', 'ai_person']);
        });
    }
};
