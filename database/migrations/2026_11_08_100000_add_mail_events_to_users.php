<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Benachrichtigungen per Mail je Person (z. B. Spamverdacht im
| Nachrichten-Eingang). null = Voreinstellung (App\Support\MailEvents).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('mail_events')->nullable()->after('ntfy_events');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_events');
        });
    }
};
