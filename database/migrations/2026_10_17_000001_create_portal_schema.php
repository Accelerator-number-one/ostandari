<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('label');
            $t->json('permissions');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });
        Schema::create('counties', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('map_key')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('video_url', 2048)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 40);
            $t->string('name');
            $t->string('name_en')->nullable();
            $t->string('slug')->unique();
            $t->string('domain')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('fax', 40)->nullable();
            $t->string('sms_number', 40)->nullable();
            $t->text('address')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('username')->nullable()->unique();
            $t->string('email')->nullable()->unique();
            $t->string('mobile', 11)->nullable()->unique();
            $t->string('national_code', 10)->nullable()->unique();
            $t->string('password')->nullable();
            $t->string('person_type', 20)->default('citizen');
            $t->string('access_scope', 20)->default('organization');
            $t->string('personnel_code', 60)->nullable();
            $t->text('address')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('mobile_verified_at')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('otp_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('mobile', 11)->index();
            $t->string('national_code', 10);
            $t->string('name');
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('disk', 20);
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 120);
            $t->unsignedBigInteger('size');
            $t->string('visibility', 10);
            $t->string('alt')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('type', 30);
            $t->string('locale', 2)->default('fa');
            $t->string('name');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->unsignedInteger('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['type', 'locale', 'slug']);
        });
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 2)->default('fa');
            $t->string('name');
            $t->string('slug');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['locale', 'slug']);
        });
        Schema::create('contents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('cover_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $t->string('type', 30);
            $t->string('locale', 2)->default('fa');
            $t->string('title');
            $t->string('slug');
            $t->string('subtitle')->nullable();
            $t->text('excerpt')->nullable();
            $t->longText('body');
            $t->json('metadata')->nullable();
            $t->string('status', 20)->default('draft');
            $t->boolean('is_featured')->default(false);
            $t->boolean('comments_enabled')->default(false);
            $t->unsignedInteger('priority')->default(0);
            $t->unsignedBigInteger('views')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['type', 'locale', 'slug']);
            $t->index(['type', 'locale', 'status', 'published_at']);
        });
        Schema::create('content_tag', function (Blueprint $t) {
            $t->foreignId('content_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['content_id', 'tag_id']);
        });
        Schema::create('mediables', function (Blueprint $t) {
            $t->id();
            $t->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $t->morphs('mediable');
            $t->string('purpose', 30)->default('attachment');
            $t->timestamps();
            $t->unique(['media_id', 'mediable_id', 'mediable_type']);
        });
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('key');
            $t->string('locale', 2)->default('fa');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['key', 'locale']);
        });
        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained()->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('menu_items')->restrictOnDelete();
            $t->foreignId('content_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('url', 2048)->nullable();
            $t->string('icon', 100)->nullable();
            $t->string('target', 10)->default('_self');
            $t->unsignedInteger('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('slides', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->default('slide');
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $t->foreignId('content_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('title');
            $t->string('subtitle')->nullable();
            $t->string('url', 2048)->nullable();
            $t->unsignedInteger('priority')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('kind', 30);
            $t->string('name');
            $t->string('position');
            $t->text('bio')->nullable();
            $t->longText('body')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->timestamp('term_start')->nullable();
            $t->timestamp('term_end')->nullable();
            $t->boolean('is_current')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('services')->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('name');
            $t->string('slug');
            $t->string('code', 80)->nullable();
            $t->text('description')->nullable();
            $t->json('requirements')->nullable();
            $t->json('steps')->nullable();
            $t->string('delivery_time')->nullable();
            $t->string('cost')->nullable();
            $t->string('target_audience')->nullable();
            $t->string('url', 2048)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['locale', 'slug']);
        });
        Schema::create('links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('title');
            $t->string('url', 2048);
            $t->string('position', 30)->default('useful');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 2)->default('fa');
            $t->string('question', 1000);
            $t->text('answer');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 2)->default('fa');
            $t->string('author');
            $t->text('text');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('galleries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('title');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['locale', 'slug']);
        });
        Schema::create('gallery_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gallery_id')->constrained()->restrictOnDelete();
            $t->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $t->string('title')->nullable();
            $t->text('caption')->nullable();
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value')->nullable();
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });
        Schema::create('polls', function (Blueprint $t) {
            $t->id();
            $t->string('locale', 2)->default('fa');
            $t->string('question', 1000);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('poll_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->unsignedInteger('priority')->default(0);
            $t->timestamps();
        });
        Schema::create('poll_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $t->foreignId('poll_option_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->unique(['poll_id', 'user_id']);
        });
        Schema::create('dynamic_forms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('locale', 2)->default('fa');
            $t->string('title');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->json('fields');
            $t->boolean('is_active')->default(false);
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closes_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['locale', 'slug']);
        });
        Schema::create('form_submissions', function (Blueprint $t) {
            $t->id();
            $t->ulid('reference')->unique();
            $t->foreignId('dynamic_form_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->json('data');
            $t->json('schema_snapshot');
            $t->string('status', 20)->default('pending');
            $t->text('reply')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->ulid('reference')->unique();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20);
            $t->string('category', 30)->nullable();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('subject');
            $t->text('body');
            $t->text('address')->nullable();
            $t->string('urgency', 10)->default('medium');
            $t->string('status', 20)->default('pending');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'status', 'created_at']);
        });
        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->boolean('is_internal')->default(false);
            $t->timestamps();
        });
        Schema::create('ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20);
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('content_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->string('status', 20)->default('pending');
            $t->text('reply')->nullable();
            $t->timestamps();
        });
        Schema::create('forum_topics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->text('body');
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });
        Schema::create('forum_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('forum_topic_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });
        Schema::create('subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->string('name')->nullable();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('verified_at')->nullable();
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80);
            $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['failed_jobs', 'jobs', 'audit_logs', 'notifications', 'subscribers', 'forum_replies', 'forum_topics', 'comments', 'ticket_events', 'ticket_messages', 'tickets', 'form_submissions', 'dynamic_forms', 'poll_votes', 'poll_options', 'polls', 'settings', 'gallery_items', 'galleries', 'quotes', 'faqs', 'links', 'services', 'members', 'slides', 'menu_items', 'menus', 'mediables', 'content_tag', 'contents', 'tags', 'categories', 'media', 'otp_challenges', 'personal_access_tokens', 'password_reset_tokens', 'users', 'organizations', 'counties', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
