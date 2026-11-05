import { api, jsonBody } from '@/lib/api/client';
import { all, feedback, fieldError, one, text, value } from '@/lib/dom';
import { bindPublicData } from './data';
import type { PageContext } from '@/types/portal';

export function siteController(context: PageContext) {
  const { root, signal, preview } = context;
  let selectedGallery: HTMLElement | null = null;
  const toggle = (selector: string, className = 'active') =>
    one(root, selector)?.classList.toggle(className);
  function mobile(open: boolean) {
    root.dispatchEvent(new CustomEvent('portal:mobile', { detail: open }));
  }

  function dispatch(command: string, element: HTMLElement, args: string[]) {
    if (command === 'mmToggle') {
      const item = element.closest('.mm-item');
      if (item) {
        all(item.parentElement ?? root, ':scope > .mm-item').forEach((sibling) => {
          if (sibling !== item) sibling.classList.remove('is-open');
        });
        const open = item.classList.toggle('is-open');
        element.setAttribute('aria-expanded', String(open));
      }
    }
    if (command === 'toggleMobileMenu') {
      toggle('#' + CSS.escape(args[0] ?? ''), 'open');
    }
    if (command === 'closeModal') {
      one(root, '#modalContent')?.classList.remove('active');
    }
    if (command === 'closeSearch')
      one<HTMLElement>(root, '#myOverlay')?.style.setProperty('display', 'none');
    if (command === 'printSection') window.print();
    if (command === 'reloadPage') window.location.reload();
    if (command === 'copyLink') {
      void navigator.clipboard
        ?.writeText(window.location.href)
        .then(() => feedback(root, 'نشانی صفحه کپی شد.'));
    }
    if (command === 'sharePage') {
      if (navigator.share)
        void navigator.share({ title: document.title, url: window.location.href }).catch(() => {});
      else
        void navigator.clipboard
          ?.writeText(window.location.href)
          .then(() => feedback(root, 'نشانی صفحه کپی شد.'));
    }
    if (command === 'cb2SwitchTab') {
      const group = element.parentElement;
      all(group ?? root, '.cb2-tab,.news-tab-btn').forEach((tab) => {
        tab.classList.remove('active');
        tab.setAttribute('aria-selected', 'false');
      });
      element.classList.add('active');
      element.setAttribute('aria-selected', 'true');
      all(root, '.cb2-panel').forEach((panel, i) =>
        panel.classList.toggle('active', i === Number(args[0])),
      );
      all<HTMLElement>(root, '.news-list2[id^="tabPanel-"]').forEach((panel) => {
        panel.hidden = panel.id !== 'tabPanel-' + args[0];
      });
    }
  }

  async function contact(form: HTMLElement) {
    if (preview)
      throw new Error('برای ارسال واقعی، حالت نمایش طرح را غیرفعال و بک‌اند را اجرا کنید.');
    const message = value(root, '#fbMsg');
    const result = await api<{ reference: string }>('contact', {
      method: 'POST',
      body: jsonBody({
        name: value(root, '#fbName'),
        email: value(root, '#fbEmail'),
        phone: value(root, '#fbPhone'),
        subject: message.slice(0, 120),
        body: message,
      }),
    });
    if (form.tagName === 'FORM') (form as HTMLFormElement).reset();
    feedback(root, 'پیام ثبت شد. شناسه پیگیری: ' + result.data.reference);
  }

  function openGallery(item: HTMLElement) {
    const source = item.querySelector('img,video,svg')?.cloneNode(true);
    if (!source) return;
    selectedGallery = item;
    const content = one(root, '#lightboxMedia');
    if (content && source) content.replaceChildren(source);
    one(root, '#lightboxOverlay')?.classList.add('active');
    text(
      root,
      '#lightboxCaption',
      item.querySelector('.gal-caption')?.textContent ?? item.textContent?.trim(),
    );
  }

  function moveGallery(direction: number) {
    const items = all<HTMLElement>(root, '.gal-item').filter((item) => !item.hidden);
    if (!items.length) return;
    const current = selectedGallery ? items.indexOf(selectedGallery) : 0;
    openGallery(items[(current + direction + items.length) % items.length]);
  }

  async function assistant() {
    const input = one<HTMLTextAreaElement>(root, '#chat-textarea');
    const question = input?.value.trim();
    if (!question) return;
    if (preview)
      throw new Error('برای پاسخ از محتوای سایت، بک‌اند را اجرا و حالت نمایش طرح را غیرفعال کنید.');
    const result = await api<{ answer: string }>('assistant/query', {
      method: 'POST',
      body: jsonBody({ question, locale: context.page.lang }),
    });
    const container = one(root, '#chat-inner');
    if (container) {
      one(root, '#welcome-screen')?.remove();
      const reply = document.createElement('div');
      reply.className = 'message assistant-message';
      reply.textContent = result.data.answer;
      container.append(reply);
    }
    if (input) input.value = '';
  }

  function init() {
    all<HTMLElement>(
      root,
      '.header-container .search,.header-container .logo-wrapper,.header-container .hum-logo,.name-btn,.login-google',
    ).forEach((control) => {
      if (control.closest('[data-alpine-header]')) return;
      control.setAttribute('role', 'button');
      control.tabIndex = 0;
      control.setAttribute(
        'aria-label',
        control.classList.contains('search')
          ? 'جستجو'
          : control.classList.contains('name-btn')
            ? 'ارتباط با استاندار'
            : control.classList.contains('login-google')
              ? 'ورود با گوگل'
              : 'صفحه اصلی',
      );
    });
    all<HTMLElement>(
      root,
      '.categories li[data-target],.nested-categories li[data-target]',
    ).forEach((item) => {
      item.setAttribute('role', 'button');
      item.tabIndex = 0;
    });
    const categoryState = (event: Event) => {
      const target = event.target as HTMLElement;
      if (target.closest('[data-alpine-header]')) return;
      const item = target.closest<HTMLElement>(
        '.categories li[data-target],.nested-categories li[data-target]',
      );
      if (!item) return;
      const nested = item.closest('.nested-categories');
      all(root, nested ? '.nested-submenu' : '.submenu').forEach((panel) =>
        panel.classList.remove('active'),
      );
      all(item.parentElement ?? root, 'li').forEach((sibling) =>
        sibling.classList.toggle('active', sibling === item),
      );
      one(root, '#' + CSS.escape(item.dataset.target ?? ''))?.classList.add('active');
    };
    root.addEventListener('focusin', categoryState, { signal });
    root.addEventListener('click', categoryState, { signal });

    const handle = one<HTMLElement>(root, '#handle');
    const panel = one<HTMLElement>(root, '#panel');
    let dragging = false,
      startY = 0,
      startHeight = 0;
    handle?.addEventListener(
      'pointerdown',
      (event) => {
        dragging = true;
        startY = event.clientY;
        startHeight = panel?.getBoundingClientRect().height ?? 0;
        handle.setPointerCapture?.(event.pointerId);
      },
      { signal },
    );
    handle?.addEventListener(
      'pointermove',
      (event) => {
        if (dragging && panel)
          panel.style.height =
            Math.min(window.innerHeight, Math.max(120, startHeight + startY - event.clientY)) +
            'px';
      },
      { signal },
    );
    handle?.addEventListener(
      'pointerup',
      () => {
        dragging = false;
        if (panel) {
          const expanded = panel.offsetHeight > window.innerHeight * 0.65;
          panel.classList.toggle('expanded', expanded);
          panel.style.height = expanded ? '100%' : '60%';
        }
      },
      { signal },
    );
    root.addEventListener(
      'click',
      (event) => {
        const target = event.target as HTMLElement;
        if (target.closest('[data-alpine-header]')) return;
        if (target.closest('.header-container .search')) window.location.assign('/search');
        if (target.closest('.header-container .logo-wrapper,.header-container .hum-logo'))
          window.location.assign('/');
        if (target.closest('.name-btn')) window.location.assign('/contact');
        if (target.closest('#hamburgerBtn')) mobile(true);
        if (target.closest('#closeMobileMenu,#mmBackdrop')) mobile(false);
        if (target.closest('#flagSwitcher')) toggle('#flagDropdown', 'active');
        if (target.closest('#installAppBtn,.js-install-app')) {
          toggle('#installAppOverlay', 'open');
          mobile(false);
        }
        if (target.closest('#installAppClose'))
          one(root, '#installAppOverlay')?.classList.remove('open');
        if (target.closest('#installAppCopyBtn'))
          void navigator.clipboard
            ?.writeText(window.location.origin)
            .then(() => text(root, '#installAppCopyText', 'کپی شد'));
        if (target.closest('#openBtn,.js-open-ai')) {
          one<HTMLElement>(root, '#panel')?.style.setProperty('height', '60%');
          one<HTMLElement>(root, '#panel')?.classList.add('open');
          one<HTMLElement>(root, '#panelOverlay')?.classList.add('active');
          mobile(false);
        }
        if (target.closest('#panelClose,#panelOverlay')) {
          one(root, '#panel')?.classList.remove('open');
          one(root, '#panelOverlay')?.classList.remove('active');
        }
        if (target.closest('#menu-btn')) toggle('#sidebar', 'open');
        if (target.closest('#sidebar-close-btn,#sidebar-overlay'))
          one(root, '#sidebar')?.classList.remove('open');
        if (target.closest('#openModalBtn')) toggle('#modalContent', 'active');
        const news = target.closest<HTMLElement>('[data-news-id]');
        if (news && !target.closest('a')) window.location.assign('/news/' + news.dataset.newsId);
        const filter = target.closest<HTMLElement>('.gal-filter-btn,[data-filter]');
        if (filter) {
          all(root, '.gal-filter-btn').forEach((e) => e.classList.remove('active'));
          filter.classList.add('active');
          all<HTMLElement>(root, '.gal-item').forEach(
            (item) =>
              (item.hidden =
                filter.dataset.filter !== 'all' &&
                Boolean(filter.dataset.filter) &&
                item.dataset.cat !== filter.dataset.filter),
          );
        }
        const gallery = target.closest<HTMLElement>('.gal-item');
        if (gallery) { event.preventDefault(); openGallery(gallery); }
        if (target.closest('#lightboxNext')) moveGallery(1);
        if (target.closest('#lightboxPrev')) moveGallery(-1);
        if (target.closest('#lightboxClose') || target.id === 'lightboxOverlay')
          one(root, '#lightboxOverlay')?.classList.remove('active');
      },
      { signal },
    );
    document.addEventListener(
      'keydown',
      (event) => {
        const lightbox = one(root, '#lightboxOverlay');
        if (!lightbox?.classList.contains('active')) return;
        if (event.key === 'Escape') lightbox.classList.remove('active');
        if (event.key === 'ArrowLeft') moveGallery(1);
        if (event.key === 'ArrowRight') moveGallery(-1);
      },
      { signal },
    );
    root.addEventListener(
      'mouseover',
      (event) => {
        const target = event.target as HTMLElement;
        if (target.closest('[data-alpine-header]')) return;
        const category = target.closest<HTMLElement>(
          '.categories li[data-target],.nested-categories li[data-target]',
        );
        if (category) {
          const nested = category.closest('.nested-categories');
          all(root, nested ? '.nested-submenu' : '.submenu').forEach((element) =>
            element.classList.remove('active'),
          );
          all(category.parentElement ?? root, 'li').forEach((element) =>
            element.classList.remove('active'),
          );
          category.classList.add('active');
          one(root, '#' + CSS.escape(category.dataset.target ?? ''))?.classList.add('active');
        }
      },
      { signal },
    );
    root.addEventListener(
      'submit',
      (event) => {
        const form = event.target as HTMLFormElement;
        event.preventDefault();
        if (form.id === 'chat-form')
          void assistant().catch((error) => feedback(root, error.message));
        else if (form.contains(one(root, '#fbMsg')))
          void contact(form).catch((error) => fieldError(form, error));
      },
      { signal },
    );
    one(root, '.fb-submit')?.addEventListener(
      'click',
      (event) => {
        const button = event.target as HTMLElement;
        const form = button.closest('form');
        if (!form) {
          event.preventDefault();
          void contact(root as unknown as HTMLFormElement).catch((error) =>
            fieldError(root, error),
          );
        }
      },
      { signal },
    );
    // The map is rendered as real links on the server, including its labels.
    // Do not rewrite destinations to numeric indexes after hydration.
    const contentReady = (
      context.page.id === 'PortalPage' || (context.page as any).serverRendered ? Promise.resolve() : bindPublicData(context)
    ).catch((error) => {
      root.dataset.apiError = 'true';
      if (!signal.aborted) feedback(root, error.message);
    });
    if (one(root, '.swiper'))
      void Promise.all([contentReady, import('swiper'), import('swiper/modules')])
        .then(([, { default: Swiper }, { Navigation, Pagination, Autoplay }]) => {
          if (signal.aborted) return;
          all<HTMLElement>(root, '.swiper:not([data-alpine-slider])').forEach((element) => {
            const slider = new Swiper(element, {
              modules: [Navigation, Pagination, Autoplay],
              loop: element.querySelectorAll('.swiper-slide').length > 1,
              autoplay: { delay: 5000 },
              pagination: { el: one<HTMLElement>(element, '.swiper-pagination'), clickable: true },
              navigation: {
                nextEl: one<HTMLElement>(element, '.swiper-button-next'),
                prevEl: one<HTMLElement>(element, '.swiper-button-prev'),
              },
            });
            signal.addEventListener('abort', () => slider.destroy(true, true), { once: true });
          });
        })
        .catch((error) => {
          if (!signal.aborted) feedback(root, error.message);
        });
  }
  return { init, dispatch };
}
