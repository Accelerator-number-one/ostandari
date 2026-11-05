<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('banner_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->foreignId('aerial_media_id')->nullable()->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aerial_media_id');
            $table->dropConstrainedForeignId('banner_media_id');
        });
    }
};
