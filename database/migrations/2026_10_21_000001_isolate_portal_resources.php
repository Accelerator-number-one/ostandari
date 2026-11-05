<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['menus', 'slides', 'links', 'faqs', 'quotes', 'polls', 'tags', 'media'];

    public function up(): void
    {
        $root = DB::table('organizations')->where('slug', 'mazandaran')->value('id');
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete());
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('organization_id'));
            if ($root) DB::table($table)->whereNull('organization_id')->update(['organization_id' => $root]);
        }
        DB::table('media')->orderBy('id')->chunkById(500, function ($files) use ($root) {
            $owners = DB::table('users')->whereIn('id', $files->pluck('user_id'))->pluck('organization_id', 'id');
            foreach ($files as $file) DB::table('media')->where('id', $file->id)->update(['organization_id' => $owners[$file->user_id] ?? $root]);
        });
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique('menus_key_locale_unique');
            $table->unique(['organization_id', 'key', 'locale']);
        });
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_locale_slug_unique');
            $table->unique(['organization_id', 'locale', 'slug']);
        });
    }

    public function down(): void
    {
        foreach (['menus' => ['key', 'locale'], 'tags' => ['locale', 'slug']] as $table => $columns) {
            if (DB::table($table)->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Consolidate duplicate organization-specific menu keys and tags before rolling back portal isolation.');
            }
        }
        Schema::table('menus', function (Blueprint $table) {
            $table->dropUnique('menus_organization_id_key_locale_unique');
            $table->unique(['key', 'locale']);
        });
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_organization_id_locale_slug_unique');
            $table->unique(['locale', 'slug']);
        });
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('organization_id'));
        }
    }
};
