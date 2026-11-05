(() => {
    const card = document.querySelector('[data-pwa-card]');
    let promptEvent = null;
    let installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

    if (card) {
        const install = card.querySelector('[data-pwa-install]');
        const guide = card.querySelector('[data-pwa-guide]');
        const status = card.querySelector('[data-pwa-installed]');
        const render = () => {
            install.hidden = installed || !promptEvent;
            guide.hidden = installed || Boolean(promptEvent);
            status.hidden = !installed;
        };

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            promptEvent = event;
            render();
        });

        window.addEventListener('appinstalled', () => {
            installed = true;
            promptEvent = null;
            render();
        });

        install.addEventListener('click', async () => {
            if (!promptEvent) return;
            const event = promptEvent;
            promptEvent = null;
            install.disabled = true;
            try {
                await event.prompt();
            } catch {
                // If the browser no longer offers its prompt, restore the manual guide.
            } finally {
                install.disabled = false;
                render();
            }
        });

        render();
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.warn('Portal service worker could not be registered.', error);
        });
    }
})();
