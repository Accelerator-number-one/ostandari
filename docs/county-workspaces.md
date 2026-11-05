# پنل روابط عمومی فرمانداری‌ها

هر فرمانداری یک فضای کاری مستقل دارد. ورود فقط برای حساب فعال روابط عمومی متصل به همان فرمانداری مجاز است. روابط عمومی استانداری، مدیر مرکزی و روابط عمومی شهر دیگر اجازهٔ ورود به این پنل را ندارند؛ مدیریت مرکزی همچنان از پنل خودش استفاده می‌کند. حساب‌های قدیمی `editor` فقط اگر به همان فرمانداری متصل باشند پشتیبانی می‌شوند.

## مسیرها

| شهرستان | ورود | داشبورد |
|---|---|---|
| رامسر | `/county-admin/ramsar/login` | `/county-admin/ramsar` |
| تنکابن | `/county-admin/tonekabon/login` | `/county-admin/tonekabon` |
| عباس‌آباد | `/county-admin/abbasabad/login` | `/county-admin/abbasabad` |
| چالوس | `/county-admin/chalus/login` | `/county-admin/chalus` |
| نوشهر | `/county-admin/noshahr/login` | `/county-admin/noshahr` |
| محمودآباد | `/county-admin/mahmudabad/login` | `/county-admin/mahmudabad` |
| آمل | `/county-admin/amol/login` | `/county-admin/amol` |
| فریدونکنار | `/county-admin/fereydunkenar/login` | `/county-admin/fereydunkenar` |
| بابلسر | `/county-admin/babolsar/login` | `/county-admin/babolsar` |
| جویبار | `/county-admin/juybar/login` | `/county-admin/juybar` |
| سیمرغ | `/county-admin/simorgh/login` | `/county-admin/simorgh` |
| بابل | `/county-admin/babol/login` | `/county-admin/babol` |
| سوادکوه | `/county-admin/savadkuh/login` | `/county-admin/savadkuh` |
| سوادکوه شمالی | `/county-admin/savadkuh-shomali/login` | `/county-admin/savadkuh-shomali` |
| ساری | `/county-admin/sari/login` | `/county-admin/sari` |
| گلوگاه | `/county-admin/galugah/login` | `/county-admin/galugah` |
| میاندورود | `/county-admin/miandorud/login` | `/county-admin/miandorud` |
| قائم‌شهر | `/county-admin/qaemshahr/login` | `/county-admin/qaemshahr` |
| نور | `/county-admin/nur/login` | `/county-admin/nur` |
| کلاردشت | `/county-admin/kelardasht/login` | `/county-admin/kelardasht` |
| نکا | `/county-admin/neka/login` | `/county-admin/neka` |
| بهشهر | `/county-admin/behshahr/login` | `/county-admin/behshahr` |

مسیرهای داخلی نیز زیر داشبورد همان شهر قرار دارند. برای نمونه در آمل:

- `/county-admin/amol/portal`: اطلاعات و ظاهر پرتال، معرفی شهرستان، تماس، تصاویر و نمایش بخش‌ها.
- `/county-admin/amol/news`: فهرست اخبار؛ `/news/new` برای افزودن و `/news/{id}/edit` برای ویرایش.
- `/county-admin/amol/announcements`، `/resolutions`، `/events`، `/duties`، `/pages`: انواع محتوا.
- `/county-admin/amol/county-governors`: مشخصات و تصویر فرماندار.
- `/county-admin/amol/members` و `/advisors`: مدیران و مشاوران شهرستان.
- `/county-admin/amol/slides`، `/advertisements`، `/links`: اسلایدشو، بنرها و پیوندها.
- `/county-admin/amol/galleries` و `/gallery-items`: آلبوم و تصاویر آن.
- `/county-admin/amol/images` و `/files`: رسانه‌های همان فرمانداری.
- `/county-admin/amol/services`، `/forms`، `/faqs`، `/polls`: خدمات، فرم‌ها، راهنما و نظرسنجی.
- `/county-admin/amol/menu-groups` و `/menu-items`: منوهای همان پرتال.
- `/county-admin/amol/reports` و `/comments/pending`: رسیدگی به پیام‌ها و دیدگاه‌های همان سازمان.

## ساخت حساب

از کنسول پروژه، یک بار برای هر شهرستان حساب بسازید:

```bash
php artisan portal:public-relations amol --username=amol_pr
```

رمز به‌صورت مخفی در کنسول پرسیده می‌شود و باید حداقل ۱۲ کاراکتر، حروف کوچک و بزرگ و عدد داشته باشد. رمز در دیتابیس به‌صورت هش ذخیره می‌شود. این دستور رمز حساب‌های قبلی را تغییر نمی‌دهد. برای شهر دیگر، `amol` را با شناسهٔ آن شهر عوض کنید؛ مانند `nur` یا `sari`.

اگر دستور اعلام کرد سازمان یا نقش موجود نیست، قبل از آن فقط این سیدرهای پایه را اجرا کنید:

```bash
php artisan db:seed --class=CountySeeder --force
php artisan db:seed --class=PortalRolesSeeder --force
```

این قابلیت به مهاجرت تازه، `migrate:fresh` یا اجرای دوبارهٔ سیدر کامل نیاز ندارد. از ریست کردن دیتابیس موجود خودداری کنید.

## مدیریت سایت شهرستان

در «ظاهر و اطلاعات پرتال» عنوان، متن معرفی، اطلاعات تماس، تصویر هوایی، لوگو، بنر و لوگوی شعار سال را تنظیم کنید. با حذف تصویر اختصاصی، طرح پیش‌فرض یا شعار سال استانداری استفاده می‌شود. نمایش بخش‌های صفحهٔ اصلی و عنوان آن‌ها نیز قابل تغییر است.

دکمه‌های ویرایش منو، در نخستین استفاده منوی پیش‌فرض همان شهرستان را آماده می‌کنند؛ تکرار آن‌ها منوی تکراری ایجاد نمی‌کند. پس از آن آیتم‌ها را اضافه، ویرایش، غیرفعال یا حذف کنید. منوهای اصلی، بالای سایت، پایین سایت و دسترسی سریع مستقل هستند.

در «صفحات داخلی»، گزینهٔ «نمایش در صفحهٔ سایت» متن و تصاویر یک مطلب منتشرشده را به صفحه‌ای مانند اطلاعات عمومی یا دسترسی آزاد به اطلاعات متصل می‌کند. برای هر محل، مطلب فعلی را ویرایش کنید. انتخاب «صفحهٔ مستقل» این اتصال را برمی‌دارد. مقررات ثبت‌نام شهروندی جداست و از این فرم تغییر نمی‌کند.

## انتشار خبر در استانداری

خبر فرمانداری پس از انتشار و رسیدن زمان انتشار، هم در سایت همان شهرستان و هم در تب «اخبار فرمانداری‌ها»ی صفحهٔ نخست استانداری و آرشیو آن قابل مشاهده است. گروه‌بندی قدیمی واردشده از SQL مانع آن نمی‌شود. پیش‌نویس، مطلب منقضی، حذف‌شده، زمان‌بندی‌شده برای آینده یا متعلق به فرمانداری غیرفعال نمایش داده نمی‌شود. لینک خبر در این تب به جزئیات آن در پرتال همان شهر می‌رود.

## محدودهٔ دسترسی

محدودیت در سرور و API نیز اعمال می‌شود: تغییر شناسهٔ سازمان، شناسهٔ شهرستان، نقش، سطح دسترسی یا استفاده از فایل و محتوای شهر دیگر رد می‌شود. تنظیمات خصوصی هر شهرستان در رکورد جداگانه ذخیره می‌شود و تنظیمات استانداری را بازنویسی نمی‌کند. ساخت کاربران و نقش‌های سامانه در اختیار پنل فرمانداری قرار نمی‌گیرد.

پس از دریافت کد، وابستگی‌های موجود را نصب و کش‌های لاراول را پاک کنید. فایل‌های ساخته‌شدهٔ فرانت نیز در مخزن قرار دارند. در هاست اشتراک فایل‌ها و تصاویر باید مانند قبل پایدار باشد.

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```
