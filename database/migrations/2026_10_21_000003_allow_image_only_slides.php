<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('slides')->whereNull('title')->update(['title' => '']);
        Schema::table('slides', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
