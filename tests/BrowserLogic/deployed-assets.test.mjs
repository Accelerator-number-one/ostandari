import test from 'node:test';
import assert from 'node:assert/strict';
import {existsSync, readdirSync, readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';

test('previously deployed pages retain their CSS and JavaScript asset URLs after a new build', () => {
  const fixtures = new URL('../Fixtures/deployed-builds/', import.meta.url);
  const output = new URL('../../public/build/', import.meta.url);
  const files = new Set();
  for (const name of readdirSync(fixtures)) {
    const manifest = JSON.parse(readFileSync(new URL(name, fixtures), 'utf8'));
    for (const entry of Object.values(manifest)) {
      files.add(entry.file);
      for (const css of entry.css ?? []) files.add(css);
    }
  }
  assert.ok(files.size > 10, 'Check real previously deployed manifests, including lazy chunks.');
  for (const file of files) {
    const path = new URL(file, output);
    assert.ok(existsSync(path), `A cached page would request a missing asset: ${file}`);
    const content = readFileSync(path, 'utf8');
    assert.ok(content.length > 0, fileURLToPath(path));
    assert.doesNotMatch(content, /^\s*<!doctype html/i, `Asset resolves to an error page: ${file}`);
  }
});
