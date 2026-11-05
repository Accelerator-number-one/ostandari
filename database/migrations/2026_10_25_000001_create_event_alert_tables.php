<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_subscriptions')) {
            Schema::create('event_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->text('mobile');
                $table->char('mobile_hash', 64);
                $table->string('scope_key', 80);
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->uuid('challenge_id')->nullable()->unique();
                $table->string('code_hash')->nullable();
                $table->string('pending_action', 20)->nullable();
                $table->timestamp('code_expires_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
                $table->unique(['mobile_hash', 'scope_key']);
            });
        }
        if (! Schema::hasTable('event_deliveries')) {
            Schema::create('event_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_subscription_id')->constrained()->cascadeOnDelete();
                // Imported portal databases can use different integer widths for contents.id.
                $table->unsignedBigInteger('content_id')->index();
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['event_subscription_id', 'content_id'], 'event_recipient_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_deliveries');
        Schema::dropIfExists('event_subscriptions');
    }
};
