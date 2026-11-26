/*
 * Imoplanta in the browser: one scene (the house from builder.js + daylight) drawn by several renderers —
 * the orbit viewer, the planner's orthographic top view and the 1600x900 shots sent to
 * the AI photographer. Every view of a planned camera goes through cameraDoPlano(), so the preview
 * and the uploaded render frame the same picture.
 *
 * A camera of the shot plan (ImoplantaCasa): {u, v} plan metres, altura (eye height), yaw (compass bearing,
 * 0 = north / up on the plan, clockwise), fov (horizontal, degrees). Eye level looks level (vertical lines
 * stay vertical); above 5 m (drone) it pitches down towards the house, as a real-estate drone shot.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { buildCasa, limites } from './builder.js';

export const ASPETO = 16 / 9;
export const RENDER = [1600, 900];
/** The ratios the image AI (Nano Banana Pro) takes, as [w, h]. */
export const PROPORCOES = [[1, 1], [5, 4], [4, 5], [4, 3], [3, 4], [3, 2], [2, 3], [16, 9], [9, 16], [21, 9]];
/** The nearest of them to a width / height ratio (compared on a log scale, so 2:1 and 1:2 are equally far from 1:1). */
export function proporcao(r) {
    return PROPORCOES.reduce((m, p) => (Math.abs(Math.log(p[0] / p[1] / r)) < Math.abs(Math.log(m[0] / m[1] / r)) ? p : m));
}
const SOL = new THREE.Vector3(-0.497, 0.5, 0.709).normalize();   // late afternoon, from the south-west, ~30° up

export function criarCena(casa) {
    const scene = new THREE.Scene();
    const ceu = document.createElement('canvas');
    ceu.width = 2; ceu.height = 256;
    const g = ceu.getContext('2d'), gr = g.createLinearGradient(0, 0, 0, 256);
    gr.addColorStop(0, '#9dbbe0'); gr.addColorStop(1, '#e4ecf2');
    g.fillStyle = gr; g.fillRect(0, 0, 2, 256);
    scene.background = new THREE.CanvasTexture(ceu);
    scene.background.colorSpace = THREE.SRGBColorSpace;

    scene.environmentIntensity = 0.35;   // RoomEnvironment reflections, kept low: the sun must model the walls
    const grupo = buildCasa(casa);
    scene.add(grupo);

    const [u1, v1, u2, v2] = limites(casa);
    const centro = [(u1 + u2) / 2, (v1 + v2) / 2];
    const caixa = new THREE.Box3().setFromObject(grupo.getObjectByName('casa'));
    for (const [u, v] of casa.lote || []) caixa.expandByPoint(new THREE.Vector3(u, 0, v));
    const meio = caixa.getCenter(new THREE.Vector3()), raio = caixa.getSize(new THREE.Vector3()).length() / 2 + 10;

    const sol = new THREE.DirectionalLight(new THREE.Color().setRGB(1, 0.9, 0.78), 4.0);
    sol.castShadow = true;
    sol.shadow.mapSize.set(4096, 4096);
    sol.shadow.bias = -0.0004;
    sol.shadow.normalBias = 0.01;
    sol.shadow.radius = 1.5;   // PCF blur: small, or sunlight bleeds in a band under the ceilings; the 4096 map keeps edges smooth
    sol.target.position.copy(meio);
    sol.position.copy(meio).addScaledVector(SOL, raio * 2);
    Object.assign(sol.shadow.camera, { left: -raio, right: raio, top: raio, bottom: -raio, near: 1, far: raio * 4 });
    sol.shadow.camera.updateProjectionMatrix();
    scene.add(sol, sol.target, new THREE.HemisphereLight(0x9dbbe0, 0xa39d8f, 0.5));

    const pontos = (casa.niveis || []).flatMap((n) => n.paredes.flatMap((w) => [w.de, w.a]));
    return { scene, grupo, centro, pontos, eixo: grupo.userData.eixo || 0, chao: grupo.userData.chao || 0, niveis: grupo.userData.niveis, topo: grupo.userData.topo, casaCaixa: new THREE.Box3().setFromObject(grupo.getObjectByName('casa')) };
}

/** Which layers a view shows: the roof only over the top storey, storeys above `piso` hidden. */
function mostrar(c, { telhado = true, piso = Infinity, vizinhos = true, vegetacao = true, lote = true } = {}) {
    for (const l of c.grupo.children) {
        const nome = l.userData.layer;
        l.visible = nome === 'cobertura' ? telhado && piso >= c.niveis - 1 : nome === 'vizinhos' ? vizinhos : nome === 'vegetacao' ? vegetacao
            : nome === 'lote' ? lote : true;
        if (nome === 'casa') for (const n of l.children) n.visible = n.userData.nivel <= piso;
    }
}

export function novoRenderer(canvas, extra = {}) {
    const r = new THREE.WebGLRenderer({ canvas, antialias: true, ...extra });
    r.shadowMap.enabled = true;
    r.shadowMap.type = THREE.PCFShadowMap;
    r.toneMapping = THREE.ACESFilmicToneMapping;
    r.toneMappingExposure = 0.9;
    return r;
}

// The environment map is a render target of the renderer that made it: one per renderer.
const ambientes = new WeakMap();
export function desenhar(c, r, camera, vis) {
    if (!ambientes.has(r)) {
        const pm = new THREE.PMREMGenerator(r);
        ambientes.set(r, pm.fromScene(new RoomEnvironment(), 0.04).texture);
        pm.dispose();
    }
    c.scene.environment = ambientes.get(r);
    mostrar(c, vis);
    r.render(c.scene, camera);
}
export function largar(r) {
    ambientes.get(r)?.dispose();
    r.dispose();
    r.forceContextLoss();
}

/** Eye, target and horizontal FOV of a planned camera, in three.js space. altura counts from chao (a flat's floor; else the ground). */
export function olhar(cam, centro, chao = 0) {
    const yaw = THREE.MathUtils.degToRad(cam.yaw);
    const pitch = cam.altura > 5 ? -Math.atan2(cam.altura - 1.5, Math.max(1, Math.hypot(cam.u - centro[0], cam.v - centro[1]))) : 0;
    const olho = new THREE.Vector3(cam.u, chao + cam.altura, cam.v);
    const dir = new THREE.Vector3(Math.sin(yaw) * Math.cos(pitch), Math.sin(pitch), -Math.cos(yaw) * Math.cos(pitch));
    return { olho, alvo: olho.clone().addScaledVector(dir, 10), hfov: cam.fov };
}
/** Vertical FOV of a W x H view whose centred 16:9 frame spans the shot's horizontal FOV. */
function fovVertical(hfov, W, H) {
    const fw = Math.min(W, H * ASPETO);
    return THREE.MathUtils.radToDeg(2 * Math.atan(Math.tan(THREE.MathUtils.degToRad(hfov) / 2) * H / fw));
}
export function moldura(W, H) {
    const w = Math.min(W, H * ASPETO), h = w / ASPETO;
    return { x: (W - w) / 2, y: (H - h) / 2, w, h };
}
export function cameraDoPlano(cam, centro, chao = 0) {
    const { olho, alvo, hfov } = olhar(cam, centro, chao);
    const k = new THREE.PerspectiveCamera(fovVertical(hfov, ASPETO, 1), ASPETO, 0.1, 2000);
    k.position.copy(olho);
    k.lookAt(alvo);
    return k;
}

/** The orbit viewer; ver() flies to a planned camera (~0.8 s) and keeps its 16:9 frame. */
export class Visor {
    constructor(c, el, aoMudar = () => {}) {
        Object.assign(this, { c, el, aoMudar, vis: {}, anim: null, tiro: null });
        this.r = novoRenderer(undefined);
        this.r.setPixelRatio(Math.min(devicePixelRatio, 2));
        el.appendChild(this.r.domElement);
        this.cam = new THREE.PerspectiveCamera(40, 1, 0.1, 2000);
        this.ctl = new OrbitControls(this.cam, this.r.domElement);
        this.ctl.enableDamping = true;
        this.ctl.maxPolarAngle = Math.PI / 2 - 0.02;   // stay above the ground
        this.r.domElement.addEventListener('pointerdown', () => { if (this.tiro) this.livre(false); });
        this.inicial();
        this.ro = new ResizeObserver(() => this.tamanho());
        this.ro.observe(el);
        this.tamanho();
        this.r.setAnimationLoop(() => this.frame());
    }

    inicial() {
        const b = this.c.casaCaixa, m = b.getCenter(new THREE.Vector3()), s = b.getSize(new THREE.Vector3()).length();
        this.ctl.target.copy(m);
        this.cam.position.set(m.x - s * 0.35, m.y + s * 0.55, m.z + s * 0.85);
        this.cam.fov = 40;
    }

    tamanho() {
        const W = this.el.clientWidth, H = this.el.clientHeight;
        if (!W || !H) return;
        this.r.setSize(W, H);
        this.cam.aspect = W / H;
        if (this.tiro && !this.anim) this.cam.fov = fovVertical(this.tiro.hfov, W, H);
        this.cam.updateProjectionMatrix();
        this.aoMudar(this.tiro ? moldura(W, H) : null);
    }

    /** Fly to a planned camera (animar = false: jump, e.g. while its sliders move). */
    ver(cam, animar = true) {
        const t = olhar(cam, this.c.centro, this.c.chao), W = this.el.clientWidth, H = this.el.clientHeight;
        this.tiro = t;
        this.ctl.enabled = false;
        const f1 = fovVertical(t.hfov, W, H);
        if (animar) {
            this.anim = { t0: performance.now(), ms: 800, p0: this.cam.position.clone(), q0: this.ctl.target.clone(), f0: this.cam.fov, p1: t.olho, q1: t.alvo, f1 };
        } else {
            this.anim = null;
            this.cam.position.copy(t.olho); this.ctl.target.copy(t.alvo); this.cam.fov = f1;
            this.cam.updateProjectionMatrix(); this.cam.lookAt(t.alvo);
        }
        this.aoMudar(moldura(W, H));
    }

    /** Back to the free orbit (voltar: fly back to the overview). */
    livre(voltar = true) {
        this.tiro = null; this.anim = null; this.ctl.enabled = true;
        if (voltar) this.inicial();
        this.cam.fov = 40;
        this.tamanho();
    }

    frame() {
        if (!this.el.isConnected) return this.largar();
        if (this.anim) {
            const a = this.anim, k = Math.min(1, (performance.now() - a.t0) / a.ms), e = k < 0.5 ? 4 * k ** 3 : 1 - (-2 * k + 2) ** 3 / 2;
            this.cam.position.lerpVectors(a.p0, a.p1, e);
            this.ctl.target.lerpVectors(a.q0, a.q1, e);
            this.cam.fov = a.f0 + (a.f1 - a.f0) * e;
            this.cam.updateProjectionMatrix();
            this.cam.lookAt(this.ctl.target);
            if (k === 1) this.anim = null;
        } else if (!this.tiro) this.ctl.update();
        desenhar(this.c, this.r, this.cam, this.tiro ? {} : this.vis);   // a shot always shows the whole house
    }

    largar() {
        this.r.setAnimationLoop(null);
        this.ro.disconnect();
        this.ctl.dispose();
        largar(this.r);
    }
}

/** The planner's top view: orthographic, north up, roof and trees hidden; vista = {u1, v1, u2, v2} (square). */
export function planta(c, canvas, vista) {
    const r = novoRenderer(canvas);
    const s = vista.u2 - vista.u1, cu = (vista.u1 + vista.u2) / 2, cv = (vista.v1 + vista.v2) / 2;
    const cam = new THREE.OrthographicCamera(-s / 2, s / 2, s / 2, -s / 2, 1, c.topo + 200);
    cam.up.set(0, 0, -1);
    cam.position.set(cu, c.topo + 100, cv);
    cam.lookAt(cu, 0, cv);
    const desenha = () => {
        const W = canvas.clientWidth;
        if (!W) return;
        r.setPixelRatio(Math.min(devicePixelRatio, 2));
        r.setSize(W, W, false);
        desenhar(c, r, cam, { telhado: false, vegetacao: false });
    };
    const ro = new ResizeObserver(desenha);
    ro.observe(canvas);
    desenha();
    return { desenha, largar: () => { ro.disconnect(); largar(r); } };
}

/** Small previews of planned shots (480x270 JPEG data URLs), for the strip under the 3D view: one offscreen renderer. */
export function miniaturas(c) {
    const canvas = document.createElement('canvas');
    [canvas.width, canvas.height] = [480, 270];
    const r = novoRenderer(canvas, { preserveDrawingBuffer: true });
    r.setPixelRatio(1);
    r.setSize(480, 270, false);
    return {
        url: (cam) => {
            desenhar(c, r, cameraDoPlano(cam, c.centro, c.chao), {});
            return canvas.toDataURL('image/jpeg', 0.8);
        },
        largar: () => largar(r),
    };
}

/** Renders planned shots (and the top-view reference) at 1600x900 into PNG blobs (one offscreen renderer for the batch). */
export function fotografo(c) {
    const canvas = document.createElement('canvas');
    [canvas.width, canvas.height] = RENDER;
    const r = novoRenderer(canvas, { preserveDrawingBuffer: true });
    r.setPixelRatio(1);
    r.setSize(...RENDER, false);
    const blob = () => new Promise((ok, falha) => canvas.toBlob((b) => (b ? ok(b) : falha(new Error('toBlob'))), 'image/png'));
    return {
        png: (cam) => {
            desenhar(c, r, cameraDoPlano(cam, c.centro, c.chao), {});
            return blob();
        },
        /**
         * Straight down onto storey `nivel`: the house alone (roof, ceilings, upper storeys, plot, trees and
         * neighbours off) on plain white, framed tight on its walls, at the house's own proportions (the nearest
         * ratio the image AI takes). A wide frame padded with lawn made the AI invent a bigger, different house.
         * Returns the PNG and, per room of that storey, where it is in the picture (% from the left / top) and
         * the pieces the builder put in it: the AI is told to make exactly those real and add nothing.
         */
        pngTopo: async (nivel) => {
            // turned so the house's walls run square to the frame (like a floor plan), framed on the walls
            const a = c.eixo > Math.PI / 4 ? c.eixo - Math.PI / 2 : c.eixo, [co, si] = [Math.cos(a), Math.sin(a)];
            const al = c.pontos.map(([u, v]) => u * co + v * si), ac = c.pontos.map(([u, v]) => u * si - v * co);
            const [a1, a2, b1, b2] = [Math.min(...al), Math.max(...al), Math.min(...ac), Math.max(...ac)];
            let [w, h] = [(a2 - a1 + 0.6) * 1.06, (b2 - b1 + 0.6) * 1.06];   // wall thickness + ~3% margin each side
            const [rw, rh] = proporcao(w / h);
            if (w / h < rw / rh) w = h * rw / rh; else h = w * rh / rw;
            const [ma, mb] = [(a1 + a2) / 2, (b1 + b2) / 2], [mx, mz] = [ma * co + mb * si, ma * si - mb * co];
            const cam = new THREE.OrthographicCamera(-w / 2, w / 2, h / 2, -h / 2, 1, c.topo + 300);
            cam.up.set(si, 0, -co);                                       // screen right = the main wall direction
            cam.position.set(mx, c.topo + 100, mz);
            cam.lookAt(mx, 0, mz);
            cam.updateMatrixWorld();
            const pc = (u, v) => { const p = new THREE.Vector3(u, 0, v).project(cam); return [Math.round((p.x + 1) * 50), Math.round((1 - p.y) * 50)]; };
            const salas = (c.grupo.userData.salas || []).filter((s) => s.nivel === nivel).map((s) => {
                const pts = s.poligono.map(([u, v]) => pc(u, v)), xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
                return { uso: s.uso, nome: s.nome, x: [Math.min(...xs), Math.max(...xs)], y: [Math.min(...ys), Math.max(...ys)], itens: s.itens };
            });
            const [cw, ch] = rw >= rh ? [1600, Math.round(1600 * rh / rw)] : [Math.round(1600 * rw / rh), 1600];
            const fundo = c.scene.background;
            c.scene.background = new THREE.Color(0xffffff);
            r.setSize(cw, ch, false);
            desenhar(c, r, cam, { telhado: false, piso: nivel, vegetacao: false, vizinhos: false, lote: false });
            try {
                return { png: await blob(), salas };
            } finally {
                c.scene.background = fundo;
                r.setSize(...RENDER, false);
            }
        },
        largar: () => largar(r),
    };
}
