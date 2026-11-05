<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_pages', function (Blueprint $table) {
            $table->string('legacy_source_url', 2048)->nullable();
            $table->string('legacy_source_key', 64)->nullable();
            $table->unique(['organization_id', 'legacy_source_key'], 'custom_pages_legacy_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('custom_pages', function (Blueprint $table) {
            $table->dropUnique('custom_pages_legacy_source_unique');
            $table->dropColumn(['legacy_source_url', 'legacy_source_key']);
        });
    }
};
