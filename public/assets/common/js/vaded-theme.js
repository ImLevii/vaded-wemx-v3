(() => {
    if (window.WemxTheme) { window.WemxTheme.apply(); return; }
    const themes = ['spring', 'summer', 'autumn', 'winter', 'newYear', 'valentines', 'stPatrickDay', 'easter', 'july4', 'halloween', 'thanksgiving', 'christmas'];
    const names = ['Spring', 'Summer', 'Autumn', 'Winter', 'New Year’s', 'Valentine’s Day', 'St. Patrick’s Day', 'Easter', 'July 4th', 'Halloween', 'Thanksgiving', 'Christmas'];
    const editions = {
        spring: { description: 'Blush blossoms, a fluttering butterfly and petals carried on a gentle breeze.', palette: ['#e99fb9', '#81bca3', '#c4b0e2'] },
        summer: { description: 'A slowly turning sun, sea-glass waves and warm glints of golden light.', palette: ['#efb34d', '#72c5cc', '#c6eece'] },
        autumn: { description: 'Copper leaves drift around the mark, with an acorn and flecks of amber.', palette: ['#d99b50', '#bc6452', '#c79661'] },
        winter: { description: 'An icy crown, crystalline snowflakes and two delicate layers of snowfall.', palette: ['#acd6eb', '#deeffa', '#91bcd2'] },
        newYear: { description: 'Champagne-gold fireworks bloom in sequence above a shower of lilac confetti.', palette: ['#f1ca7e', '#c7b7ef', '#fff0bc'] },
        valentines: { description: 'Sculpted rose hearts float above silk-like ribbons and soft champagne sparkles.', palette: ['#d77e99', '#ecb4c8', '#f2d5b3'] },
        stPatrickDay: { description: 'A jade shamrock sways beside a turning gold coin and tiny lucky stars.', palette: ['#59a580', '#c49a50', '#b7d59c'] },
        easter: { description: 'Porcelain bunny ears, a gently rocking patterned egg and pastel spring details.', palette: ['#b8a3d4', '#f4d6a6', '#e9b3c5'] },
        july4: { description: 'Ruby and sapphire fireworks unfold in staggered bursts, framed by silver stars.', palette: ['#da8681', '#9fbde8', '#dceaff'] },
        halloween: { description: 'A velvet-winged bat, a floating little ghost and a candle glowing through the mist.', palette: ['#9e82b8', '#edb86a', '#e9e1ef'] },
        thanksgiving: { description: 'A golden lattice pie releases curls of steam beneath wheat and harvest leaves.', palette: ['#d8a36a', '#dbc18b', '#c98b50'] },
        christmas: { description: 'A plush Santa hat, evergreen garland and individually twinkling lights under falling snow.', palette: ['#b95d60', '#88aa83', '#e3b96c'] },
    };
    const modes = ['site', 'auto', 'system', 'light', 'dark'];
    const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const systemPreference = window.matchMedia('(prefers-color-scheme: dark)');
    let memoryMode;
    let revealObserver;
    let logoObserver;
    const canChooseTheme = () => document.querySelector('meta[name="wemx-theme-control"]')?.content === 'manual';
    const readStorage = key => { try { return localStorage.getItem(key); } catch { return null; } };
    const writeStorage = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
    const settings = () => {
        let config = {};
        try { config = JSON.parse(document.querySelector('meta[name="wemx-appearance"]')?.content || '{}'); } catch {}
        return { mode: 'auto', accent: 'vaded', seasonal: 'auto', motion: true, logoMotion: true, ...config };
    };
    const easterDate = year => {
        const a = year % 19, b = Math.floor(year / 100), c = year % 100;
        const d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
        const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
        const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
        const m = Math.floor((a + 11 * h + 22 * l) / 451), n = h + l - 7 * m + 114;
        return new Date(year, Math.floor(n / 31) - 1, n % 31 + 1);
    };
    const withinDaysBefore = (date, end, days) => {
        const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - days);
        const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        return day >= start && day <= end;
    };
    const resolveLogoTheme = (date = new Date(), override = 'auto') => {
        if (override === 'disabled') return 'none';
        if (themes.includes(override)) return override;
        const day = (date.getMonth() + 1) * 100 + date.getDate();
        if (day === 1231 || day <= 102) return 'newYear';
        if (day >= 210 && day <= 214) return 'valentines';
        if (day >= 315 && day <= 317) return 'stPatrickDay';
        if (withinDaysBefore(date, easterDate(date.getFullYear()), 3)) return 'easter';
        if (day >= 701 && day <= 704) return 'july4';
        if (day >= 1024 && day <= 1031) return 'halloween';
        const first = new Date(date.getFullYear(), 10, 1);
        const thanksgiving = new Date(date.getFullYear(), 10, 1 + (4 - first.getDay() + 7) % 7 + 21);
        if (withinDaysBefore(date, thanksgiving, 2)) return 'thanksgiving';
        if (day >= 1201 && day <= 1226) return 'christmas';
        if (day >= 320 && day <= 620) return 'spring';
        if (day >= 621 && day <= 921) return 'summer';
        if (day >= 922 && day <= 1220) return 'autumn';
        return 'winter';
    };
    const motionAllowed = () => !motionPreference.matches && !navigator.connection?.saveData && document.visibilityState !== 'hidden';
    const updatePreview = (element, logo) => {
        const resolved = logo.dataset.logoTheme;
        const edition = editions[resolved];
        element.dataset.logoTheme = resolved;
        const name = element.querySelector('[data-logo-theme-name]');
        if (name) name.textContent = (logo.dataset.logoPreview === 'auto' ? 'Automatic · ' : '') + (names[themes.indexOf(resolved)] || 'Original logo');
        const description = element.querySelector('[data-logo-theme-description]');
        if (description) description.textContent = edition?.description || 'Your original logo, with seasonal artwork switched off.';
        const status = element.querySelector('[data-logo-motion-status]');
        const playing = logo.dataset.logoMotion === 'on';
        element.dataset.previewPlaying = String(playing);
        if (status) status.textContent = resolved === 'none' ? 'Original branding' : playing ? 'Motion playing' : motionPreference.matches ? 'Reduced motion' : 'Still artwork';
        const replay = element.querySelector('[data-logo-replay]');
        if (replay) replay.disabled = !playing || resolved === 'none';
        element.querySelectorAll('.vh-seasonal-palette i').forEach((swatch, index) => { swatch.style.backgroundColor = edition?.palette[index] || 'var(--vh-muted)'; });
    };
    const updateLogo = logo => {
        const config = settings();
        logo.dataset.logoTheme = resolveLogoTheme(new Date(), logo.dataset.logoPreview ?? config.seasonal);
        const preview = logo.closest('[data-preview-motion]');
        const enabled = preview ? preview.dataset.previewMotion === 'on' : config.motion && config.logoMotion;
        logo.dataset.logoMotion = enabled && motionAllowed() && logo.dataset.logoStatic !== 'true' && logo.dataset.logoVisible !== 'false' ? 'on' : 'off';
        if (preview) updatePreview(preview, logo);
    };
    const refreshLogos = () => {
        document.querySelectorAll('[data-brand-logo]').forEach(logo => {
            updateLogo(logo);
            if (logoObserver && !logo.dataset.logoObserved) { logo.dataset.logoObserved = 'true'; logoObserver.observe(logo); }
        });
    };
    if ('IntersectionObserver' in window) {
        logoObserver = new IntersectionObserver(entries => entries.forEach(({ target, isIntersecting }) => {
            target.dataset.logoVisible = String(isIntersecting); updateLogo(target);
        }));
    }
    const selectedMode = () => {
        if (!canChooseTheme()) return 'site';
        const requested = new URLSearchParams(location.search).get('theme');
        if (['light', 'dark'].includes(requested)) return requested;
        if (memoryMode) return memoryMode;
        const saved = readStorage('wemx-display-mode');
        if (modes.includes(saved)) return saved;
        const legacy = readStorage('wemx-theme') || readStorage(document.documentElement.dataset.vadedArea === 'admin' ? 'tablerTheme' : 'color-theme');
        return ['light', 'dark'].includes(legacy) ? legacy : 'site';
    };
    const apply = preferred => {
        if (canChooseTheme() && modes.includes(preferred)) {
            memoryMode = preferred; writeStorage('wemx-display-mode', preferred);
        }
        const config = settings();
        const personal = selectedMode();
        const mode = personal === 'site' ? config.mode : personal;
        const hour = new Date().getHours();
        const dark = mode === 'dark' || (mode === 'system' && systemPreference.matches) || (!['light', 'dark', 'system'].includes(mode) && (hour < 7 || hour >= 19));
        const theme = dark ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', dark);
        for (const element of [document.documentElement, document.body].filter(Boolean)) {
            element.dataset.bsTheme = theme;
            element.dataset.accent = ['vaded', 'emerald', 'violet', 'amber'].includes(config.accent) ? config.accent : 'vaded';
            element.dataset.motion = config.motion && motionAllowed() ? 'on' : 'off';
        }
        if (!canChooseTheme()) memoryMode = undefined;
        else if (new URLSearchParams(location.search).has('theme')) {
            memoryMode = personal; writeStorage('wemx-display-mode', personal);
        }
        document.querySelectorAll('[data-personal-theme]').forEach(control => { control.value = personal; });
        refreshLogos();
    };
    const setMode = mode => {
        if (!canChooseTheme() || !modes.includes(mode)) return;
        const url = new URL(location.href); url.searchParams.delete('theme');
        history.replaceState(history.state, '', url); apply(mode);
    };
    const revealSections = () => {
        revealObserver?.disconnect();
        if (!settings().motion || !motionAllowed() || !('IntersectionObserver' in window)) return;
        revealObserver = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.dataset.vhReveal = 'visible'; revealObserver.unobserve(entry.target);
        }), { threshold: .12 });
        document.querySelectorAll('.vh-section-heading, .vh-service-card, .vh-pricing-card, .vh-spec-card, .vh-launch-cta').forEach(element => {
            if (!element.dataset.vhReveal) revealObserver.observe(element);
        });
    };
    const preview = (element, theme, enabled) => {
        element.dataset.previewMotion = enabled ? 'on' : 'off';
        element.querySelectorAll('[data-brand-logo]').forEach(logo => { logo.dataset.logoPreview = theme; updateLogo(logo); });
    };
    window.WemxTheme = { apply, setMode, resolveLogoTheme, refreshLogos, preview };
    window.toggleDarkmode = () => setMode(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
    document.addEventListener('change', event => { if (event.target.matches('[data-personal-theme]')) setMode(event.target.value); });
    document.addEventListener('click', event => {
        const modeLink = event.target.closest('[data-theme-mode]');
        if (modeLink) { event.preventDefault(); setMode(modeLink.dataset.themeMode); return; }
        const button = event.target.closest('[data-logo-replay]');
        if (!button) return;
        button.closest('.vh-seasonal-preview')?.querySelectorAll('[data-brand-logo]').forEach(logo => {
            logo.dataset.logoMotion = 'off'; void logo.offsetWidth; updateLogo(logo);
        });
    });
    window.addEventListener('appearance-saved', event => {
        const meta = document.querySelector('meta[name="wemx-appearance"]');
        if (meta) meta.content = JSON.stringify(event.detail.settings);
        apply(); revealSections();
    });
    apply();
    document.addEventListener('DOMContentLoaded', () => { apply(); revealSections(); });
    document.addEventListener('livewire:init', () => { window.Livewire.hook('morphed', () => refreshLogos()); });
    document.addEventListener('livewire:navigated', () => { apply(); revealSections(); });
    document.addEventListener('livewire:navigating', event => {
        revealObserver?.disconnect(); logoObserver?.disconnect();
        document.querySelectorAll('[data-logo-observed]').forEach(logo => { delete logo.dataset.logoObserved; });
        event.detail?.onSwap?.(() => apply());
    });
    systemPreference.addEventListener('change', () => apply());
    motionPreference.addEventListener('change', () => { apply(); revealSections(); });
    window.addEventListener('storage', event => {
        if (['wemx-display-mode', 'wemx-theme', null].includes(event.key)) { memoryMode = undefined; apply(); }
    });
    window.setInterval(() => apply(), 60_000);
    window.addEventListener('focus', () => apply());
    document.addEventListener('visibilitychange', () => apply());
})();
