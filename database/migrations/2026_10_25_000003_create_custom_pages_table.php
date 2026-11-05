<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('custom_pages')) Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('path');
            $table->longText('body');
            $table->string('link_title')->nullable();
            $table->string('link_style', 10)->default('text');
            $table->foreignId('link_image_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->json('placements');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'path'], 'custom_pages_portal_path_unique');
            $table->index(['organization_id', 'status', 'priority']);
        });
        if (!Schema::hasTable('custom_page_images')) Schema::create('custom_page_images', function (Blueprint $table) {
            $table->foreignId('media_id')->primary()->constrained('media')->restrictOnDelete();
            $table->longText('base64');
        });
    }

    public function down(): void { Schema::dropIfExists('custom_page_images'); Schema::dropIfExists('custom_pages'); }
};
