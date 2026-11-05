# Unit duties

Public directory: `/duties`. Each published unit has its own `/duties/{id}` page. Imported legacy `/section/menu-*` links resolve to the same record.

Management: `/panel/admin/duties`; county management: `/county-admin/{slug}/duties`. The existing content permissions and province/county boundaries are preserved. Editors can add a unit, choose or type a group, edit the full rich text, set publication dates and ordering, archive or delete it. Groups are shown together on the public directory.

`php artisan portal:import-unit-duties` imports the checked-in official snapshot. Vercel runs this command in the background at startup. It never runs a full seed or modifies accounts. Initial synthetic source-reference pages retain their IDs. Existing editor text and publication state are preserved. Re-running the same import does not undo later edits, drafts or deletions.

Source: https://ostan-mz.ir/ — checked 2026-10-26. Direct access returned HTTP 504. Complete indexed copies of the official pages were accepted; incomplete extracts were excluded. The directory contains 24 source titles in 6 groups. **11 complete texts are available; 13 source texts could not be retrieved completely.** Unavailable texts are not published as fabricated content. The management list reports the missing titles and provides a prefilled create form.

The source snapshot records original text, source URL, original publication date, retrieval method and SHA-256. The full source body is formatted into paragraphs and lists; navigation and footer text are excluded.

## Missing official texts

- معاون هماهنگی امور اقتصادی — MenuId=52
- معاونت توسعه مدیریت و منابع انسانی — MenuId=190
- معاونت هماهنگی امور عمرانی — MenuId=51
- دفتر استاندار و روابط عمومی و بین الملل — MenuId=43
- دفتر ، مدیریت عملکرد، بازرسی و امور حقوقی — MenuId=45
- دفتر برنامه ریزی ، نوسازی وتحول اداری — MenuId=37
- مدیریت فناوری اطلاعات ، امنیت فضای مجازی و شبکه دولت — MenuId=38
- اداره کل امور اجتماعی و فرهنگی — MenuId=23
- اداره کل امور امنیتی و انتظامی — MenuId=34
- دفتر جذب و حمایت از سرمایه گذاری — MenuId=40
- دفتر هماهنگی امور اقتصادی — MenuId=41
- اداره کل مدیریت بحران — MenuId=31
- دفتر فنی ،امور عمرانی و حمل ونقل و ترافیک — MenuId=32
