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
            return saved === 'light' ? 'light' : 'dark';
        } catch {
            return 'dark';
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
    document.addEventListener('DOMContentLoaded', () => apply());
    document.addEventListener('livewire:navigated', () => apply());
    window.addEventListener('storage', (event) => {
        if (event.key === 'wemx-theme') apply();
    });
})();
