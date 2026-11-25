// Imoplanta's property page (resources/views/livewire/imoplanta/imovel.blade.php): the 3D viewer, the shot
// planner and the browser-side renders sent for the AI photos. Its own Vite entry, loaded only on that page,
// so three.js stays out of the main bundle. three.js objects live in the closure: Alpine's reactive proxies
// would break them.
import { criarCena, fotografo, miniaturas, planta, Visor } from './imoplanta/cena.js';

/**
 * Renders what the server still needs from this browser — each storey's top view, then each planned shot — and
 * uploads them one by one. Used by the uploader component below, on whichever step is showing.
 */
async function enviarRenders(cena, pendentes, cfg, progresso) {
    let foto = null, falhas = 0;
    try {
        foto = fotografo(cena);
        for (const [k, img] of pendentes.entries()) {
            progresso(`A preparar as vistas: ${k + 1} de ${pendentes.length}… mantenha esta página aberta.`);
            const dados = new FormData();
            dados.append('render', await (img.topo !== undefined ? foto.pngTopo(img.topo) : foto.png(img.camera)), `vista-${img.numero}.png`);
            // redirect 'manual': an expired session's redirect to the login must count as a failure, not a 200
            const r = await fetch(img.envio, { method: 'POST', body: dados, redirect: 'manual', headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' } });
            if (!r.ok && r.status !== 409) falhas++;   // 409: already received (another tab) — fine
        }
    } catch (e) {
        console.error(e);
        falhas++;
    } finally {
        foto?.largar();
    }
    progresso(falhas ? 'Algumas vistas não foram enviadas. Recarregue a página para tentar de novo.' : '');
}

/** The uploader: no viewer, just the scene, the renders still owed, a progress line, and a refresh after. */
const envios = (cfg) => ({
    progresso: '',
    init() {
        if (!cfg.pendentes.length) return;
        let cena;
        try { cena = criarCena(cfg.casa); } catch (e) { console.error(e); this.progresso = 'Não foi possível preparar as vistas neste navegador.'; return; }
        this.$nextTick(async () => {
            await enviarRenders(cena, cfg.pendentes, cfg, (t) => { this.progresso = t; });
            this.$wire.$refresh();
        });
    },
});

const componente = (cfg) => {
    let cena = null, visor = null, topo = null, mini = null, espera = null;
    const s = cfg.vista.u2 - cfg.vista.u1;

    return {
        erro: null, telhado: true, niveis: 1, piso: 0,
        // shot plan (the planner exists only while the model can change)
        editavel: cfg.editavel, cams: cfg.cameras, max: cfg.max, sel: 0, arrasto: null, moveu: false, guardado: true,
        tiro: null, quadro: null,
        // a preview of every shot, under the 3D view
        minis: [],

        init() {
            try {
                cena = criarCena(cfg.casa);
            } catch (e) {
                console.error(e);
                this.erro = 'Não foi possível construir o modelo 3D.';
                return;
            }
            this.niveis = cena.niveis;
            this.piso = cena.niveis - 1;
            try {
                visor = new Visor(cena, this.$refs.tela, (q) => { this.quadro = q; });
                if (this.$refs.topo) topo = planta(cena, this.$refs.topo, cfg.vista);   // also read-only, once the images exist
            } catch (e) {
                console.error(e);
                this.erro = 'Este navegador não consegue mostrar o modelo 3D (WebGL).';
                return;
            }
            try { mini = miniaturas(cena); this.$nextTick(() => this.atualizarMinis()); } catch (e) { console.error(e); }
        },
        destroy() { visor?.largar(); topo?.largar(); mini?.largar(); },

        /** Re-draws the previews: all of them, or only camera i (after a change to it, once it settles). */
        atualizarMinis(i = null) {
            if (!mini) return;
            if (i === null) { this.minis = this.cams.map((c) => mini.url(c)); return; }
            clearTimeout(espera);
            espera = setTimeout(() => { if (this.cams[i]) this.minis[i] = mini.url(this.cams[i]); }, 250);
        },
        escolher(i) { this.sel = i; this.verTiro(i); },
        /** The selected camera's angle on every camera. */
        todasFov() {
            const f = this.cams[this.sel]?.fov;
            if (!f) return;
            this.cams.forEach((c) => { c.fov = f; });
            this.guardado = false;
            this.atualizarMinis();
        },

        aplicar() { if (visor) visor.vis = { telhado: this.telhado, piso: this.piso }; },
        alternarTelhado() { this.telhado = !this.telhado; this.aplicar(); },

        // ── preview of a shot in the 3D view ──
        verTiro(i, animar = true) {
            if (!visor || !this.cams[i]) return;
            this.tiro = i;
            visor.ver(this.cams[i], animar);
        },
        vistaLivre() { this.tiro = null; visor?.livre(); },
        rotulo() {
            const c = this.cams[this.tiro];
            return c ? `Fotografia ${this.tiro + 1} · ${c.altura > 8 ? 'drone ' : ''}${String(c.altura).replace('.', ',')} m · ${Math.round(c.fov)}°` : '';
        },
        quadroEstilo() {
            const q = this.quadro;
            return q ? `left:${q.x}px;top:${q.y}px;width:${q.w}px;height:${q.h}px` : 'display:none';
        },

        // ── planner (SVG cones over the live top view, in plan metres) ──
        cone(c) {
            const a = c.yaw * Math.PI / 180, h = c.fov * Math.PI / 360, L = s * 0.08;
            const p = (ang) => (c.u + Math.sin(ang) * L).toFixed(3) + ',' + (c.v - Math.cos(ang) * L).toFixed(3);
            return `${c.u},${c.v} ${p(a - h)} ${p(a + h)}`;
        },
        ponta(c) { const a = c.yaw * Math.PI / 180, L = s * 0.1; return [c.u + Math.sin(a) * L, c.v - Math.cos(a) * L]; },
        ponto(ev) {
            const svg = this.$refs.svg, pt = svg.createSVGPoint();
            pt.x = ev.clientX; pt.y = ev.clientY;
            return pt.matrixTransform(svg.getScreenCTM().inverse());
        },
        marcas() {
            const f = (n) => n.toFixed(3);
            return this.cams.map((c, i) => {
                const on = i === this.sel, cor = on ? '#F06052' : '#05B8E6', [px, py] = this.ponta(c);
                return `<polygon points="${this.cone(c)}" fill="${on ? 'rgba(240,96,82,.45)' : 'rgba(5,184,230,.35)'}" stroke="${cor}" stroke-width="${f(s * 0.002)}" data-i="${i}" data-modo="mover" style="cursor:${this.editavel ? 'move' : 'pointer'}"/>`
                    + `<line x1="${c.u}" y1="${c.v}" x2="${f(px)}" y2="${f(py)}" stroke="${cor}" stroke-width="${f(s * 0.002)}" pointer-events="none"/>`
                    + `<circle cx="${f(px)}" cy="${f(py)}" r="${f(s * 0.012)}" fill="#fff" stroke="${cor}" stroke-width="${f(s * 0.003)}" data-i="${i}" data-modo="rodar" style="cursor:${this.editavel ? 'grab' : 'pointer'}"><title>Rodar</title></circle>`
                    + `<circle cx="${c.u}" cy="${c.v}" r="${f(s * 0.018)}" fill="${on ? '#F06052' : '#0B1F3A'}" stroke="#fff" stroke-width="${f(s * 0.002)}" data-i="${i}" data-modo="mover" style="cursor:${this.editavel ? 'move' : 'pointer'}"/>`
                    + `<text x="${c.u}" y="${c.v}" font-size="${f(s * 0.022)}" fill="#fff" text-anchor="middle" dominant-baseline="central" font-weight="600" pointer-events="none">${i + 1}</text>`;
            }).join('');
        },
        comecar(ev) {
            const d = ev.target.closest('[data-modo]')?.dataset;
            if (!d) return;
            ev.preventDefault();
            if (!this.editavel) { this.sel = +d.i; this.verTiro(this.sel); return; }   // fixed cameras: click = preview
            this.$refs.svg.setPointerCapture?.(ev.pointerId);
            this.sel = +d.i; this.arrasto = d.modo; this.moveu = false;
        },
        mover(ev) {
            if (!this.arrasto) return;
            const p = this.ponto(ev), c = this.cams[this.sel];
            if (this.arrasto === 'mover') {
                if (!this.moveu && Math.hypot(p.x - c.u, p.y - c.v) < s * 0.004) return;   // a click, not a drag (yet)
                c.u = +p.x.toFixed(2); c.v = +p.y.toFixed(2);
            } else c.yaw = +((Math.atan2(p.x - c.u, -(p.y - c.v)) * 180 / Math.PI + 360) % 360).toFixed(1);
            this.moveu = true;
            this.mudou();
        },
        largar() {
            if (this.arrasto === 'mover' && !this.moveu) this.verTiro(this.sel);   // click on a camera: preview its shot
            this.arrasto = null;
        },
        /** Any change to a camera (drag, rotate, sliders): the plan must be saved again and the 3D view shows its shot live. */
        mudou() {
            this.guardado = false;
            this.verTiro(this.sel, false);
            this.atualizarMinis(this.sel);
        },
        adicionar() {
            if (this.cams.length >= this.max) return;
            this.cams.push({ u: +((cfg.vista.u1 + cfg.vista.u2) / 2).toFixed(2), v: +(cfg.vista.v2 - s * 0.12).toFixed(2), altura: 1.6, yaw: 0, fov: cfg.fov });
            this.sel = this.cams.length - 1;
            this.minis.push('');
            this.mudou();
        },
        remover() {
            if (this.cams.length <= 1) return;
            this.cams.splice(this.sel, 1);
            this.minis.splice(this.sel, 1);
            if (this.tiro !== null) this.vistaLivre();
            this.sel = 0;
            this.guardado = false;
        },
        async guardar() { this.guardado = (await this.$wire.guardarCameras(JSON.parse(JSON.stringify(this.cams)))) === true; },
        /** Step 1 → 2: saves the cameras and fixes them and the model (the server moves the property on). */
        async avancar() {
            if (!confirm('Continuar para os acabamentos e as plantas 3D? Depois disso o modelo 3D e as câmaras ficam fixos.')) return;
            this.guardado = (await this.$wire.avancar(JSON.parse(JSON.stringify(this.cams)))) === true;
        },
    };
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('imoplanta', componente);
    window.Alpine.data('imoplantaEnvios', envios);
});
