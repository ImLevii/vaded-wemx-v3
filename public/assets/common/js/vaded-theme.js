(() => {
    if (window.WemxTheme) {
        window.WemxTheme.apply();
        return;
    }

    const canChooseTheme = () => document.querySelector('meta[name="wemx-theme-control"]')?.content === 'manual';
    const automaticTheme = () => {
        const hour = new Date().getHours();
        return hour >= 7 && hour < 19 ? 'light' : 'dark';
    };
    const isTheme = (theme) => theme === 'dark' || theme === 'light';
    let manualTheme;

    const readTheme = () => {
        if (!canChooseTheme()) return automaticTheme();

        const requested = new URLSearchParams(window.location.search).get('theme');
        if (isTheme(requested)) return requested;
        if (isTheme(manualTheme)) return manualTheme;

        try {
            const legacyKey = document.documentElement.dataset.vadedArea === 'admin' ? 'tablerTheme' : 'color-theme';
            const saved = localStorage.getItem('wemx-theme') || localStorage.getItem(legacyKey);
            return isTheme(saved) ? saved : automaticTheme();
        } catch {
            return automaticTheme();
        }
    };

    const apply = (preferredTheme) => {
        const canChoose = canChooseTheme();
        const theme = canChoose && isTheme(preferredTheme) ? preferredTheme : readTheme();

        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.dataset.bsTheme = theme;
        if (document.body) document.body.dataset.bsTheme = theme;

        if (!canChoose) {
            manualTheme = undefined;
            return;
        }

        const requested = new URLSearchParams(window.location.search).get('theme');
        if (isTheme(preferredTheme) || isTheme(requested)) {
            manualTheme = theme;
            try {
                for (const key of ['wemx-theme', 'tablerTheme', 'color-theme']) localStorage.setItem(key, theme);
            } catch {
                // Keep the administrator's choice for this page when storage is unavailable.
            }
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
        if (!canChooseTheme()) {
            apply();
            return;
        }

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
    document.addEventListener('livewire:navigating', (event) => {
        revealObserver?.disconnect();
        event.detail?.onSwap?.(() => apply());
    });
    motionPreference.addEventListener('change', revealSections);
    window.addEventListener('storage', (event) => {
        if (event.key === 'wemx-theme' || event.key === null) {
            manualTheme = undefined;
            apply();
        }
    });
    window.setInterval(() => apply(), 60_000);
    window.addEventListener('focus', () => apply());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') apply();
    });
})();
