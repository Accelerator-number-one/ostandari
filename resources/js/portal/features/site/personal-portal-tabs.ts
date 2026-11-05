const tabs = ['news', 'announcement', 'about'] as const;
type PersonalTab = typeof tabs[number];

export function personalPortalTabs(initial = 'news') {
  return {
    tab: (tabs.includes(initial as PersonalTab) ? initial : 'news') as PersonalTab,
    select(value: string, focus = false) {
      if (!tabs.includes(value as PersonalTab)) return;
      this.tab = value as PersonalTab;
      if (focus) this.$nextTick(() => this.$refs.tabs.querySelector(`[data-tab="${this.tab}"]`)?.focus());
    },
    move(delta: number) {
      this.select(tabs[(tabs.indexOf(this.tab) + delta + tabs.length) % tabs.length], true);
    },
  };
}
