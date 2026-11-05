import { all, commandArgs, one } from '@/lib/dom';
import type { PageContext } from '@/types/portal';

export function closeOverlays(root: HTMLElement): void {
  all(
    root,
    '.cbn-modal-overlay,.top-modal-overlay,.cbn-actions-menu,.install-app-overlay,#panel,#panelOverlay,#lightboxOverlay,#flagDropdown,.sidebar.open,.sidebar-parent.open,.dark-background-tablet.open',
  ).forEach((element) =>
    element.classList.remove(
      'active',
      'show',
      'open',
      'cbn-modal-overlay-open',
      'cbn-actions-menu-open',
    ),
  );
}

export function toggleModal(root: HTMLElement, open: boolean): void {
  const modal = one(root, '#cbnCreateMenuModalOverlay,#editCommitmentModal');
  if (!modal) return;
  modal.classList.toggle('active', open);
  // The supplied admin designs use this class to switch the overlay from display:none.
  if (modal.classList.contains('cbn-modal-overlay'))
    modal.classList.toggle('cbn-modal-overlay-open', open);
}

export function bindUi(
  context: PageContext,
  dispatch: (command: string, element: HTMLElement, args: string[], event: Event) => void,
): void {
  const { root, signal } = context;
  if (context.page.controller === 'admin') {
    const sidebar = one(root, '.sidebar');
    one(root, '#clicksidebar')?.setAttribute(
      'aria-expanded',
      String(
        window.innerWidth <= 900
          ? Boolean(sidebar?.classList.contains('open'))
          : !sidebar?.classList.contains('shrink'),
      ),
    );
  }
  const listen = (type: string, action: EventListener) =>
    root.addEventListener(type, action, { signal });
  listen('click', (event) => {
    const target = event.target as HTMLElement;
    const commandElement = target.closest<HTMLElement>('[data-command],[data-navigate]');
    if (commandElement && root.contains(commandElement)) {
      if (commandElement.dataset.navigate) {
        event.preventDefault();
        (context.navigate ?? ((href) => window.location.assign(href)))(
          commandElement.dataset.navigate,
        );
        return;
      }
      if (!commandElement.dataset.commandEvent || commandElement.dataset.commandEvent === 'click') {
        event.preventDefault();
        dispatch(
          commandElement.dataset.command ?? '',
          commandElement,
          commandArgs(commandElement.dataset.commandArgs ?? ''),
          event,
        );
      }
    }
    const create = target.closest<HTMLElement>('#cbnOpenCreateMenuModalBtn');
    if (
      create &&
      !(create.tagName === 'A' && /^\/(?:panel\/admin|county-admin\/[a-z0-9-]+)\//.test(create.getAttribute('href') ?? ''))
    ) {
      event.preventDefault();
      toggleModal(root, true);
    }
    if (target.closest('#cbnCloseCreateMenuModalBtn,.cbn-modal-close')) toggleModal(root, false);
    if (target.matches('.cbn-modal-overlay,.top-modal-overlay,.install-app-overlay'))
      closeOverlays(root);
    const tab = target.closest<HTMLElement>('.tab-btn');
    if (tab?.dataset.tab) {
      const group = tab.closest('.tabs') ?? tab.parentElement;
      const tabs = all<HTMLElement>(group ?? root, '.tab-btn');
      tabs.forEach((element) => {
        const active = element === tab;
        element.classList.toggle('active', active);
        element.setAttribute('aria-selected', String(active));
        if (element.dataset.tab)
          one(root, '#' + CSS.escape(element.dataset.tab))?.classList.toggle('active', active);
      });
      tab.classList.add('active');
      one(root, '#' + CSS.escape(tab.dataset.tab))?.classList.add('active');
    }
    if (target.closest('#sidebarHamburgerBtn,.hambergur-responsive-btn')) {
      one(root, '#sidebarParent,.sidebar-parent,.sidebar')?.classList.toggle('open');
      one(root, '#sidebarDarkBg,.dark-background-tablet')?.classList.toggle('open');
    }
    if (target.closest('#clicksidebar')) {
      const sidebar = one(root, '.sidebar-parent,.sidebar');
      const control = one(root, '#clicksidebar');
      if (window.innerWidth <= 900) {
        const open = sidebar?.classList.toggle('open') ?? false;
        one(root, '#sidebarDarkBg,.dark-background-tablet')?.classList.toggle('open', open);
        control?.setAttribute('aria-expanded', String(open));
      } else {
        const compact = one(root, '.sidebar')?.classList.toggle('shrink') ?? false;
        control?.setAttribute('aria-expanded', String(!compact));
      }
    }
    if (
      target.closest('#sidebarCloseBtn,.sidebar-close-btn,#sidebarDarkBg,.dark-background-tablet')
    ) {
      all(root, '.sidebar-parent,.sidebar,.dark-background-tablet').forEach((element) =>
        element.classList.remove('open'),
      );
      one(root, '#clicksidebar')?.setAttribute('aria-expanded', 'false');
      one<HTMLElement>(root, '#clicksidebar')?.focus();
    }
    const accordion = target.closest<HTMLElement>('.sidebar-extended-pages-link');
    if (accordion?.closest('.menu-item')?.querySelector('.submenu-items')) {
      event.preventDefault();
      accordion.closest('.menu-item')?.querySelector('.submenu-items')?.classList.toggle('open');
    }
    const quickCreate = one<HTMLDetailsElement>(root, '.portal-admin-create-menu');
    if (quickCreate?.open && !target.closest('.portal-admin-create-menu')) quickCreate.open = false;
    const headerButton = target.closest<HTMLElement>('[id^=clickdiv]');
    if (headerButton) {
      const number = headerButton.id.replace('clickdiv', '');
      one(root, '#hiddendiv' + number)?.classList.toggle('active');
      headerButton.classList.toggle('highlight');
    }
    if (target.closest('#clickforsearch')) {
      if (context.page.controller === 'admin') {
        if (window.innerWidth <= 900) {
          one(root, '.sidebar')?.classList.add('open');
          one(root, '#sidebarDarkBg')?.classList.add('open');
          one(root, '#clicksidebar')?.setAttribute('aria-expanded', 'true');
        }
        one<HTMLInputElement>(root, '[data-admin-menu-search]')?.focus();
      } else window.location.assign('/search');
    }
    if (target.closest('#theme-toggle')) {
      const dark = root.classList.toggle('portal-dark');
      try {
        localStorage.setItem('portal-admin-theme', dark ? 'dark' : 'light');
      } catch {}
      target.closest('#theme-toggle')?.setAttribute('aria-pressed', String(dark));
    }
  });
  listen('keydown', (event) => {
    const key = event as KeyboardEvent;
    if (!['Enter', ' '].includes(key.key)) return;
    const button = (key.target as HTMLElement).closest<HTMLElement>(
      '[role="button"]:not(button):not(a)',
    );
    if (button && root.contains(button)) {
      key.preventDefault();
      button.click();
    }
  });
  listen('input', (event) => {
    const target = event.target as HTMLElement;
    if (target.dataset.command && target.dataset.commandEvent === 'input')
      dispatch(
        target.dataset.command,
        target,
        commandArgs(target.dataset.commandArgs ?? ''),
        event,
      );
  });
  listen('change', (event) => {
    const input = event.target as HTMLInputElement;
    if (input.type === 'file' && input.files?.[0]) {
      const label = input.parentElement?.querySelector<HTMLElement>(
        '.file-name,[id*=FileName],[id*=fileName]',
      );
      if (label) label.textContent = input.files[0].name;
    }
  });
  root.addEventListener(
    'error',
    (event) => {
      const image = event.target as HTMLImageElement;
      if (image.tagName === 'IMG' && image.dataset.hideOnError) image.hidden = true;
    },
    { capture: true, signal },
  );
  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Escape') {
        const sidebarOpen = Boolean(one(root, '.sidebar.open,.sidebar-parent.open'));
        closeOverlays(root);
        if (sidebarOpen) {
          one(root, '#clicksidebar')?.setAttribute('aria-expanded', 'false');
          one<HTMLElement>(root, '#clicksidebar')?.focus();
        }
        all<HTMLDetailsElement>(root, '.portal-admin-create-menu[open]').forEach((menu) => {
          menu.open = false;
        });
      }
    },
    { signal },
  );
}

export function rowActions(root: HTMLElement, trigger: HTMLElement): void {
  const menu = trigger
    .closest('.cbn-actions-wrapper')
    ?.querySelector<HTMLElement>('.cbn-actions-menu');
  if (!menu) return;
  const wasOpen = menu.classList.contains('cbn-actions-menu-open');
  all(root, '.cbn-actions-menu-open').forEach((element) =>
    element.classList.remove('cbn-actions-menu-open'),
  );
  if (wasOpen) return;
  menu.classList.add('cbn-actions-menu-open');
  const box = trigger.getBoundingClientRect();
  menu.style.position = 'fixed';
  menu.style.left =
    Math.max(8, Math.min(box.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8)) +
    'px';
  menu.style.top =
    Math.max(8, Math.min(box.bottom + 6, window.innerHeight - menu.offsetHeight - 8)) + 'px';
}
