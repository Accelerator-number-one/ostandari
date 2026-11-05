# اجرای لوکال و انتقال به سرور

این شاخه یک برنامهٔ معمول Laravel 13 با Blade، Tailwind و Alpine است. برای اجرا به Vercel، Docker، FrankenPHP، Next.js یا سرور فرانت‌اند جدا نیاز ندارد. `vercel.json`، `Dockerfile.vercel` و راه‌انداز خودکار ورسل از این شاخه حذف شده‌اند. طراحی و مسیرهای سایت حفظ شده‌اند.

## ۱. پیش‌نیازها

- PHP **8.4 یا 8.5**، مطابق `composer.json` و `composer.lock` همین پروژه.
- Composer 2 و MySQL 8؛ برای بیشترین سازگاری با بکاپ، از همان نسخهٔ اصلی MySQL سرویس قبلی استفاده کنید.
- افزونه‌های PHP: `pdo_mysql`، `mbstring`، `openssl`، `fileinfo`، `xml`، `dom`، `ctype`، `curl`، `tokenizer`، `session`، `exif`، `gd` و `intl`.
- Node.js 24 و npm فقط برای تغییر یا بازسازی CSS و JavaScript. خروجی ساخت داخل `public/build` همراه پروژه است.

در ترمینال بررسی کنید:

```bash
php -v
composer --version
mysql --version
```

اگر روی مک از Herd استفاده می‌کنید، نسخهٔ PHP پروژه را 8.4 یا 8.5 انتخاب کنید. MySQL نیز باید روشن باشد. نبودن دستور `mysql` در PATH با خاموش بودن سرویس متفاوت است؛ می‌توانید برای Import از phpMyAdmin هم استفاده کنید.

## ۲. دریافت کد و بکاپ خصوصی

```bash
git clone --branch local-server-ready https://github.com/AmirRiahi2008/ostandari-laravel.git
cd ostandari-laravel
composer install
```

بستهٔ ZIP خصوصی داده‌ها را **داخل ریشهٔ همین پروژهٔ کلون‌شده** استخراج کنید؛ `.env`، `backup-database` و `storage` باید کنار `artisan` قرار بگیرند. این ZIP جایگزین کد پروژه نیست. در مک نمایش فایل‌های مخفی با `Command + Shift + .` فعال می‌شود؛ فایل `.env` را هنگام کپی‌کردن جا نیندازید. `vendor` و `node_modules` قابل نصب مجددند و جزو دادهٔ سایت نیستند.

فایل کامل SQL را با نام `ostandari.sql` در `backup-database` بگذارید. این بکاپ به‌دلیل داشتن کاربران، اطلاعات شهروندان و فایل‌های خصوصی در ریپازیتوری عمومی commit نمی‌شود. پوشهٔ `backup-database` باید بیرون از `public` بماند.

در بستهٔ تحویل، `backup-database/ostandari.sql` نسخهٔ آمادهٔ Import است. انتخاب دیتابیس قدیمی (`CREATE DATABASE` و `USE`) از سرآیند آن حذف شده تا در دیتابیس لوکال انتخابی شما وارد شود. تصویرهای داخل متن که به آدرس هاست قبلی اشاره می‌کردند، پس از تطبیق با فایل موجود به مسیر نسبی تبدیل شده‌اند. فایل اصلی چابکان هم بدون تغییر در `backup-database/original` نگهداری می‌شود؛ برای مراحل این راهنما **نسخهٔ آمادهٔ `ostandari.sql`** را انتخاب کنید. جزئیات فایل‌ها و هر فایل گمشده در `backup-database/BACKUP-STATUS.txt` بستهٔ خصوصی آمده است.

برای انتقال تمام فایل‌ها، محتوای اصلی `storage/app/public` و `storage/app/private` هاست قبلی را هم در همین مسیرهای پروژه کپی کنید. فایل‌ها و نام مسیرها را تغییر ندهید. پوشه‌های cache، session، view و log سرور قبلی را کپی نکنید. پوشهٔ `public/storage` باید لینک جدید ساخته‌شده در همین سیستم باشد، نه لینک مطلق کپی‌شده از سرور.

## ۳. تنظیم فایل محلی `.env`

اگر `.env` ندارید:

```bash
cp .env.example .env
```

اگر بستهٔ خصوصی از قبل `.env` دارد، آن را بازنویسی نکنید. مقدارهای اتصال لوکال را تنظیم کنید:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Tehran
APP_LOCALE=fa

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ostandari_local
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

MAIL_MAILER=log
OTP_DRIVER=log
OTP_EXPOSE_LOCAL=true
EVENT_SMS_DRIVER=log
CITIZEN_DEMO=false
PORTAL_SNAPSHOT=false
```

اگر MySQL لوکال رمز دارد، رمز همان MySQL را در `DB_PASSWORD` بگذارید؛ این مقدار، رمز ورود مدیر سایت نیست. اطلاعات اتصال چابکان را برای اجرای آفلاین لوکال استفاده نکنید. `DB_URL`، `DB_SOCKET`، `ASSET_URL` و تنظیمات به‌جاماندهٔ ورسل را از `.env` محلی حذف کنید، مگر عمداً به آن‌ها نیاز داشته باشید.

**برای دیتابیس منتقل‌شده، `APP_KEY` اصلی سایت قبلی را حفظ کنید.** شماره‌های اشتراک رویدادها در دیتابیس رمز شده‌اند و با کلید جدید قابل خواندن نیستند. نام کاربری و هش رمز مدیر از دیتابیس خوانده می‌شود و `ADMIN_PASSWORD` لازم نیست. فقط در نصب کاملاً تازه که دیتای قبلی ندارد می‌توانید `php artisan key:generate` بزنید. کلید و رمزهای واقعی را در Git قرار ندهید.

## ۴. ایجاد دیتابیس خالی و اجرای SQL

از ریشهٔ پروژه:

```bash
mysql -h 127.0.0.1 -P 3306 -u root -p -e 'CREATE DATABASE ostandari_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
mysql --default-character-set=utf8mb4 -h 127.0.0.1 -P 3306 -u root -p ostandari_local < backup-database/ostandari.sql
```

هر بار رمز **MySQL لوکال** پرسیده می‌شود. اگر رمز ندارد Enter بزنید. ایجاد دیتابیس عمداً `IF NOT EXISTS` ندارد تا دیتابیس پر موجود، اشتباهی مقصد بازیابی نشود. اگر نام از قبل وجود دارد، یک نام تازه انتخاب کنید و همان را در هر دو دستور و `.env` بگذارید.

در phpMyAdmin: یک دیتابیس خالی با collation برابر `utf8mb4_unicode_ci` بسازید، همان را انتخاب کنید، تب **Import** را بزنید و فایل SQL کامل را انتخاب کنید. اگر فایل بزرگ است از ترمینال استفاده کنید. SQL را در ترمینال PHP یا `artisan tinker` اجرا نکنید؛ مقصدش MySQL است.

برای بکاپ فشرده، ابتدا فایل SQL را استخراج کنید؛ فایل اصلی بکاپ را نگه دارید:

```bash
gzip -dk backup-database/ostandari.sql.gz
```

Import را **قبل از migrate و seed روی دیتابیس تازه** انجام دهید. بکاپ کامل، ساختار جدول‌ها، جدول `migrations`، کاربران، محتوا و نسخه‌های پایدار فایل‌ها را با هم برمی‌گرداند. بعد از Import، `db:seed` یا Seederهای دمو را اجرا نکنید.

## ۵. راه‌اندازی لاراول و فایل‌ها

```bash
composer setup
php artisan portal:restore-media
php artisan portal:check --database
php artisan serve --host=127.0.0.1 --port=8000
```

`composer setup` مسیرهای قابل نوشتن را می‌سازد، کش تنظیمات را پاک می‌کند، کلید موجود را نگه می‌دارد، migrationهای اجرا‌نشده را اعمال می‌کند، لینک `public/storage` را می‌سازد و ثبت واقعی لاگ را آزمایش می‌کند. **هیچ Seeder و هیچ importer محتوایی را اجرا نمی‌کند.** هر تغییر migration به‌صورت معمول لاراول و با جدول `migrations` پیگیری می‌شود؛ روی هر درخواست تکرار نمی‌شود.

`portal:restore-media` نسخه‌های موجود در `public_media_files` و `private_media_files` را با کنترل SHA-256 به دیسک مربوط برمی‌گرداند. فایل موجود با محتوای متفاوت به‌صورت پیش‌فرض دست‌نخورده می‌ماند. اگر پیام `Need storage backup` دیدید، آن فایل در SQL کپی ندارد و باید از `storage/app` قبلی منتقل شود. PDF، ویدیو و بعضی فایل‌ها ممکن است فقط در storage باشند؛ SQL به‌تنهایی همیشه تمام فایل‌ها را ندارد. فایل‌های خصوصی به `public` منتقل نمی‌شوند.

اگر فایلی موجود نباشد، دستور بازیابی وضعیت خطا برمی‌گرداند و شناسهٔ رسانه را گزارش می‌کند؛ داده‌های دیتابیس را حذف نمی‌کند. وضعیت را با گزارش داخل بسته مقایسه کنید. وجود یک رسانهٔ قدیمی بدون استفاده در گالری آپلودها، به معنی خراب بودن تمام انتقال نیست؛ فایل گمشده را با تصویر حدسی جایگزین نکنید.

فقط پس از بررسی تعارض‌ها، اگر هدف جایگزینی فایل‌های محلی با نسخهٔ معتبر SQL است:

```bash
php artisan portal:restore-media --overwrite
```

سایت: `http://127.0.0.1:8000`؛ ورود مدیر: `http://127.0.0.1:8000/panel/admin/login`. از حساب‌ها و رمزهای فعلی دیتابیس استفاده کنید.

## ۶. بررسی و مشاهدهٔ لاگ

```bash
php artisan portal:check
tail -f storage/logs/laravel.log
```

دستور بررسی، یک پیام مشخص `PORTAL_LOG_CHECK` با شناسهٔ تصادفی می‌نویسد و وجود همان پیام را در فایل واقعی می‌خواند. موفقیت آن فقط بررسی تنظیمات نیست. خطاهای برنامه نیز از سازوکار استاندارد Laravel در لاگ ثبت می‌شوند؛ `APP_DEBUG=false` آن‌ها را از فایل حذف نمی‌کند. در حالت معمول برای هر درخواست موفق HTTP یک پیام در `laravel.log` نوشته نمی‌شود؛ این رفتار پیش‌فرض لاراول است. خروجی درخواست‌های `artisan serve` را در همان ترمینال اجرا می‌بینید.

پس از تغییر `.env`:

```bash
php artisan config:clear
```

اگر لاگ نوشته نشد، مالک و دسترسی پوشه‌های `storage` و `bootstrap/cache` را برای کاربر PHP درست کنید. روی سیستم خودتان یا سرور، به‌صورت بی‌هدف `chmod 777` نزنید. اگر تنظیمات قبلی `LOG_CHANNEL=stderr` بوده، آن را به مقادیر بالا برگردانید و `config:clear` بزنید.

## ۷. تغییر ظاهر و کار با Vite

برای دیدن همین طراحی، فایل‌های ساخته‌شدهٔ `public/build` کافی‌اند. برای توسعه، در دو ترمینال:

```bash
# ترمینال اول
php artisan serve
```

```bash
# ترمینال دوم
npm ci
npm run dev
```

برای خروجی نهایی CSS و JavaScript:

```bash
npm ci
npm run build
```

پروژه به Vite به‌عنوان ابزار ساخت لاراول نیاز دارد، نه یک برنامهٔ Next.js. روی سرور `npm run dev` اجرا نکنید و فایل `public/hot` را منتقل نکنید.

## ۸. انتقال همین نسخه به سرور

1. از دیتابیس و `storage/app` لوکال، بکاپ تازه بگیرید. اگر سرور از قبل دادهٔ واقعی دارد، بکاپ لوکال را بدون برنامهٔ انتقال روی آن Import نکنید.
2. کد پروژه، `public/build`، فایل‌های طراحی و تمام `storage/app` را به سرور منتقل کنید. Document Root وب‌سرور باید **پوشهٔ `public`** پروژه باشد؛ ریشهٔ پروژه نباید عمومی باشد.
3. یک `.env` سرور بسازید یا فایل فعلی آن را ویرایش کنید. `.env.production.example` فقط نمونه است؛ `APP_KEY` اصلی، اتصال دیتابیس سرور، دامنهٔ واقعی و سرویس پیامک را تنظیم کنید. `APP_ENV=production`، `APP_DEBUG=false` و برای HTTPS، `SESSION_SECURE_COOKIE=true` باشد. رمزها و `.env` محلی را داخل Git نفرستید.
4. از ریشهٔ پروژه اجرا کنید:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan storage:link
php artisan portal:restore-media
php artisan portal:check --database
php artisan optimize
```

اگر لینک `public/storage` از قبل درست است، پیام وجود داشتن آن طبیعی است. لینک مطلق سرور قبلی را با لینک سرور جدید جایگزین کنید، اما پوشهٔ واقعی حاوی فایل‌ها را حذف نکنید. پوشه‌های `storage` و `bootstrap/cache` باید برای کاربر PHP-FPM قابل نوشتن باشند. `php artisan serve` فقط برای توسعه است؛ روی سرور از وب‌سرور و PHP-FPM همان هاست استفاده کنید.

پیشنهاد فایل لاگ سرور در نمونهٔ `.env`:

```dotenv
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

در این حالت فایل به‌شکل `storage/logs/laravel-YYYY-MM-DD.log` ساخته می‌شود. `portal:check` مسیر واقعی را نشان می‌دهد. اگر همان یک فایل را می‌خواهید `LOG_STACK=single` بگذارید.

برای اعلان‌های زمان‌بندی‌شده، Cron استاندارد لاراول لازم است:

```cron
* * * * * cd /path/to/ostandari-laravel && php artisan schedule:run >> /dev/null 2>&1
```

با `QUEUE_CONNECTION=sync` پردازش صف Worker جداگانه لازم ندارد. برای پیامک واقعی باید gateway تنظیم شود؛ `OTP_DRIVER=log` و `EVENT_SMS_DRIVER=log` فقط پیام را ثبت می‌کنند و پیامک ارسال نمی‌کنند.

## ۹. مواردی که موقع انتقال نباید اجرا کنید

- `migrate:fresh` یا `db:wipe` روی دیتابیس بازیابی‌شده.
- Seeder دمو برای به‌دست آوردن اطلاعات سایت واقعی.
- `key:generate` روی نصب دارای دادهٔ رمز‌شدهٔ قبلی.
- قراردادن `.env`، بکاپ SQL یا `storage/app/private` در Git عمومی یا در Document Root.

نصب کاملاً تازه و بدون بکاپ مسیر جداگانه دارد: پس از ساخت دیتابیس خالی و `composer setup`، یک‌بار `php artisan db:seed` برای نقش‌ها و سازمان‌های پایه و سپس `php artisan portal:admin-create` برای ایجاد مدیر بزنید. این مسیر اطلاعات واقعی سایت فعلی را بازسازی نمی‌کند.
