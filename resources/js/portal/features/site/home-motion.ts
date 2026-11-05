export function mountHomeMotion(root: HTMLElement): () => void {
  const view = root.ownerDocument.defaultView;
  if (!view?.matchMedia) return () => {};
  const preference = view.matchMedia('(prefers-reduced-motion: reduce)');
  const visited = new WeakSet<Element>();
  const animations = new Set<Animation>();
  let observer: IntersectionObserver | undefined;

  const stop = () => {
    observer?.disconnect();
    observer = undefined;
    animations.forEach((animation) => animation.cancel());
    animations.clear();
  };
  const start = () => {
    stop();
    if (preference.matches || !view.IntersectionObserver || !view.Element.prototype.animate) return;
    const current = new view.IntersectionObserver(
      (entries) => {
        if (observer !== current || preference.matches) return;
        for (const entry of entries) {
          if (!entry.isIntersecting || visited.has(entry.target)) continue;
          visited.add(entry.target);
          current.unobserve(entry.target);
          const targets = [
            entry.target,
            ...entry.target.querySelectorAll('[data-home-item]'),
          ].slice(0, 9);
          targets.forEach((target, index) => {
            const animation = target.animate(
              [
                { opacity: 0, transform: `translateY(${index ? 10 : 20}px)` },
                { opacity: 1, transform: 'translateY(0)' },
              ],
              {
                duration: index ? 460 : 620,
                delay: index ? Math.min(index * 45, 270) : 0,
                easing: 'cubic-bezier(.22,1,.36,1)',
              },
            );
            animations.add(animation);
            const release = () => animations.delete(animation);
            void animation.finished.then(release, release);
          });
        }
      },
      { threshold: 0.08, rootMargin: '0px 0px -24px 0px' },
    );
    observer = current;
    root.querySelectorAll('[data-home-reveal]').forEach((element) => {
      if (!visited.has(element)) current.observe(element);
    });
  };

  start();
  preference.addEventListener('change', start);
  return () => {
    preference.removeEventListener('change', start);
    stop();
  };
}
