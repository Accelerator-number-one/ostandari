<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, DB, Schema};

final class PrepareCustomPages extends Command
{
    protected $signature = 'portal:prepare-pages';
    protected $description = 'Create only the additive custom-page table without modifying existing portal data.';

    public function handle(): int
    {
        $lock = false;
        try {
            if (Schema::hasTable('custom_pages') && Schema::hasTable('custom_page_images')) return self::SUCCESS;
            $lock = (int) DB::selectOne("SELECT GET_LOCK('portal_prepare_custom_pages', 10) AS acquired")->acquired === 1;
            if (!$lock) return self::FAILURE;
            $result = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_25_000003_create_custom_pages_table.php', '--force' => true, '--no-interaction' => true]);
            $this->output->write(Artisan::output());
            return $result;
        } finally { if ($lock) DB::select("SELECT RELEASE_LOCK('portal_prepare_custom_pages')"); }
    }
}
