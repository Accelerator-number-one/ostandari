# بکاپ دیتابیس

این پوشه برای **بکاپ کامل SQL واقعی چابکان** است. فایل SQL، اطلاعات شخصی و رمزها در ریپازیتوری عمومی قرار نمی‌گیرند؛ فایل بکاپ باید از بستهٔ خصوصی تحویل، در همین پوشه کپی شود. وجود این README به معنای تهیه‌شدن بکاپ نیست.

بکاپ کامل باید ساختار و دادهٔ همهٔ جدول‌ها، از جمله `migrations`، `users`، `media`، `public_media_files`، `private_media_files` و `custom_page_images` را شامل شود. خروجی صرفاً دادهٔ متنی یا Seeder جایگزین بکاپ نیست.

در بستهٔ خصوصی، فایل آمادهٔ Import نام `ostandari.sql` دارد؛ نسخهٔ اصلی بدون تغییر در زیرپوشهٔ `original` نگهداری شده است. نسخهٔ آماده دیتابیس قبلی را انتخاب یا ایجاد نمی‌کند و در دیتابیسی وارد می‌شود که خودتان انتخاب می‌کنید. گزارش فایل‌ها و محدودیت‌های بکاپ در `BACKUP-STATUS.txt` همان بسته است؛ این فایل‌ها در Git عمومی نیستند.

برای بازیابی در دیتابیس **خالی** محلی:

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p -e 'CREATE DATABASE ostandari_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
mysql --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root -p ostandari_local < backup-database/ostandari.sql
```

اگر کاربر MySQL لوکال رمز ندارد، در درخواست رمز Enter بزنید. حساب و رمز MySQL با حساب پنل سایت متفاوت است. این دستور روی دیتابیسی که از قبل داده دارد اجرا نشود. در phpMyAdmin هم همین دیتابیس خالی را انتخاب کنید و از تب Import فایل را وارد کنید؛ در دیتابیس بزرگ از دستور ترمینال استفاده کنید.

سپس اطلاعات همین دیتابیس و **APP_KEY اصلی سایت قبلی** را در `.env` قرار دهید و از ریشهٔ پروژه اجرا کنید:

```bash
composer setup
php artisan portal:restore-media
php artisan portal:check --database
php artisan serve
```

`composer setup` مایگریشن‌های اجرا‌نشده را اعمال می‌کند؛ Seeder یا واردکنندهٔ محتوا اجرا نمی‌کند. `portal:restore-media` نسخهٔ ذخیره‌شدهٔ فایل‌ها در SQL را با کنترل SHA-256 به `storage/app/public` و `storage/app/private` برمی‌گرداند. اگر گزارشی از فایل‌های گمشده دریافت کردید، نسخهٔ اصلی `storage/app` هاست قبلی نیز لازم است؛ ویدیو، PDF و همهٔ فایل‌ها الزاماً داخل دیتابیس نیستند.

`APP_KEY` برای اطلاعات رمز‌شدهٔ موجود لازم است. روی نصب منتقل‌شده `key:generate` اجرا نکنید. روی دیتابیس واردشده `migrate:fresh`، `db:wipe` یا Seeder دمو اجرا نکنید.

راهنمای کامل لوکال و انتقال به سرور: [docs/LOCAL-SETUP-FA.md](../docs/LOCAL-SETUP-FA.md).
