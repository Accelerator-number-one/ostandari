import { citizenDemo } from './demo';
import { api, ApiError, jsonBody, upload } from '@/lib/api/client';
import { all, date, digits, feedback, one, text, value } from '@/lib/dom';
import { previewReports, renderCategories, renderReports } from './render';
import { bindAttachments } from './attachments';
import { loadCitizenCatalog, renderPoll } from './data';
import type { PageContext, PortalUser, RecordData } from '@/types/portal';

export function citizenController(context: PageContext) {
  const { root, signal, preview } = context;
  const demo = context.citizenDemo === true;
  const requestApi = demo ? citizenDemo().api : api;
  let challenge = '';
  let user: PortalUser | null = null;
  let reports: RecordData[] = [];
  let filter = 'all';
  let busy = false;
  let category = '';
  let urgency = 'medium';
  let poll: RecordData | null = null;
  let attachments: ReturnType<typeof bindAttachments> | undefined;
  let voting = false;
  const uploaded = new WeakMap<File, number>();
  let timer: ReturnType<typeof setInterval> | undefined;
  const post = <T>(path: string, data: unknown) =>
    requestApi<T>(path, { method: 'POST', body: jsonBody(data), signal });
  const allowWrite = () => {
    if (preview && !demo)
      throw new Error(
        'حالت نمایش طرح فعال است؛ برای ثبت واقعی TEMPLATE_PREVIEW=false را تنظیم کنید.',
      );
  };

  function showView(name: string) {
    all(root, '.view').forEach((element) => element.classList.remove('active'));
    one(root, '#view-' + CSS.escape(name))?.classList.add('active');
    all<HTMLElement>(root, '.nav-item').forEach((element) =>
      element.classList.toggle('active', element.dataset.view === name),
    );
    one(root, '.nav-item.active')?.closest('.nav-group')?.classList.add('open');
    all(root, '.dropdown').forEach((element) => element.classList.remove('open'));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function enter() {
    if (!user) return;
    for (const selector of ['#chip-name', '#profile-name-show']) text(root, selector, user.name);
    for (const selector of ['#chip-phone', '#profile-phone-show'])
      text(root, selector, user.mobile);
    for (const selector of ['#user-avatar-initial', '#profile-avatar-initial'])
      text(root, selector, user.name.charAt(0));
    for (const [selector, key] of [
      ['#prof-name', 'name'],
      ['#prof-melli', 'national_code'],
      ['#prof-phone', 'mobile'],
    ] as const) {
      const input = one<HTMLInputElement>(root, selector);
      if (input) {
        input.value = String(user[key] ?? '');
        if (key !== 'name') input.readOnly = true;
      }
    }
    fillProfile();
    text(root, '#home-greeting', 'سلام، ' + user.name.split(' ')[0]);
    text(root, '#home-date', date(new Date().toISOString()));
    one(root, '#screen-login')?.classList.remove('active');
    one(root, '#screen-dashboard')?.classList.add('active');
  }

  function fillProfile() {
    if (!user) return;
    const profile = one(root, '#view-profile');
    if (!profile) return;
    const county = one<HTMLSelectElement>(profile, 'select');
    if (county) county.value = String(user.county_id ?? '');
    const address = all<HTMLInputElement>(profile, 'input').find((input) =>
      input.placeholder.includes('آدرس'),
    );
    if (address) address.value = user.address ?? '';
  }

  async function refresh() {
    if (!user) return;
    const [tickets, dashboard] = await Promise.all([
      requestApi<RecordData[]>('citizen/tickets?per_page=100', { signal }),
      requestApi<RecordData>('citizen/dashboard', { signal }),
    ]);
    reports = tickets.data;
    renderReports(root, reports, filter);
    text(root, '#notif-count', dashboard.data.unread_notifications ?? 0);
    text(root, '#nav-count', reports.length);
  }

  async function requestOtp() {
    allowWrite();
    const identity = {
      name: value(root, '#in-name'),
      mobile: digits(value(root, '#in-phone')),
      national_code: digits(value(root, '#in-melli')),
    };
    const data = (
      await post<{ challenge_id: string; dev_code?: string }>('auth/otp/request', identity)
    ).data;
    challenge = data.challenge_id;
    sessionStorage.setItem('ostandari_challenge', JSON.stringify({ challenge_id: challenge }));
    text(root, '#otp-phone-show', identity.mobile);
    one(root, '#step-identity')?.classList.remove('active');
    one(root, '#step-otp')?.classList.add('active');
    let seconds = 60;
    if (timer) clearInterval(timer);
    timer = setInterval(() => {
      seconds--;
      text(
        root,
        '#resend-timer',
        Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0'),
      );
      if (seconds <= 0) {
        clearInterval(timer);
        one<HTMLElement>(root, '#resend-wrap')?.style.setProperty('display', 'none');
        one<HTMLElement>(root, '#resend-btn')?.style.setProperty('display', 'inline');
      }
    }, 1000);
    feedback(root, data.dev_code ? 'کد محلی ورود: ' + data.dev_code : 'کد تأیید ارسال شد.');
  }

  async function confirmOtp() {
    allowWrite();
    const code = digits(
      all<HTMLInputElement>(root, '.otp-boxes input')
        .map((input) => input.value)
        .join(''),
    );
    if (code.length !== 6) throw new Error('کد تأیید شش‌رقمی را کامل وارد کنید.');
    user = (await post<{ user: PortalUser }>('auth/otp/verify', { challenge_id: challenge, code }))
      .data.user;
    sessionStorage.removeItem('ostandari_challenge');
    enter();
    await refresh();
  }

  async function submitTicket(type: 'report' | 'governor') {
    allowWrite();
    const mediaIds: number[] = [];
    if (type === 'report' && !demo)
      for (const file of attachments?.files() ?? []) {
        const id = uploaded.get(file) ?? (await upload(file, 'private', signal));
        uploaded.set(file, id);
        mediaIds.push(id);
      }
    const data =
      type === 'report'
        ? {
            type,
            subject: value(root, '#rep-title'),
            body: value(root, '#rep-desc'),
            category,
            county_id: Number(value(root, '#rep-city')),
            address: value(root, '#rep-address'),
            urgency,
            media_ids: mediaIds,
            ...(demo
              ? { attachment_names: attachments?.files().map((file) => file.name) ?? [] }
              : {}),
          }
        : { type, subject: value(root, '#gov-subject'), body: value(root, '#gov-msg') };
    const result = await post<{ reference: string }>('citizen/tickets', data);
    if (type === 'report') attachments?.clear();
    feedback(root, 'درخواست ثبت شد. شناسه پیگیری: ' + result.data.reference);
    await refresh();
    showView('track');
  }

  async function profile() {
    allowWrite();
    const section = one(root, '#view-profile');
    const county = one<HTMLSelectElement>(section ?? root, 'select');
    const address = all<HTMLInputElement>(section ?? root, 'input').find((input) =>
      input.placeholder.includes('آدرس'),
    );
    user = (
      await requestApi<PortalUser>('auth/profile', {
        method: 'PATCH',
        body: jsonBody({
          name: value(root, '#prof-name'),
          county_id: county?.value ? Number(county.value) : null,
          address: address?.value ?? '',
        }),
      })
    ).data;
    enter();
    feedback(root, 'اطلاعات پروفایل ذخیره شد.');
  }

  async function dispatch(command: string, element: HTMLElement, args: string[]) {
    if (busy) return;
    try {
      if (command === 'showView') {
        showView(args[0]);
        return;
      }
      if (command === 'toggleGroup') {
        element.closest('.nav-group')?.classList.toggle('open');
        return;
      }
      if (command === 'toggleFaq') {
        element.closest('.faq-item')?.classList.toggle('open');
        return;
      }
      if (command === 'toggleDD') {
        one(root, '#' + CSS.escape(args[0]))?.classList.toggle('open');
        return;
      }
      if (command === 'otpMove') {
        const input = element as HTMLInputElement;
        input.value = digits(input.value).replace(/\D/g, '').slice(-1);
        if (input.value)
          all<HTMLInputElement>(root, '.otp-boxes input')[Number(args[0]) + 1]?.focus();
        return;
      }
      if (command === 'backToIdentity') {
        one(root, '#step-otp')?.classList.remove('active');
        one(root, '#step-identity')?.classList.add('active');
        return;
      }
      if (command === 'resetReportForm') {
        all<HTMLInputElement>(root, '#rep-title,#rep-address,#rep-desc').forEach(
          (input) => (input.value = ''),
        );
        category = '';
        attachments?.clear();
        all(root, '.cat-card').forEach((e) => e.classList.remove('selected'));
        return;
      }
      busy = true;
      if (['goToOtp', 'resendOtp'].includes(command)) await requestOtp();
      if (command === 'confirmOtp') await confirmOtp();
      if (command === 'submitReport') await submitTicket('report');
      if (command === 'submitGovMsg') await submitTicket('governor');
      if (command === 'toast') await profile();
      if (command === 'logout') {
        await post('auth/logout', {});
        user = null;
        window.location.assign(demo ? '/login' : '/citizen');
      }
    } catch (error) {
      feedback(root, error instanceof Error ? error.message : 'عملیات ناموفق بود.');
    } finally {
      busy = false;
    }
  }

  async function vote(optionId: number) {
    if (voting || !poll) return;
    try {
      allowWrite();
      voting = true;
      const result = await post<RecordData>('polls/' + poll.id + '/vote', { option_id: optionId });
      renderPoll(root, result.data, true, optionId);
    } catch (error) {
      feedback(root, error instanceof Error ? error.message : 'ثبت رأی ناموفق بود.');
    } finally {
      voting = false;
    }
  }

  function init() {
    renderCategories(root);
    if (preview && !demo) renderReports(root, previewReports());
    if (demo) {
      const notice = document.createElement('div');
      notice.className = 'portal-citizen-demo-notice';
      notice.setAttribute('role', 'status');
      notice.textContent =
        'محیط آزمایشی — حساب و درخواست‌ها فقط در همین مرورگر ذخیره می‌شوند و برای سازمان ارسال نمی‌شوند. پیوست‌ها بارگذاری نمی‌شوند.';
      root.prepend(notice);
      signal.addEventListener('abort', () => notice.remove(), { once: true });
    }
    attachments = bindAttachments(root, signal);
    const pending = JSON.parse(sessionStorage.getItem('ostandari_identity') ?? 'null');
    if (pending) {
      for (const [key, selector] of [
        ['name', '#in-name'],
        ['mobile', '#in-phone'],
        ['national_code', '#in-melli'],
      ]) {
        const input = one<HTMLInputElement>(root, selector);
        if (input) input.value = pending[key] ?? '';
      }
      sessionStorage.removeItem('ostandari_identity');
    }
    signal.addEventListener(
      'abort',
      () => {
        if (timer) clearInterval(timer);
      },
      { once: true },
    );
    root.addEventListener(
      'click',
      (event) => {
        const target = event.target as HTMLElement;
        const choice = target.closest<HTMLElement>('[data-category]');
        if (choice) {
          category = choice.dataset.category ?? '';
          all(root, '.cat-card').forEach((element) =>
            element.classList.toggle('selected', element === choice),
          );
        }
        const urgencyChoice = target.closest<HTMLElement>('#urgency-seg .seg-opt');
        if (urgencyChoice) {
          all(root, '#urgency-seg .seg-opt').forEach((element) =>
            element.classList.remove('selected'),
          );
          urgencyChoice.classList.add('selected');
          urgency =
            (
              { کم: 'low', متوسط: 'medium', زیاد: 'high', فوری: 'urgent' } as Record<string, string>
            )[urgencyChoice.dataset.val ?? ''] ?? 'medium';
        }
        const filterChoice = target.closest<HTMLElement>('#filter-row .filter-chip');
        if (filterChoice) {
          filter = filterChoice.dataset.f ?? 'all';
          all(root, '#filter-row .filter-chip').forEach((element) =>
            element.classList.toggle('active', element === filterChoice),
          );
          renderReports(root, reports, filter);
        }
        const report = target.closest<HTMLElement>('[data-ticket]');
        if (report) {
          report.classList.toggle('open');
          if ((!preview || demo) && report.classList.contains('open'))
            void requestApi<RecordData>('citizen/tickets/' + report.dataset.ticket, { signal })
              .then((result) => {
                reports = reports.map((item) =>
                  item.reference === result.data.reference ? result.data : item,
                );
                renderReports(root, reports, filter);
                one(
                  root,
                  '[data-ticket="' + CSS.escape(report.dataset.ticket ?? '') + '"]',
                )?.classList.add('open');
              })
              .catch((error) => feedback(root, error.message));
        }
        const option = target.closest<HTMLElement>('[data-option]');
        if (option) void vote(Number(option.dataset.option));
      },
      { signal },
    );
    if (!preview || demo) {
      void loadCitizenCatalog(context, requestApi)
        .then((result) => {
          poll = result;
          if (demo && result?.selected_option_id)
            renderPoll(root, result, true, Number(result.selected_option_id));
          fillProfile();
        })
        .catch((error) => {
          if (!signal.aborted) feedback(root, error.message);
        });
      void requestApi<PortalUser>('auth/me', { signal })
        .then((result) => {
          if (result.data.person_type !== 'citizen')
            throw new Error('برای استفاده از پنل شهروندی، با حساب شهروندی وارد شوید.');
          user = result.data;
          enter();
          return refresh();
        })
        .catch((error) => {
          if (demo && !signal.aborted) window.location.assign('/login');
          if (error instanceof ApiError && error.status === 401 && !signal.aborted) window.location.assign('/login');
          if (!(error instanceof ApiError && error.status === 401) && !signal.aborted)
            feedback(root, error.message);
        });
    }
  }
  return { init, dispatch };
}
