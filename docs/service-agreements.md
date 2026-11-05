# Service-agreement pages

The provincial dashboard has two entries under «خدمات و راهنما»:

- `/panel/admin/letter-tracking` — «رهگیری نامه‌ها»
- `/panel/admin/government-network-support` — «پشتیبانی شبکه دولت»

Each opens the existing full-text page editor for its database record. The public addresses remain `/section/menu-177` and `/section/menu-176`. Changing a title or slug does not change those addresses. Publication status, scheduling, expiry and existing account permissions still apply; county staff cannot manage the provincial records.

`php artisan portal:import-service-agreements` installs the supplied text once. Vercel runs this targeted command in the background at startup. An active governorate and one active staff account attached to it must already exist. It updates only these two records, keeps their IDs and attachments, and stores their previous text in a private import marker. Conflicting ownership or deleted records abort the transaction. Re-running it preserves subsequent CMS edits and deletions. No migration, account changes or full database seeding is needed.

The supplied tracking text's reference to Lorestan was corrected to Mazandaran; spelling and spacing were normalized in both texts.
