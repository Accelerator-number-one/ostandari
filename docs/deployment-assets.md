# Keep deployed asset URLs available

A page opened during a deployment can retain the previous Vite manifest's CSS/JavaScript URLs. Deleting those hashed files made `/news` load without its main stylesheet when the Vercel alias switched releases. An old CSS URL returned the Laravel 404 page instead of CSS.

The build now uses `emptyOutDir: false`. Public assets from the deployed versions included in this refresh are retained, including their lazy JavaScript chunks. This repository tracks `public/build`; keep those generated files in each release so the next container also ships the earlier hashes:

```sh
npm ci
npm run build
npm test
git add resources public/build vite.config.js
```

Commit the generated assets with the corresponding source changes. Do not clear `public/build/assets` as part of deployment. `deployed-assets.test.mjs` checks the real earlier manifests after a build and fails if their referenced assets disappear. Removing old versions should be a separate maintenance decision after their cached pages are no longer in use.
