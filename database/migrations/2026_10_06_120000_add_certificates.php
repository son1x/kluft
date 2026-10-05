<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teilnahmebescheinigung: je Veranstaltung einschaltbar (mit Text zu
 * Inhalten/Umfang und Unterzeichner*in), je Anmeldung von der
 * Veranstaltungsleitung freigegeben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('certificate_enabled')->default(false)->after('member_check');
            $table->text('certificate_text')->nullable()->after('certificate_enabled');
            $table->string('certificate_signer', 150)->nullable()->after('certificate_text');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('certificate_approved_at')->nullable();
            $table->foreignId('certificate_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_approved_by');
            $table->dropColumn('certificate_approved_at');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['certificate_enabled', 'certificate_text', 'certificate_signer']);
        });
    }
};
