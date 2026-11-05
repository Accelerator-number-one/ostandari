import Alpine from 'alpinejs';
import {customPages} from './portal/features/admin/custom-pages';
import {api,jsonBody,upload} from './portal/lib/api/client';
import {dynamicAnswers} from './forms';
import {prepareUpload} from './portal/features/admin/uploads';
import {ticketStatuses,ticketTransitions,ticketUrgencies,ticketTypes,ticketCategories} from './portal/data/ticket-status';
import {bindUi,closeOverlays} from './portal/lib/alpine/ui';
import {citizenDemo} from './portal/features/citizen/demo';
import {adminDestination} from './portal/lib/auth/destinations';
import {mountHomeMotion} from './portal/features/site/home-motion';
import {personalPortalTabs} from './portal/features/site/personal-portal-tabs';

const digits=(s:unknown)=>String(s??'').replace(/[۰-۹]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c))).replace(/[٠-٩]/g,c=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(c)));
const post=(path:string,data:unknown,method='POST')=>api<any>(path,{method,body:jsonBody(data)});
const citizenDestination=()=>{
  const value=new URLSearchParams(location.search).get('return');
  if(!value||!value.startsWith('/')||value.startsWith('//')||value.includes('\\'))return '/citizen';
  const url=new URL(value,location.origin);
  if(url.origin!==location.origin||/^\/(?:panel|admin|session|web-api|api|login|register)(?:\/|$)/.test(url.pathname))return '/citizen';
  return url.pathname+url.search+url.hash;
};

Alpine.data('customPages',customPages);
Alpine.data('personalPortalTabs',personalPortalTabs);

Alpine.data('staffEditor',()=>({
  abort:new AbortController(),
  async init(){
    const {mountRichText,validateRichText}=await import('./portal/features/admin/rich-text');
    if(this.abort.signal.aborted)return;
    mountRichText(this.$el,this.abort.signal,false);
    this.$el.addEventListener('submit',(event:SubmitEvent)=>{
      try{validateRichText(this.$el);}
      catch(error){event.preventDefault();let notice=this.$el.querySelector('[data-staff-form-error]');
        if(!notice){notice=document.createElement('p');notice.className='staff-errors';notice.dataset.staffFormError='';notice.setAttribute('role','alert');this.$el.prepend(notice);}
        notice.textContent=(error as Error).message;notice.scrollIntoView({block:'center'});
      }
    },{signal:this.abort.signal});
  },
  destroy(){this.abort.abort();}
}));

Alpine.data('portalPage',()=>({
  busy:false,mobileOpen:false,abort:new AbortController(),motion:undefined as undefined|(()=>void),
  async init(){
    const page=JSON.parse(document.getElementById('portal-page')?.textContent??'{}');
    const root=this.$el as HTMLElement;
    const context={root,page,path:location.pathname,preview:false,citizenDemo:document.documentElement.dataset.citizenDemo==='true',signal:this.abort.signal};
    let controller:any;
    if(page.controller==='admin') controller=(await import('./portal/features/admin/controller')).adminController(context);
    else if(page.controller==='citizen') controller=(await import('./portal/features/citizen/controller')).citizenController(context);
    else if(page.controller==='site') controller=(await import('./portal/features/site/controller')).siteController(context);
    if(this.abort.signal.aborted)return;
    this.$watch('mobileOpen',(open:boolean)=>{root.querySelector('#mobileMenu')?.classList.toggle('active',open);root.querySelector('#mobileMenu')?.classList.toggle('open',open);root.querySelector('#mmBackdrop')?.classList.toggle('active',open);});
    root.addEventListener('portal:mobile',(event:CustomEvent<boolean>)=>{this.mobileOpen=event.detail;},{signal:this.abort.signal});
    if(controller){bindUi(context,(command,element,args)=>{this.busy=true;Promise.resolve(controller.dispatch(command,element,args)).finally(()=>this.busy=false);});controller.init();}
    if(!page.resource)root.dataset.ready='true';this.motion=mountHomeMotion(root);
  },
  dismiss(){closeOverlays(this.$el);this.mobileOpen=false;},
  destroy(){this.abort.abort();this.motion?.();}
}));

Alpine.data('portalHeader',()=>({
  mobile:false,language:false,mega:false,category:0,timer:undefined as ReturnType<typeof setTimeout>|undefined,
  openMega(){clearTimeout(this.timer);this.mega=true;},
  closeMega(){clearTimeout(this.timer);this.timer=setTimeout(()=>{if(!this.$el.querySelector('.source-mega')?.contains(document.activeElement))this.mega=false;},180);},
  init(){this.$watch('mobile',(value:boolean)=>{document.body.style.overflow=value?'hidden':'';if(value)this.$nextTick(()=>this.$refs.closeMenu?.focus());});},
  dismiss(){this.mobile=false;this.mega=false;this.language=false;document.body.style.overflow='';},
  trap(event:KeyboardEvent){const elements=[...this.$refs.drawer.querySelectorAll('a[href],button')].filter((x:any)=>x.getClientRects().length) as HTMLElement[];const first=elements[0],last=elements.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}},
  destroy(){clearTimeout(this.timer);document.body.style.overflow='';}
}));

Alpine.data('portalCarousel',(count:number)=>({
  index:0,paused:false,hovered:false,focused:false,timer:undefined as ReturnType<typeof setInterval>|undefined,
  init(){if(count>1&&!matchMedia('(prefers-reduced-motion: reduce)').matches)this.timer=setInterval(()=>{if(!this.paused&&!this.hovered&&!this.focused)this.move(1);},5000);},
  move(delta:number){this.index=(this.index+delta+count)%Math.max(count,1);},
  destroy(){clearInterval(this.timer);}
}));

Alpine.data('authForm',(mode:string,countySlug:string,termsVersion:string,termsHash:string)=>({
  mode,countySlug,busy:false,error:'',challenge:'',devCode:'',accepted:false,
  login:'',password:'',name:'',mobile:'',national_code:'',code:'',
  demo:mode!=='staff'&&document.documentElement.dataset.citizenDemo==='true',
  async submit(){
    if(this.busy)return;this.busy=true;this.error='';
    try{
      if(this.mode==='staff'){
        const result=await post('auth/login',{login:digits(this.login).trim(),password:this.password,audience:'staff',...(this.countySlug?{county_slug:this.countySlug}:{})});
        location.assign(adminDestination(result.data.user,new URLSearchParams(location.search).get('return')));return;
      }
      if(this.mode==='register'&&!this.accepted)throw Error('برای ادامهٔ ثبت‌نام، قوانین و مقررات را تأیید کنید.');
      if(this.demo){citizenDemo().signIn({name:this.name,mobile:this.mobile,national_code:this.national_code},this.mode==='register');location.assign(citizenDestination());return;}
      if(this.challenge){await post('auth/otp/verify',{challenge_id:this.challenge,code:digits(this.code)});location.assign(citizenDestination());return;}
      const result=await post(this.mode==='register'?'auth/register':'auth/citizen/login',this.mode==='register'?{name:this.name,mobile:digits(this.mobile),national_code:digits(this.national_code),terms_accepted:this.accepted,terms_version:termsVersion,terms_document_sha256:termsHash}:{mobile:digits(this.mobile)});
      this.challenge=result.data.challenge_id;this.devCode=result.data.dev_code??'';
    }catch(e){this.error=e instanceof Error?e.message:'ورود انجام نشد.';}finally{this.busy=false;}
  }
}));

Alpine.data('apiForm',(endpoint:string,method='POST')=>({
  busy:false,message:'',error:'',answer:'',recoveryLink:'',
  async submit(event:SubmitEvent){
    if(this.busy)return;this.busy=true;this.error='';this.message='';
    try{
      const form=event.target as HTMLFormElement;
      const body:any=Object.fromEntries(new FormData(form));delete body._token;
      for(const key of ['option_id','category_id','organization_id'])if(body[key])body[key]=Number(body[key]);
      if(form.dataset.dynamicForm){for(const key of Object.keys(body))delete body[key];body.answers=await dynamicAnswers(form,async file=>upload(await prepareUpload(file),'private'));}
      const result=await post(endpoint,body,method);
      this.answer=result.data?.answer??'';
      if(result.data?.dev_token)this.recoveryLink='/password?'+new URLSearchParams({email:String(body.email),token:result.data.dev_token});
      this.message=result.data?.reference?'درخواست ثبت شد؛ کد پیگیری: '+result.data.reference:(result.message??'عملیات با موفقیت انجام شد.');
    }catch(e){this.error=e instanceof Error?e.message:'عملیات انجام نشد.';}finally{this.busy=false;}
  }
}));


Alpine.data('eventSubscription',(county:string,unsubscribe=false)=>({
  county,unsubscribe,mobile:'',code:'',challenge:'',devCode:'',consent:false,busy:false,error:'',message:'',
  async submit(){
    if(this.busy)return;this.busy=true;this.error='';this.message='';
    try{
      if(this.challenge){
        const result=await post('events/confirm',{challenge_id:this.challenge,code:digits(this.code)});
        this.message=result.message;this.challenge='';this.code='';this.devCode='';this.consent=false;
      }else{
        const result=await post('events/subscribe',{mobile:digits(this.mobile),county:this.county||null,action:this.unsubscribe?'unsubscribe':'subscribe',consent:this.consent});
        this.challenge=result.data.challenge_id;this.devCode=result.data.dev_code??'';
      }
    }catch(e){this.error=e instanceof Error?e.message:'ثبت درخواست انجام نشد.';}finally{this.busy=false;}
  }
}));

Alpine.data('annualSlogan',()=>({
  busy:false,error:'',message:'',current:'/design/site/assets/image/شعار پ.png',preview:'',file:null as File|null,
  async init(){try{const result=await api<any>('admin/annual-slogan');this.current=typeof result.data==='string'?result.data:(result.data?.url??this.current);}catch(e){this.error=(e as Error).message;}},
  choose(event:Event){const file=(event.target as HTMLInputElement).files?.[0]??null;if(this.preview)URL.revokeObjectURL(this.preview);this.file=file;this.preview=file?URL.createObjectURL(file):'';},
  async save(){if(!this.file||this.busy)return;this.busy=true;this.error='';this.message='';try{const body=new FormData();body.set('file',this.file);const result=await api<any>('admin/annual-slogan',{method:'POST',body});this.current=result.data.url;if(this.preview)URL.revokeObjectURL(this.preview);this.preview='';this.file=null;this.$refs.file.value='';this.message='لوگوی شعار سال ذخیره شد و در استانداری و فرمانداری‌ها نمایش داده می‌شود.';}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}},
  destroy(){if(this.preview)URL.revokeObjectURL(this.preview);}
}));

Alpine.data('siteSettings',()=>({
  busy:false,error:'',message:'',entries:[] as any[],contact:{} as any,files:{} as Record<string,File>,
  contactLabels:{address:'نشانی',phone:'تلفن',fax:'نمابر',email:'ایمیل',postal_code:'کد پستی'},
  async init(){try{const rows=(await api<any[]>('admin/settings')).data;const labels={'site.name':'نام سایت','site.description':'توضیح سایت','site.logo':'لوگوی سایت','site.banner':'تصویر سربرگ سایت','site.slogan':'تصویر شعار سال','site.slogan_text':'متن شعار زیر منو','site.app_url':'نشانی دانلود برنامه','site.app_qr':'تصویر کد QR برنامه'};this.entries=Object.entries(labels).map(([key,label])=>{const value=rows.find(e=>e.key===key)?.value;return {key,label,editing:typeof value==='string'?value:(value?.url??''),image:['site.logo','site.banner','site.slogan','site.app_qr'].includes(key)};});this.contact=rows.find(e=>e.key==='site.contact')?.value??{};}catch(e){this.error=(e as Error).message;}},
  async save(){if(this.busy)return;this.busy=true;this.error='';this.message='';try{
    const settings=[];
    for(const entry of this.entries){const file=this.files[entry.key];if(file){const body=new FormData();body.set('file',await prepareUpload(file));body.set('visibility','public');const result=await api<any>(entry.key==='site.slogan'?'admin/annual-slogan':'media',{method:'POST',body});entry.editing=result.data.url;}settings.push({key:entry.key,value:entry.editing,is_public:true});}
    settings.push({key:'site.contact',value:this.contact,is_public:true});await post('admin/settings',{settings},'PUT');this.files={};this.$el.querySelectorAll('input[type=file]').forEach(e=>e.value='');this.message='تنظیمات ذخیره شد و در سایت قابل مشاهده است.';
  }catch(e){this.error=(e as Error).message;}finally{this.busy=false;}}
}));

Alpine.data('accountForm',()=>({
  busy:false,error:'',message:'',name:'',email:'',current_password:'',password:'',password_confirmation:'',
  async init(){try{const u=(await api<any>('auth/me')).data;this.name=u.name;this.email=u.email??'';}catch(e){this.error=(e as Error).message;}},
  async saveProfile(){this.busy=true;try{await post('auth/profile',{name:this.name,email:this.email||null},'PATCH');this.message='مشخصات ذخیره شد.';}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}},
  async savePassword(){this.busy=true;try{await post('auth/password',{current_password:this.current_password,password:this.password,password_confirmation:this.password_confirmation},'PUT');await post('auth/logout',{});location.assign(document.querySelector<HTMLElement>('[data-workspace-login]')?.dataset.workspaceLogin??'/panel/admin/login');}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}}
}));

Alpine.data('reportsPanel',()=>({
  busy:false,error:'',message:'',rows:[] as any[],meta:null as any,summary:{} as any,options:{organizations:[],counties:[],assignees:[]} as any,
  statuses:ticketStatuses,urgencies:ticketUrgencies,types:ticketTypes,categories:ticketCategories,
  reference:'',detail:null as any,status:'',urgency:'',organization_id:'',assigned_to:'',due_at:'',note:'',reply:'',is_internal:false,page:1,
  get allowedStatuses(){return this.detail?[this.detail.status,...(ticketTransitions[this.detail.status]??[])]:[];},
  get availableAssignees(){return this.options.assignees.filter(a=>!a.organization_ids.length||a.organization_ids.includes(Number(this.organization_id)));},
  date(value:string){return value?new Date(value).toLocaleString('fa-IR',{timeZone:'Asia/Tehran',dateStyle:'medium',timeStyle:'short'}):'—';},
  download(id:number){return '/web-api/v1/media/'+id+'/download';},
  async init(){this.reference=this.$el.dataset.reference??'';await this.load();},
  async load(page=this.page){this.page=page;this.busy=true;this.error='';try{
    this.options=(await api<any>('admin/reports/options')).data;
    if(this.reference){this.detail=(await api<any>('admin/reports/'+encodeURIComponent(this.reference))).data;for(const key of ['status','urgency','organization_id','assigned_to'])this[key]=this.detail[key]??'';this.due_at=this.detail.due_at?.slice(0,16)??'';}
    else{const form=this.$refs.filters as HTMLFormElement|undefined;const params=form?new URLSearchParams(new FormData(form) as any):new URLSearchParams(location.search);for(const[k,v]of [...params])if(!v)params.delete(k);params.set('page',String(page));const result=await api<any[]>('admin/reports?'+params);this.rows=result.data;this.meta=result.meta;this.summary=result.summary??{};}
  }catch(e){this.error=(e as Error).message;}finally{this.busy=false;}},
  async update(){this.busy=true;this.error='';this.message='';try{await post('admin/reports/'+encodeURIComponent(this.reference),{status:this.status,urgency:this.urgency,organization_id:this.organization_id?Number(this.organization_id):null,assigned_to:this.assigned_to?Number(this.assigned_to):null,due_at:this.due_at||null,note:this.note||null},'PATCH');this.message='وضعیت گزارش ذخیره شد.';this.note='';await this.load();}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}},
  async send(){if(!this.reply.trim())return;this.busy=true;this.error='';try{const media_ids=[];for(const file of this.$refs.attachments?.files??[])media_ids.push(await upload(await prepareUpload(file),'private'));await post('admin/reports/'+encodeURIComponent(this.reference)+'/messages',{body:this.reply,is_internal:this.is_internal,media_ids});this.reply='';this.$refs.attachments.value='';await this.load();this.message='پاسخ ثبت شد.';}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}}
}));

Alpine.data('auditLog',()=>({rows:[] as any[],meta:null as any,busy:false,error:'',page:1,action:'',user_id:'',
  async init(){await this.load();},
  async load(page=1){this.busy=true;this.page=page;this.error='';try{const q=new URLSearchParams({page:String(page),per_page:'30'});if(this.action)q.set('action',this.action);if(this.user_id)q.set('user_id',this.user_id);const result=await api<any[]>('admin/audit-logs?'+q);this.rows=result.data;this.meta=result.meta;}catch(e){this.error=(e as Error).message;}finally{this.busy=false;}}
}));
Alpine.start();
