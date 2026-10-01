<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog/News, s. Pflichtenheft 6.1 F-CMS-03/10/11 und 8.2 „posts“.
 * wp_id bleibt für die spätere WordPress-Migration (Phase 1e, bewusst
 * zuletzt, s. 6.9) nullable vorgesehen – Datensatz ist schon idempotenz-
 * fähig, ohne dass die Migration selbst schon gebaut werden muss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->enum('stage', ['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'])->nullable();
            $table->enum('scope', ['general', 'stage', 'section', 'vorstand'])->default('general');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('teaser');
            $table->text('body');
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->enum('status', ['draft', 'review', 'published'])->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_id')->nullable()->unique()->comment('Für spätere WordPress-Migration, Phase 1e');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
