<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-Migration (Pflichtenheft 6.9, Phase 1e): Weiterleitungen alter
 * URLs, Importläufe als Bericht, Herkunft importierter Medien und
 * Import-Status der Beiträge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique()->comment('Pfad ohne Domain, ohne abschließenden Schrägstrich, z. B. /alter-beitrag');
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->string('source', 30)->nullable()->comment('wordpress | manuell');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wp_import_runs', function (Blueprint $table) {
            $table->id();
            $table->boolean('dry_run')->default(false);
            $table->json('options')->nullable();
            $table->json('counts')->nullable();
            $table->json('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->string('source_url', 500)->nullable()->unique()->comment('Herkunft beim WordPress-Import');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('wp_url', 500)->nullable()->comment('Alte WordPress-Adresse');
            $table->string('import_hash', 64)->nullable()->comment('Hash des importierten Inhalts; Abweichung = lokal bearbeitet');
            $table->json('import_issues')->nullable()->comment('Nacharbeitsliste');
            $table->boolean('import_reviewed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['wp_url', 'import_hash', 'import_issues', 'import_reviewed']);
        });
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn('source_url');
        });
        Schema::dropIfExists('wp_import_runs');
        Schema::dropIfExists('redirects');
    }
};
