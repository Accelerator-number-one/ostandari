-- Text-only import for the EXISTING Ostandari Laravel/MySQL schema.
-- Select the target database first; execute each whole file in one session.
-- Base migrations and DatabaseSeeder must already exist. No fixed numeric IDs.
-- Existing rows, passwords, permissions and media are not replaced.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @import_root = (SELECT MIN(id) FROM organizations WHERE slug = 'mazandaran' AND deleted_at IS NULL AND is_active = 1);
SET @import_author = (SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = 'admin' AND u.person_type <> 'citizen' AND u.is_active = 1 AND (JSON_CONTAINS(r.permissions, JSON_QUOTE('*')) OR JSON_CONTAINS(r.permissions, JSON_QUOTE('content.manage'))) ORDER BY u.id LIMIT 1);
-- Source: user-supplied HTML snapshot of https://ostan-mz.ir/, saved 2026-10-18.
-- Public titles/URLs/contact values are sourced; descriptions are short editorial introductions.
-- Publication records are introductions, not the full legal documents.
SET @import_ready = IF(@import_root IS NOT NULL AND @import_author IS NOT NULL, 1, 0);
SELECT IF(@import_ready = 1, 'READY', 'STOP: required base organization/admin/roles missing; no import will be performed') AS import_preflight;
START TRANSACTION;

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'معاونین استاندار مازندران', 'official-member-menu-64', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-64' OR name = 'معاونین استاندار مازندران'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'مدیران ستادی استانداری مازندران', 'official-member-menu-61', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-61' OR name = 'مدیران ستادی استانداری مازندران'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'فرمانداران مازندران', 'official-member-menu-63', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-63' OR name = 'فرمانداران مازندران'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'مشاورین استاندار', 'official-member-menu-109', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-109' OR name = 'مشاورین استاندار'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'مدیران ادارات و دستگاههای اجرایی', 'official-member-menu-62', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-62' OR name = 'مدیران ادارات و دستگاههای اجرایی'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'member', 'fa', 'مدیران روابط عمومی استان', 'official-member-menu-198', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'member' AND locale = 'fa' AND (slug = 'official-member-menu-198' OR name = 'مدیران روابط عمومی استان'));

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'service', 'fa', 'خدمات معرفی‌شده در درگاه استانداری', 'official-service-directory', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'service' AND locale = 'fa' AND (slug = 'official-service-directory' OR name = 'خدمات معرفی‌شده در درگاه استانداری'));
INSERT INTO `services` (`organization_id`, `category_id`, `locale`, `name`, `slug`, `description`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'service' AND locale = 'fa' AND name = 'خدمات معرفی‌شده در درگاه استانداری' AND deleted_at IS NULL), 'fa', 'خدمات الکترونیکی استانداری مازندران', 'official-menu-1', 'صفحهٔ معرفی «خدمات الکترونیکی استانداری مازندران» در درگاه رسمی استانداری مازندران. جزئیات و مسیر ارائهٔ خدمت از پیوند مرجع قابل بررسی است.', 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=1&OrgId=1', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `services` WHERE locale = 'fa' AND (slug = 'official-menu-1' OR name = 'خدمات الکترونیکی استانداری مازندران'));

INSERT INTO `services` (`organization_id`, `category_id`, `locale`, `name`, `slug`, `description`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'service' AND locale = 'fa' AND name = 'خدمات معرفی‌شده در درگاه استانداری' AND deleted_at IS NULL), 'fa', 'خدمات الکترونیکی', 'official-menu-8', 'صفحهٔ معرفی «خدمات الکترونیکی» در درگاه رسمی استانداری مازندران. جزئیات و مسیر ارائهٔ خدمت از پیوند مرجع قابل بررسی است.', 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=8&OrgId=1', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `services` WHERE locale = 'fa' AND (slug = 'official-menu-8' OR name = 'خدمات الکترونیکی'));

INSERT INTO `services` (`organization_id`, `category_id`, `locale`, `name`, `slug`, `description`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'service' AND locale = 'fa' AND name = 'خدمات معرفی‌شده در درگاه استانداری' AND deleted_at IS NULL), 'fa', 'میز ارتباطات مردمی وزارت کشور', 'official-menu-188', 'صفحهٔ معرفی «میز ارتباطات مردمی وزارت کشور» در درگاه رسمی استانداری مازندران. جزئیات و مسیر ارائهٔ خدمت از پیوند مرجع قابل بررسی است.', 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=188&OrgId=1', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `services` WHERE locale = 'fa' AND (slug = 'official-menu-188' OR name = 'میز ارتباطات مردمی وزارت کشور'));

INSERT INTO `services` (`organization_id`, `category_id`, `locale`, `name`, `slug`, `description`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'service' AND locale = 'fa' AND name = 'خدمات معرفی‌شده در درگاه استانداری' AND deleted_at IS NULL), 'fa', 'سامانه مدیریت خدمات دولت', 'official-menu-171', 'صفحهٔ معرفی «سامانه مدیریت خدمات دولت» در درگاه رسمی استانداری مازندران. جزئیات و مسیر ارائهٔ خدمت از پیوند مرجع قابل بررسی است.', 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=171&OrgId=1', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `services` WHERE locale = 'fa' AND (slug = 'official-menu-171' OR name = 'سامانه مدیریت خدمات دولت'));

INSERT INTO `services` (`organization_id`, `category_id`, `locale`, `name`, `slug`, `description`, `url`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'service' AND locale = 'fa' AND name = 'خدمات معرفی‌شده در درگاه استانداری' AND deleted_at IS NULL), 'fa', 'پیشخوان ارباب رجوع', 'official-menu-197', 'صفحهٔ معرفی «پیشخوان ارباب رجوع» در درگاه رسمی استانداری مازندران. جزئیات و مسیر ارائهٔ خدمت از پیوند مرجع قابل بررسی است.', 'https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=197&OrgId=1', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `services` WHERE locale = 'fa' AND (slug = 'official-menu-197' OR name = 'پیشخوان ارباب رجوع'));

INSERT INTO `organizations` (`parent_id`, `type`, `name`, `slug`, `domain`, `description`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'department', 'اداره کل مدیریت بحران', 'official-department-bohran', 'ostan-mz.ir', 'عنوان واحد از فهرست رسمی واحدهای ستادی استخراج شده است. منبع: https://ostan-mz.ir/bohran؛ شماره و نشانی اختصاصی این واحد در فایل مرجع موجود نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `organizations` WHERE slug = 'official-department-bohran' OR (type = 'department' AND name = 'اداره کل مدیریت بحران'));

INSERT INTO `organizations` (`parent_id`, `type`, `name`, `slug`, `domain`, `description`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'department', 'دفتر امور بانوان و خانواده', 'official-department-banovan', 'ostan-mz.ir', 'عنوان واحد از فهرست رسمی واحدهای ستادی استخراج شده است. منبع: https://ostan-mz.ir/banovan؛ شماره و نشانی اختصاصی این واحد در فایل مرجع موجود نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `organizations` WHERE slug = 'official-department-banovan' OR (type = 'department' AND name = 'دفتر امور بانوان و خانواده'));

INSERT INTO `organizations` (`parent_id`, `type`, `name`, `slug`, `domain`, `description`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'department', 'دفتر جذب و حمایت از سرمایه گذاری', 'official-department-jazb', 'ostan-mz.ir', 'عنوان واحد از فهرست رسمی واحدهای ستادی استخراج شده است. منبع: https://ostan-mz.ir/jazb؛ شماره و نشانی اختصاصی این واحد در فایل مرجع موجود نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `organizations` WHERE slug = 'official-department-jazb' OR (type = 'department' AND name = 'دفتر جذب و حمایت از سرمایه گذاری'));

INSERT INTO `organizations` (`parent_id`, `type`, `name`, `slug`, `domain`, `description`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'department', 'دفتر امور شهری و شوراها', 'official-department-shahri', 'ostan-mz.ir', 'عنوان واحد از فهرست رسمی واحدهای ستادی استخراج شده است. منبع: https://ostan-mz.ir/shahri؛ شماره و نشانی اختصاصی این واحد در فایل مرجع موجود نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `organizations` WHERE slug = 'official-department-shahri' OR (type = 'department' AND name = 'دفتر امور شهری و شوراها'));

INSERT INTO `organizations` (`parent_id`, `type`, `name`, `slug`, `domain`, `description`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'department', 'مدیریت فناوری اطلاعات ، امنیت فضای مجازی و شبکه دولت', 'official-department-it-network', 'ostan-mz.ir', 'عنوان واحد از فهرست رسمی واحدهای ستادی استخراج شده است. منبع: https://ostan-mz.ir/Home/ShowDetailsMenuContent?MenuId=38&OrgId=1؛ شماره و نشانی اختصاصی این واحد در فایل مرجع موجود نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `organizations` WHERE slug = 'official-department-it-network' OR (type = 'department' AND name = 'مدیریت فناوری اطلاعات ، امنیت فضای مجازی و شبکه دولت'));

-- Verified root contact: fill only blank fields, preserve existing nonempty values.
UPDATE organizations SET `address` = COALESCE(NULLIF(`address`, ''), 'ساری، خیابان انقلاب، استانداری مازندران'), `phone` = COALESCE(NULLIF(`phone`, ''), '01133310211-19'), `fax` = COALESCE(NULLIF(`fax`, ''), '01133310283'), `email` = COALESCE(NULLIF(`email`, ''), 'info@ostan-mz.ir'), updated_at = NOW() WHERE @import_ready = 1 AND id = @import_root AND (address IS NULL OR address = '' OR phone IS NULL OR phone = '' OR fax IS NULL OR fax = '' OR email IS NULL OR email = '');
INSERT INTO `settings` (`key`, `value`, `is_public`, `created_at`, `updated_at`)
SELECT 'site.contact', '{"address":"ساری، خیابان انقلاب، استانداری مازندران","phone":"01133310211-19","fax":"01133310283","email":"info@ostan-mz.ir"}', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'site.contact');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'publication', 'fa', 'قوانین و راهنماهای معرفی‌شده در سایت اصلی', 'official-publication-introductions', 1, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'publication' AND locale = 'fa' AND (slug = 'official-publication-introductions' OR name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی'));
INSERT INTO `contents` (`organization_id`, `category_id`, `author_id`, `type`, `locale`, `title`, `slug`, `excerpt`, `body`, `metadata`, `status`, `published_at`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'publication' AND locale = 'fa' AND name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی' AND deleted_at IS NULL), @import_author, 'publication', 'fa', 'متن قانون انتشار و دسترسی آزاد به اطلاعات', 'official-publication-73836', 'معرفی قانون مربوط به دسترسی عمومی به اطلاعات که عنوان و معرفی آن در بخش قوانین درگاه استانداری آمده است.', 'معرفی قانون مربوط به دسترسی عمومی به اطلاعات که عنوان و معرفی آن در بخش قوانین درگاه استانداری آمده است.

منبع: https://ostan-mz.ir/news/73836

این رکورد معرفیِ متن است؛ فایل و تصویر ضمیمه ندارد.', '{"source_url":"https://ostan-mz.ir/news/73836","source_snapshot":"2026-10-18","source_date_text":"۲ مهر ۱۳۹۷","content_scope":"source_title_with_editorial_introduction"}', 'published', NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `contents` WHERE type = 'publication' AND locale = 'fa' AND slug = 'official-publication-73836');

INSERT INTO `contents` (`organization_id`, `category_id`, `author_id`, `type`, `locale`, `title`, `slug`, `excerpt`, `body`, `metadata`, `status`, `published_at`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'publication' AND locale = 'fa' AND name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی' AND deleted_at IS NULL), @import_author, 'publication', 'fa', 'آیین‌نامه اجرایی قانون انتشار و دسترسی آزاد به اطلاعات', 'official-publication-73837', 'معرفی آیین‌نامهٔ اجرایی مربوط به قانون دسترسی آزاد به اطلاعات؛ متن کامل را در پیوند مرجع مطالعه کنید.', 'معرفی آیین‌نامهٔ اجرایی مربوط به قانون دسترسی آزاد به اطلاعات؛ متن کامل را در پیوند مرجع مطالعه کنید.

منبع: https://ostan-mz.ir/news/73837

این رکورد معرفیِ متن است؛ فایل و تصویر ضمیمه ندارد.', '{"source_url":"https://ostan-mz.ir/news/73837","source_snapshot":"2026-10-18","source_date_text":"۲ مهر ۱۳۹۷","content_scope":"source_title_with_editorial_introduction"}', 'published', NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `contents` WHERE type = 'publication' AND locale = 'fa' AND slug = 'official-publication-73837');

INSERT INTO `contents` (`organization_id`, `category_id`, `author_id`, `type`, `locale`, `title`, `slug`, `excerpt`, `body`, `metadata`, `status`, `published_at`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'publication' AND locale = 'fa' AND name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی' AND deleted_at IS NULL), @import_author, 'publication', 'fa', 'دستورالعمل تفویض اختیارات وزارت کشور در خصوص صدور مجوز برگزاری تجمعات', 'official-publication-73844', 'معرفی دستورالعملی با این عنوان در فهرست قوانین سایت اصلی. این صفحه جایگزین متن رسمی دستورالعمل نیست.', 'معرفی دستورالعملی با این عنوان در فهرست قوانین سایت اصلی. این صفحه جایگزین متن رسمی دستورالعمل نیست.

منبع: https://ostan-mz.ir/news/73844

این رکورد معرفیِ متن است؛ فایل و تصویر ضمیمه ندارد.', '{"source_url":"https://ostan-mz.ir/news/73844","source_snapshot":"2026-10-18","source_date_text":"۲ مهر ۱۳۹۷","content_scope":"source_title_with_editorial_introduction"}', 'published', NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `contents` WHERE type = 'publication' AND locale = 'fa' AND slug = 'official-publication-73844');

INSERT INTO `contents` (`organization_id`, `category_id`, `author_id`, `type`, `locale`, `title`, `slug`, `excerpt`, `body`, `metadata`, `status`, `published_at`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'publication' AND locale = 'fa' AND name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی' AND deleted_at IS NULL), @import_author, 'publication', 'fa', 'ایثارگران در قانون برنامه ششم توسعه', 'official-publication-73840', 'معرفی مطلب سایت اصلی دربارهٔ موضوع ایثارگران در برنامهٔ ششم؛ این عنوان به مطلب تاریخی منبع اشاره دارد و اعلام مقررات جاری نیست.', 'معرفی مطلب سایت اصلی دربارهٔ موضوع ایثارگران در برنامهٔ ششم؛ این عنوان به مطلب تاریخی منبع اشاره دارد و اعلام مقررات جاری نیست.

منبع: https://ostan-mz.ir/news/73840

این رکورد معرفیِ متن است؛ فایل و تصویر ضمیمه ندارد.', '{"source_url":"https://ostan-mz.ir/news/73840","source_snapshot":"2026-10-18","source_date_text":"۲ مهر ۱۳۹۷","content_scope":"source_title_with_editorial_introduction"}', 'published', NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `contents` WHERE type = 'publication' AND locale = 'fa' AND slug = 'official-publication-73840');

INSERT INTO `contents` (`organization_id`, `category_id`, `author_id`, `type`, `locale`, `title`, `slug`, `excerpt`, `body`, `metadata`, `status`, `published_at`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'publication' AND locale = 'fa' AND name = 'قوانین و راهنماهای معرفی‌شده در سایت اصلی' AND deleted_at IS NULL), @import_author, 'publication', 'fa', 'ایثارگران درقانون جامع خدمات رسانی به ایثارگران', 'official-publication-73839', 'معرفی مطلب حقوقی درج‌شده در سایت اصلی دربارهٔ قانون جامع خدمات‌رسانی به ایثارگران؛ مرجع کامل در پیوند منبع آمده است.', 'معرفی مطلب حقوقی درج‌شده در سایت اصلی دربارهٔ قانون جامع خدمات‌رسانی به ایثارگران؛ مرجع کامل در پیوند منبع آمده است.

منبع: https://ostan-mz.ir/news/73839

این رکورد معرفیِ متن است؛ فایل و تصویر ضمیمه ندارد.', '{"source_url":"https://ostan-mz.ir/news/73839","source_snapshot":"2026-10-18","source_date_text":"۲ مهر ۱۳۹۷","content_scope":"source_title_with_editorial_introduction"}', 'published', NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `contents` WHERE type = 'publication' AND locale = 'fa' AND slug = 'official-publication-73839');

COMMIT;
SELECT 'Official text import completed; preflight READY must be 1 for changes' AS result, @import_ready AS imported;
