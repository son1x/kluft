<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram-Integration, Pflichtenheft 6.4 und 8.2 (`social_posts`,
 * `integration_tokens`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('instagram');
            $table->text('caption');
            $table->string('image_path')->nullable();
            $table->string('status')->default('pending'); // pending|published|failed
            $table->string('external_id')->nullable();
            $table->string('permalink')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('account_id')->nullable();
            $table->string('account_name')->nullable();
            $table->text('access_token'); // verschlüsselt
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('refreshed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_tokens');
        Schema::dropIfExists('social_posts');
    }
};
