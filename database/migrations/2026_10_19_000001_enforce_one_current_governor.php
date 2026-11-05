<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('members')->where('kind', 'governor')->where('is_current', true)->where('is_active', true)
            ->whereNull('deleted_at')->count() > 1;
        if ($duplicate) {
            throw new RuntimeException('More than one current governor exists. Keep one current record before retrying this migration; existing records have not been changed.');
        }
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_governor_key')->nullable()->virtualAs("CASE WHEN kind = 'governor' AND is_current = 1 AND is_active = 1 AND deleted_at IS NULL THEN 1 ELSE NULL END");
            $table->unique('current_governor_key', 'members_one_current_governor');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique('members_one_current_governor');
            $table->dropColumn('current_governor_key');
        });
    }
};
