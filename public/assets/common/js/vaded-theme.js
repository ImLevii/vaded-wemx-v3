(() => {
    if (window.WemxTheme) {
        window.WemxTheme.apply();
        return;
    }

    const readTheme = () => {
        const requested = new URLSearchParams(window.location.search).get('theme');
        if (requested === 'dark' || requested === 'light') return requested;

        try {
            const legacyKey = document.documentElement.dataset.vadedArea === 'admin' ? 'tablerTheme' : 'color-theme';
            const saved = localStorage.getItem('wemx-theme') || localStorage.getItem(legacyKey);
            return saved === 'dark' ? 'dark' : 'light';
        } catch {
            return 'light';
        }
    };

    const apply = (theme = readTheme()) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.dataset.bsTheme = theme;
        if (document.body) document.body.dataset.bsTheme = theme;

        try {
            for (const key of ['wemx-theme', 'tablerTheme', 'color-theme']) localStorage.setItem(key, theme);
        } catch {
            // The theme still works when browser storage is unavailable.
        }
    };

    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    let revealObserver;

    const revealSections = () => {
        revealObserver?.disconnect();
        if (motionPreference.matches || !('IntersectionObserver' in window)) return;

        revealObserver = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                entry.target.dataset.vhReveal = 'visible';
                revealObserver.unobserve(entry.target);
            }
        }, { threshold: .12 });

        document.querySelectorAll('.vh-section-heading, .vh-service-card, .vh-pricing-card, .vh-spec-card, .vh-launch-cta').forEach((element) => {
            if (!element.dataset.vhReveal) revealObserver.observe(element);
        });
    };

    window.WemxTheme = { apply };
    window.toggleDarkmode = () => {
        const theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        const url = new URL(window.location.href);
        if (url.searchParams.has('theme')) {
            url.searchParams.delete('theme');
            window.history.replaceState(window.history.state, '', url);
        }
        apply(theme);
    };

    apply();
    document.addEventListener('DOMContentLoaded', () => { apply(); revealSections(); });
    document.addEventListener('livewire:navigated', () => { apply(); revealSections(); });
    document.addEventListener('livewire:navigating', () => revealObserver?.disconnect());
    motionPreference.addEventListener('change', revealSections);
    window.addEventListener('storage', (event) => {
        if (event.key === 'wemx-theme') apply();
    });
})();
