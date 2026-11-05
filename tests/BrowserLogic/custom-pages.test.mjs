import test from 'node:test';
import assert from 'node:assert/strict';
import {registerHooks} from 'node:module';

const portal=new URL('../../resources/js/portal/',import.meta.url);
registerHooks({resolve(specifier,context,next){
  if(specifier.startsWith('@/'))return next(new URL(specifier.slice(2)+'.ts',portal).href,context);
  if(context.parentURL?.startsWith(portal.href)&&specifier.startsWith('.')&&!/\.[a-z]+$/i.test(specifier))
    return next(new URL(specifier+'.ts',context.parentURL).href,context);
  return next(specifier,context);
}});
const {customPages}=await import('../../resources/js/portal/features/admin/custom-pages.ts');

function component(){
  const c=customPages();let changed=0;
  c.$nextTick=()=>Promise.resolve();
  c.$el={querySelector:()=>null,querySelectorAll:()=>[]};
  c.$refs={body:{value:'',dataset:{},dispatchEvent:()=>{changed++;}}};
  c.base='/county-admin/amol/custom-pages';c.prefix='/amol';
  global.document={querySelector:()=>({content:'csrf-fixture'})};
  return {c,changes:()=>changed};
}
test('editing a saved page loads its rich-text source and saves the edited body without owner fields',async()=>{
  const {c,changes}=component();
  const record={id:42,organization_id:3,author_id:9,title:'Existing page',path:'/public-page',body:'<p>Existing rich text</p>',link_style:'text',placements:['footer'],status:'published'};
  let sent,redirect;
  global.location={origin:'https://portal.test',assign:url=>{redirect=url;}};
  global.fetch=async(url,options)=>{sent={url,options};return new Response(JSON.stringify({success:true,data:record}));};
  await c.load(42);
  assert.equal(c.$refs.body.value,record.body);assert.equal(changes(),1);assert.equal(c.id,42);assert.equal(c.busy,false);
  c.$refs.body.value='<p>Edited in the visual editor</p>';
  await c.save();
  assert.equal(sent.url,'/web-api/v1/admin/custom-pages/42');assert.equal(sent.options.method,'PATCH');
  const body=JSON.parse(sent.options.body);
  assert.equal(body.body,'<p>Edited in the visual editor</p>');assert.equal('organization_id' in body,false);assert.equal('author_id' in body,false);assert.equal('id' in body,false);
  assert.equal(redirect,'/county-admin/amol/custom-pages?tab=list');
  assert.equal(c.href('/public-page'),'/amol/public-page');
});
test('an imported blank page remains editable without sending its source or ownership fields',async()=>{
  const {c}=component();let sent;
  const record={id:43,title:'خدمات الکترونیکی',path:'/imported-pages/example',body:'',legacy_source_key:'source-key',
    legacy_source_url:'https://ostan-mz.ir/example',status:'published',placements:[],link_style:'text'};
  global.location={origin:'https://portal.test',assign:()=>{}};
  global.fetch=async(url,options)=>{sent={url,options};return new Response(JSON.stringify({success:true,data:record}));};
  await c.load(43);
  assert.equal(c.$refs.body.dataset.cmsRequired,'false');
  await c.save();
  const payload=JSON.parse(sent.options.body);
  assert.equal(payload.body,'');assert.equal('legacy_source_key' in payload,false);assert.equal('legacy_source_url' in payload,false);
});
test('a failed save retains the rich-text input and exposes field validation instead of redirecting',async()=>{
  const {c}=component();let redirected=false;
  global.location={origin:'https://portal.test',assign:()=>{redirected=true;}};
  c.form.title='Draft title';c.form.path='/duplicate';c.$refs.body.value='<p>Do not lose this text</p>';
  global.fetch=async()=>new Response(JSON.stringify({message:'Invalid',errors:{path:['این مسیر قبلاً استفاده شده است.']}}),{status:422});
  await c.save();
  assert.equal(redirected,false);assert.equal(c.busy,false);assert.equal(c.$refs.body.value,'<p>Do not lose this text</p>');
  assert.deepEqual(c.errors.path,['این مسیر قبلاً استفاده شده است.']);
});
