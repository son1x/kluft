<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Begriffserklärungen (Glossar): Abkürzungen wie DAK oder FAK werden im
 * Text beim Darüberfahren erklärt. Den Startbestand legt der Seeder der
 * Instanz an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 60)->unique();
            $table->string('explanation', 300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
