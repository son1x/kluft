<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Redaktion/Admins (keine Besucherdaten), s. Pflichtenheft 8.2 „users“.
            $table->string('upn')->nullable()->comment('User Principal Name aus Entra ID, z. B. vorname.nachname@dpsg-trier.de');
            $table->string('entra_oid')->nullable()->unique()->comment('Unveränderliche Entra-Objekt-ID, Schlüssel für die Kontoverknüpfung (nicht E-Mail/UPN)');
            $table->enum('status', ['pending', 'active', 'disabled'])->default('pending');
            $table->boolean('is_local_admin')->default(false)->comment('true nur für den Notfall-Super-Admin (Break-Glass, 5.2)');
            $table->string('password')->nullable()->comment('Nur für is_local_admin gesetzt, Argon2id');
            $table->text('totp_secret')->nullable()->comment('Verschlüsselt (encrypted-Cast), nur für is_local_admin');
            $table->foreignId('provisioned_by')->nullable()->constrained('users')->nullOnDelete()->comment('Welche Verwaltungsperson die Zuweisung vorab angelegt hat');
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
