import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = (path) => readFileSync(new URL('../../public/' + path, import.meta.url), 'utf8');

test('PWA install uses the browser prompt once and offers the manual guide if dismissed', async () => {
    const handlers = {};
    const controls = {
        '[data-pwa-install]': { addEventListener: (name, handler) => { handlers[name] = handler; } },
        '[data-pwa-guide]': {},
        '[data-pwa-installed]': {},
    };
    runInNewContext(source('pwa/install.js'), {
        document: { querySelector: () => ({ querySelector: (key) => controls[key] }) },
        window: { matchMedia: () => ({ matches: false }), addEventListener: (name, handler) => { handlers[name] = handler; } },
        navigator: {},
    });
    const install = controls['[data-pwa-install]'], guide = controls['[data-pwa-guide]'];
    assert.equal(install.hidden, true);
    assert.equal(guide.hidden, false);
    let prompts = 0;
    handlers.beforeinstallprompt({ preventDefault() {}, async prompt() { prompts++; return { outcome: 'dismissed' }; } });
    assert.equal(install.hidden, false);
    await handlers.click();
    await handlers.click();
    assert.equal(prompts, 1);
    assert.equal(guide.hidden, false);
    handlers.appinstalled();
    assert.equal(install.hidden, true);
    assert.equal(guide.hidden, true);
    assert.equal(controls['[data-pwa-installed]'].hidden, false);
});

test('only public PWA entry navigations get the offline fallback; HTML is never cached', async () => {
    const origin = 'https://portal.test';
    const offline = new Response('Offline page');
    const online = new Response('Current HTML');
    let disconnected = false;
    let onFetch;
    runInNewContext(source('sw.js'), {
        self: { location: { origin }, addEventListener: (name, handler) => { if (name === 'fetch') onFetch = handler; } },
        URL, Response, fetch: async () => {
            if (disconnected) throw new TypeError('Network unavailable');
            return online;
        },
        caches: { open: async () => ({ match: async (path) => {
            assert.equal(path, '/pwa/offline.html');
            return offline;
        } }) },
    });
    const navigate = (path, options = {}) => {
        let result;
        onFetch({ request: { url: new URL(path, origin).href, method: 'GET', mode: 'navigate', ...options }, respondWith: (value) => { result = value; } });
        return result;
    };
    assert.equal(await navigate('/install'), online);
    disconnected = true;
    assert.equal(await navigate('/'), offline);
    assert.equal(await navigate('/counties'), offline);
    for (const path of ['/citizen', '/panel/admin', '/api/v1/home', 'https://other.test/install']) {
        assert.equal(navigate(path), undefined);
    }
    assert.equal(navigate('/install', { method: 'POST' }), undefined);
    assert.equal(navigate('/install', { mode: 'cors' }), undefined);
});
