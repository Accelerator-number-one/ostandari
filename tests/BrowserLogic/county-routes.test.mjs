import test from 'node:test';
import assert from 'node:assert/strict';
import {canonicalAdminPath,workspaceAdminHref,countyReturn} from '../../resources/js/portal/lib/workspace-routes.ts';

test('county navigation retains its city across module, relation and editor URLs',()=>{
 const home='/county-admin/amol';
 for(const suffix of ['/news','/gallery-items?gallery_id=42','/news/8/edit','/account','/reports/R1/edit']){
  assert.equal(workspaceAdminHref(home,'/panel/admin'+suffix),home+suffix);
  assert.equal(canonicalAdminPath(home+suffix),'/panel/admin'+suffix);
 }
 assert.equal(workspaceAdminHref(home,'/amol/news'),'/amol/news');
 assert.equal(workspaceAdminHref(home,'https://example.test/panel/admin'),'https://example.test/panel/admin');
 assert.equal(workspaceAdminHref('/panel/admin','/panel/admin/news'),'/panel/admin/news');
});

test('county login return cannot send a user to another city, external site or a logout page',()=>{
 const home='/county-admin/amol';
 for(const path of ['/county-admin/nur/news','https://evil.test','//evil.test','/citizen',home+'/login',home+'/logout','/panel/admin/logout','/\\evil.test','\u0000'])
  assert.equal(countyReturn(home,path),home,path);
 assert.equal(countyReturn(home,home+'/news/new?return=%2Fcounty-admin%2Famol%2Fnews'),home+'/news/new?return=%2Fcounty-admin%2Famol%2Fnews');
 assert.equal(countyReturn(home,'/panel/admin/news?q=report'),home+'/news?q=report');
});
