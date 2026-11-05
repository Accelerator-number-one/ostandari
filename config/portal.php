<?php

return [
    'locales' => ['fa', 'en', 'ar'],
    'content_types' => ['news', 'announcement', 'resolution', 'page', 'publication', 'tender', 'contract', 'call', 'event', 'duty', 'document'],
    'category_types' => ['news', 'document', 'slide', 'gallery', 'media', 'member', 'service', 'form', 'form_attribute', 'forum', 'link', 'publication'],
    'permissions' => ['dashboard.view', 'content.manage', 'categories.manage', 'navigation.manage', 'media.manage', 'organizations.manage', 'members.manage', 'services.manage', 'settings.manage', 'users.manage', 'roles.manage', 'tickets.manage', 'forms.manage', 'polls.manage', 'comments.manage', 'forum.manage', 'newsletter.manage', 'audit.view'],
    'public_settings' => ['site.name', 'site.description', 'site.logo', 'site.banner', 'site.slogan', 'site.slogan_text', 'site.socials', 'site.contact', 'site.footer', 'site.app_url', 'site.app_qr', 'site.seo', 'home.sections'],
    'report_categories' => ['waste', 'roads', 'water', 'electricity', 'environment', 'health', 'security', 'other'],
    'otp' => ['driver' => env('OTP_DRIVER', 'log'), 'expose_local' => (bool) env('OTP_EXPOSE_LOCAL', false), 'ttl' => (int) env('OTP_TTL_SECONDS', 120), 'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5)],
    'sms' => ['endpoint' => env('SMS_ENDPOINT'), 'token' => env('SMS_TOKEN'), 'sender' => env('SMS_SENDER')],
    'events' => ['driver' => env('EVENT_SMS_DRIVER', env('OTP_DRIVER', 'log'))],
    'upload_max_kb' => (int) env('UPLOAD_MAX_KB', 20480),
    'images' => [
        'quality' => (int) env('UPLOAD_IMAGE_QUALITY', 84),
        'max_edge' => (int) env('UPLOAD_IMAGE_MAX_EDGE', 2000),
        'max_pixels' => (int) env('UPLOAD_IMAGE_MAX_PIXELS', 40000000),
    ],
    'sla' => ['report_days' => (int) env('REPORT_RESPONSE_DAYS', 7), 'message_days' => (int) env('MESSAGE_RESPONSE_DAYS', 5),
        'weekend_days' => array_map('intval', explode(',', env('WORKING_WEEKEND_DAYS', '5'))), 'holidays' => array_filter(explode(',', env('WORKING_HOLIDAYS', '')))],
];
