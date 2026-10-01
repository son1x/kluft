<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veranstaltungsmanagement, Pflichtenheft 6.7 und 8.2 (`events`,
 * `event_registrations`). Der Formular-Baukasten (`event_fields`) liegt
 * als JSON-Spalte `form_fields` direkt an der Veranstaltung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('teaser', 500)->nullable();
            $table->longText('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('stage')->nullable()->index();
            $table->string('section')->nullable()->index();
            $table->string('target_group')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('cost_info')->nullable();
            $table->dateTime('reg_opens_at')->nullable();
            $table->dateTime('reg_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('waitlist_enabled')->default(false);
            $table->string('status')->default('draft'); // draft|open|closed|cancelled
            $table->json('form_fields')->nullable();
            $table->unsignedSmallInteger('retention_days')->default(90);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending|confirmed|waitlist|cancelled
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->text('data')->nullable(); // verschlüsselt (encrypted:array)
            $table->string('guardian_name')->nullable();
            $table->boolean('guardian_consent')->default(false);
            $table->string('consent_text_version');
            $table->dateTime('consent_at');
            $table->string('confirm_token_hash', 64)->nullable()->unique();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('cancel_token_hash', 64)->nullable()->unique();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('purge_at')->nullable()->index();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
