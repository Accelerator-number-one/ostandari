import shapes from './county-shapes.json';

export type County = {
  slug: string;
  name: string;
  shape: string;
  image: string;
  label: readonly [number, number];
  labelAnchor?: readonly [number, number];
  labelLines?: readonly string[];
  previewImage: string;
  usesPreviewTemplate: boolean;
};

export const countyPreviewTemplate = '/design/site/assets/Img/100.png';

// Small coastal regions use callouts so their names do not cover one another.
const records = [
  ['ramsar', 'رامسر', 0, 45, 53],
  ['tonekabon', 'تنکابن', 1, 87, 105],
  ['abbasabad', 'عباس‌آباد', 2, 139, 56],
  ['chalus', 'چالوس', 3, 191, 75],
  ['noshahr', 'نوشهر', 4, 219, 143],
  ['mahmudabad', 'محمودآباد', 5, 318, 49],
  ['amol', 'آمل', 6, 306, 218],
  ['fereydunkenar', 'فریدونکنار', 7, 364, 22],
  ['babolsar', 'بابلسر', 8, 413, 2],
  ['juybar', 'جویبار', 9, 449, 29],
  ['simorgh', 'سیمرغ', 10, 378, 126],
  ['babol', 'بابل', 11, 380, 178],
  ['savadkuh', 'سوادکوه', 12, 436, 216],
  ['savadkuh-shomali', 'سوادکوه شمالی', 13, 450, 160],
  ['sari', 'ساری', 14, 503, 185],
  ['galugah', 'گلوگاه', 15, 604, 72],
  ['miandorud', 'میاندورود', 16, 505, 43],
  ['qaemshahr', 'قائم‌شهر', 17, 431, 137],
  ['nur', 'نور', 18, 264, 168],
  ['kelardasht', 'کلاردشت', 19, 141, 154],
  ['neka', 'نکا', 20, 548, 134],
  ['behshahr', 'بهشهر', 21, 541, 63],
] as const;

const callouts: Record<string, readonly [number, number]> = {
  abbasabad: [144, 94],
  chalus: [176, 103],
  mahmudabad: [335, 100],
  fereydunkenar: [368, 89],
  babolsar: [393, 91],
  juybar: [431, 89],
  simorgh: [412, 107],
  miandorud: [488, 76],
};

export const counties: County[] = records.map(([slug, name, index, x, y]) => {
  // The legacy Abbasabad entry points to a photo bearing Babol's name.
  const sourceImage =
    slug !== 'abbasabad' && shapes[index].image.startsWith('/design/') ? shapes[index].image : '';
  return {
    slug,
    name,
    shape: shapes[index].d,
    image: sourceImage || countyPreviewTemplate,
    previewImage: sourceImage || countyPreviewTemplate,
    usesPreviewTemplate: !sourceImage,
    label: [x, y],
    labelAnchor: callouts[slug],
    labelLines: slug === 'savadkuh-shomali' ? ['سوادکوه', 'شمالی'] : undefined,
  };
});
const aliases: Record<string, string> = {
  chalous: 'chalus',
  'north-savadkuh': 'savadkuh-shomali',
  mahmoudabad: 'mahmudabad',
  noor: 'nur',
  nour: 'nur',
  gov_tonekabon: 'tonekabon',
  nowshahr: 'noshahr',
  joybar: 'juybar',
  juybar: 'juybar',
  mahmoodabad: 'mahmudabad',
  fereydounkenar: 'fereydunkenar',
  miandorood: 'miandorud',
  ghalugah: 'galugah',
  galoogah: 'galugah',
  ghaemshahr: 'qaemshahr',
};
export function findCounty(value: string | undefined): County | undefined {
  if (!value) return undefined;
  const clean = value.toLowerCase().trim();
  return counties.find(
    (county) => county.slug === (aliases[clean] ?? clean) || county.name === value,
  );
}
export function officeHref(county: Pick<County, 'slug'>, section?: string): string {
  return (
    '/' +
    county.slug +
    (section === 'about' ? '/about' : section ? '/' + encodeURIComponent(section) : '')
  );
}
export function countyQuery(county?: Pick<County, 'slug'>): string {
  return county ? '?name=' + encodeURIComponent(county.slug) : '';
}
