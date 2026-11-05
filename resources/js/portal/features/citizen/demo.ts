import type { ApiResult, PortalUser, RecordData } from '@/types/portal';
import { counties } from '@/data/counties';

const DATA_KEY = 'ostandari:citizen-demo:v1';
const SESSION_KEY = 'ostandari:citizen-demo:active:v1';
type Store = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;
type Account = { key: string; user: PortalUser; tickets: RecordData[]; vote?: number };
type Database = { version: 1; accounts: Account[] };
const normalize = (value: string) =>
  value
    .trim()
    .replace(/[۰-۹]/g, (c) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c)))
    .replace(/[٠-٩]/g, (c) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(c)));
const samplePoll = {
  id: 1,
  question: 'نمونهٔ آزمایشی: کدام خدمت برای شما کاربردی‌تر است؟',
  options: [
    { id: 1, title: 'پیگیری درخواست‌ها', percentage: 50 },
    { id: 2, title: 'اخبار استان', percentage: 30 },
    { id: 3, title: 'ارتباط با مسئولان', percentage: 20 },
  ],
};

/** This transport never sends requests or mints a server session. All records are browser-local. */
export function createCitizenDemo(storage: Store, session: Store) {
  function read(): Database {
    const raw = storage.getItem(DATA_KEY);
    if (!raw) return { version: 1, accounts: [] };
    try {
      const value = JSON.parse(raw) as Database;
      if (
        value.version !== 1 ||
        !Array.isArray(value.accounts) ||
        value.accounts.some(
          (account) =>
            !account ||
            typeof account.key !== 'string' ||
            !account.user ||
            typeof account.user.id !== 'string' ||
            !account.user.id.startsWith('demo-') ||
            account.user.person_type !== 'citizen' ||
            !Array.isArray(account.tickets),
        )
      )
        throw new Error();
      return value;
    } catch {
      throw new Error(
        'داده‌های آزمایشی این مرورگر قابل خواندن نیست. دادهٔ سایت را پاک کنید و دوباره وارد شوید.',
      );
    }
  }
  function write(database: Database) {
    try {
      storage.setItem(DATA_KEY, JSON.stringify(database));
    } catch {
      throw new Error(
        'ذخیرهٔ دادهٔ آزمایشی در مرورگر ممکن نیست؛ فضای ذخیره‌سازی یا تنظیمات مرورگر را بررسی کنید.',
      );
    }
  }
  function account(database: Database) {
    const current = database.accounts.find((item) => item.user.id === session.getItem(SESSION_KEY));
    if (!current)
      throw new Error('برای ورود به محیط آزمایشی، ابتدا از صفحهٔ ورود یا ثبت‌نام استفاده کنید.');
    return current;
  }
  function signIn(
    identity: { name?: string; mobile?: string; national_code?: string },
    registration = false,
  ): PortalUser {
    const database = read();
    const key = normalize(identity.mobile ?? '') || 'anonymous';
    let selected = database.accounts.find((item) => item.key === key);
    if (!selected) {
      if (database.accounts.length >= 50)
        throw new Error('ظرفیت حساب‌های آزمایشی این مرورگر تکمیل شده است.');
      selected = {
        key,
        user: {
          id: 'demo-' + crypto.randomUUID(),
          name: identity.name?.trim() || 'شهروند آزمایشی',
          mobile: identity.mobile?.trim() || 'آزمایشی',
          national_code: identity.national_code?.trim() || '',
          person_type: 'citizen',
          is_active: true,
          role: { name: 'citizen', permissions: [] },
        },
        tickets: [],
      };
      database.accounts.push(selected);
    } else if (registration) {
      selected.user.name = identity.name?.trim() || selected.user.name;
      selected.user.national_code = identity.national_code?.trim() || selected.user.national_code;
    }
    write(database);
    session.setItem(SESSION_KEY, String(selected.user.id));
    return structuredClone(selected.user);
  }
  async function request<T>(path: string, options: RequestInit = {}): Promise<ApiResult<T>> {
    if (options.signal?.aborted) throw new DOMException('Aborted', 'AbortError');
    const database = read();
    const selected = account(database);
    const method = options.method ?? 'GET';
    const route = path.split('?')[0];
    const data = typeof options.body === 'string' ? (JSON.parse(options.body) as RecordData) : {};
    const ok = (value: unknown): ApiResult<T> => ({
      success: true,
      data: structuredClone(value) as T,
    });
    if (route === 'auth/me' && method === 'GET') return ok(selected.user);
    if (route === 'auth/logout' && method === 'POST') {
      session.removeItem(SESSION_KEY);
      return ok(null);
    }
    if (route === 'auth/profile' && method === 'PATCH') {
      selected.user.name = String(data.name ?? selected.user.name).trim() || 'شهروند آزمایشی';
      selected.user.address = String(data.address ?? '');
      selected.user.county_id = data.county_id ? Number(data.county_id) : undefined;
      write(database);
      return ok(selected.user);
    }
    if (route === 'citizen/tickets' && method === 'GET') return ok(selected.tickets);
    if (route.startsWith('citizen/tickets/') && method === 'GET') {
      const ticket = selected.tickets.find((item) => item.reference === route.split('/')[2]);
      if (!ticket) throw new Error('درخواست آزمایشی پیدا نشد.');
      return ok(ticket);
    }
    if (route === 'citizen/tickets' && method === 'POST') {
      if (!String(data.subject ?? '').trim() || !String(data.body ?? '').trim())
        throw new Error('عنوان و متن درخواست را وارد کنید.');
      if (selected.tickets.length >= 100)
        throw new Error('ظرفیت درخواست‌های آزمایشی این حساب تکمیل شده است.');
      const created = new Date().toISOString();
      const reference = 'DEMO-' + crypto.randomUUID().slice(0, 8).toUpperCase();
      const ticket = {
        ...data,
        id: reference,
        reference,
        user_id: selected.user.id,
        status: 'pending',
        created_at: created,
        events: [{ to_status: 'pending', created_at: created }],
        messages: [{ body: data.body }],
      };
      selected.tickets.unshift(ticket);
      write(database);
      return ok(ticket);
    }
    if (route === 'citizen/dashboard' && method === 'GET')
      return ok({ unread_notifications: 0, latest_ticket: selected.tickets[0] ?? null });
    if (route === 'counties' && method === 'GET')
      return ok(counties.map((county, index) => ({ id: index + 1, name: county.name })));
    if (route === 'polls' && method === 'GET') return ok([{ ...samplePoll, status: 'active' }]);
    if (route === 'polls/1' && method === 'GET')
      return ok({ ...samplePoll, selected_option_id: selected.vote });
    if (route === 'polls/1/vote' && method === 'POST') {
      const option = Number(data.option_id);
      if (!samplePoll.options.some((item) => item.id === option))
        throw new Error('گزینه معتبر نیست.');
      selected.vote = option;
      write(database);
      return ok(samplePoll);
    }
    if (route === 'news' && method === 'GET')
      return ok([
        {
          id: 'demo-news',
          title: 'اخبار محیط آزمایشی',
          excerpt: 'در این حالت، اطلاعات واقعی حساب‌های شهروندان بارگذاری نمی‌شود.',
          published_at: new Date().toISOString(),
        },
      ]);
    if (route === 'faqs' && method === 'GET')
      return ok([
        {
          id: 1,
          question: 'اطلاعات آزمایشی کجا ذخیره می‌شود؟',
          answer: 'فقط در همین مرورگر؛ این درخواست‌ها برای سازمان ارسال نمی‌شوند.',
        },
      ]);
    if (route === 'organizations' && method === 'GET')
      return ok([{ id: 1, name: 'دستگاه نمونهٔ آزمایشی', phone: '—' }]);
    throw new Error('این عملیات در محیط آزمایشی پشتیبانی نمی‌شود.');
  }
  return { signIn, api: request };
}

export const citizenDemo = () => createCitizenDemo(window.localStorage, window.sessionStorage);
