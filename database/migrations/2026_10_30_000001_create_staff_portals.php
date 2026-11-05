<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('staff_portals')) Schema::create('staff_portals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('members')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 1000)->nullable();
            $table->unsignedBigInteger('portrait_id')->nullable();
            $table->unsignedBigInteger('banner_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        if (!Schema::hasTable('staff_portal_assets')) Schema::create('staff_portal_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_portal_id')->constrained('staff_portals')->restrictOnDelete();
            $table->string('name', 80)->unique();
            $table->string('original_name');
            $table->string('mime', 60);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size');
            $table->longText('base64');
            $table->timestamps();
        });
        if (!Schema::hasTable('staff_portal_items')) Schema::create('staff_portal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_portal_id')->constrained('staff_portals')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('staff_portal_assets')->restrictOnDelete();
            $table->string('url', 2000)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['staff_portal_id', 'kind', 'status', 'priority'], 'staff_portal_items_feed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_portal_items');
        Schema::dropIfExists('staff_portal_assets');
        Schema::dropIfExists('staff_portals');
    }
};
