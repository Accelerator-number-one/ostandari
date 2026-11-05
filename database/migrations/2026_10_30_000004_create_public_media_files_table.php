<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public_media_files')) Schema::create('public_media_files', function (Blueprint $table) {
            $table->foreignId('media_id')->primary()->constrained('media')->restrictOnDelete();
            $table->longText('base64');
            $table->char('sha256', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_media_files');
    }
};
