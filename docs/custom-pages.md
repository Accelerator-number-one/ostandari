# Custom pages

- Province: `/panel/admin/custom-pages`
- County: `/county-admin/{slug}/custom-pages`

«ساخت صفحه جدید» creates independent pages inside the existing site layout. Editors set a relative path, full rich text (including images, albums and tables), publication status and ordering. Links can be text or images and can appear in the services menu, introduction menu, quick access, useful links, footer and home banners. No placement means a published page is accessible only through its URL. Banners require an image.

County paths are automatically prefixed with that county's slug. Ownership comes from the authenticated workspace and cannot be submitted by the browser. The same relative path can exist in different portals. Built-in routes, county names and aliases, assets and authentication prefixes are reserved. Paths remain reserved while a page is in the recoverable trash. Changing a path updates all generated links; drafts, archived and deleted pages have no public links or public page response.

Provincial PR receives a narrow write exception for this builder and its image endpoint. The existing read-only policy for other provincial PR modules is unchanged. The generic content editor does not control custom pages. Uploaded builder images use a separate database-backed image store and remain available after a container replacement; files in use are protected from deletion.

Run `php artisan migrate` on a conventional Laravel host. Vercel runs `php artisan portal:prepare-pages` at startup, applying only `2026_10_25_000003_create_custom_pages_table.php` under a database lock. Existing portal data, users, passwords, menus and old storage files are preserved. No full seed is required.

The former «اطلاعات عمومی» section and its legacy URLs are hidden from public navigation, directories and sitemaps. Its original database content is retained.
