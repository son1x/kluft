<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp-Newsletter (docs/whatsapp_newsletter.md, Abschnitt 5):
 * Abonnent:innen nur mit verschlüsselter Nummer und Einwilligungsnachweis,
 * Sperrliste nur als Hash, Zustellungen kurzlebig, Statistik als Summen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_subscribers', function (Blueprint $table) {
            $table->id();
            $table->text('phone');
            $table->char('phone_hash', 64)->unique();
            $table->string('status', 10)->default('pending');
            $table->string('source', 10);
            $table->string('consent_version', 20);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedTinyInteger('failed_streak')->default(0);
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('whatsapp_suppressions', function (Blueprint $table) {
            $table->char('phone_hash', 64)->primary();
            $table->string('reason', 20);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_id')->nullable();
            $table->string('name');
            $table->string('language', 10)->default('de');
            $table->string('category', 20);
            $table->string('purpose', 20)->default('newsletter');
            $table->string('status', 20)->default('PENDING');
            $table->json('components')->nullable();
            $table->string('quality', 20)->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['name', 'language']);
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('variables')->nullable();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->decimal('estimated_cost_eur', 8, 2)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained('whatsapp_subscribers')->nullOnDelete();
            $table->string('wamid')->nullable()->unique();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('error_code')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
        });

        // Weitere Zugangsdaten (App-Secret, WABA-ID, Verify-Token) verschlüsselt.
        Schema::table('integration_tokens', function (Blueprint $table) {
            $table->text('settings')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('integration_tokens', fn (Blueprint $table) => $table->dropColumn('settings'));
        Schema::dropIfExists('whatsapp_deliveries');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_suppressions');
        Schema::dropIfExists('whatsapp_subscribers');
    }
};
