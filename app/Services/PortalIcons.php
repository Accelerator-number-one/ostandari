<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;

final class PortalIcons
{
    public const ITEMS = [
        'home' => ['خانه', 'home house منزل'], 'building' => ['ساختمان', 'building سازمان اداره'],
        'users' => ['کاربران', 'users people شهروند مردم'], 'phone' => ['تلفن', 'phone تماس'],
        'mail' => ['نامه', 'mail email ایمیل پست'], 'file' => ['سند', 'file document مصوبه اسناد'],
        'news' => ['اخبار', 'news newspaper خبر روزنامه'], 'calendar' => ['تقویم', 'calendar event رویداد'],
        'location' => ['موقعیت', 'location map pin نقشه نشانی'], 'globe' => ['وب‌سایت', 'globe world internet جهان'],
        'link' => ['پیوند', 'link chain لینک'], 'download' => ['دریافت', 'download دانلود نصب'],
        'search' => ['جستجو', 'search find ذره بین'], 'heart' => ['سلامت', 'heart health قلب بهداشت'],
        'education' => ['آموزش', 'education book تحصیل کتاب'], 'briefcase' => ['کسب‌وکار', 'briefcase business کار اشتغال'],
        'shield' => ['امنیت', 'shield safety حفاظت سپر'], 'chart' => ['آمار', 'chart statistics نمودار'],
        'camera' => ['تصویر', 'camera image photo عکس گالری'], 'bell' => ['اطلاع‌رسانی', 'bell notification زنگ اطلاعیه'],
        'settings' => ['تنظیمات', 'settings tools ابزار'], 'help' => ['راهنما', 'help question پرسش سوال'],
        'mobile' => ['تلفن همراه', 'mobile app اپلیکیشن گوشی'], 'leaf' => ['محیط زیست', 'leaf nature طبیعت برگ'],
    ];

    public static function media(string $key, User $actor, ?int $organizationId): Media
    {
        abort_unless(isset(self::ITEMS[$key]), 422);
        $path = $key.'.svg';
        return Media::firstOrCreate(['disk' => 'portal-icons', 'path' => $path, 'organization_id' => $organizationId,
            'user_id' => $actor->id], ['original_name' => self::ITEMS[$key][0].'.svg', 'mime' => 'image/svg+xml',
            'size' => filesize(public_path('design/icons/'.$path)), 'visibility' => 'public', 'alt' => self::ITEMS[$key][0],
            'width' => 48, 'height' => 48]);
    }
}
