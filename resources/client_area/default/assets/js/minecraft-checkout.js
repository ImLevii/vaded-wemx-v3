const registerMinecraftPing = () => {
    window.Alpine.data('minecraftLocationPing', (targets = {}) => ({
        testing: false,
        results: {},
        best: null,
        pingLabel(key) {
            const value = this.results[key];
            if (typeof value === 'number') return `Estimated ping – ${value}ms`;
            return value === null ? 'Ping unavailable' : 'Ping not tested';
        },
        async testLocations() {
            if (this.testing) return;
            this.testing = true;
            this.best = null;
            this.results = {};
            try {
                await Promise.all(Object.entries(targets).map(async ([key, endpoint]) => {
                    try {
                        const url = new URL(endpoint, window.location.origin);
                        if (!['https:', 'http:'].includes(url.protocol)) throw new Error('Invalid endpoint');
                        const samples = [];
                        for (let attempt = 0; attempt < 3; attempt++) {
                            const start = performance.now();
                            await fetch(url, { mode: 'no-cors', credentials: 'omit', cache: 'no-store', signal: AbortSignal.timeout(4000) });
                            samples.push(Math.round(performance.now() - start));
                        }
                        this.results[key] = samples.sort((a, b) => a - b)[1];
                    } catch {
                        this.results[key] = null;
                    }
                }));
                const successful = Object.entries(this.results).filter(([, value]) => typeof value === 'number');
                this.best = successful.sort((a, b) => a[1] - b[1])[0]?.[0] ?? null;
            } finally {
                this.testing = false;
            }
        },
    }));
};

if (window.Alpine) {
    registerMinecraftPing();
} else {
    document.addEventListener('alpine:init', registerMinecraftPing, { once: true });
}
