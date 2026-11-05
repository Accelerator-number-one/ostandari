<?php

namespace App\Console\Commands;

use App\Services\ManagerPages;
use Illuminate\Console\Command;

final class PrepareManagerPages extends Command
{
    protected $signature = 'portal:prepare-manager-pages';
    protected $description = 'Connect provincial manager directories to local pages and prepare the editable PR download page';

    public function handle(ManagerPages $pages): int
    {
        $result = $pages->prepare();
        $this->info($result['already_prepared'] ? 'صفحه‌های مدیران از قبل آماده شده‌اند؛ تغییرات پنل حفظ شدند.' : 'صفحه‌های مدیران آماده شدند.');
        $this->table(['پیوند اصلاح‌شده', 'پیوند جدید', 'شناسه صفحه روابط عمومی'], [[$result['updated_links'], $result['created_links'], $result['page_id']]]);
        $this->line('ویرایش متن و لینک PDF: /panel/admin/pages/'.$result['page_id'].'/edit');
        $this->line('اسامی، سمت‌ها و تصاویر فعلی مدیران تغییر نکردند.');
        return self::SUCCESS;
    }
}
