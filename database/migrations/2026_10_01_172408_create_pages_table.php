<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seiten mit hierarchischer Navigation, s. Pflichtenheft 6.1 F-CMS-01/02/08
 * und 8.2 „pages“. site_id/section_id folgen erst mit der Bolivien-Sektion
 * (Phase 1b) – aktuell eine einzelne Site, daher bewusst weggelassen statt
 * vorab mit nur einem möglichen Wert zu modellieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body')->comment('Bereinigtes HTML (Whitelist), s. F-CMS-02');
            $table->enum('status', ['draft', 'review', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->enum('stage', ['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'])->nullable();
            $table->unsignedInteger('nav_sort')->default(0)->comment('Reihenfolge in der Hauptnavigation');
            $table->boolean('show_in_nav')->default(false);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
