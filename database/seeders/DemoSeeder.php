<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Content;
use App\Models\County;
use App\Models\DynamicForm;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\Link;
use App\Models\Member;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Optional, clearly marked fictional data. Never replaces existing records. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DemoSeeder is available only in local/testing.');
        }
        $this->call(DatabaseSeeder::class);
        DB::transaction(function () {
            $root = Organization::where('slug', 'mazandaran')->firstOrFail();
            $author = User::where('username', 'admin')->firstOrFail();
            $this->call(CountySeeder::class);
            foreach (config('portal.locales') as $locale) {
                foreach (config('portal.category_types') as $type) {
                    Category::firstOrCreate(['type' => $type, 'locale' => $locale, 'slug' => $type], ['name' => $type.' (نمونه)', 'organization_id' => $root->id]);
                }
                $menu = Menu::firstOrCreate(['key' => 'main', 'locale' => $locale], ['name' => 'منوی اصلی']);
                foreach (['اخبار' => '/news', 'خدمات' => '/services', 'سامانه شفافیت' => '/transparency', 'ارتباط با استاندار' => '/contact'] as $title => $url) {
                    MenuItem::firstOrCreate(['menu_id' => $menu->id, 'url' => $url], ['title' => $title, 'priority' => MenuItem::where('menu_id', $menu->id)->count()]);
                }
                foreach (config('portal.content_types') as $type) {
                    for ($i = 1; $i <= 3; $i++) {
                        $title = match ($locale) {
                            'en' => "Sample {$type} {$i}",'ar' => "محتوى تجريبي {$type} {$i}",default => ['news' => 'خبر', 'announcement' => 'اطلاعیه', 'resolution' => 'مصوبه', 'page' => 'صفحه', 'publication' => 'انتشار', 'tender' => 'مناقصه', 'contract' => 'قرارداد', 'call' => 'فراخوان', 'event' => 'رویداد', 'duty' => 'شرح وظایف', 'document' => 'سند'][$type].' نمونه '.$i
                        };
                        Content::firstOrCreate(['type' => $type, 'locale' => $locale, 'slug' => $type.'-sample-'.$i], ['title' => $title, 'excerpt' => 'این محتوا صرفاً برای آزمایش API است و خبر رسمی نیست.',
                            'body' => '<p>این متن، داده آزمایشی قابل ویرایش است. محتوا و تصاویر رسمی باید توسط مدیر سامانه وارد شوند.</p>', 'author_id' => $author->id, 'organization_id' => $root->id,
                            'category_id' => Category::where('type', $type === 'news' ? 'news' : 'document')->where('locale', $locale)->value('id'), 'status' => 'published', 'published_at' => now()->subDays($i),
                            'is_featured' => $type === 'news' && $i === 1, 'comments_enabled' => $type === 'news', 'metadata' => ['demo' => true]]);
                    }
                }
            }
            foreach (['manager', 'governor', 'county_governor', 'district_head', 'village_head', 'advisor'] as $i => $kind) {
                Member::firstOrCreate(['kind' => $kind, 'name' => 'عضو آزمایشی '.$kind], ['position' => 'سمت نمونه', 'locale' => 'fa', 'organization_id' => $root->id, 'priority' => $i, 'is_current' => $kind !== 'governor' || ! Member::active()->where('kind', 'governor')->where('is_current', true)->exists(), 'bio' => 'اطلاعات نمایشی؛ نام شخص واقعی نیست.']);
            }
            for ($i = 1; $i <= 8; $i++) {
                Service::firstOrCreate(['slug' => 'sample-service-'.$i, 'locale' => 'fa'], ['name' => 'خدمت الکترونیکی نمونه '.$i, 'code' => 'MZ-'.$i, 'organization_id' => $root->id,
                    'category_id' => Category::where('type', 'service')->where('locale', 'fa')->value('id'), 'description' => 'شرح آزمایشی خدمت', 'requirements' => ['مدارک شناسایی', 'تکمیل فرم درخواست'], 'steps' => ['ثبت درخواست', 'بررسی مدارک', 'اعلام نتیجه'], 'delivery_time' => '۷ روز کاری', 'cost' => 'مطابق تعرفه']);
                Link::firstOrCreate(['title' => 'پیوند آزمایشی '.$i, 'locale' => 'fa'], ['url' => 'https://new.ostan-mz.ir/', 'priority' => $i]);
            }
            foreach (['چگونه گزارش خود را پیگیری کنم؟' => 'پس از ورود، از بخش پیگیری گزارش‌ها و شناسه درخواست استفاده کنید.', 'چقدر زمان برای پاسخ‌گویی نیاز است؟' => 'برای گزارش‌ها هفت روز کاری و برای پیام‌های دفتر استاندار پنج روز کاری در داده نمونه در نظر گرفته شده است.', 'آیا فایل گزارش عمومی است؟' => 'فایل‌های گزارش خصوصی هستند و فقط مالک و کارشناسان مجاز می‌توانند آن‌ها را دریافت کنند.'] as $q => $a) {
                Faq::firstOrCreate(['question' => $q, 'locale' => 'fa'], ['answer' => $a]);
            }
            Quote::firstOrCreate(['author' => 'نمونه', 'text' => 'پاسخ‌گویی، شفافیت و مشارکت مردمی', 'locale' => 'fa']);
            Gallery::firstOrCreate(['slug' => 'sample-gallery', 'locale' => 'fa'], ['title' => 'گالری آزمایشی', 'description' => 'برای تکمیل این گالری، فایل واقعی را از API رسانه بارگذاری کنید.', 'organization_id' => $root->id, 'status' => 'published', 'published_at' => now()]);
            $poll = Poll::firstOrCreate(['question' => 'اولویت پیشنهادی شما برای توسعه استان چیست؟'], ['locale' => 'fa', 'status' => 'draft']);
            foreach (['زیرساخت و راه', 'محیط زیست', 'گردشگری', 'بهداشت'] as $i => $title) {
                PollOption::firstOrCreate(['poll_id' => $poll->id, 'title' => $title], ['priority' => $i]);
            }
            if ($poll->wasRecentlyCreated) {
                $poll->update(['status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
            }
            DynamicForm::firstOrCreate(['slug' => 'sample-application', 'locale' => 'fa'], ['title' => 'فرم درخواست نمونه', 'organization_id' => $root->id, 'is_active' => true,
                'fields' => [['key' => 'full_name', 'label' => 'نام کامل', 'type' => 'text', 'required' => true, 'max' => 120], ['key' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => true],
                    ['key' => 'subject', 'label' => 'موضوع', 'type' => 'select', 'required' => true, 'options' => ['درخواست اطلاعات', 'پیشنهاد']], ['key' => 'description', 'label' => 'توضیحات', 'type' => 'textarea', 'required' => false]]]);
            $settings = ['site.name' => 'استانداری مازندران', 'site.description' => 'محیط محلی و داده‌های آزمایشی', 'site.slogan' => 'اقتصاد مقاومتی در سایه وحدت ملی و امنیت ملی',
                'site.contact' => ['address' => 'مازندران، ساری', 'email' => null, 'phone' => null], 'site.socials' => [], 'site.footer' => ['text' => 'پرتال استانداری مازندران'],
                'home.sections' => [['key' => 'news', 'position' => 'center', 'priority' => 1, 'enabled' => true], ['key' => 'announcements', 'position' => 'right', 'priority' => 2, 'enabled' => true]]];
            foreach ($settings as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['value' => $value, 'is_public' => true]);
            }
        });
    }
}
