<?php

namespace App\Services;

use App\Models\{Category, Member, Organization, Setting};
use Illuminate\Support\Facades\{DB, File, Http};

final class MemberDirectory
{
    public const IMPORT_KEY = 'imports.member-directory.2026-10-25.v1';
    public const PHOTO_URL = 'https://ostan-mz.ir/OstanFiles/MunicipalityMember/MunicipalityMember68457915IMG_9366.JPG';
    public const PHOTO_PATH = 'design/members/temporary-shared.jpg';
    public const GROUPS = [
        '2' => ['slug'=>'official-member-menu-64','title'=>'معاونین استاندار مازندران','kind'=>'manager','position'=>'معاون استاندار مازندران','count'=>4],
        '1' => ['slug'=>'official-member-menu-61','title'=>'مدیران ستادی استانداری مازندران','kind'=>'manager','position'=>'مدیر ستادی استانداری مازندران','count'=>24],
        '3' => ['slug'=>'official-member-menu-62','title'=>'مدیران ادارات و دستگاههای اجرایی','kind'=>'manager','position'=>'مدیر دستگاه اجرایی استان مازندران','count'=>148],
        '4' => ['slug'=>'official-member-menu-63','title'=>'فرمانداران مازندران','kind'=>'county_governor','position'=>'فرماندار','count'=>22],
    ];

    public static function portraitUrl(): string
    {
        return is_file(public_path(self::PHOTO_PATH)) ? '/'.self::PHOTO_PATH : self::PHOTO_URL;
    }

    public function import(): array
    {
        $file=database_path('data/member-directory-2026-10-25.json');
        $source=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        $groups=array_column($source['groups'],null,'group');
        if(array_diff(array_keys($groups),array_keys(self::GROUPS)) || count($groups)!==count(self::GROUPS))throw new \RuntimeException('Unexpected directory groups.');
        foreach(self::GROUPS as $key=>$group){
            $names=$groups[$key]['names']??[];
            if(count($names)!==$group['count'] || count(array_unique($names))!==count($names))throw new \RuntimeException('Directory count mismatch for group '.$key);
            foreach($names as $name)if(!is_string($name) || trim($name)==='' || mb_strlen($name)>255)throw new \RuntimeException('Invalid directory name.');
        }

        if($import=Setting::where('key',self::IMPORT_KEY)->first())return [...$import->value,'already_imported'=>true];
        return DB::transaction(function()use($groups,$file){
            $root=Organization::active()->where('type','governorate')->orderBy('id')->lockForUpdate()->firstOrFail();
            if($import=Setting::where('key',self::IMPORT_KEY)->first())return [...$import->value,'already_imported'=>true];
            $result=['created'=>0,'existing'=>0,'skipped'=>0,'groups'=>[],'member_ids'=>[],'source'=>'user_supplied',
                'source_sha256'=>hash_file('sha256',$file),'shared_portrait_url'=>self::PHOTO_URL,'imported_at'=>now()->toIso8601String()];
            foreach(self::GROUPS as $key=>$group){
                $category=Category::withTrashed()->firstOrCreate(['type'=>'member','locale'=>'fa','slug'=>$group['slug']],
                    ['organization_id'=>$root->id,'name'=>$group['title'],'is_active'=>true]);
                if($category->trashed() || !$category->is_active || $category->organization_id && (int)$category->organization_id!==$root->id)
                    throw new \RuntimeException('Directory group is disabled or belongs to another portal: '.$group['slug']);
                $result['groups'][$key]=0;
                $existing=Member::withTrashed()->where('organization_id',$root->id)->where('locale','fa')->where('kind',$group['kind'])
                    ->whereIn('name',$groups[$key]['names'])->where(fn($q)=>$q->where('category_id',$category->id)->orWhereNull('category_id'))
                    ->orderBy('id')->get()->groupBy('name');
                $new=[];$attach=[];$now=now();
                foreach($groups[$key]['names'] as $index=>$name){
                    $member=$existing->get($name)?->first();
                    if($member){
                        if($member->trashed() || !$member->is_active){$result['skipped']++;continue;}
                        if(!$member->category_id)$attach[]=$member->id;
                        $result['existing']++;$result['member_ids'][]=$member->id;
                    }else{
                        $new[]=['organization_id'=>$root->id,'county_id'=>null,'category_id'=>$category->id,
                            'locale'=>'fa','kind'=>$group['kind'],'name'=>$name,'position'=>$group['position'],
                            'priority'=>$index+1,'is_current'=>false,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now];
                        $result['created']++;
                    }
                    $result['groups'][$key]++;
                }
                if($attach)Member::whereIn('id',$attach)->update(['category_id'=>$category->id]);
                if($new){
                    Member::insert($new);
                    $ids=Member::where('organization_id',$root->id)->where('locale','fa')->where('kind',$group['kind'])
                        ->where('category_id',$category->id)->whereIn('name',array_column($new,'name'))->orderBy('priority')->pluck('id')->all();
                    $result['member_ids']=[...$result['member_ids'],...$ids];
                }
            }
            Setting::create(['key'=>self::IMPORT_KEY,'value'=>$result,'is_public'=>false]);
            return [...$result,'already_imported'=>false];
        });
    }

    public function downloadPortrait(): bool
    {
        $path=public_path(self::PHOTO_PATH);
        if(is_file($path))return true;
        try{
            $response=Http::connectTimeout(3)->timeout(8)->withoutRedirecting()->get(self::PHOTO_URL);
            if(!$response->successful())return false;
            $bytes=$response->body();
            if(strlen($bytes)>10*1024*1024)return false;
            $info=@getimagesizefromstring($bytes);
            if(!$info || ($info['mime']??'')!=='image/jpeg')return false;
            File::ensureDirectoryExists(dirname($path));
            File::put($path,$bytes);
            return true;
        }catch(\Throwable){return false;}
    }
}
