import {api, jsonBody} from '@/lib/api/client';
import {all, one, fieldError} from '@/lib/dom';
import {collectFields, fillFields} from './fields';
import {mountImageFields} from './image-fields';
import {mountRichText, validateRichText} from './rich-text';
import {lookupRecords} from './resources';
import type {RecordData} from '@/types/portal';

export function mountCountyPortal(root: HTMLElement, signal: AbortSignal): void {
  const form=one<HTMLFormElement>(root,'[data-county-portal-form]');
  if(!form)return;
  const fields=one<HTMLFieldSetElement>(form,'[data-county-portal-fields]')!;
  const status=one<HTMLElement>(root,'[data-county-portal-status]')!;
  const save=one<HTMLButtonElement>(form,'button[type=submit]')!;
  const retry=one<HTMLButtonElement>(root,'[data-county-portal-retry]')!;
  const images=mountImageFields(form,signal,false);
  mountRichText(form,signal,false);
  let busy=false,ready=false;
  const lock=()=>{fields.disabled=!ready||busy;save.disabled=!ready||busy;};
  const message=(text:string,failed=false)=>{status.textContent=text;status.setAttribute('role',failed?'alert':'status');status.classList.toggle('is-error',failed);};
  async function load(){
    if(busy)return;busy=true;lock();retry.hidden=true;
    try{
      const [result,files]=await Promise.all([api<RecordData>('admin/county-portal',{signal}),lookupRecords('media',signal,{kind:'image',visibility:'public'})]);
      if(signal.aborted)return;
      for(const select of all<HTMLSelectElement>(form,'[data-image-value]')){
        select.replaceChildren(new Option('بدون تصویر اختصاصی',''));
        for(const file of files){const option=new Option(String(file.original_name??'تصویر'),String(file.id));option.dataset.imageUrl=String(file.url??'');select.add(option);}
      }
      fillFields(form,result.data);ready=true;message('تنظیمات همین فرمانداری را ویرایش کنید.');
    }catch(error){if(!signal.aborted){message(error instanceof Error?error.message:'دریافت تنظیمات انجام نشد.',true);retry.hidden=false;}}
    finally{busy=false;lock();}
  }
  form.addEventListener('submit',event=>{
    event.preventDefault();if(busy||!ready||!form.reportValidity())return;
    void(async()=>{
      busy=true;lock();message('در حال ذخیره…');
      try{
        await images.ready();images.validate();validateRichText(form);
        const result=await api<RecordData>('admin/county-portal',{method:'PUT',body:jsonBody(collectFields(form)),signal});
        if(signal.aborted)return;
        fillFields(form,result.data);message('تغییرات پرتال فرمانداری ذخیره شد.');
      }catch(error){if(!signal.aborted){message(error instanceof Error?error.message:'ذخیره انجام نشد.',true);fieldError(form,error);}}
      finally{busy=false;lock();}
    })();
  },{signal});
  retry.addEventListener('click',()=>void load(),{signal});
  for(const button of all<HTMLButtonElement>(root,'[data-county-prepare-menu]'))button.addEventListener('click',()=>{
    if(button.disabled)return;button.disabled=true;
    void api<{id:number}>('admin/county-portal/menus',{method:'POST',body:jsonBody({group:button.dataset.countyPrepareMenu}),signal})
      .then(result=>{if(!signal.aborted)location.assign(root.dataset.workspaceHome+'/menu-items?menu_id='+result.data.id);})
      .catch(error=>{if(!signal.aborted)message(error.message,true);}).finally(()=>{button.disabled=false;});
  },{signal});
  void load();
}
