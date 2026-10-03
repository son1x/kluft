<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push-Benachrichtigungen über ntfy, je Person freiwillig: geheimes Thema
 * (verschlüsselt) und gewählte Ereignisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ntfy_topic')->nullable()->after('last_login_at');
            $table->json('ntfy_events')->nullable()->after('ntfy_topic');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ntfy_topic', 'ntfy_events']);
        });
    }
};
