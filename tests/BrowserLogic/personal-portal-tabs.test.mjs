import test from 'node:test';
import assert from 'node:assert/strict';
import {personalPortalTabs} from '../../resources/js/portal/features/site/personal-portal-tabs.ts';

test('personal tabs switch locally without navigation, URL changes or network requests', () => {
  const original = global.location;
  const fetch = global.fetch;
  global.location = Object.freeze({href:'https://portal.test/staff/member-722?tab=news',assign:()=>assert.fail('unexpected navigation')});
  global.fetch = () => assert.fail('unexpected request');
  try {
    const tabs = personalPortalTabs('news');
    tabs.select('announcement');
    assert.equal(tabs.tab, 'announcement');
    tabs.select('about');
    assert.equal(tabs.tab, 'about');
    tabs.select('news');
    assert.equal(tabs.tab, 'news');
    assert.equal(global.location.href, 'https://portal.test/staff/member-722?tab=news');
  } finally { global.location = original; global.fetch = fetch; }
});

test('bookmarked tabs initialize correctly and unsupported tabs are ignored', () => {
  const tabs = personalPortalTabs('about');
  assert.equal(tabs.tab, 'about');
  tabs.select('gallery');
  assert.equal(tabs.tab, 'about');
  assert.equal(personalPortalTabs('invalid').tab, 'news');
});

test('RTL keyboard movement wraps and focuses the selected tab', () => {
  const tabs = personalPortalTabs('news');
  let focused;
  tabs.$nextTick = callback => callback();
  tabs.$refs = {tabs:{querySelector:selector=>({focus:()=>{focused=selector;}})}};
  tabs.move(-1);
  assert.equal(tabs.tab, 'about');
  assert.equal(focused, '[data-tab="about"]');
  tabs.move(1);
  assert.equal(tabs.tab, 'news');
  tabs.move(1);
  assert.equal(tabs.tab, 'announcement');
  tabs.select('about', true);
  assert.equal(focused, '[data-tab="about"]');
});
