/*
 * Vaded seasonal page effects (snow, confetti, petals, leaves, ...).
 * Loaded on demand by vaded-theme.js only when an effect should play, and drawn
 * on one pointer-events-free canvas in the active edition's colours.
 *
 *   const field = WemxEffects.mount(document.body, { effect: 'snow', palette: ['#acd6eb'], dark: true });
 *   field.update({ ...options, intensity: 'heavy' }); field.destroy();
 *
 * Options: effect, palette (hex list), dark, intensity (light|normal|heavy),
 * speed (calm|normal|lively), strength (subtle|normal|bold). A host other than <body>
 * gets a contained canvas sized to that element (the admin preview).
 */
(() => {
    if (window.WemxEffects) return;

    const TAU = Math.PI * 2;
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = list => list[Math.floor(Math.random() * list.length)];
    const rgb = hex => {
        let value = String(hex || '').replace('#', '');
        if (value.length === 3) value = value.split('').map(c => c + c).join('');
        const n = /^[0-9a-f]{6}$/i.test(value) ? parseInt(value, 16) : 0xffffff;
        return [n >> 16 & 255, n >> 8 & 255, n & 255];
    };
    const rgba = (hex, alpha) => { const [r, g, b] = rgb(hex); return `rgba(${r},${g},${b},${alpha})`; };
    const INTENSITY = { light: 0.55, normal: 1, heavy: 1.75 };
    const SPEED = { calm: 0.6, normal: 1, lively: 1.5 };
    const STRENGTH = { subtle: 0.45, normal: 0.85, bold: 1 };
    const REFERENCE_AREA = 1440 * 900;

    // Unit shapes centred on (0, 0), roughly one unit in radius.
    const path = d => new Path2D(d);
    const polar = (points, radius) => {
        const shape = new Path2D();
        for (let i = 0; i <= points; i++) {
            const angle = i / points * TAU, r = radius(angle, i);
            const x = Math.sin(angle) * r, y = -Math.cos(angle) * r;
            if (i) shape.lineTo(x, y); else shape.moveTo(x, y);
        }
        shape.closePath();
        return shape;
    };
    const shapes = {
        heart: path('M 0 0.9 C -1.2 0 -0.9 -1 -0.45 -1 C -0.15 -1 0 -0.75 0 -0.6 C 0 -0.75 0.15 -1 0.45 -1 C 0.9 -1 1.2 0 0 0.9 Z'),
        petal: path('M -0.24 -1 L 0 -0.74 L 0.24 -1 C 0.95 -0.6 0.7 0.62 0 1 C -0.7 0.62 -0.95 -0.6 -0.24 -1 Z'),
        leaf: path('M 0 -1 C 0.62 -0.62 0.62 0.38 0 0.92 C -0.62 0.38 -0.62 -0.62 0 -1 Z'),
        vein: path('M 0 -0.8 L 0 1.18'),
        maple: polar(120, angle => 0.46 + 0.54 * Math.pow(Math.abs(Math.cos(angle * 2.5)), 1.35)),
        star4: path('M 0 -1 Q 0.14 -0.14 1 0 Q 0.14 0.14 0 1 Q -0.14 0.14 -1 0 Q -0.14 -0.14 0 -1 Z'),
        star5: polar(10, (angle, i) => (i % 2 ? 0.42 : 1)),
        crystal: (() => {
            const shape = new Path2D();
            for (let i = 0; i < 6; i++) {
                const angle = i / 6 * TAU, sx = Math.sin(angle), sy = -Math.cos(angle);
                shape.moveTo(0, 0); shape.lineTo(sx, sy);
                for (const [distance, length] of [[0.5, 0.3], [0.76, 0.2]]) {
                    for (const side of [-1, 1]) {
                        const branch = angle + side * Math.PI / 3;
                        shape.moveTo(sx * distance, sy * distance);
                        shape.lineTo(sx * distance + Math.sin(branch) * length, sy * distance - Math.cos(branch) * length);
                    }
                }
            }
            return shape;
        })(),
    };

    // Soft round glow, pre-rendered once per colour.
    const glows = new Map();
    const glow = hex => {
        if (glows.has(hex)) return glows.get(hex);
        const sprite = document.createElement('canvas');
        sprite.width = sprite.height = 64;
        const g = sprite.getContext('2d'), gradient = g.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, rgba(hex, 1)); gradient.addColorStop(0.22, rgba(hex, 0.6)); gradient.addColorStop(1, rgba(hex, 0));
        g.fillStyle = gradient; g.fillRect(0, 0, 64, 64);
        glows.set(hex, sprite);
        return sprite;
    };

    // Transform for one particle: translate, rotate, then scale (sx/sy flip for tumbling shapes).
    const place = (ctx, env, x, y, size, rotation = 0, sx = 1, sy = 1) => {
        const k = size * env.dpr, c = Math.cos(rotation) * k, s = Math.sin(rotation) * k;
        ctx.setTransform(c * sx, s * sx, -s * sy, c * sy, x * env.dpr, y * env.dpr);
    };
    const drawGlow = (ctx, env, hex, x, y, radius) => {
        ctx.setTransform(env.dpr, 0, 0, env.dpr, 0, 0);
        ctx.drawImage(glow(hex), x - radius, y - radius, radius * 2, radius * 2);
    };
    const enterTop = (p, env, initial) => { p.x = rand(-30, env.w + 30); p.y = initial ? rand(-30, env.h) : rand(-70, -12); };
    const enterBottom = (p, env, initial) => { p.x = rand(-20, env.w + 20); p.y = initial ? rand(0, env.h + 20) : env.h + rand(12, 70); };
    const sway = (p, env) => Math.sin(env.t * p.freq + p.phase) * p.sway;
    const tumble = flip => 0.3 + 0.7 * Math.abs(Math.cos(flip));
    const snowColours = env => (env.dark ? ['#ffffff', '#e4f2ff'] : ['#9cc3da', '#7aaac7']);

    const snowflake = {
        make(p, env, initial) {
            p.depth = 0.35 + 0.65 * Math.pow(Math.random(), 1.4);
            p.crystal = Math.random() < 0.2;
            p.size = p.crystal ? rand(4, 7.5) * (0.6 + p.depth * 0.5) : rand(0.9, 2.6) * p.depth + 0.4;
            p.vy = rand(16, 32) * (0.45 + p.depth);
            p.sway = rand(8, 28) * p.depth; p.freq = rand(0.35, 1.1); p.phase = rand(0, TAU);
            p.rotation = rand(0, TAU); p.spin = rand(-0.7, 0.7);
            p.alpha = rand(0.5, 0.95) * (0.55 + 0.45 * p.depth);
            enterTop(p, env, initial);
        },
        step(p, dt, env) {
            p.y += p.vy * env.speed * dt; p.rotation += p.spin * env.speed * dt;
            return p.y < env.h + 24;
        },
        draw(ctx, p, env) {
            const [flake, crystal] = snowColours(env), x = p.x + sway(p, env);
            ctx.globalAlpha = p.alpha * env.opacity;
            if (p.crystal) {
                place(ctx, env, x, p.y, p.size, p.rotation);
                ctx.lineWidth = 0.13; ctx.strokeStyle = crystal; ctx.stroke(shapes.crystal);
            } else {
                ctx.setTransform(env.dpr, 0, 0, env.dpr, 0, 0);
                ctx.fillStyle = flake; ctx.beginPath(); ctx.arc(x, p.y, p.size, 0, TAU); ctx.fill();
            }
        },
    };

    const effects = {
        snow: { count: 95, ...snowflake },

        festive: {
            count: 85,
            make(p, env, initial) {
                p.light = Math.random() < 0.2;
                if (!p.light) return snowflake.make(p, env, initial);
                p.colour = pick(env.palette); p.size = rand(2, 3.6); p.vy = rand(7, 15);
                p.sway = rand(10, 24); p.freq = rand(0.3, 0.7); p.phase = rand(0, TAU); p.twinkle = rand(1.4, 3.2);
                enterTop(p, env, initial);
            },
            step(p, dt, env) { return p.light ? (p.y += p.vy * env.speed * dt) < env.h + 30 : snowflake.step(p, dt, env); },
            draw(ctx, p, env) {
                if (!p.light) return snowflake.draw(ctx, p, env);
                const x = p.x + sway(p, env), shine = 0.5 + 0.5 * Math.sin(env.t * p.twinkle + p.phase);
                ctx.globalAlpha = env.opacity * (0.45 + 0.55 * shine);
                drawGlow(ctx, env, p.colour, x, p.y, p.size * 5.5);
                ctx.globalAlpha = env.opacity * (0.7 + 0.3 * shine); ctx.fillStyle = p.colour;
                ctx.beginPath(); ctx.arc(x, p.y, p.size * 0.55, 0, TAU); ctx.fill();
            },
        },

        confetti: {
            count: 75,
            make(p, env, initial) {
                p.colour = pick(env.palette); p.dot = Math.random() < 0.16;
                p.w = rand(4, 7.5); p.h = p.w * rand(1.4, 2.3);
                p.vy = rand(42, 88); p.vx = rand(-14, 14); p.sway = rand(10, 26); p.freq = rand(0.8, 1.8); p.phase = rand(0, TAU);
                p.rotation = rand(0, TAU); p.spin = rand(-3, 3); p.flip = rand(0, TAU); p.flipRate = rand(3, 8);
                p.alpha = rand(0.75, 0.95);
                enterTop(p, env, initial);
            },
            step(p, dt, env) {
                p.y += p.vy * env.speed * dt; p.x += p.vx * env.speed * dt;
                p.rotation += p.spin * env.speed * dt; p.flip += p.flipRate * env.speed * dt;
                return p.y < env.h + 24;
            },
            draw(ctx, p, env) {
                ctx.globalAlpha = p.alpha * env.opacity; ctx.fillStyle = p.colour;
                if (p.dot) {
                    ctx.setTransform(env.dpr, 0, 0, env.dpr, 0, 0);
                    ctx.beginPath(); ctx.arc(p.x + sway(p, env), p.y, p.w * 0.42, 0, TAU); ctx.fill();
                    return;
                }
                place(ctx, env, p.x + sway(p, env), p.y, 1, p.rotation, 1, Math.cos(p.flip));
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            },
        },

        hearts: {
            count: 24,
            make(p, env, initial) {
                p.colour = pick(env.palette); p.size = rand(5.5, 12); p.vy = rand(14, 30);
                p.sway = rand(12, 30); p.freq = rand(0.4, 0.9); p.phase = rand(0, TAU); p.alpha = rand(0.35, 0.7);
                enterBottom(p, env, initial);
            },
            step(p, dt, env) { p.y -= p.vy * env.speed * dt; return p.y > -30; },
            draw(ctx, p, env) {
                const fade = Math.min(1, Math.max(0, p.y / (env.h * 0.3)));
                const pulse = 1 + 0.08 * Math.sin(env.t * 3 + p.phase);
                ctx.globalAlpha = p.alpha * fade * env.opacity; ctx.fillStyle = p.colour;
                place(ctx, env, p.x + sway(p, env), p.y, p.size * pulse, Math.sin(env.t * p.freq + p.phase) * 0.25);
                ctx.fill(shapes.heart);
            },
        },

        petals: {
            count: 42,
            make(p, env, initial) {
                p.colour = pick(env.palette); p.size = rand(5.5, 9.5);
                p.vy = rand(22, 44); p.vx = rand(12, 34); p.sway = rand(10, 24); p.freq = rand(0.5, 1.2); p.phase = rand(0, TAU);
                p.rotation = rand(0, TAU); p.spin = rand(-1.4, 1.4); p.flip = rand(0, TAU); p.flipRate = rand(1.5, 3.5);
                p.alpha = rand(0.6, 0.9);
                enterTop(p, env, initial);
                if (!initial) p.x = rand(-env.w * 0.35, env.w);
            },
            step(p, dt, env) {
                p.y += p.vy * env.speed * dt; p.x += p.vx * env.speed * dt;
                p.rotation += p.spin * env.speed * dt; p.flip += p.flipRate * env.speed * dt;
                return p.y < env.h + 24 && p.x < env.w + 40;
            },
            draw(ctx, p, env) {
                ctx.globalAlpha = p.alpha * env.opacity; ctx.fillStyle = p.colour;
                place(ctx, env, p.x + sway(p, env), p.y, p.size, p.rotation, tumble(p.flip), 1);
                ctx.fill(shapes.petal);
            },
        },

        leaves: {
            count: 30,
            make(p, env, initial) {
                p.colour = pick(env.palette); p.maple = Math.random() < 0.5; p.size = rand(7, 13);
                p.vy = rand(26, 52); p.sway = rand(28, 60); p.freq = rand(0.5, 1); p.phase = rand(0, TAU);
                p.rotation = rand(0, TAU); p.spin = rand(-0.8, 0.8); p.flip = rand(0, TAU); p.flipRate = rand(1, 2.6);
                p.alpha = rand(0.7, 0.95);
                enterTop(p, env, initial);
            },
            step(p, dt, env) {
                p.y += p.vy * env.speed * dt; p.rotation += p.spin * env.speed * dt; p.flip += p.flipRate * env.speed * dt;
                return p.y < env.h + 30;
            },
            draw(ctx, p, env) {
                const rock = Math.sin(env.t * p.freq + p.phase);
                ctx.globalAlpha = p.alpha * env.opacity; ctx.fillStyle = p.colour;
                place(ctx, env, p.x + rock * p.sway, p.y, p.size, p.rotation + rock * 0.8, tumble(p.flip), 1);
                ctx.fill(p.maple ? shapes.maple : shapes.leaf);
                ctx.lineWidth = 0.08; ctx.strokeStyle = 'rgba(0,0,0,.22)'; ctx.stroke(shapes.vein);
            },
        },

        clovers: {
            count: 26,
            make(p, env, initial) {
                p.glint = Math.random() < 0.18;
                p.colour = p.glint ? (env.palette[1] || env.palette[0]) : pick([env.palette[0], env.palette[2] || env.palette[0]]);
                p.leaves = Math.random() < 0.12 ? 4 : 3; p.size = p.glint ? rand(3, 5.5) : rand(7.5, 12);
                p.vy = rand(18, 36); p.sway = rand(12, 28); p.freq = rand(0.4, 0.9); p.phase = rand(0, TAU);
                p.rotation = rand(0, TAU); p.spin = rand(-0.9, 0.9); p.alpha = rand(0.6, 0.9);
                enterTop(p, env, initial);
            },
            step(p, dt, env) { p.y += p.vy * env.speed * dt; p.rotation += p.spin * env.speed * dt; return p.y < env.h + 24; },
            draw(ctx, p, env) {
                const x = p.x + sway(p, env);
                ctx.fillStyle = p.colour;
                if (p.glint) {
                    ctx.globalAlpha = env.opacity * (0.3 + 0.7 * Math.abs(Math.sin(env.t * 2 + p.phase)));
                    place(ctx, env, x, p.y, p.size, p.rotation); ctx.fill(shapes.star4);
                    return;
                }
                ctx.globalAlpha = p.alpha * env.opacity;
                for (let i = 0; i < p.leaves; i++) {
                    const angle = p.rotation + i / p.leaves * TAU;
                    place(ctx, env, x + Math.sin(angle) * p.size * 0.42, p.y - Math.cos(angle) * p.size * 0.42, p.size * 0.44, angle);
                    ctx.fill(shapes.heart);
                }
                place(ctx, env, x, p.y, p.size, p.rotation + Math.PI * 0.85);
                ctx.lineWidth = 0.09; ctx.strokeStyle = p.colour; ctx.beginPath(); ctx.moveTo(0, 0); ctx.quadraticCurveTo(0.12, 0.5, 0.05, 0.95); ctx.stroke();
            },
        },

        sparkles: {
            count: 42,
            make(p, env, initial) {
                p.colour = Math.random() < 0.25 ? (env.dark ? '#ffffff' : '#9aa9bf') : pick(env.palette);
                p.five = Math.random() < 0.55; p.size = rand(3.5, 8); p.vy = rand(5, 14);
                p.twinkle = rand(1.2, 3); p.phase = rand(0, TAU); p.rotation = rand(0, TAU); p.spin = rand(-0.5, 0.5);
                enterTop(p, env, initial);
            },
            step(p, dt, env) { p.y += p.vy * env.speed * dt; p.rotation += p.spin * env.speed * dt; return p.y < env.h + 16; },
            draw(ctx, p, env) {
                const shine = Math.max(0, Math.sin(env.t * p.twinkle + p.phase));
                ctx.globalAlpha = env.opacity * (0.3 + 0.7 * shine); ctx.fillStyle = p.colour;
                place(ctx, env, p.x, p.y, p.size * (0.75 + 0.35 * shine), p.rotation);
                ctx.fill(p.five ? shapes.star5 : shapes.star4);
            },
        },

        glints: {
            count: 34,
            make(p, env, initial) {
                p.colour = pick(env.palette); p.size = rand(1.6, 4.2); p.vy = rand(6, 16);
                p.sway = rand(10, 30); p.freq = rand(0.2, 0.6); p.phase = rand(0, TAU); p.pulse = rand(0.8, 2);
                enterBottom(p, env, initial);
            },
            step(p, dt, env) { p.y -= p.vy * env.speed * dt; return p.y > -30; },
            draw(ctx, p, env) {
                ctx.globalAlpha = env.opacity * (0.3 + 0.55 * (0.5 + 0.5 * Math.sin(env.t * p.pulse + p.phase)));
                drawGlow(ctx, env, p.colour, p.x + sway(p, env), p.y, p.size * 5);
            },
        },

        bats: {
            count: 30,
            make(p, env, initial, index) {
                p.bat ??= index % 5 === 0;
                if (!p.bat) {
                    p.colour = env.palette[1] || env.palette[0]; p.size = rand(1.4, 3); p.vy = rand(10, 22);
                    p.sway = rand(8, 20); p.freq = rand(0.5, 1.2); p.phase = rand(0, TAU); p.pulse = rand(2, 4);
                    return enterBottom(p, env, initial);
                }
                p.direction = Math.random() < 0.5 ? 1 : -1; p.size = rand(7, 12); p.vx = rand(40, 75);
                p.flap = rand(7, 11); p.phase = rand(0, TAU); p.bob = rand(8, 22); p.freq = rand(0.6, 1.3);
                p.baseY = rand(env.h * 0.06, env.h * 0.7);
                p.x = initial ? rand(0, env.w) : (p.direction > 0 ? -rand(24, 90) : env.w + rand(24, 90));
            },
            step(p, dt, env) {
                if (!p.bat) { p.y -= p.vy * env.speed * dt; return p.y > -20; }
                p.x += p.vx * p.direction * env.speed * dt;
                p.y = p.baseY + Math.sin(env.t * p.freq + p.phase) * p.bob;
                return p.x > -200 && p.x < env.w + 200;
            },
            draw(ctx, p, env) {
                if (!p.bat) {
                    ctx.globalAlpha = env.opacity * (0.3 + 0.5 * Math.abs(Math.sin(env.t * p.pulse + p.phase)));
                    return drawGlow(ctx, env, p.colour, p.x + sway(p, env), p.y, p.size * 4);
                }
                const flap = Math.sin(env.t * p.flap * env.speed + p.phase);
                ctx.globalAlpha = env.opacity * (env.dark ? 0.6 : 0.5);
                ctx.fillStyle = env.dark ? (env.palette[0] || '#9e82b8') : '#3d2f4c';
                place(ctx, env, p.x, p.y, p.size, Math.sin(env.t * p.freq + p.phase) * 0.12, p.direction, 1);
                ctx.beginPath();
                for (const side of [-1, 1]) {
                    ctx.moveTo(0, -0.08);
                    ctx.quadraticCurveTo(0.45 * side, -0.35 - 0.35 * flap, side, -0.12 - 0.55 * flap);
                    ctx.quadraticCurveTo(0.86 * side, 0.04 - 0.28 * flap, 0.72 * side, 0.18 - 0.22 * flap);
                    ctx.quadraticCurveTo(0.6 * side, 0.02, 0.45 * side, 0.2 - 0.1 * flap);
                    ctx.quadraticCurveTo(0.32 * side, 0.06, 0.18 * side, 0.22);
                    ctx.lineTo(0, 0.12);
                    ctx.closePath();
                }
                ctx.fill();
                ctx.beginPath(); ctx.ellipse(0, 0.04, 0.12, 0.22, 0, 0, TAU); ctx.fill();
                ctx.beginPath();
                ctx.moveTo(-0.1, -0.12); ctx.lineTo(-0.07, -0.3); ctx.lineTo(-0.01, -0.15);
                ctx.moveTo(0.1, -0.12); ctx.lineTo(0.07, -0.3); ctx.lineTo(0.01, -0.15);
                ctx.fill();
            },
        },
    };

    class Field {
        constructor(host, options) {
            this.host = host;
            this.page = host === document.body;
            this.canvas = document.createElement('canvas');
            this.canvas.className = 'vh-effects-canvas';
            this.canvas.setAttribute('aria-hidden', 'true');
            if (!this.page) this.canvas.dataset.effectsContained = 'true';
            this.ctx = this.canvas.getContext('2d');
            this.particles = [];
            this.env = { w: 1, h: 1, dpr: 1, t: 0, speed: 1, opacity: 1, dark: true, palette: ['#ffffff'] };
            this.frame = this.frame.bind(this);
            this.onResize = () => { this.needsResize = true; };
            if (this.page) window.addEventListener('resize', this.onResize, { passive: true });
            else if ('ResizeObserver' in window) { this.observer = new ResizeObserver(this.onResize); this.observer.observe(host); }
            host.appendChild(this.canvas);
            this.resize();
            this.update(options);
            this.raf = requestAnimationFrame(this.frame);
        }

        connected() { return this.canvas.isConnected; }

        update(options = {}) {
            const definition = effects[options.effect];
            if (!definition) return this.destroy();
            const palette = (Array.isArray(options.palette) ? options.palette : []).filter(c => /^#[0-9a-f]{3,6}$/i.test(c));
            const look = JSON.stringify([options.effect, palette, !!options.dark]);
            this.definition = definition;
            this.env.palette = palette.length ? palette : ['#ffffff'];
            this.env.dark = !!options.dark;
            this.env.speed = SPEED[options.speed] || 1;
            this.env.opacity = STRENGTH[options.strength] || STRENGTH.normal;
            this.canvas.dataset.effect = options.effect;
            this.intensity = INTENSITY[options.intensity] || 1;
            if (look !== this.look) { this.look = look; this.particles = []; }
            this.populate(this.particles.length === 0);
            return this;
        }

        target() {
            const area = (this.env.w * this.env.h) / REFERENCE_AREA;
            const scale = this.page ? Math.min(1.5, Math.max(0.3, area)) : Math.min(1, Math.max(0.2, area * 2.4));
            return Math.max(6, Math.round(this.definition.count * this.intensity * scale));
        }

        populate(initial) {
            const wanted = this.target();
            if (this.particles.length > wanted) this.particles.length = wanted;
            while (this.particles.length < wanted) {
                const particle = {};
                this.definition.make(particle, this.env, true, this.particles.length);
                this.particles.push(particle);
            }
            if (initial) this.env.t = rand(0, 100);
        }

        resize() {
            this.needsResize = false;
            const box = this.page ? { width: window.innerWidth, height: window.innerHeight } : this.host.getBoundingClientRect();
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            this.env.w = Math.max(1, Math.round(box.width));
            this.env.h = Math.max(1, Math.round(box.height));
            this.env.dpr = dpr;
            this.canvas.width = Math.round(this.env.w * dpr);
            this.canvas.height = Math.round(this.env.h * dpr);
            if (this.definition) this.populate(false);
        }

        frame(now) {
            this.raf = 0;
            if (!this.canvas.isConnected) { this.destroy(); return; }
            const dt = this.last ? Math.min(0.05, Math.max(0, (now - this.last) / 1000)) : 0.016;
            this.last = now;
            if (this.needsResize) this.resize();
            const { ctx, env, definition } = this;
            env.t += dt;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.globalAlpha = 1;
            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            ctx.lineCap = 'round';
            for (const particle of this.particles) {
                if (!definition.step(particle, dt, env)) definition.make(particle, env, false);
                definition.draw(ctx, particle, env);
            }
            this.raf = requestAnimationFrame(this.frame);
        }

        destroy() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = 0;
            window.removeEventListener('resize', this.onResize);
            this.observer?.disconnect();
            this.canvas.remove();
            this.particles = [];
        }
    }

    window.WemxEffects = {
        effects: Object.keys(effects),
        mount: (host, options) => new Field(host, options),
    };
    document.dispatchEvent(new CustomEvent('wemx-effects:ready'));
})();
