import {api, ApiError, jsonBody} from '@/lib/api/client';
import {mountRichText, validateRichText} from './rich-text';
import {prepareUpload} from './uploads';

const emptyPage=()=>({title:'',path:'',body:'',link_title:'',link_style:'text',link_image_media_id:null as number|null,placements:[] as string[],status:'draft',priority:0});
const endpoint='admin/custom-pages';

export function customPages(){return {
  form:emptyPage(),id:null as number|null,rows:[] as any[],meta:null as any,images:[] as any[],
  busy:false,loading:false,galleryBusy:false,error:'',message:'',errors:{} as Record<string,string[]>,
  q:'',imageQuery:'',trash:false,pageNumber:1,tab:'editor',imageUrl:'',base:'',prefix:'',abort:new AbortController(),
  async init(this:any){
    this.base=this.$el.dataset.adminBase;
    this.prefix=(this.$el.dataset.publicPrefix??'').replace(/\/$/,'');
    const query=new URLSearchParams(location.search);
    this.tab=query.get('tab')==='list'?'list':'editor';
    await this.$nextTick();
    mountRichText(this.$el,this.abort.signal,false);
    this.$watch('form.placements',(slots:string[])=>{if(slots.includes('banner'))this.form.link_style='image';});
    const id=query.get('edit');
    await this.loadList();
    if(id&&/^[1-9]\d*$/.test(id))await this.load(Number(id));
  },
  href(this:any,path:string){return this.prefix+'/'+path.replace(/^\/+|\/+$/g,'');},
  url(this:any){return location.origin+this.href(this.form.path||'new-page');},
  fail(this:any,error:unknown){if(this.abort.signal.aborted)return;this.error=(error as Error).message;this.errors=error instanceof ApiError?error.errors:{};},
  async loadList(this:any,page=1){
    this.loading=true;this.error='';
    try{const query=new URLSearchParams({page:String(page),trashed:this.trash?'1':'0'});if(this.q.trim())query.set('q',this.q.trim());
      const result=await api<any[]>(endpoint+'?'+query,{signal:this.abort.signal});this.rows=result.data;this.meta=result.meta;this.pageNumber=page;
    }catch(e){this.fail(e);}finally{this.loading=false;}
  },
  async load(this:any,id:number){
    this.busy=true;this.error='';
    try{const record=(await api<any>(endpoint+'/'+id,{signal:this.abort.signal})).data;
      this.id=record.id;this.form={...emptyPage(),...record};this.imageUrl=record.link_image?.url??'';this.tab='editor';
      await this.$nextTick();this.$refs.body.dataset.cmsRequired=record.legacy_source_key?'false':'true';
      this.$refs.body.value=record.body??'';this.$refs.body.dispatchEvent(new Event('change'));
    }catch(e){this.fail(e);}finally{this.busy=false;}
  },
  async save(this:any){
    if(this.busy)return;this.error='';this.errors={};this.message='';
    try{validateRichText(this.$el);this.form.body=this.$refs.body.value;
      this.busy=true;
      const fields=['title','path','body','link_title','link_style','link_image_media_id','placements','status','priority'];
      const body=Object.fromEntries(fields.map(key=>[key,this.form[key]]));
      await api(endpoint+(this.id?'/'+this.id:''),{method:this.id?'PATCH':'POST',body:jsonBody(body),signal:this.abort.signal});
      location.assign(this.base+'?tab=list');
    }catch(e){this.fail(e);}finally{this.busy=false;}
  },
  async chooseImage(this:any,event:Event){
    const input=event.target as HTMLInputElement,file=input.files?.[0];if(!file)return;
    this.busy=true;this.error='';
    try{const body=new FormData();body.set('file',await prepareUpload(file));
      const image=(await api<any>(endpoint+'/image',{method:'POST',body,signal:this.abort.signal})).data;
      this.form.link_image_media_id=image.id;this.form.link_style='image';this.imageUrl=image.url;
    }catch(e){this.fail(e);}finally{this.busy=false;input.value='';}
  },
  async loadImages(this:any){
    this.galleryBusy=true;this.error='';
    try{this.images=(await api<any[]>(endpoint+'/images?per_page=60&q='+encodeURIComponent(this.imageQuery),{signal:this.abort.signal})).data;}
    catch(e){this.fail(e);}finally{this.galleryBusy=false;}
  },
  selectImage(this:any,image:any){this.form.link_image_media_id=image.id;this.form.link_style='image';this.imageUrl=image.url;},
  removeImage(this:any){this.form.link_image_media_id=null;this.imageUrl='';},
  async remove(this:any,row:any){
    if(!confirm('صفحه «'+row.title+'» به زباله‌دان منتقل شود؟ پیوندهای آن نیز از سایت برداشته می‌شوند.'))return;
    this.loading=true;
    try{await api(endpoint+'/'+row.id,{method:'DELETE',signal:this.abort.signal});await this.loadList(1);}
    catch(e){this.fail(e);}finally{this.loading=false;}
  },
  async restore(this:any,row:any){this.loading=true;try{await api(endpoint+'/'+row.id+'/restore',{method:'POST',signal:this.abort.signal});await this.loadList(1);}catch(e){this.fail(e);}finally{this.loading=false;}},
  destroy(this:any){this.abort.abort();}
};}
