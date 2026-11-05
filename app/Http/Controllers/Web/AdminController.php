<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\SystemController;
use App\Services\PortalView;
use App\Services\ServiceAgreementPages;
use App\Services\Workspace;
use Illuminate\Http\Request;

final class AdminController
{
    public function county(Request $request, string $slug, PortalView $ui, ?string $path = null)
    {
        $county=$ui->county($slug);$context=Workspace::context($request->user());
        abort_unless($county,404);
        Workspace::assertCounty($request->user(), $county['slug']);
        $suffix=$path?'/'.trim($path,'/'):'';
        if($slug!==$county['slug'])return redirect('/county-admin/'.$county['slug'].$suffix.($request->getQueryString()?'?'.$request->getQueryString():''));
        return $this->render($request, $ui, '/county-admin/'.$county['slug'].$suffix, '/panel/admin'.$suffix);
    }

    public function show(Request $request, PortalView $ui, ?string $path=null)
    {
        $full='/panel/admin'.($path?'/'.trim($path,'/'):'');
        if (Workspace::context($request->user())['type']==='county') {
            Workspace::assertCounty($request->user());
            return redirect($ui->adminHref($full,Workspace::context($request->user())).($request->getQueryString()?'?'.$request->getQueryString():''));
        }
        if ($full==='/panel/admin/organization-contact/new') return redirect('/panel/admin/organization-contact');
        return $this->render($request,$ui,$full,$full);
    }

    private function render(Request $request, PortalView $ui, string $path, string $modulePath)
    {
        $user=$request->user();$context=Workspace::context($user);$meta=$ui->data('frontend');
        $module=null;$mode=null;$id=null;$reference=null;
        if(preg_match('~^(/panel/admin/reports)/([^/]+)/edit$~',$modulePath,$report)){ $modulePath=$report[1];$reference=$report[2]; }
        elseif(preg_match('~^(.+)/(new)$~',$modulePath,$editor)){ $modulePath=$editor[1];$mode='create'; }
        elseif(preg_match('~^(.+)/([^/]+)/edit$~',$modulePath,$editor)){ $modulePath=$editor[1];$id=$editor[2];$mode='edit'; }
        $available=[...$meta['modules'], ...ServiceAgreementPages::modules(), \App\Services\CustomPages::module(), \App\Services\ManagerPages::module()];
        if($context['type']==='county')$available[]=['path'=>'/panel/admin/portal','title'=>'ظاهر و اطلاعات پرتال','description'=>'معرفی شهرستان، تصاویر، اطلاعات تماس، منوها و نمایش بخش‌ها','section'=>'appearance','resource'=>'','permission'=>'organizations.manage','showInNavigation'=>true,'canCreate'=>false,'canEdit'=>true];
        $module=collect($available)->firstWhere('path',$modulePath);
        if ($modulePath === '/panel/admin/governors' && $module) {
            array_splice($module['columns'], 4, 0, [['key' => 'term_period', 'label' => 'دوره تصدی (شمسی)']]);
        }
        if($module) abort_unless($ui->moduleAllowed($module,$user),403);
        if ($modulePath === '/panel/admin/custom-pages') $context['can_write'] = true;
        if (\App\Services\ManagerPages::mayManage($user)) {
            if (in_array($modulePath, ['/panel/admin/members', '/panel/admin/county-governors', '/panel/admin/links', '/panel/admin/media'], true)
                || $modulePath === '/panel/admin/pages' && $id && \App\Services\ManagerPages::ownsPage($user, (int) $id)) {
                $context['can_write'] = true;
            }
        }
        if (!empty($module['manager_directory_page'])) {
            $rootId = \App\Models\Organization::active()->where('type', 'governorate')->orderBy('id')->value('id');
            $record = \App\Services\ManagerPages::page($rootId, false);
            abort_unless($record, 503, 'ابتدا دستور portal:prepare-manager-pages را اجرا کنید.');
            \App\Services\Access::organization($user, $record->organization_id);
            return redirect('/panel/admin/pages/'.$record->id.'/edit');
        }
        if (isset($module['service_agreement'])) {
            $rootId = \App\Models\Organization::active()->where('type','governorate')->orderBy('id')->value('id');
            abort_unless($rootId, 503, 'اطلاعات استانداری آماده نیست.');
            $record = ServiceAgreementPages::query($module['service_agreement'], $rootId)->first();
            abort_unless($record, 503, 'متن این صفحه هنوز وارد نشده است. دستور portal:import-service-agreements را اجرا کنید.');
            \App\Services\Access::organization($user, $record->organization_id);
            return redirect('/panel/admin/pages/'.$record->id.'/edit');
        }
        if($module && $context['type']==='county')$module=$this->countyModule($module,$ui,$context['county']);
        $utility=['/panel/admin/account'=>'حساب کاربری','/panel/admin/logout'=>'خروج از مدیریت'];
        abort_unless($module || $modulePath==='/panel/admin' || isset($utility[$modulePath]),404);
        if($mode){
            abort_unless(!empty($module['page']),404);
            if(!$context['can_write'])return redirect($ui->adminHref($modulePath,$context));
            abort_unless($mode==='create'?$module['canCreate']:$module['canEdit'],403);
            abort_if($mode==='create'&&!$context['is_global']&&in_array($module['resource'],['organizations','counties'],true),403);
        }
        $base=collect($meta['templates'])->firstWhere('path','/panel/admin');
        $page=$module['page'] ?? $base;
        $page=[...$page,'path'=>$path,'controller'=>'admin'];
        if($mode)$page['editor']=['mode'=>$mode,'id'=>$id,'returnTo'=>$ui->adminHref($modulePath,$context)];
        $title=$module['title'] ?? $utility[$modulePath] ?? ($context['type']==='county'?'داشبورد فرمانداری '.$context['county']['name']:'داشبورد استانداری');
        if($context['type']==='county')$title=$ui->countyTitle($title,$context['county']);
        $content=$mode?'editor':($reference?'reports':($modulePath==='/panel/admin'?'dashboard':(!empty($module['page'])?'list':'utility')));
        if($modulePath==='/panel/admin/reports')$content='reports';
        if($modulePath==='/panel/admin/portal')$content='county-portal';
        if($modulePath==='/panel/admin/custom-pages')$content='custom-pages';
        $stats=null;
        if($content==='dashboard'){abort_unless($user->hasPermission('dashboard.view'),403);$stats=app(SystemController::class)->dashboard($request)->getData(true)['data'];}
        $moduleUrl=$ui->adminHref($modulePath,$context);
        $modules=array_values(array_map(function($entry)use($ui,$context){
            $entry['canonical_path']=$entry['path'];$entry['path']=$ui->adminHref($entry['path'],$context);
            if($context['type']==='county'){
                $entry=$this->countyModule($entry,$ui,$context['county']);
            }
            return $entry;
        },array_filter($available,fn($m)=>$ui->moduleAllowed($m,$user))));
        return view('admin.page',compact('page','path','modulePath','moduleUrl','module','mode','id','reference','title','content','context','stats','modules')+['user'=>$user]);
    }

    private function countyModule(array $module, PortalView $ui, array $county): array
    {
        $label=fn($text)=>str_replace(['نام استان یا فرمانداری','استان یا فرمانداری','استان و فرمانداری‌ها'],['نام فرمانداری','فرمانداری','فرمانداری‌ها'],$ui->countyTitle($text,$county));
        $path=$module['canonical_path']??$module['path'];
        $module['title']=match($path){
            '/panel/admin/county-governors'=>'فرماندار',
            '/panel/admin/organizations'=>'اطلاعات فرمانداری',
            '/panel/admin/organization-contact'=>'اطلاعات تماس',
            default=>$label($module['title']),
        };
        $module['description']='';
        if(isset($module['itemName']))$module['itemName']=$label($module['itemName']);
        if(isset($module['columns']))$module['columns']=array_map(fn($column)=>[...$column,'label'=>$label($column['label'])],$module['columns']);
        return $module;
    }
}
