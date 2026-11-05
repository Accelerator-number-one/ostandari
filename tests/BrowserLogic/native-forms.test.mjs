import test from 'node:test';
import assert from 'node:assert/strict';
import {api,ApiError} from '../../resources/js/portal/lib/api/client.ts';
import {dynamicAnswers} from '../../resources/js/forms.ts';

test('native requests use CSRF sessions and local session login',async()=>{
 global.document={querySelector:()=>({content:'fixture-csrf'})};
 let sent;
 global.fetch=async(url,options)=>{sent={url,options};return new Response(JSON.stringify({success:true,data:{id:1}}));};
 await api('auth/login',{method:'POST',body:JSON.stringify({login:'fixture'})});
 assert.equal(sent.url,'/session/login');assert.equal(sent.options.credentials,'same-origin');
 assert.equal(sent.options.headers.get('X-CSRF-TOKEN'),'fixture-csrf');assert.equal(sent.options.headers.get('Authorization'),null);
 await api('admin/annual-slogan');assert.equal(sent.url,'/web-api/v1/admin/annual-slogan');
});
test('validation and expired-session errors remain actionable',async()=>{
 global.fetch=async()=>new Response(JSON.stringify({message:'Invalid',errors:{title:['عنوان الزامی است.']}}),{status:422});
 await assert.rejects(api('admin/contents'),e=>e instanceof ApiError&&e.status===422&&e.message==='عنوان الزامی است.');
 global.fetch=async()=>new Response('<html>login</html>',{status:419});
 await assert.rejects(api('admin/contents'),e=>e instanceof ApiError&&e.status===419&&e.code==='INVALID_RESPONSE');
});
test('form answers preserve optional fields, multiple selections and private file IDs',async()=>{
 const fields=[
  {dataset:{answerKey:'optional',answerType:'select'},value:''},
  {dataset:{answerKey:'count',answerType:'number'},value:'0'},
  {dataset:{answerKey:'choices',answerType:'multi_select'},selectedOptions:[{value:'الف'},{value:'ب'}]},
  {dataset:{answerKey:'consent',answerType:'boolean'},checked:false},
  {dataset:{answerKey:'empty_file',answerType:'file'},files:[]},
  {dataset:{answerKey:'attachment',answerType:'file'},files:[{size:100,name:'fixture.pdf'}]},
 ];
 const answers=await dynamicAnswers({querySelectorAll:()=>fields},async file=>{assert.equal(file.name,'fixture.pdf');return 42;});
 assert.deepEqual(answers,{optional:null,count:0,choices:['الف','ب'],consent:false,empty_file:null,attachment:42});
});
