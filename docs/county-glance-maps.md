# County glance maps

The county home/about pages use the original province locator in their existing
“در یک نگاه” section. It shows Mazandaran's county outlines and highlights the
current county in blue. The province-wide interactive county selector is unchanged.

## Appearance and behavior

- The inline SVG uses the existing county outlines from
  `$ui->data('frontend')['counties']`.
- The current county is selected by its slug, with its Persian name placed at
  its existing label coordinates. No map image or third-party map service is loaded.
- The original `0 -20 650 315` view box, blue/gray colors, responsive sizing and
  300-pixel maximum height are preserved.
- The existing county dashboard section switch still controls visibility.
  No database migration or seeder is needed.

## Files

- `resources/views/site/county-glance-map.blade.php`: the original SVG locator
  and county caption, shared by county home/about pages.
- `resources/data/frontend.json`: county outlines, slugs and label coordinates.
- `resources/css/legacy/county-office.css`: existing map frame and sizing.

## Previous raster exports

The MapToPoster exports in `public/images/county-maps/{county-slug}.webp` and
their source/attribution manifest in `resources/data/county-map-sources.json`
are retained as historical assets. The county locator no longer renders them.
