<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_portal_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_portal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('path', 100);
            $table->longText('body')->nullable();
            $table->string('nav_title', 120)->nullable();
            $table->boolean('show_in_navigation')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['staff_portal_id', 'path']);
            $table->index(['staff_portal_id', 'status', 'show_in_navigation', 'priority'], 'staff_pages_navigation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_portal_pages');
    }
};
