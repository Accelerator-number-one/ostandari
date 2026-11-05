<?php

namespace App\Console\Commands;

use App\Services\LegacyLinkPages;
use Illuminate\Console\Command;

final class PrepareLegacyLinkPages extends Command
{
    protected $signature = 'portal:prepare-legacy-link-pages';
    protected $description = 'Create editable empty CMS pages for links to the previous governorate website';

    public function handle(LegacyLinkPages $pages): int
    {
        $result = $pages->prepare();
        $this->info('صفحه‌های ساخته‌شده: '.$result['created']);
        $this->line('صفحه‌های قبلی حفظ‌شده: '.$result['existing']);
        $this->line('پیوندهای اصلاح‌شده: '.$result['updated_links']);
        $this->line('مدیریت محتوا: /panel/admin/custom-pages?tab=list');
        return self::SUCCESS;
    }
}
