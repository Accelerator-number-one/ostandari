<?php

namespace App\Services;

use App\Models\{Media, Menu, MenuItem, Organization, Setting, User};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Display configuration belongs to one organization; it never changes portal identity or permissions. */
final class CountyPortal
{
    public const SECTIONS = ['slider'=>'اسلایدشو', 'governor'=>'کارت فرماندار', 'aerial'=>'تصویر هوایی',
        'news'=>'اخبار و اطلاعیه‌ها', 'gallery'=>'گالری تصاویر', 'managers'=>'مدیران شهرستان',
        'shortcuts'=>'دسترسی سریع', 'newsletter'=>'عضویت در خبرنامه', 'links'=>'پیوندهای مرتبط',
        'events'=>'رویدادها', 'location'=>'شهرستان در یک نگاه', 'advertisements'=>'بنرهای اطلاع‌رسانی',
        'nav_duties'=>'شرح وظایف در منو', 'nav_polls'=>'نظرسنجی در منو',
        'nav_agreement'=>'توافق خدمات در منو', 'nav_assistant'=>'هوش مصنوعی در منو'];
    public const TITLES = ['news'=>'اخبار و اطلاعیه‌ها', 'gallery'=>'گالری تصاویر', 'managers'=>'مدیران شهرستان',
        'shortcuts'=>'دسترسی سریع', 'newsletter'=>'عضویت در خبرنامه', 'links'=>'پیوندهای مرتبط', 'events'=>'رویدادها'];
    public const MENUS = ['main'=>['official-main','منوی اصلی و مگامنو'], 'utility'=>['portal-utility','پیوندهای بالای سایت'],
        'quick'=>['portal-quick','دسترسی سریع'], 'footer'=>['portal-footer','پیوندهای پایین سایت']];
    public const IMAGES = ['logo_media_id'=>'لوگوی پرتال', 'banner_media_id'=>'بنر بالای صفحه',
        'slogan_media_id'=>'لوگوی شعار سال', 'aerial_media_id'=>'تصویر هوایی شهرستان'];

    public static function pages(): array
    {
        $pages = ['/information'=>'دسترسی آزاد به اطلاعات','/performance'=>'عملکرد فرمانداری',
            '/participation'=>'مشارکت مردمی','/service-agreement'=>'توافق خدمات','/service-agreement/statement'=>'بیانیهٔ توافق خدمات',
            '/accessibility'=>'دسترس‌پذیری','/privacy'=>'حریم خصوصی',
            '/districts'=>'بخش‌ها و روستاها','/units'=>'واحدهای فرمانداری','/governorate'=>'معرفی فرمانداری'];
        foreach (app(PortalView::class)->data('frontend')['officialSections'] as $section) {
            if (!RetiredSections::entry($section) && in_array($section['kind']??'', ['page','article'], true) && str_starts_with($section['href'],'/section/')) $pages[$section['href']]=$section['title'];
        }
        return $pages;
    }

    public static function key(Organization $organization): string { return 'county.'.$organization->id.'.portal'; }

    public static function configuration(Organization $organization): array
    {
        return Setting::where('key', self::key($organization))->value('value') ?? [];
    }

    public static function publicData(Organization $organization): array
    {
        $data = self::configuration($organization);
        return ['title'=>$data['title'] ?? 'فرمانداری شهرستان '.$organization->county?->name,
            'about_body'=>$data['about_body'] ?? $organization->county?->description ?? '',
            'slogan_text'=>$data['slogan_text'] ?? null,
            'sections'=>array_replace(array_fill_keys(array_keys(self::SECTIONS), true), $data['sections'] ?? []),
            'titles'=>array_replace(self::TITLES, $data['titles'] ?? []),
            'logo'=>self::image($data['logo_media_id'] ?? null), 'slogan'=>self::image($data['slogan_media_id'] ?? null)];
    }

    private static function image(?int $id): ?array
    {
        return $id ? Media::where('visibility', 'public')->where('mime', 'like', 'image/%')->find($id)?->toArray() : null;
    }

    public static function form(User $user): array
    {
        Workspace::assertCounty($user);
        $org = $user->organization->load('county', 'banner', 'aerial');
        $data = self::configuration($org); $public = self::publicData($org);
        $fields = Arr::only($org->toArray(), ['email','phone','fax','sms_number','address','banner_media_id','aerial_media_id','banner','aerial']);
        $fields += ['title'=>$public['title'], 'about_body'=>$public['about_body'],
            'county_description'=>$org->county->description ?? '', 'video_url'=>$org->county->video_url ?? '',
            'slogan_text'=>$data['slogan_text'] ?? '', 'logo_media_id'=>$data['logo_media_id'] ?? null,
            'slogan_media_id'=>$data['slogan_media_id'] ?? null, 'logo'=>$public['logo'], 'slogan'=>$public['slogan']];
        foreach ($public['sections'] as $key=>$value) $fields['section_'.$key] = $value;
        foreach ($public['titles'] as $key=>$value) $fields['title_'.$key] = $value;
        return $fields;
    }

    public static function save(User $user, array $input): array
    {
        Workspace::assertCounty($user);
        abort_unless($user->hasPermission('organizations.manage'), 403);
        abort_if(array_intersect_key($input, array_flip(['organization_id','county_id','key','slug','type','is_active','role_id','access_scope'])), 403,
            'شناسهٔ پرتال و فرمانداری مربوط از حساب شما تعیین می‌شود و قابل تغییر نیست.');
        $rules = ['title'=>['sometimes','required','string','max:255'], 'slogan_text'=>['nullable','string','max:500'],
            'about_body'=>['nullable','string','max:1000000'], 'county_description'=>['nullable','string','max:16000'],
            'video_url'=>['nullable','url:http,https','max:2048'], 'email'=>['nullable','email','max:255'],
            'address'=>['nullable','string','max:10000']];
        foreach (['phone','fax','sms_number'] as $key) $rules[$key] = ['nullable','string','max:40'];
        foreach (self::IMAGES as $key=>$_) $rules[$key] = ['nullable','integer',Rule::exists('media','id')->where('visibility','public')->whereNull('deleted_at')];
        foreach (self::SECTIONS as $key=>$_) $rules['section_'.$key] = ['sometimes','boolean'];
        foreach (self::TITLES as $key=>$_) $rules['title_'.$key] = ['sometimes','required','string','max:100'];
        $validated = Validator::make($input, $rules)->validate();
        return DB::transaction(function () use ($user, $validated) {
            $org = Organization::whereKey($user->organization_id)->lockForUpdate()->firstOrFail();
            $record = Setting::firstOrCreate(['key'=>self::key($org)], ['value'=>[], 'is_public'=>false]);
            $data = $record->value ?? [];
            foreach (self::IMAGES as $key=>$_) if (!empty($validated[$key])) {
                $file = Media::findOrFail($validated[$key]);
                abort_unless(str_starts_with($file->mime, 'image/') && FileAccess::mayManage($file, $user), 403, 'تصویر باید عمومی و متعلق به همین فرمانداری باشد.');
            }
            $org->update(Arr::only($validated, ['email','phone','fax','sms_number','address','banner_media_id','aerial_media_id']));
            $county = $org->county;
            if (array_key_exists('county_description', $validated)) $county->description = Html::clean($validated['county_description'] ?? '');
            if (array_key_exists('video_url', $validated)) $county->video_url = $validated['video_url'];
            if ($county->isDirty()) $county->save();
            $data = array_replace($data, Arr::only($validated, ['title','slogan_text','logo_media_id','slogan_media_id']));
            if (array_key_exists('slogan_text', $validated)) $data['slogan_text'] = $validated['slogan_text'] ?? '';
            if (array_key_exists('about_body', $validated)) $data['about_body'] = Html::clean($validated['about_body'] ?? '');
            foreach (self::SECTIONS as $key=>$_) if (array_key_exists('section_'.$key, $validated)) $data['sections'][$key] = $validated['section_'.$key];
            foreach (self::TITLES as $key=>$_) if (array_key_exists('title_'.$key, $validated)) $data['titles'][$key] = $validated['title_'.$key];
            $record->update(['value'=>$data, 'is_public'=>false]);
            $html = $data['about_body'] ?? '';
            foreach (['logo_media_id','slogan_media_id'] as $key) if ($image = self::image($data[$key] ?? null)) $html .= '<img src="'.e($image['url']).'">';
            InlineMedia::syncHtml($record, $user, $html, 24);
            Audit::record('county-portal.updated', $org, ['fields'=>array_keys($validated)]);
            $user->unsetRelation('organization');
            return self::form($user);
        });
    }

    public static function prepareMenu(User $user, string $group, PortalView $ui): Menu
    {
        Workspace::assertCounty($user);
        abort_unless($user->hasPermission('navigation.manage'), 403);
        abort_unless(isset(self::MENUS[$group]), 422);
        return DB::transaction(function () use ($user, $group, $ui) {
            $org = Organization::whereKey($user->organization_id)->lockForUpdate()->firstOrFail();
            [$key,$name] = self::MENUS[$group];
            $menu = Menu::where('organization_id',$org->id)->where('locale','fa')->where('key',$key)->first();
            if ($menu) return $menu;
            $menu = Menu::create(['organization_id'=>$org->id,'name'=>$name,'key'=>$key,'locale'=>'fa','is_active'=>true]);
            $county = $ui->county(Workspace::context($user)['county']['slug']);
            $simple = fn ($rows) => array_map(fn ($row) => ['title'=>$row[0], 'href'=>$ui->href($row[1],$county)], $rows);
            $items = match ($group) {
                'main'=>$ui->navigation([], $county),
                'utility'=>$simple([['تماس با ما','/contact'],['دسترسی سریع','/quick-access'],['سامانه شفافیت','/transparency'],['قوانین و مقررات','/regulations']]),
                'quick'=>$simple([['میز خدمت الکترونیکی','/services'],['ارتباط با فرمانداری','/contact'],['پیگیری درخواست‌ها','/citizen']]),
                'footer'=>$simple([['صفحه اصلی','/'],['معرفی شهرستان','/about'],['تماس با ما','/contact'],['خدمات الکترونیکی','/services'],['اخبار','/news']]),
            };
            $insert = function (array $items, ?int $parent = null) use (&$insert, $menu) {
                foreach ($items as $priority=>$item) {
                    $row = MenuItem::create(['menu_id'=>$menu->id,'parent_id'=>$parent,'title'=>$item['title'],
                        'url'=>$item['href'] ?? '/','priority'=>$priority,'is_active'=>true]);
                    $insert($item['children'] ?? [], $row->id);
                }
            };
            $insert($items);
            Audit::record('county-menu.created', $org, ['menu_id'=>$menu->id,'group'=>$group]);
            return $menu;
        });
    }
}
