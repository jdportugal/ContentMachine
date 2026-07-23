// Nebula particle loader — a reusable, full-screen "constellation nucleus"
// overlay for any long-running wait in the app.
//
// Trigger from ANYWHERE:
//   • Livewire (PHP):  $this->dispatch('loader-show', message: 'A processar…')
//                      $this->dispatch('loader-hide')
//   • JavaScript:      window.CMLoader.show('A processar…'); window.CMLoader.hide()
//   • Browser event:   window.dispatchEvent(new CustomEvent('loader-show', {detail:{message}}))
//
// The markup lives in components/particle-loader.blade.php (rendered once in the
// layout). Alpine is provided by Livewire, so we register on `alpine:init`.

document.addEventListener('alpine:init', () => {
    window.Alpine.data('particleLoader', () => ({
        open: false,
        message: '',
        raf: null,
        ctx: null,
        canvas: null,
        dpr: 1,
        w: 0,
        h: 0,
        tick: 0,
        particles: [],
        reduced: false,

        init() {
            this.canvas = this.$refs.canvas;
            this.ctx = this.canvas.getContext('2d');
            this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            window.addEventListener('loader-show', (e) => this.show(e?.detail?.message));
            window.addEventListener('loader-hide', () => this.hide());
            window.addEventListener('resize', () => { if (this.open) this.resize(); });

            // Global helper for non-Livewire callers.
            window.CMLoader = { show: (m) => this.show(m), hide: () => this.hide() };

            this.$watch('open', (v) => (v ? this.start() : this.stop()));
        },

        show(message) {
            this.message = message || 'A processar';
            this.open = true;
        },
        hide() {
            this.open = false;
        },

        resize() {
            this.dpr = Math.min(window.devicePixelRatio || 1, 2);
            this.w = window.innerWidth;
            this.h = window.innerHeight;
            this.canvas.width = this.w * this.dpr;
            this.canvas.height = this.h * this.dpr;
            this.canvas.style.width = this.w + 'px';
            this.canvas.style.height = this.h + 'px';
            this.ctx.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
        },

        seed() {
            const count = Math.max(28, Math.min(96, Math.round((this.w * this.h) / 22000)));
            const palette = ['#FFB347', '#FFD98A', '#5A7BFF', '#7DA2FF', '#EAF0FF'];
            this.particles = Array.from({ length: count }, () => {
                const a = Math.random() * Math.PI * 2;
                const r = 46 + Math.random() * Math.max(this.w, this.h) * 0.48;
                return {
                    a,
                    r,
                    x: 0,
                    y: 0,
                    spin: (Math.random() * 0.4 + 0.15) * (Math.random() < 0.5 ? -1 : 1) * 0.004,
                    size: Math.random() * 1.8 + 0.6,
                    color: palette[(Math.random() * palette.length) | 0],
                    tw: Math.random() * Math.PI * 2,
                };
            });
        },

        start() {
            this.resize();
            this.seed();
            if (this.reduced) {
                this.draw(); // single static frame — respects reduced motion
                return;
            }
            const frame = () => {
                this.draw();
                this.raf = requestAnimationFrame(frame);
            };
            this.raf = requestAnimationFrame(frame);
        },
        stop() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
        },

        draw() {
            const ctx = this.ctx;
            const cx = this.w / 2;
            const cy = this.h / 2;
            this.tick++;
            ctx.clearRect(0, 0, this.w, this.h);
            ctx.globalCompositeOperation = 'lighter';

            // Orbit the particles around the nucleus (slight ellipse + breathing).
            for (const p of this.particles) {
                p.a += p.spin;
                const rr = p.r + Math.sin(this.tick * 0.01 + p.tw) * 12;
                p.x = cx + Math.cos(p.a) * rr;
                p.y = cy + Math.sin(p.a) * rr * 0.82;
            }

            // Constellation lines between nearby particles.
            for (let i = 0; i < this.particles.length; i++) {
                const a = this.particles[i];
                for (let j = i + 1; j < this.particles.length; j++) {
                    const b = this.particles[j];
                    const dx = a.x - b.x;
                    const dy = a.y - b.y;
                    const d2 = dx * dx + dy * dy;
                    if (d2 < 130 * 130) {
                        const o = (1 - Math.sqrt(d2) / 130) * 0.22;
                        ctx.strokeStyle = `rgba(90,123,255,${o})`;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.stroke();
                    }
                }
            }

            // Glowing particle dots (twinkle).
            for (const p of this.particles) {
                ctx.globalAlpha = 0.55 + 0.45 * Math.sin(this.tick * 0.05 + p.tw);
                ctx.fillStyle = p.color;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.globalAlpha = 1;

            // Molten-gold nucleus glow.
            const pulse = 1 + Math.sin(this.tick * 0.06) * 0.12;
            const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, 96 * pulse);
            g.addColorStop(0, 'rgba(255,213,138,0.55)');
            g.addColorStop(0.4, 'rgba(255,138,61,0.20)');
            g.addColorStop(1, 'rgba(255,138,61,0)');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(cx, cy, 96 * pulse, 0, Math.PI * 2);
            ctx.fill();

            // Electric-blue orbital arcs sweeping around the nucleus.
            ctx.lineWidth = 2;
            const R = 56 + Math.sin(this.tick * 0.06) * 4;
            for (let k = 0; k < 3; k++) {
                ctx.globalAlpha = 0.5 - k * 0.14;
                ctx.strokeStyle = 'rgba(125,162,255,0.9)';
                const s = this.tick * 0.02 + k * 2.1;
                ctx.beginPath();
                ctx.arc(cx, cy, R + k * 12, s, s + Math.PI * 1.1);
                ctx.stroke();
            }
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'source-over';
        },
    }));
});
