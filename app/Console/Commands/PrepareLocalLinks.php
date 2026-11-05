<?php

namespace App\Console\Commands;

use App\Services\LocalLinks;
use Illuminate\Console\Command;

final class PrepareLocalLinks extends Command
{
    protected $signature = 'portal:prepare-local-links';
    protected $description = 'Point legacy link-index destinations to the local database-backed link directory';

    public function handle(LocalLinks $links): int
    {
        $count = $links->prepare();
        $this->info('تعداد پیوندهای اصلاح‌شده: '.$count);
        $this->line('همهٔ پیوندها: /links');
        $this->line('مدیریت پیوندها: /panel/admin/links');
        return self::SUCCESS;
    }
}
