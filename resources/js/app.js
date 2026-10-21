// Nebula particle loader — a reusable, full-screen "constellation nucleus"
// overlay for any long-running wait in the app.
//
// Trigger from ANYWHERE:
//   • Livewire (PHP):  $this->dispatch('loader-show', message: 'A processar…')
//                      $this->dispatch('loader-hide')
//   • JavaScript:      window.CMLoader.show('A processar…'); window.CMLoader.hide()
//   • Browser event:   window.dispatchEvent(new CustomEvent('loader-show', {detail:{message}}))
//   • Sync Livewire:   window.CMLoader.busy('A processar…')  ← shows now, auto-hides
//                      when the Livewire request it triggers settles. Use on
//                      wire:click / wire:submit that do slow work in-request.
//
// The markup lives in components/particle-loader.blade.php (rendered once in the
// layout). Alpine is provided by Livewire, so we register on `alpine:init`.

// When a busy()-armed action's Livewire request finishes (ok or error), drop the
// overlay. ponytail: hides on the next settling commit, not strictly the armed
// one — fine for one action at a time; revisit if concurrent busy actions appear.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed, fail }) => {
        const done = () => {
            if (window.CMLoader && window.CMLoader._auto) {
                window.CMLoader._auto = false;
                window.CMLoader.hide();
            }
        };
        succeed(done);
        fail(done);
    });
});

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
            // .busy() arms auto-hide: the next Livewire request to settle hides it.
            window.CMLoader = {
                _auto: false,
                show: (m) => this.show(m),
                hide: () => this.hide(),
                busy: (m) => { window.CMLoader._auto = true; this.show(m); },
            };

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
            const palette = ['#A06E1C', '#8C7330', '#2F5A85', '#5C6E8A', '#23201A'];
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
                        ctx.strokeStyle = `rgba(44,95,99,${o})`;
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
            g.addColorStop(0, 'rgba(160,110,28,0.35)');
            g.addColorStop(0.4, 'rgba(138,74,43,0.12)');
            g.addColorStop(1, 'rgba(138,74,43,0)');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(cx, cy, 96 * pulse, 0, Math.PI * 2);
            ctx.fill();

            // Electric-blue orbital arcs sweeping around the nucleus.
            ctx.lineWidth = 2;
            const R = 56 + Math.sin(this.tick * 0.06) * 4;
            for (let k = 0; k < 3; k++) {
                ctx.globalAlpha = 0.5 - k * 0.14;
                ctx.strokeStyle = 'rgba(35,32,26,0.75)';
                const s = this.tick * 0.02 + k * 2.1;
                ctx.beginPath();
                ctx.arc(cx, cy, R + k * 12, s, s + Math.PI * 1.1);
                ctx.stroke();
            }
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'source-over';
        },
    }));

    // Property walkthrough: click-drag on the filmstrip to mark the sections to
    // animate (1–10 s each), drag a section's edges to adjust it; the player
    // seeks along so the exact start and end frames are visible.
    // Also the clip editor's moment picker (opts: max moments, min/max length).
    window.Alpine.data('sectionPicker', (duration, initial, opts = {}) => ({
        duration,
        max: opts.max ?? 10,
        minLen: opts.min ?? 1,
        maxLen: opts.maxLen ?? 10,
        sections: (initial || []).map((s) => ({ start: +s.start, end: +s.end, note: s.note || '', state: s.state || '' })),
        cursor: 0,
        drag: null,   // { from, to } while marking a new section
        resize: null, // { i, edge } while dragging an edge
        sending: false,

        pct(t) { return (t / this.duration) * 100; },
        label(t) {
            const m = Math.floor(t / 60);
            return `${m}:${(t - m * 60).toFixed(1).padStart(4, '0')}`;
        },
        timeAt(e) {
            const r = this.$refs.strip.getBoundingClientRect();
            return Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * this.duration;
        },
        seek(t) {
            this.cursor = t;
            const v = this.$refs.video;
            if (v && Number.isFinite(t)) { v.pause(); v.currentTime = t; }
        },
        down(e) {
            this.$refs.strip.setPointerCapture?.(e.pointerId);
            const edge = e.target.dataset?.edge;
            if (edge) {
                this.resize = { i: +e.target.dataset.i, edge };
                return;
            }
            const t = this.timeAt(e);
            this.drag = { from: t, to: t };
            this.seek(t);
        },
        move(e) {
            const t = this.timeAt(e);
            if (this.resize) {
                const s = this.sections[this.resize.i];
                const prev = this.sections[this.resize.i - 1];
                const next = this.sections[this.resize.i + 1];
                s.state = '';   // moved: it will be redone
                if (this.resize.edge === 'start') {
                    s.start = Math.max(prev ? prev.end : 0, s.end - this.maxLen, Math.min(t, s.end - this.minLen));
                } else {
                    s.end = Math.min(next ? next.start : this.duration, s.start + this.maxLen, Math.max(t, s.start + this.minLen));
                }
                this.seek(this.resize.edge === 'start' ? s.start : s.end);
            } else if (this.drag) {
                this.drag.to = t;
                this.seek(t);
            }
        },
        up() {
            if (this.drag) {
                let start = Math.min(this.drag.from, this.drag.to);
                let end = Math.max(this.drag.from, this.drag.to);
                end = Math.min(end, start + this.maxLen);
                const clash = this.sections.some((s) => start < s.end && end > s.start);
                if (end - start >= this.minLen && !clash && this.sections.length < this.max) {
                    this.sections.push({ start, end, note: '', state: '' });
                    this.sections.sort((a, b) => a.start - b.start);
                }
            }
            this.drag = null;
            this.resize = null;
        },
        preview(s) {
            const v = this.$refs.video;
            this.seek(s.start);
            const stop = () => { if (v.currentTime >= s.end) { v.pause(); v.removeEventListener('timeupdate', stop); } };
            v.addEventListener('timeupdate', stop);
            v.play();
        },
    }));
});
