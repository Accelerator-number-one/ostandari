-- Text-only import for the EXISTING Ostandari Laravel/MySQL schema.
-- Select the target database first; execute each whole file in one session.
-- Base migrations and DatabaseSeeder must already exist. No fixed numeric IDs.
-- Existing rows, passwords, permissions and media are not replaced.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @import_root = (SELECT MIN(id) FROM organizations WHERE slug = 'mazandaran' AND deleted_at IS NULL AND is_active = 1);
SET @import_author = (SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = 'admin' AND u.person_type <> 'citizen' AND u.is_active = 1 AND (JSON_CONTAINS(r.permissions, JSON_QUOTE('*')) OR JSON_CONTAINS(r.permissions, JSON_QUOTE('content.manage'))) ORDER BY u.id LIMIT 1);
-- ALL records in this file are SYNTHETIC TEST DATA, never verified citizens or subscribers.
-- New users have NULL passwords, no verified identifiers and is_active = 0.
-- Forms stay inactive; forum topics remain pending; subscribers remain unverified/inactive.
SET @import_operator_role = (SELECT MIN(id) FROM roles WHERE name = 'operator');
SET @import_citizen_role = (SELECT MIN(id) FROM roles WHERE name = 'citizen');
SET @import_ready = IF(@import_root IS NOT NULL AND @import_author IS NOT NULL AND @import_operator_role IS NOT NULL AND @import_citizen_role IS NOT NULL, 1, 0);
SELECT IF(@import_ready = 1, 'READY', 'STOP: required base organization/admin/roles missing; no import will be performed') AS import_preflight;
START TRANSACTION;

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارمند آزمایشی 1', 'demo_20261019_employee_01', 'employee01@demo.ostandari.test', NULL, 'employee', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_employee_01' OR email = 'employee01@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارمند آزمایشی 2', 'demo_20261019_employee_02', 'employee02@demo.ostandari.test', NULL, 'employee', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_employee_02' OR email = 'employee02@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارمند آزمایشی 3', 'demo_20261019_employee_03', 'employee03@demo.ostandari.test', NULL, 'employee', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_employee_03' OR email = 'employee03@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارمند آزمایشی 4', 'demo_20261019_employee_04', 'employee04@demo.ostandari.test', NULL, 'employee', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_employee_04' OR email = 'employee04@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارمند آزمایشی 5', 'demo_20261019_employee_05', 'employee05@demo.ostandari.test', NULL, 'employee', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_employee_05' OR email = 'employee05@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارشناس فرم آزمایشی 1', 'demo_20261019_expert_01', 'expert01@demo.ostandari.test', NULL, 'expert', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_expert_01' OR email = 'expert01@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارشناس فرم آزمایشی 2', 'demo_20261019_expert_02', 'expert02@demo.ostandari.test', NULL, 'expert', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_expert_02' OR email = 'expert02@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارشناس فرم آزمایشی 3', 'demo_20261019_expert_03', 'expert03@demo.ostandari.test', NULL, 'expert', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_expert_03' OR email = 'expert03@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارشناس فرم آزمایشی 4', 'demo_20261019_expert_04', 'expert04@demo.ostandari.test', NULL, 'expert', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_expert_04' OR email = 'expert04@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_operator_role, @import_root, 'کارشناس فرم آزمایشی 5', 'demo_20261019_expert_05', 'expert05@demo.ostandari.test', NULL, 'expert', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_expert_05' OR email = 'expert05@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_citizen_role, @import_root, 'متقاضی آزمایشی 1', 'demo_20261019_citizen_01', 'citizen01@demo.ostandari.test', NULL, 'citizen', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_citizen_01' OR email = 'citizen01@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_citizen_role, @import_root, 'متقاضی آزمایشی 2', 'demo_20261019_citizen_02', 'citizen02@demo.ostandari.test', NULL, 'citizen', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_citizen_02' OR email = 'citizen02@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_citizen_role, @import_root, 'متقاضی آزمایشی 3', 'demo_20261019_citizen_03', 'citizen03@demo.ostandari.test', NULL, 'citizen', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_citizen_03' OR email = 'citizen03@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_citizen_role, @import_root, 'متقاضی آزمایشی 4', 'demo_20261019_citizen_04', 'citizen04@demo.ostandari.test', NULL, 'citizen', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_citizen_04' OR email = 'citizen04@demo.ostandari.test');

INSERT INTO `users` (`role_id`, `organization_id`, `name`, `username`, `email`, `password`, `person_type`, `access_scope`, `is_active`, `created_at`, `updated_at`)
SELECT @import_citizen_role, @import_root, 'متقاضی آزمایشی 5', 'demo_20261019_citizen_05', 'citizen05@demo.ostandari.test', NULL, 'citizen', 'organization', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `users` WHERE username = 'demo_20261019_citizen_05' OR email = 'citizen05@demo.ostandari.test');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'forum', 'fa', '[آزمایشی] محیط زیست و پسماند', 'demo-20261019-forum-1', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'forum' AND locale = 'fa' AND (slug = 'demo-20261019-forum-1' OR name = '[آزمایشی] محیط زیست و پسماند'));
INSERT INTO `forum_topics` (`category_id`, `user_id`, `title`, `body`, `status`, `created_at`, `updated_at`)
SELECT (SELECT MIN(id) FROM categories WHERE type = 'forum' AND locale = 'fa' AND name = '[آزمایشی] محیط زیست و پسماند' AND deleted_at IS NULL), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_01'), '[آزمایشی] پیشنهاد تفکیک پسماند در محله‌ها', 'این یک موضوع آزمایشی است: چه روش‌هایی برای آموزش تفکیک پسماند و زمان‌بندی جمع‌آوری در محله‌ها پیشنهاد می‌کنید؟', 'pending', NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `forum_topics` WHERE title = '[آزمایشی] پیشنهاد تفکیک پسماند در محله‌ها');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'forum', 'fa', '[آزمایشی] راه و حمل‌ونقل', 'demo-20261019-forum-2', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'forum' AND locale = 'fa' AND (slug = 'demo-20261019-forum-2' OR name = '[آزمایشی] راه و حمل‌ونقل'));
INSERT INTO `forum_topics` (`category_id`, `user_id`, `title`, `body`, `status`, `created_at`, `updated_at`)
SELECT (SELECT MIN(id) FROM categories WHERE type = 'forum' AND locale = 'fa' AND name = '[آزمایشی] راه و حمل‌ونقل' AND deleted_at IS NULL), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_02'), '[آزمایشی] بهبود روشنایی مسیرهای روستایی', 'این یک موضوع آزمایشی است: اولویت‌بندی مسیرهای نیازمند روشنایی و اطلاع‌رسانی وضعیت رسیدگی چگونه انجام شود؟', 'pending', NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `forum_topics` WHERE title = '[آزمایشی] بهبود روشنایی مسیرهای روستایی');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'forum', 'fa', '[آزمایشی] خدمات و پاسخ‌گویی', 'demo-20261019-forum-3', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'forum' AND locale = 'fa' AND (slug = 'demo-20261019-forum-3' OR name = '[آزمایشی] خدمات و پاسخ‌گویی'));
INSERT INTO `forum_topics` (`category_id`, `user_id`, `title`, `body`, `status`, `created_at`, `updated_at`)
SELECT (SELECT MIN(id) FROM categories WHERE type = 'forum' AND locale = 'fa' AND name = '[آزمایشی] خدمات و پاسخ‌گویی' AND deleted_at IS NULL), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_03'), '[آزمایشی] راهنمای پیگیری درخواست‌های مردمی', 'این یک موضوع آزمایشی است: نمایش زمان پاسخ، شناسهٔ پیگیری و وضعیت درخواست چه کمکی به مراجعه‌کنندگان می‌کند؟', 'pending', NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `forum_topics` WHERE title = '[آزمایشی] راهنمای پیگیری درخواست‌های مردمی');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'forum', 'fa', '[آزمایشی] گردشگری و فرهنگ', 'demo-20261019-forum-4', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'forum' AND locale = 'fa' AND (slug = 'demo-20261019-forum-4' OR name = '[آزمایشی] گردشگری و فرهنگ'));
INSERT INTO `forum_topics` (`category_id`, `user_id`, `title`, `body`, `status`, `created_at`, `updated_at`)
SELECT (SELECT MIN(id) FROM categories WHERE type = 'forum' AND locale = 'fa' AND name = '[آزمایشی] گردشگری و فرهنگ' AND deleted_at IS NULL), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_04'), '[آزمایشی] معرفی مسیرهای گردشگری مسئولانه', 'این یک موضوع آزمایشی است: برای معرفی مسیرهای محلی همراه با حفاظت از طبیعت چه پیشنهادهایی دارید؟', 'pending', NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `forum_topics` WHERE title = '[آزمایشی] معرفی مسیرهای گردشگری مسئولانه');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'forum', 'fa', '[آزمایشی] پیشنهادهای توسعه استان', 'demo-20261019-forum-5', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'forum' AND locale = 'fa' AND (slug = 'demo-20261019-forum-5' OR name = '[آزمایشی] پیشنهادهای توسعه استان'));
INSERT INTO `forum_topics` (`category_id`, `user_id`, `title`, `body`, `status`, `created_at`, `updated_at`)
SELECT (SELECT MIN(id) FROM categories WHERE type = 'forum' AND locale = 'fa' AND name = '[آزمایشی] پیشنهادهای توسعه استان' AND deleted_at IS NULL), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_05'), '[آزمایشی] پیشنهادهای بهبود خدمات الکترونیکی', 'این یک موضوع آزمایشی است: چه تغییرهایی دسترسی به خدمات الکترونیکی استان را ساده‌تر می‌کند؟', 'pending', NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `forum_topics` WHERE title = '[آزمایشی] پیشنهادهای بهبود خدمات الکترونیکی');

INSERT INTO `subscribers` (`email`, `name`, `token_hash`, `is_active`, `created_at`, `updated_at`)
SELECT 'newsletter01@demo.ostandari.test', 'عضو خبرنامهٔ آزمایشی 1', SHA2(UUID(), 256), 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `subscribers` WHERE email = 'newsletter01@demo.ostandari.test');

INSERT INTO `subscribers` (`email`, `name`, `token_hash`, `is_active`, `created_at`, `updated_at`)
SELECT 'newsletter02@demo.ostandari.test', 'عضو خبرنامهٔ آزمایشی 2', SHA2(UUID(), 256), 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `subscribers` WHERE email = 'newsletter02@demo.ostandari.test');

INSERT INTO `subscribers` (`email`, `name`, `token_hash`, `is_active`, `created_at`, `updated_at`)
SELECT 'newsletter03@demo.ostandari.test', 'عضو خبرنامهٔ آزمایشی 3', SHA2(UUID(), 256), 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `subscribers` WHERE email = 'newsletter03@demo.ostandari.test');

INSERT INTO `subscribers` (`email`, `name`, `token_hash`, `is_active`, `created_at`, `updated_at`)
SELECT 'newsletter04@demo.ostandari.test', 'عضو خبرنامهٔ آزمایشی 4', SHA2(UUID(), 256), 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `subscribers` WHERE email = 'newsletter04@demo.ostandari.test');

INSERT INTO `subscribers` (`email`, `name`, `token_hash`, `is_active`, `created_at`, `updated_at`)
SELECT 'newsletter05@demo.ostandari.test', 'عضو خبرنامهٔ آزمایشی 5', SHA2(UUID(), 256), 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `subscribers` WHERE email = 'newsletter05@demo.ostandari.test');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form_attribute', 'fa', '[آزمایشی] اطلاعات هویتی', 'demo-20261019-attribute-1', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form_attribute' AND locale = 'fa' AND (slug = 'demo-20261019-attribute-1' OR name = '[آزمایشی] اطلاعات هویتی'));
INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form', 'fa', '[آزمایشی] گروه درخواست ملاقات عمومی', 'demo-20261019-form-group-1', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form' AND locale = 'fa' AND (slug = 'demo-20261019-form-group-1' OR name = '[آزمایشی] گروه درخواست ملاقات عمومی'));
INSERT INTO `dynamic_forms` (`organization_id`, `category_id`, `locale`, `title`, `slug`, `description`, `fields`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'form' AND locale = 'fa' AND name = '[آزمایشی] گروه درخواست ملاقات عمومی' AND deleted_at IS NULL), 'fa', '[آزمایشی] درخواست ملاقات عمومی', 'demo-20261019-form-1', 'این فرم صرفاً دادهٔ آزمایشی برای بررسی پنل است و فرم رسمی ارائهٔ خدمت نیست.', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `dynamic_forms` WHERE locale = 'fa' AND slug = 'demo-20261019-form-1');
INSERT INTO `form_submissions` (`reference`, `dynamic_form_id`, `user_id`, `data`, `schema_snapshot`, `status`, `reply`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`)
SELECT '01M58QAF0075AXME0X77VB9SGB', (SELECT id FROM dynamic_forms WHERE locale = 'fa' AND slug = 'demo-20261019-form-1'), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_01'), '{"full_name":"متقاضی آزمایشی 1","subject":"[آزمایشی] درخواست ملاقات عمومی","description":"اطلاعات ساختگی برای بررسی گردش رسیدگی؛ متعلق به شخص واقعی نیست."}', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 'pending', NULL, NULL, NULL, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `form_submissions` WHERE reference = '01M58QAF0075AXME0X77VB9SGB');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form_attribute', 'fa', '[آزمایشی] راه‌های تماس', 'demo-20261019-attribute-2', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form_attribute' AND locale = 'fa' AND (slug = 'demo-20261019-attribute-2' OR name = '[آزمایشی] راه‌های تماس'));
INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form', 'fa', '[آزمایشی] گروه درخواست اطلاعات عمومی', 'demo-20261019-form-group-2', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form' AND locale = 'fa' AND (slug = 'demo-20261019-form-group-2' OR name = '[آزمایشی] گروه درخواست اطلاعات عمومی'));
INSERT INTO `dynamic_forms` (`organization_id`, `category_id`, `locale`, `title`, `slug`, `description`, `fields`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'form' AND locale = 'fa' AND name = '[آزمایشی] گروه درخواست اطلاعات عمومی' AND deleted_at IS NULL), 'fa', '[آزمایشی] درخواست اطلاعات عمومی', 'demo-20261019-form-2', 'این فرم صرفاً دادهٔ آزمایشی برای بررسی پنل است و فرم رسمی ارائهٔ خدمت نیست.', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `dynamic_forms` WHERE locale = 'fa' AND slug = 'demo-20261019-form-2');
INSERT INTO `form_submissions` (`reference`, `dynamic_form_id`, `user_id`, `data`, `schema_snapshot`, `status`, `reply`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`)
SELECT '01M58QAF00J2H9XQN0S948C6ZR', (SELECT id FROM dynamic_forms WHERE locale = 'fa' AND slug = 'demo-20261019-form-2'), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_02'), '{"full_name":"متقاضی آزمایشی 2","subject":"[آزمایشی] درخواست اطلاعات عمومی","description":"اطلاعات ساختگی برای بررسی گردش رسیدگی؛ متعلق به شخص واقعی نیست."}', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 'review', NULL, NULL, NULL, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `form_submissions` WHERE reference = '01M58QAF00J2H9XQN0S948C6ZR');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form_attribute', 'fa', '[آزمایشی] نشانی و محل درخواست', 'demo-20261019-attribute-3', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form_attribute' AND locale = 'fa' AND (slug = 'demo-20261019-attribute-3' OR name = '[آزمایشی] نشانی و محل درخواست'));
INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form', 'fa', '[آزمایشی] گروه ثبت پیشنهاد بهبود خدمات', 'demo-20261019-form-group-3', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form' AND locale = 'fa' AND (slug = 'demo-20261019-form-group-3' OR name = '[آزمایشی] گروه ثبت پیشنهاد بهبود خدمات'));
INSERT INTO `dynamic_forms` (`organization_id`, `category_id`, `locale`, `title`, `slug`, `description`, `fields`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'form' AND locale = 'fa' AND name = '[آزمایشی] گروه ثبت پیشنهاد بهبود خدمات' AND deleted_at IS NULL), 'fa', '[آزمایشی] ثبت پیشنهاد بهبود خدمات', 'demo-20261019-form-3', 'این فرم صرفاً دادهٔ آزمایشی برای بررسی پنل است و فرم رسمی ارائهٔ خدمت نیست.', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `dynamic_forms` WHERE locale = 'fa' AND slug = 'demo-20261019-form-3');
INSERT INTO `form_submissions` (`reference`, `dynamic_form_id`, `user_id`, `data`, `schema_snapshot`, `status`, `reply`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`)
SELECT '01M58QAF00VK5PF7JR0KW63E0X', (SELECT id FROM dynamic_forms WHERE locale = 'fa' AND slug = 'demo-20261019-form-3'), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_03'), '{"full_name":"متقاضی آزمایشی 3","subject":"[آزمایشی] ثبت پیشنهاد بهبود خدمات","description":"اطلاعات ساختگی برای بررسی گردش رسیدگی؛ متعلق به شخص واقعی نیست."}', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 'accepted', 'پاسخ آزمایشی کارشناس؛ این یک پروندهٔ واقعی نیست.', @import_author, NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `form_submissions` WHERE reference = '01M58QAF00VK5PF7JR0KW63E0X');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form_attribute', 'fa', '[آزمایشی] شرح درخواست', 'demo-20261019-attribute-4', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form_attribute' AND locale = 'fa' AND (slug = 'demo-20261019-attribute-4' OR name = '[آزمایشی] شرح درخواست'));
INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form', 'fa', '[آزمایشی] گروه پیگیری خدمت اداری', 'demo-20261019-form-group-4', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form' AND locale = 'fa' AND (slug = 'demo-20261019-form-group-4' OR name = '[آزمایشی] گروه پیگیری خدمت اداری'));
INSERT INTO `dynamic_forms` (`organization_id`, `category_id`, `locale`, `title`, `slug`, `description`, `fields`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'form' AND locale = 'fa' AND name = '[آزمایشی] گروه پیگیری خدمت اداری' AND deleted_at IS NULL), 'fa', '[آزمایشی] پیگیری خدمت اداری', 'demo-20261019-form-4', 'این فرم صرفاً دادهٔ آزمایشی برای بررسی پنل است و فرم رسمی ارائهٔ خدمت نیست.', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `dynamic_forms` WHERE locale = 'fa' AND slug = 'demo-20261019-form-4');
INSERT INTO `form_submissions` (`reference`, `dynamic_form_id`, `user_id`, `data`, `schema_snapshot`, `status`, `reply`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`)
SELECT '01M58QAF00NHH73JWVH5H6K6QK', (SELECT id FROM dynamic_forms WHERE locale = 'fa' AND slug = 'demo-20261019-form-4'), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_04'), '{"full_name":"متقاضی آزمایشی 4","subject":"[آزمایشی] پیگیری خدمت اداری","description":"اطلاعات ساختگی برای بررسی گردش رسیدگی؛ متعلق به شخص واقعی نیست."}', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 'rejected', 'پاسخ آزمایشی کارشناس؛ این یک پروندهٔ واقعی نیست.', @import_author, NOW(), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `form_submissions` WHERE reference = '01M58QAF00NHH73JWVH5H6K6QK');

INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form_attribute', 'fa', '[آزمایشی] تأیید و پیگیری', 'demo-20261019-attribute-5', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form_attribute' AND locale = 'fa' AND (slug = 'demo-20261019-attribute-5' OR name = '[آزمایشی] تأیید و پیگیری'));
INSERT INTO `categories` (`organization_id`, `type`, `locale`, `name`, `slug`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, 'form', 'fa', '[آزمایشی] گروه ارائهٔ پیشنهاد سرمایه‌گذاری', 'demo-20261019-form-group-5', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `categories` WHERE type = 'form' AND locale = 'fa' AND (slug = 'demo-20261019-form-group-5' OR name = '[آزمایشی] گروه ارائهٔ پیشنهاد سرمایه‌گذاری'));
INSERT INTO `dynamic_forms` (`organization_id`, `category_id`, `locale`, `title`, `slug`, `description`, `fields`, `is_active`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'form' AND locale = 'fa' AND name = '[آزمایشی] گروه ارائهٔ پیشنهاد سرمایه‌گذاری' AND deleted_at IS NULL), 'fa', '[آزمایشی] ارائهٔ پیشنهاد سرمایه‌گذاری', 'demo-20261019-form-5', 'این فرم صرفاً دادهٔ آزمایشی برای بررسی پنل است و فرم رسمی ارائهٔ خدمت نیست.', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `dynamic_forms` WHERE locale = 'fa' AND slug = 'demo-20261019-form-5');
INSERT INTO `form_submissions` (`reference`, `dynamic_form_id`, `user_id`, `data`, `schema_snapshot`, `status`, `reply`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`)
SELECT '01M58QAF00FDGVYFBGTTRT9E82', (SELECT id FROM dynamic_forms WHERE locale = 'fa' AND slug = 'demo-20261019-form-5'), (SELECT id FROM users WHERE username = 'demo_20261019_citizen_05'), '{"full_name":"متقاضی آزمایشی 5","subject":"[آزمایشی] ارائهٔ پیشنهاد سرمایه‌گذاری","description":"اطلاعات ساختگی برای بررسی گردش رسیدگی؛ متعلق به شخص واقعی نیست."}', '[{"key":"full_name","label":"نام و نام خانوادگی","type":"text","required":true,"max":120},{"key":"subject","label":"موضوع درخواست","type":"text","required":true,"max":255},{"key":"description","label":"شرح درخواست","type":"textarea","required":true,"max":4000}]', 'pending', NULL, NULL, NULL, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `form_submissions` WHERE reference = '01M58QAF00FDGVYFBGTTRT9E82');

INSERT INTO `members` (`organization_id`, `category_id`, `locale`, `kind`, `name`, `position`, `bio`, `is_active`, `is_current`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'member' AND locale = 'fa' AND name = 'معاونین استاندار مازندران' AND deleted_at IS NULL), 'fa', 'manager', 'عضو سازمان آزمایشی 1', 'سمت آزمایشی؛ شخص واقعی نیست', 'این رکورد فقط برای تست مدیریت اعضا ساخته شده است.', 0, 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `members` WHERE name = 'عضو سازمان آزمایشی 1' AND organization_id = @import_root);

INSERT INTO `members` (`organization_id`, `category_id`, `locale`, `kind`, `name`, `position`, `bio`, `is_active`, `is_current`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'member' AND locale = 'fa' AND name = 'مدیران ستادی استانداری مازندران' AND deleted_at IS NULL), 'fa', 'manager', 'عضو سازمان آزمایشی 2', 'سمت آزمایشی؛ شخص واقعی نیست', 'این رکورد فقط برای تست مدیریت اعضا ساخته شده است.', 0, 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `members` WHERE name = 'عضو سازمان آزمایشی 2' AND organization_id = @import_root);

INSERT INTO `members` (`organization_id`, `category_id`, `locale`, `kind`, `name`, `position`, `bio`, `is_active`, `is_current`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'member' AND locale = 'fa' AND name = 'فرمانداران مازندران' AND deleted_at IS NULL), 'fa', 'manager', 'عضو سازمان آزمایشی 3', 'سمت آزمایشی؛ شخص واقعی نیست', 'این رکورد فقط برای تست مدیریت اعضا ساخته شده است.', 0, 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `members` WHERE name = 'عضو سازمان آزمایشی 3' AND organization_id = @import_root);

INSERT INTO `members` (`organization_id`, `category_id`, `locale`, `kind`, `name`, `position`, `bio`, `is_active`, `is_current`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'member' AND locale = 'fa' AND name = 'مشاورین استاندار' AND deleted_at IS NULL), 'fa', 'manager', 'عضو سازمان آزمایشی 4', 'سمت آزمایشی؛ شخص واقعی نیست', 'این رکورد فقط برای تست مدیریت اعضا ساخته شده است.', 0, 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `members` WHERE name = 'عضو سازمان آزمایشی 4' AND organization_id = @import_root);

INSERT INTO `members` (`organization_id`, `category_id`, `locale`, `kind`, `name`, `position`, `bio`, `is_active`, `is_current`, `created_at`, `updated_at`)
SELECT @import_root, (SELECT MIN(id) FROM categories WHERE type = 'member' AND locale = 'fa' AND name = 'مدیران ادارات و دستگاههای اجرایی' AND deleted_at IS NULL), 'fa', 'manager', 'عضو سازمان آزمایشی 5', 'سمت آزمایشی؛ شخص واقعی نیست', 'این رکورد فقط برای تست مدیریت اعضا ساخته شده است.', 0, 0, NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `members` WHERE name = 'عضو سازمان آزمایشی 5' AND organization_id = @import_root);

INSERT INTO `tickets` (`reference`, `user_id`, `organization_id`, `county_id`, `type`, `category`, `subject`, `body`, `address`, `urgency`, `status`, `created_at`, `due_at`, `resolved_at`, `updated_at`)
SELECT '01M58QAF00HXPYSYS560XVWSR5', (SELECT id FROM users WHERE username = 'demo_20261019_citizen_01'), (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'sari' OR name = 'ساری') AND deleted_at IS NULL) AND deleted_at IS NULL), (SELECT MIN(id) FROM counties WHERE (slug = 'sari' OR name = 'ساری') AND deleted_at IS NULL), 'report', 'waste', '[آزمایشی] انباشت پسماند در محلهٔ نمونه', 'این گزارش و نشانی آن صرفاً نمونهٔ ساختگی برای بررسی پنل است؛ رخداد واقعی و درخواست شهروند واقعی نیست.', 'نشانی آزمایشی؛ فاقد مکان واقعی', 'urgent', 'pending', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NOW()
WHERE @import_ready = 1 AND (SELECT MIN(id) FROM counties WHERE (slug = 'sari' OR name = 'ساری') AND deleted_at IS NULL) IS NOT NULL AND (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'sari' OR name = 'ساری') AND deleted_at IS NULL) AND deleted_at IS NULL) IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `tickets` WHERE reference = '01M58QAF00HXPYSYS560XVWSR5');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5'), @import_author, NULL, 'pending', 'رویداد آزمایشی پرونده 1، گام 1', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5') AND note = 'رویداد آزمایشی پرونده 1، گام 1');
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5'), @import_author, 'پیام آزمایشی 1: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.', 1, NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_messages` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00HXPYSYS560XVWSR5') AND body = 'پیام آزمایشی 1: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.');

INSERT INTO `tickets` (`reference`, `user_id`, `organization_id`, `county_id`, `type`, `category`, `subject`, `body`, `address`, `urgency`, `status`, `created_at`, `due_at`, `resolved_at`, `updated_at`)
SELECT '01M58QAF008PXTJVJV54AMNEAZ', (SELECT id FROM users WHERE username = 'demo_20261019_citizen_02'), (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'amol' OR name = 'آمل') AND deleted_at IS NULL) AND deleted_at IS NULL), (SELECT MIN(id) FROM counties WHERE (slug = 'amol' OR name = 'آمل') AND deleted_at IS NULL), 'report', 'roads', '[آزمایشی] روشنایی مسیر نمونه', 'این گزارش و نشانی آن صرفاً نمونهٔ ساختگی برای بررسی پنل است؛ رخداد واقعی و درخواست شهروند واقعی نیست.', 'نشانی آزمایشی؛ فاقد مکان واقعی', 'high', 'review', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NOW()
WHERE @import_ready = 1 AND (SELECT MIN(id) FROM counties WHERE (slug = 'amol' OR name = 'آمل') AND deleted_at IS NULL) IS NOT NULL AND (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'amol' OR name = 'آمل') AND deleted_at IS NULL) AND deleted_at IS NULL) IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `tickets` WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ'), @import_author, NULL, 'pending', 'رویداد آزمایشی پرونده 2، گام 1', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') AND note = 'رویداد آزمایشی پرونده 2، گام 1');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ'), @import_author, 'pending', 'review', 'رویداد آزمایشی پرونده 2، گام 2', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') AND note = 'رویداد آزمایشی پرونده 2، گام 2');
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ'), @import_author, 'پیام آزمایشی 2: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.', 0, NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_messages` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF008PXTJVJV54AMNEAZ') AND body = 'پیام آزمایشی 2: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.');

INSERT INTO `tickets` (`reference`, `user_id`, `organization_id`, `county_id`, `type`, `category`, `subject`, `body`, `address`, `urgency`, `status`, `created_at`, `due_at`, `resolved_at`, `updated_at`)
SELECT '01M58QAF00QR5ZG0GXWEFSTXYK', (SELECT id FROM users WHERE username = 'demo_20261019_citizen_03'), @import_root, NULL, 'governor', NULL, '[آزمایشی] پیشنهاد بهبود پاسخ‌گویی', 'این گزارش و نشانی آن صرفاً نمونهٔ ساختگی برای بررسی پنل است؛ رخداد واقعی و درخواست شهروند واقعی نیست.', NULL, 'medium', 'referred', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), NULL, NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `tickets` WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK'), @import_author, NULL, 'pending', 'رویداد آزمایشی پرونده 3، گام 1', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') AND note = 'رویداد آزمایشی پرونده 3، گام 1');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK'), @import_author, 'pending', 'referred', 'رویداد آزمایشی پرونده 3، گام 2', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') AND note = 'رویداد آزمایشی پرونده 3، گام 2');
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK'), @import_author, 'پیام آزمایشی 3: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.', 0, NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_messages` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00QR5ZG0GXWEFSTXYK') AND body = 'پیام آزمایشی 3: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.');

INSERT INTO `tickets` (`reference`, `user_id`, `organization_id`, `county_id`, `type`, `category`, `subject`, `body`, `address`, `urgency`, `status`, `created_at`, `due_at`, `resolved_at`, `updated_at`)
SELECT '01M58QAF00Q4GK0H57P7W5E1CC', (SELECT id FROM users WHERE username = 'demo_20261019_citizen_04'), (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'nur' OR name = 'نور') AND deleted_at IS NULL) AND deleted_at IS NULL), (SELECT MIN(id) FROM counties WHERE (slug = 'nur' OR name = 'نور') AND deleted_at IS NULL), 'report', 'water', '[آزمایشی] گرفتگی آبراه نمونه', 'این گزارش و نشانی آن صرفاً نمونهٔ ساختگی برای بررسی پنل است؛ رخداد واقعی و درخواست شهروند واقعی نیست.', 'نشانی آزمایشی؛ فاقد مکان واقعی', 'medium', 'resolved', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT MIN(id) FROM counties WHERE (slug = 'nur' OR name = 'نور') AND deleted_at IS NULL) IS NOT NULL AND (SELECT MIN(id) FROM organizations WHERE type = 'county' AND county_id = (SELECT MIN(id) FROM counties WHERE (slug = 'nur' OR name = 'نور') AND deleted_at IS NULL) AND deleted_at IS NULL) IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `tickets` WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC'), @import_author, NULL, 'pending', 'رویداد آزمایشی پرونده 4، گام 1', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') AND note = 'رویداد آزمایشی پرونده 4، گام 1');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC'), @import_author, 'pending', 'review', 'رویداد آزمایشی پرونده 4، گام 2', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') AND note = 'رویداد آزمایشی پرونده 4، گام 2');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC'), @import_author, 'review', 'resolved', 'رویداد آزمایشی پرونده 4، گام 3', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') AND note = 'رویداد آزمایشی پرونده 4، گام 3');
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC'), @import_author, 'پیام آزمایشی 4: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.', 0, NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_messages` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00Q4GK0H57P7W5E1CC') AND body = 'پیام آزمایشی 4: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.');

INSERT INTO `tickets` (`reference`, `user_id`, `organization_id`, `county_id`, `type`, `category`, `subject`, `body`, `address`, `urgency`, `status`, `created_at`, `due_at`, `resolved_at`, `updated_at`)
SELECT '01M58QAF00ZMHSB2NVQ8SQ2NP6', (SELECT id FROM users WHERE username = 'demo_20261019_citizen_05'), @import_root, NULL, 'governor', NULL, '[آزمایشی] درخواست ملاقات آزمایشی', 'این گزارش و نشانی آن صرفاً نمونهٔ ساختگی برای بررسی پنل است؛ رخداد واقعی و درخواست شهروند واقعی نیست.', NULL, 'low', 'closed', NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW()
WHERE @import_ready = 1  AND NOT EXISTS (SELECT 1 FROM `tickets` WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6'), @import_author, NULL, 'pending', 'رویداد آزمایشی پرونده 5، گام 1', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') AND note = 'رویداد آزمایشی پرونده 5، گام 1');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6'), @import_author, 'pending', 'review', 'رویداد آزمایشی پرونده 5، گام 2', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') AND note = 'رویداد آزمایشی پرونده 5، گام 2');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6'), @import_author, 'review', 'resolved', 'رویداد آزمایشی پرونده 5، گام 3', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') AND note = 'رویداد آزمایشی پرونده 5، گام 3');
INSERT INTO `ticket_events` (`ticket_id`, `user_id`, `from_status`, `to_status`, `note`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6'), @import_author, 'resolved', 'closed', 'رویداد آزمایشی پرونده 5، گام 4', NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_events` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') AND note = 'رویداد آزمایشی پرونده 5، گام 4');
INSERT INTO `ticket_messages` (`ticket_id`, `user_id`, `body`, `is_internal`, `created_at`, `updated_at`)
SELECT (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6'), @import_author, 'پیام آزمایشی 5: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.', 0, NOW(), NOW()
WHERE @import_ready = 1 AND (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ticket_messages` WHERE ticket_id = (SELECT id FROM tickets WHERE reference = '01M58QAF00ZMHSB2NVQ8SQ2NP6') AND body = 'پیام آزمایشی 5: اطلاعات برای بررسی ثبت شد؛ این پاسخ مربوط به پروندهٔ واقعی نیست.');

COMMIT;
SELECT 'SYNTHETIC TEST DATA import completed; no real identities or subscriptions were created' AS result, @import_ready AS imported;
