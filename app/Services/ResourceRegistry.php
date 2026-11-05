<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Content;
use App\Models\County;
use App\Models\DynamicForm;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Link;
use App\Models\Member;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Slide;
use App\Models\Tag;
use App\Rules\SafeLink;
use Illuminate\Validation\Rule;

/** Explicit resource allowlist: clients cannot select arbitrary models, columns or relations. */
final class ResourceRegistry
{
    public static function all(): array
    {
        $locale = ['sometimes', Rule::in(config('portal.locales'))];
        $text = ['required', 'string', 'max:255'];
        $optional = ['nullable', 'string', 'max:255'];
        $long = ['nullable', 'string', 'max:100000'];
        $flag = ['sometimes', 'boolean'];
        $priority = ['sometimes', 'integer', 'min:0', 'max:100000'];
        $category = ['nullable', Rule::exists('categories', 'id')->whereNull('deleted_at')];
        $org = ['nullable', Rule::exists('organizations', 'id')->whereNull('deleted_at')];
        $image = ['nullable', Rule::exists('media', 'id')->where('visibility', 'public')->whereNull('deleted_at')];
        $url = ['nullable', 'url:http,https', 'max:2048'];
        $linkUrl = ['nullable', 'string', 'max:2048', new SafeLink];
        $date = ['nullable', 'date'];
        $content = ['model' => Content::class, 'permission' => 'content.manage', 'search' => ['title', 'excerpt', 'body'], 'tenant' => true,
            'filters' => ['type', 'category_id', 'organization_id', 'status', 'locale', 'is_featured'], 'with' => ['cover', 'category', 'tags', 'media', 'author'],
            'rules' => ['type' => ['required', Rule::in(config('portal.content_types'))], 'locale' => $locale, 'organization_id' => $org, 'category_id' => $category,
                'cover_media_id' => $image, 'title' => $text, 'slug' => $optional, 'subtitle' => $optional, 'excerpt' => ['nullable', 'string', 'max:4000'],
                'news_scope' => ['nullable', Rule::in(array_keys(NewsFeeds::LABELS))],
                'duty_group' => ['nullable', 'string', 'max:255'],
                'portal_path' => ['nullable', Rule::in(array_keys(CountyPortal::pages()))],
                'body' => ['required', 'string', 'max:1000000'], 'metadata' => ['nullable', 'array', 'max:30'], 'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
                'is_featured' => $flag, 'show_in_slideshow' => $flag, 'comments_enabled' => $flag, 'priority' => $priority, 'published_at' => $date, 'expires_at' => $date,
                'tag_ids' => ['sometimes', 'array', 'max:30'], 'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->whereNull('deleted_at')],
                'media_ids' => ['sometimes', 'array', 'max:20'], 'media_ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')->where('visibility', 'public')->whereNull('deleted_at')]]];
        $all = [
            'contents' => $content,
            'categories' => ['model' => Category::class, 'permission' => 'categories.manage', 'search' => ['name'], 'tenant' => true, 'filters' => ['type', 'locale', 'parent_id', 'is_active'],
                'rules' => ['name' => $text, 'slug' => $optional, 'type' => ['required', Rule::in(config('portal.category_types'))], 'locale' => $locale, 'description' => $long, 'parent_id' => $category, 'organization_id' => $org, 'priority' => $priority, 'is_active' => $flag]],
            'tags' => ['model' => Tag::class, 'permission' => 'content.manage', 'search' => ['name'], 'filters' => ['locale'], 'rules' => ['name' => $text, 'slug' => $optional, 'locale' => $locale]],
            'counties' => ['model' => County::class, 'permission' => 'organizations.manage', 'search' => ['name'], 'filters' => ['is_active'], 'rules' => ['name' => $text, 'slug' => $optional, 'map_key' => $optional, 'description' => $long, 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'video_url' => $url, 'priority' => $priority, 'is_active' => $flag]],
            'organizations' => ['model' => Organization::class, 'permission' => 'organizations.manage', 'search' => ['name', 'domain'], 'filters' => ['type', 'county_id', 'parent_id', 'is_active'], 'with' => ['county', 'banner', 'aerial'],
                'rules' => ['banner_media_id' => $image, 'aerial_media_id' => $image, 'name' => $text, 'name_en' => $optional, 'slug' => $optional, 'type' => ['required', Rule::in(['governorate', 'county', 'district', 'village', 'department'])],
                    'parent_id' => $org, 'county_id' => ['nullable', Rule::exists('counties', 'id')->whereNull('deleted_at')], 'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
                    'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'fax' => ['nullable', 'string', 'max:40'], 'sms_number' => ['nullable', 'string', 'max:40'], 'address' => $long, 'description' => $long, 'priority' => $priority, 'is_active' => $flag]],
            'menus' => ['model' => Menu::class, 'permission' => 'navigation.manage', 'search' => ['name'], 'filters' => ['key', 'locale', 'is_active'], 'rules' => ['name' => $text, 'key' => ['required', 'string', 'max:60', 'alpha_dash:ascii'], 'locale' => $locale, 'is_active' => $flag]],
            'menu-items' => ['model' => MenuItem::class, 'permission' => 'navigation.manage', 'search' => ['title'], 'filters' => ['menu_id', 'parent_id', 'is_active'],
                'rules' => ['menu_id' => ['required', Rule::exists('menus', 'id')->whereNull('deleted_at')], 'parent_id' => ['nullable', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
                    'content_id' => ['nullable', Rule::exists('contents', 'id')->whereNull('deleted_at')], 'title' => $text, 'url' => ['nullable', 'string', 'max:2048', new SafeLink],
                    'icon' => ['nullable', 'string', 'max:100'], 'target' => ['sometimes', Rule::in(['_self', '_blank'])], 'priority' => $priority, 'is_active' => $flag]],
            'slides' => ['model' => Slide::class, 'permission' => 'content.manage', 'search' => ['title'], 'scope' => ['kind' => 'slide'], 'filters' => ['category_id', 'locale', 'is_active'], 'with' => ['image'],
                'rules' => ['category_id' => $category, 'locale' => $locale, 'title' => $optional, 'subtitle' => $optional, 'media_id' => $image, 'content_id' => ['nullable', Rule::exists('contents', 'id')->whereNull('deleted_at')],
                    'url' => $linkUrl, 'priority' => $priority, 'is_active' => $flag, 'starts_at' => $date, 'ends_at' => $date]],
            'members' => ['model' => Member::class, 'permission' => 'members.manage', 'search' => ['name', 'position'], 'tenant' => true, 'filters' => ['kind', 'category_id', 'organization_id', 'county_id', 'locale', 'is_current', 'is_active'], 'with' => ['image', 'organization', 'category'],
                'rules' => ['name' => $text, 'position' => $text, 'kind' => ['required', Rule::in(['manager', 'governor', 'county_governor', 'district_head', 'village_head', 'advisor'])],
                    'locale' => $locale, 'organization_id' => $org, 'county_id' => ['nullable', Rule::exists('counties', 'id')->whereNull('deleted_at')], 'category_id' => $category, 'media_id' => $image,
                    'bio' => $long, 'body' => $long, 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'term_start' => $date, 'term_end' => $date,
                    'term_start_year' => ['nullable', 'integer', 'between:1200,1600'], 'term_end_year' => ['nullable', 'integer', 'between:1200,1600'],
                    'is_current' => $flag, 'is_active' => $flag, 'priority' => $priority]],
            'services' => ['model' => Service::class, 'permission' => 'services.manage', 'search' => ['name', 'description', 'code'], 'tenant' => true, 'filters' => ['category_id', 'organization_id', 'parent_id', 'locale', 'is_active'], 'with' => ['organization', 'category'],
                'rules' => ['name' => $text, 'slug' => $optional, 'code' => ['nullable', 'string', 'max:80'], 'locale' => $locale, 'organization_id' => $org, 'category_id' => $category, 'parent_id' => ['nullable', Rule::exists('services', 'id')->whereNull('deleted_at')],
                    'description' => $long, 'requirements' => ['nullable', 'array', 'max:50'], 'requirements.*' => ['string', 'max:1000'], 'steps' => ['nullable', 'array', 'max:50'], 'steps.*' => ['string', 'max:2000'],
                    'delivery_time' => $optional, 'cost' => $optional, 'target_audience' => $optional, 'url' => $linkUrl, 'is_active' => $flag, 'priority' => $priority]],
            'links' => ['model' => Link::class, 'permission' => 'content.manage', 'search' => ['title'], 'filters' => ['category_id', 'locale', 'position', 'is_active'], 'with' => ['image'],
                'rules' => ['icon' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(PortalIcons::ITEMS))], 'title' => $text, 'url' => ['required', 'string', 'max:2048', new SafeLink], 'locale' => $locale, 'category_id' => $category, 'media_id' => $image, 'position' => ['sometimes', Rule::in(['useful', 'footer', 'banner', 'quick'])], 'is_active' => $flag, 'priority' => $priority]],
            'faqs' => ['model' => Faq::class, 'permission' => 'content.manage', 'search' => ['question', 'answer'], 'filters' => ['locale', 'is_active'], 'rules' => ['question' => ['required', 'string', 'max:1000'], 'answer' => ['required', 'string', 'max:30000'], 'locale' => $locale, 'is_active' => $flag, 'priority' => $priority]],
            'quotes' => ['model' => Quote::class, 'permission' => 'content.manage', 'search' => ['text', 'author'], 'filters' => ['locale', 'is_active'], 'rules' => ['author' => $text, 'text' => ['required', 'string', 'max:10000'], 'locale' => $locale, 'is_active' => $flag, 'priority' => $priority, 'starts_at' => $date, 'ends_at' => $date]],
            'galleries' => ['model' => Gallery::class, 'permission' => 'content.manage', 'search' => ['title', 'description'], 'tenant' => true, 'filters' => ['category_id', 'organization_id', 'locale', 'status'], 'with' => ['items'],
                'rules' => ['title' => $text, 'slug' => $optional, 'description' => $long, 'locale' => $locale, 'category_id' => $category, 'organization_id' => $org, 'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])], 'published_at' => $date, 'priority' => $priority]],
            'gallery-items' => ['model' => GalleryItem::class, 'permission' => 'content.manage', 'search' => ['title'], 'filters' => ['gallery_id'], 'with' => ['image'],
                'rules' => ['gallery_id' => ['required', Rule::exists('galleries', 'id')->whereNull('deleted_at')], 'media_id' => ['required', Rule::exists('media', 'id')->where('visibility', 'public')->whereNull('deleted_at')], 'title' => $optional, 'caption' => $long, 'priority' => $priority]],
            'polls' => ['model' => Poll::class, 'permission' => 'polls.manage', 'search' => ['question'], 'filters' => ['locale', 'status'], 'with' => ['options'],
                'rules' => ['question' => ['required', 'string', 'max:1000'], 'description' => $long, 'locale' => $locale, 'status' => ['sometimes', Rule::in(['draft', 'active', 'closed'])], 'starts_at' => $date, 'ends_at' => $date]],
            'poll-options' => ['model' => PollOption::class, 'permission' => 'polls.manage', 'search' => ['title'], 'filters' => ['poll_id'], 'rules' => ['poll_id' => ['required', Rule::exists('polls', 'id')->whereNull('deleted_at')], 'title' => $text, 'priority' => $priority]],
            'forms' => ['model' => DynamicForm::class, 'permission' => 'forms.manage', 'search' => ['title', 'description'], 'tenant' => true, 'filters' => ['category_id', 'locale', 'is_active'],
                'rules' => ['title' => $text, 'slug' => $optional, 'description' => $long, 'locale' => $locale, 'organization_id' => $org, 'category_id' => $category, 'fields' => ['required', 'array', 'min:1', 'max:40'], 'is_active' => $flag, 'opens_at' => $date, 'closes_at' => $date]],
        ];
        foreach (['menus', 'slides', 'links', 'faqs', 'quotes', 'polls', 'tags'] as $resource) {
            $all[$resource]['tenant'] = true;
            $all[$resource]['rules']['organization_id'] = $org;
            $all[$resource]['filters'][] = 'organization_id';
        }
        foreach (['news' => 'news' , 'announcements' => 'announcement', 'resolutions' => 'resolution', 'pages' => 'page', 'publications' => 'publication', 'tenders' => 'tender', 'contracts' => 'contract', 'calls' => 'call', 'events' => 'event', 'duties' => 'duty', 'documents' => 'document'] as $key => $type) {
            $all[$key] = $content;
            $all[$key]['scope'] = ['type' => $type];
        }
        $all['advertisements'] = $all['slides'];
        $all['advertisements']['scope'] = ['kind' => 'advertisement'];

        return $all;
    }

    public static function get(string $name): array
    {
        return self::all()[$name] ?? abort(404);
    }
}
