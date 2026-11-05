# راهنمای API

آدرس پایه: `http://127.0.0.1:8000/api/v1`

تمام مسیرهای این سند نسبت به آدرس پایه هستند. برای JSON هدرهای `Accept: application/json` و `Content-Type: application/json` را بفرستید. توکن با `Authorization: Bearer ...` ارسال می‌شود. برای آپلود، از `multipart/form-data` استفاده کنید و boundary را به کلاینت بسپارید.

## قرارداد پاسخ

```json
{"success":true,"message":"...","data":{}}
```

لیست‌ها:

```json
{"success":true,"data":[],"meta":{"current_page":1,"last_page":1,"per_page":20,"total":0}}
```

خطا:

```json
{"success":false,"message":"اطلاعات واردشده معتبر نیست.","errors":{"title":["وارد کردن عنوان الزامی است."]}}
```

حذف موفق `204` و بدون بدنه است. وضعیت‌های اصلی: `401` ورود لازم، `403` مجوز ناکافی، `404` رکورد پیدا نشد/خارج از دسترسی، `409` تعارض یا وابستگی، `422` اعتبارسنجی، `429` محدودیت درخواست، `503` سرویس جانبی تنظیم نشده یا در دسترس نیست.

صفحه‌بندی: `page`, `per_page` حداکثر ۱۰۰. `q` جست‌وجوی متنی است. شناسه‌ها عدد صحیح‌اند؛ `reference` درخواست/فرم ULID است. تاریخ‌ها ISO 8601، تاریخ بدون ساعت `YYYY-MM-DD` و زبان یکی از `fa/en/ar` است. ارقام هویتی فارسی/عربی قبل از بررسی به لاتین تبدیل می‌شوند.

## عمومی

| روش و مسیر | کاربرد |
|---|---|
| `GET /health` | وضعیت API و اتصال دیتابیس |
| `GET /home?locale=fa` | داده تجمیعی صفحه اصلی |
| `GET /portals/{organization_slug}` | داده پرتال یک سازمان |
| `GET /settings` | فقط تنظیمات عمومی مجاز |
| `GET /menus?key=main&locale=fa` | منوهای درختی؛ children چندسطحی |
| `GET /contents` | محتوا؛ فیلتر type/category_id/organization_id/tag_id |
| `GET /contents/{id}` | محتوای منتشرشده، مرتبط‌ها و نظرات تأییدشده |
| `GET /contents/by-slug/{type}/{slug}?locale=fa` | دریافت محتوا با نوع و نشانی |
| `GET /search?q=...&locale=fa` | جست‌وجوی محتوا، خدمت و FAQ |
| `POST /assistant/query` | پاسخ‌گوی جست‌وجوی محلی؛ question و locale |
| `GET /counties/by-slug/{slug}` | معرفی شهرستان، سازمان‌ها و مدیران |
| `POST /contact` | پیام مهمان به روابط عمومی |

فهرست‌های مستقیم محتوا: `/news`, `/announcements`, `/resolutions`, `/documents`, `/publications`, `/tenders`, `/contracts`, `/calls`, `/events`, `/pages`, `/duties`.

فهرست و جزئیات عمومی: `/categories`, `/counties`, `/organizations`, `/members`, `/services`, `/links`, `/faqs`, `/quotes`, `/galleries`, `/polls`, `/forms`. برای جزئیات `/{id}` اضافه کنید. فیلترهای مجاز هر کدام در `resources.json` ثبت شده است. منابع غیرفعال، پیش‌نویس یا دارای زمان انتشار آینده نمایش داده نمی‌شوند. گالری شامل items و مشخصات media است؛ MIME مشخص می‌کند فایل تصویر، ویدئو یا صوت است.

ورودی تماس مهمان:

```json
{"name":"نام فرستنده","email":"person@example.com","phone":"09120000000","subject":"موضوع","body":"متن پیام با حداقل ده نویسه"}
```

تماس مهمان فقط شناسه ثبت می‌دهد و داده تماس را عمومی نمی‌کند؛ دریافت وضعیت و مکاتبه خصوصی نیازمند ورود و مالکیت حساب است.

## احراز هویت

| مسیر | ورودی / رفتار |
|---|---|
| `POST /auth/login` | login (نام کاربری/ایمیل/موبایل)، password، device_name اختیاری |
| `POST /auth/register` | شروع ثبت‌نام شهروندی؛ name, mobile, national_code, terms_accepted=true, terms_version, terms_document_sha256 |
| `POST /auth/otp/request` | همان ثبت‌نام؛ پذیرش صریح نسخه و هش فعلی آیین‌نامه الزامی است → challenge_id |
| `POST /auth/otp/verify` | challenge_id, code شش‌رقمی → توکن و user |
| `GET /auth/me` | اطلاعات حساب فعلی، نقش و سازمان |
| `PATCH /auth/profile` | فقط name, email, county_id, address؛ نقش/موبایل/کد ملی قابل تغییر نیست |
| `POST /auth/logout` | ابطال توکن فعلی |
| `POST /auth/logout-all` | ابطال تمام توکن‌ها |
| `POST /auth/token/rotate` | صدور توکن جدید و ابطال توکن فعلی |
| `PUT /auth/password` | current_password, password, password_confirmation؛ سپس ورود مجدد |
| `POST /auth/password/forgot` | email؛ در حالت محلی mail=log، dev_token نیز برمی‌گردد |
| `POST /auth/password/reset` | email, token, password, password_confirmation |

توکن پیش‌فرض ۲۴ ساعت معتبر است؛ `AUTH_TOKEN_MINUTES` قابل تغییر است. کد OTP پیش‌فرض ۱۲۰ ثانیه عمر و حداکثر ۵ تلاش دارد؛ کد جدید، چالش قبلی همان شماره را باطل می‌کند. درخواست کد برای هر شماره حداکثر یک بار در دقیقه است. حساب جدید شهروند همواره نقش citizen بدون مجوز مدیریتی می‌گیرد.

ارسال واقعی SMS یک POST با Bearer `SMS_TOKEN` به `SMS_ENDPOINT` می‌فرستد:

```json
{"to":"09120000000","message":"کد ورود: 123456","sender":"..."}
```

درگاه اختصاصی با قرارداد متفاوت باید با ویرایش قسمت HTTP در `OtpService` تطبیق داده شود. پیامک و SMTP واقعی در این تحویل به هیچ حساب خارجی متصل نشده‌اند.

## مدیریت عمومی منابع

مسیرهای زیر نیازمند کاربر staff فعال با مجوز منبع‌اند:

| روش | مسیر | کاربرد |
|---|---|---|
| GET | `/admin/{resource}` | لیست، فیلتر و q |
| POST | `/admin/{resource}` | ایجاد |
| GET | `/admin/{resource}/{id}` | جزئیات |
| PATCH / PUT | `/admin/{resource}/{id}` | ویرایش جزئی |
| DELETE | `/admin/{resource}/{id}` | حذف منطقی در منابع دارای deleted_at |
| POST | `/admin/{resource}/{id}/restore` | بازیابی منبع حذف منطقی‌شده |
| POST | `/admin/{resource}/reorder` | ترتیب با items: [{id,priority}]؛ فقط منابع دارای priority |

برای سطل حذف‌شده‌ها `?trashed=1` بفرستید. poll-options حذف دائمی و فاقد restore است؛ گزینه دارای رأی قابل حذف نیست. حذف گروه/سازمان/والد دارای وابستگی با 409 متوقف می‌شود. اسناد و فرم‌های دارای سابقه حفظ می‌شوند؛ نسخه تعریف فرم در submission جداگانه ثبت می‌شود.

`GET /admin/resources` منابع قابل استفاده حساب فعلی، فیلدها، مجوزها و فیلترها را می‌دهد. تعریف کامل قراردادها در `resources.json` و `openapi.json` است؛ فیلدهای خارج از allowlist وارد مدل نمی‌شوند.

| resource | فیلدهای اصلی | مجوز |
|---|---|---|
| contents | type, title, body, locale, slug, category_id, organization_id, cover_media_id, metadata, status, published_at, expires_at, tag_ids, media_ids | content.manage |
| news / announcements / resolutions / documents / pages / publications / tenders / contracts / calls / events / duties | همان محتوا با type ثابت | content.manage |
| categories | type, locale, name, slug, parent_id, organization_id, priority, is_active | categories.manage |
| tags | name, slug, locale | content.manage |
| counties | name, slug, map_key, description, latitude, longitude, video_url | organizations.manage |
| organizations | type, name, slug, parent_id, county_id, domain, email, phone, fax, sms_number, address | organizations.manage |
| menus | name, key, locale, is_active | navigation.manage |
| menu-items | menu_id, parent_id, title, content_id, url, icon, target, priority | navigation.manage |
| slides / advertisements | category_id, title, subtitle, locale, media_id, content_id, url, starts_at, ends_at, priority | content.manage |
| members | kind, name, position, locale, organization_id, county_id, category_id, media_id, bio, body, email, phone, term_start, term_end, is_current | members.manage |
| services | name, slug, code, locale, parent_id, category_id, organization_id, description, requirements[], steps[], delivery_time, cost, target_audience, url | services.manage |
| links | title, url, locale, category_id, media_id, position, priority | content.manage |
| faqs | question, answer, locale, priority | content.manage |
| quotes | author, text, locale, starts_at, ends_at, priority | content.manage |
| galleries | title, slug, locale, category_id, organization_id, status, published_at | content.manage |
| gallery-items | gallery_id, media_id, title, caption, priority | content.manage |
| polls | question, description, locale, status, starts_at, ends_at | polls.manage |
| poll-options | poll_id, title, priority | polls.manage |
| forms | title, slug, locale, category_id, organization_id, description, fields[], opens_at, closes_at, is_active | forms.manage |

بدنه محتوای غنی با HTMLPurifier پاک‌سازی می‌شود. تصویر اختیاری است و در نبود آن جایگزین ساختگی ثبت نمی‌شود. برای انتشار، `status=published` بفرستید؛ اگر published_at خالی باشد، اکنون ثبت می‌شود. تاریخ آینده، انتشار زمان‌بندی‌شده است. برای خاموش‌کردن انقضا از `expires_at:null` استفاده کنید. slug تکراری در همان نوع/زبان مجاز نیست؛ اگر slug ندهید، خودکار از عنوان ساخته می‌شود.

نوع محتوا: `news, announcement, resolution, page, publication, tender, contract, call, event, duty, document`.

نوع گروه: `news, document, slide, gallery, media, member, service, form, form_attribute, forum, link, publication`.

نوع عضو: `manager, governor, county_governor, district_head, village_head, advisor`.

نمونه ایجاد خبر:

```json
{"title":"عنوان خبر","body":"<p>متن خبر</p>","locale":"fa","status":"draft","category_id":1,"organization_id":1,"cover_media_id":null,"is_featured":false,"comments_enabled":true,"tag_ids":[],"media_ids":[]}
```

## کاربران و نقش‌ها

`GET/POST /admin/users` و `GET/PATCH/DELETE /admin/users/{id}` نیازمند users.manage است. DELETE حساب را غیرفعال و توکن‌ها را باطل می‌کند، نه سابقه درخواست‌هایش را. فیلتر person_type یکی از `citizen/staff/employee/expert` و is_active است.

فیلدها: `name, username, email, mobile, national_code, personnel_code, password, role_id, organization_id, person_type, access_scope, is_active`. رمز جدید حداقل ۱۲ نویسه، شامل حرف بزرگ/کوچک و عدد است. حسابی برای ورود با رمز باید username یا email یا mobile داشته باشد. ایجاد/تغییر نقش کاربر به roles.manage نیز نیاز دارد. اعطای مجوزی بالاتر از مجوزهای خود مجاز نیست. مدیر اصلی، حذف آخرین مدیر اصلی و قطع دسترسی خود حفاظت می‌شوند.

`GET/POST /admin/roles`, `PATCH/DELETE /admin/roles/{id}` نیازمند roles.manage است. فیلدهای نقش name, label, permissions[] هستند. نقش‌های سیستمی تغییر نمی‌کنند؛ نقش سفارشی بسازید. مجوز `*` فقط نقش مدیر اصلی سیستمی دارد و از API قابل ایجاد نیست. ویرایش نقش سراسری به access_scope=all نیاز دارد.

`access_scope=organization` دسترسی محتوا، گروه‌ها، سازمان‌ها، مدیران، خدمات، گالری، فرم، کاربران و درخواست‌ها را به سازمان کاربر و زیرمجموعه‌های آن محدود می‌کند. دسترسی سراسری با `all` مشخص است. تنظیمات سایت و خبرنامه امکانات مرکزی هستند؛ مدیریت نقش سراسری و فهرست خبرنامه به حساب دارای دسترسی سراسری سپرده شود.

## رسانه

- `GET /media`: فایل‌های خود؛ مدیر رسانه با محدوده all می‌تواند همه را ببیند.
- `POST /media`: file و visibility=`public/private`؛ alt اختیاری. انتشار عمومی نیازمند media.manage است.
- `GET /media/{id}/download`: دریافت با توکن و بررسی مالکیت یا دسترسی رسیدگی به درخواست/فرم. فایل خصوصی URL عمومی ندارد.
- `DELETE /media/{id}`: مالک یا مدیر رسانه؛ فایل استفاده‌شده قابل حذف نیست.

حد پیش‌فرض ۲۰ مگابایت، قابل تغییر با UPLOAD_MAX_KB. فرمت‌های مجاز jpg/jpeg/png/webp/pdf/docx/xlsx/mp4/mp3/txt با بررسی MIME واقعی. SVG، HTML، PHP و نام مسیر انتخابی کاربر مجاز نیستند. نام ذخیره UUID است. عکس و پیوست منتشرشده باید media عمومی باشند؛ ضمیمه گزارش/فرم باید خصوصی و متعلق به فرستنده باشد. فایل خصوصی در `storage/app/private` است.

## پرتال مردمی و درخواست‌ها

`GET /citizen/dashboard`, `GET/POST /citizen/tickets`, `GET /citizen/tickets/{reference}`, `POST /citizen/tickets/{reference}/messages`.

ثبت گزارش:

```json
{"type":"report","category":"waste","county_id":1,"subject":"عنوان گزارش","body":"شرح کامل مشکل","address":"نشانی","urgency":"medium","media_ids":[]}
```

برای پیام دفتر استاندار، `type=governor` و subject/body کافی است. گزارش به category و county_id نیاز دارد. گروه‌ها: waste, roads, water, electricity, environment, health, security, other. فوریت: low, medium, high, urgent.

مدیر با tickets.manage از `GET /admin/tickets`, `GET/PATCH /admin/tickets/{reference}`, `POST /admin/tickets/{reference}/messages` استفاده می‌کند. فیلتر type و status و q پشتیبانی می‌شود. PATCH: status, organization_id, assigned_to, urgency, note. کارشناس ارجاع‌شده باید دسترسی رسیدگی به سازمان درخواست داشته باشد.

ارسال پیام: `{ "body":"پاسخ", "media_ids":[], "is_internal":false }`. فقط کارشناس مجاز می‌تواند پیام داخلی بسازد؛ این پیام و پیوست آن به صاحب درخواست نمایش داده نمی‌شود.

وضعیت‌ها و انتقال‌ها:

| وضعیت فعلی | وضعیت بعدی مجاز |
|---|---|
| pending | review, referred, rejected, closed |
| review | referred, resolved, rejected, closed |
| referred | review, resolved, rejected, closed |
| resolved | closed, review |
| rejected | review, closed |
| closed | review |

پیام جدید روی درخواست closed مجاز نیست. مهلت پیش‌فرض گزارش ۷ روز و پیام ۵ روز کاری است. WORKING_WEEKEND_DAYS شماره روزهای تعطیل هفتگی Carbon را می‌گیرد؛ پیش‌فرض ۵ یعنی جمعه. تعطیلات موردی در WORKING_HOLIDAYS به صورت تاریخ‌های میلادی جداشده با کاما تنظیم می‌شود. این یک تنظیم پروژه است، نه تقویم رسمی متصل به سرویس بیرونی.

## فرم پویا

نمونه fields:

```json
[
  {"key":"full_name","label":"نام کامل","type":"text","required":true,"max":120},
  {"key":"subject","label":"موضوع","type":"select","required":true,"options":["اطلاعات","پیشنهاد"]},
  {"key":"attachment","label":"پیوست","type":"file","required":false}
]
```

انواع: text, textarea, email, number, date, select, multi_select, boolean, file. key انگلیسی یکتا با underscore؛ حداکثر ۴۰ فیلد. `min/max` عددی و سقف طول متن محدودند. هیچ نام Rule یا کد اجرایی از تعریف فرم اجرا نمی‌شود. file شناسه رسانه خصوصی متعلق به همان کاربر است.

- `POST /citizen/forms/{id}/submissions`: `{"answers":{"full_name":"...","subject":"اطلاعات"}}`.
- `GET /citizen/submissions` و `GET /citizen/submissions/{reference}`: فقط سابقه حساب فعلی.
- `GET /admin/submissions?form_id=...&status=pending` و `GET /admin/submissions/{reference}`: مجوز forms.manage و سازمان مجاز.
- `PATCH /admin/submissions/{reference}`: status=`pending/review/accepted/rejected` و reply.

تغییر تعریف فرم، schema_snapshot پاسخ‌های قبلی را تغییر نمی‌دهد.

## نظرسنجی، نظرات و تالار

نظرسنجی را draft بسازید، حداقل دو poll-options اضافه کنید، سپس status=active کنید. `POST /polls/{id}/vote` با option_id به حساب واردشده نیاز دارد. هر حساب در هر نظرسنجی یک رأی دارد. `GET /polls/{id}/results` فقط جمع رأی و درصد می‌دهد، نه هویت رأی‌دهندگان. متن و گزینه‌های دارای رأی حفاظت می‌شوند.

`POST /contents/{id}/comments` با body نظر را pending ثبت می‌کند. `GET /admin/moderation/comments` و `PATCH /admin/moderation/comments/{id}` با status و reply برای comments.manage است.

`GET/POST /forum/topics`, `GET /forum/topics/{id}`, `POST /forum/topics/{id}/replies`. ایجاد به ورود نیاز دارد. category_id باید گروه forum فعال باشد. موضوع/پاسخ جدید pending است. مدیریت از `/admin/moderation/forum-topics` و `/admin/moderation/forum-replies` و PATCH همان مسیر با `/{id}` انجام می‌شود؛ مجوز forum.manage. تنها approved عمومی است؛ موضوع closed قابل خواندن ولی بدون پاسخ جدید است.

## خبرنامه، اعلان و تنظیمات

خبرنامه: `POST /newsletter/subscribe` با email/name؛ `POST /newsletter/confirm` با email/token؛ `POST /newsletter/unsubscribe` با همان قرارداد. توکن تأیید ۲۴ ساعت معتبر است؛ مقدار ذخیره‌شده هش است. در حالت محلی mail=log، dev_token برای آزمایش برمی‌گردد. مدیریت: `GET /admin/subscribers`, `PATCH /admin/subscribers/{id}` با is_active، و DELETE همان مسیر؛ newsletter.manage و محدوده all.

اعلان: `GET /citizen/notifications`, `POST /citizen/notifications/{uuid}/read`, `POST /citizen/notifications/read-all`. فقط اعلان‌های حساب فعلی قابل خواندن‌اند.

`GET/PUT /admin/settings` با settings.manage. ورودی PUT:

```json
{"settings":[{"key":"site.name","value":"استانداری مازندران","is_public":true}]}
```

کلیدهای عمومی مجاز در config/portal.php ثبت شده‌اند. رمز، secret و API key در جدول تنظیمات پذیرفته نمی‌شوند. تنظیمات زیرساخت در `.env` قرار دارند.

`GET /admin/dashboard` و `/admin/reports` با dashboard.view. `GET /admin/exports/{resource}` خروجی CSV با مجوز متناظر می‌دهد؛ resource یکی از contents, tickets, users, members, services و فیلتر from/to است. حداکثر ده هزار ردیف؛ بازه را محدود کنید. `GET /admin/audit-logs` با audit.view؛ لاگ شامل عملیات و تغییرات مجاز است و رمز/توکن/متن مکاتبات در آن ذخیره نمی‌شود.

## فرمانداری‌ها و ورود محلی (به‌روزرسانی)

`php artisan portal:local-admin` فقط در محیط‌های `local` و `testing` حساب `admin / admin` را ایجاد یا بازنشانی می‌کند. این دستور توکن‌های قبلی همان حساب را باطل می‌کند. `composer setup` در محیط محلی همین دستور را در پایان اجرا می‌کند.

Seeder پایه، فهرست ۲۲ شهرستان و واحد فرمانداری مربوط را ایجاد می‌کند؛ برای فرماندار، تصویر، خبر یا نشانی محلی، دادهٔ ساختگی ثبت نمی‌کند. برای پایگاه دادهٔ قبلی می‌توان `php artisan db:seed --class=CountySeeder` را اجرا کرد.

`GET /counties/by-slug/nur` و نام‌های قدیمی مانند `noor` به یک شهرستان می‌رسند. برای گالری اختصاصی، `GET /galleries?organization_id=...` اعمال فیلتر می‌کند.

در `POST /contact`، فیلد اختیاری `county_slug` پیام را به واحد همان فرمانداری می‌فرستد. شهرستان نامعتبر یا واحد دریافت‌کنندهٔ غیرفعال پاسخ ۴۲۲ می‌گیرد؛ پیام به واحد دیگری منتقل نمی‌شود.

### پذیرش آیین‌نامهٔ ثبت‌نام شهروندی

نسخهٔ فعلی `1.0` و SHA-256 متن کامل `2404265b8f811b2fc36c447ff8b4e22954f752a7786975553b1b0a8b31070778` است. هر دو مسیر شروع ثبت‌نام، بدون `terms_accepted=true` و نسخه و هش معتبر، پاسخ 422 می‌دهند و کد پیامکی نمی‌سازند. متن نسخه‌دار در `resources/legal/citizen-registration-terms-1.0.json` نگهداری می‌شود. پس از تأیید OTP، پذیرش در audit_logs با رویداد `auth.registration_terms_accepted`، شناسهٔ شهروند، نسخه، هش و زمان سمت سرور ثبت می‌شود. ورود بعدی شهروند ثبت‌شده در `/auth/citizen/login` فقط شمارهٔ همراه می‌خواهد؛ این ورودی‌ها به ورود مدیران اضافه نشده‌اند.
