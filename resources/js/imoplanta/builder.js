/*
 * Imoplanta: the house description (app/Services/Imoplanta/ImoplantaCasa.php has the schema) -> THREE.Group.
 * Runs in the browser and in Node (scripts/imoplanta/medir.mjs): canvas textures only where a canvas exists.
 *
 * Plan metres: u east, v south (down the drawing), z up. three.js: x = u, y = z, z = v
 * (seen from above with camera.up = -z, north is at the top and east on the right, as on the drawing).
 *
 * The result's children are the layers the viewer toggles, each a Group with userData.layer:
 *   lote (plot, lawns, terraces, pool, street, boundary walls) · vegetacao (trees) · vizinhos ·
 *   cobertura (roofs and the columns under them) · casa (one Group per storey, userData.nivel = i).
 * Meshes are merged per layer and material (a few dozen draw calls for a whole house).
 *
 * Geometry rule from the first prototype's QA: never leave two same-facing faces flush (they flicker).
 * Walls get a few mm of per-wall thickness/height jitter and run 1-2 cm into the slab above; thresholds are
 * buried in the floor; slabs are inset 2 cm behind the outer wall faces; overlapping floors and lawns get mm
 * height offsets. Walls are one elevation outline (doors as notches, windows as holes) extruded through the
 * thickness: watertight, no T-junction cracks.
 *
 * Limits: straight walls; pitched roof parts use the bounding
 * rectangle of their polygon; storeys stack (no stairs); a few low-poly furniture pieces per room use.
 */
import * as THREE from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

// name: [linear rgb, roughness, metalness, extra]; extra.pattern = tiles/stripes in world metres
// (w x h cells, w >= 10 means stripes), map "wall" for vertical surfaces, "floor" for horizontal ones.
const P = (color2, gap, w, h, map) => ({ pattern: { color2, gap, w, h, mortar: 0.008, map } });
const MATERIAIS = {
    reboco: [[0.90, 0.89, 0.86], 0.85, 0],
    pedra: [[0.62, 0.58, 0.52], 0.6, 0, P([0.55, 0.51, 0.46], [0.35, 0.33, 0.30], 0.6, 0.3, 'wall')],
    madeira: [[0.42, 0.27, 0.15], 0.6, 0, P([0.50, 0.33, 0.19], [0.12, 0.08, 0.05], 100, 0.14, 'wall')],
    tijolo: [[0.55, 0.30, 0.20], 0.7, 0, P([0.50, 0.27, 0.18], [0.70, 0.68, 0.64], 0.25, 0.075, 'wall')],
    betao: [[0.60, 0.60, 0.58], 0.9, 0],
    caixilho: [[0.06, 0.065, 0.07], 0.35, 0.6],
    vidro: [[0.85, 0.90, 0.92], 0.02, 0, { opacity: 0.35 }],
    portao: [[0.10, 0.105, 0.11], 0.6, 0, P([0.10, 0.105, 0.11], [0.04, 0.04, 0.04], 100, 0.5, 'wall')],
    porta: [[0.30, 0.18, 0.10], 0.4, 0],
    calcada: [[0.70, 0.69, 0.66], 0.6, 0, P([0.66, 0.65, 0.62], [0.45, 0.45, 0.43], 0.6, 0.6, 'floor')],
    capeamento: [[0.85, 0.83, 0.78], 0.7, 0],
    agua: [[0.10, 0.45, 0.55], 0.12, 0],
    deck: [[0.45, 0.30, 0.18], 0.6, 0, P([0.50, 0.34, 0.20], [0.15, 0.10, 0.06], 2.4, 0.14, 'floor')],
    gravilha: [[0.45, 0.44, 0.42], 1.0, 0],
    telha: [[0.55, 0.25, 0.15], 0.6, 0, P([0.50, 0.22, 0.13], [0.30, 0.12, 0.08], 0.3, 0.35, 'floor')],
    zinco: [[0.25, 0.26, 0.27], 0.45, 0.3, P([0.25, 0.26, 0.27], [0.18, 0.18, 0.19], 100, 0.5, 'floor')],
    asfalto: [[0.12, 0.12, 0.12], 0.9, 0],
    relva: [[0.16, 0.30, 0.10], 0.95, 0],
    campo: [[0.30, 0.34, 0.18], 1.0, 0],
    folha: [[0.10, 0.28, 0.10], 0.8, 0],
    casca: [[0.25, 0.18, 0.12], 0.9, 0],
    vizinho: [[0.88, 0.84, 0.76], 0.9, 0],
    vidro_vizinho: [[0.05, 0.07, 0.09], 0.05, 0.3],
    carvalho: [[0.55, 0.38, 0.22], 0.6, 0, P([0.62, 0.44, 0.27], [0.30, 0.20, 0.12], 1.2, 0.18, 'floor')],
    mosaico: [[0.78, 0.77, 0.74], 0.6, 0, P([0.74, 0.73, 0.70], [0.55, 0.55, 0.53], 0.6, 0.6, 'floor')],
    tecido: [[0.42, 0.42, 0.40], 0.95, 0],
    linho: [[0.93, 0.92, 0.88], 0.95, 0],
    tapete: [[0.82, 0.78, 0.70], 1.0, 0],
    nogueira: [[0.25, 0.15, 0.09], 0.4, 0],
    lacado: [[0.93, 0.93, 0.92], 0.3, 0],
    ceramica: [[0.95, 0.95, 0.93], 0.15, 0],
    cortina: [[0.86, 0.83, 0.77], 0.95, 0],
    vaso: [[0.20, 0.20, 0.21], 0.5, 0],
    quadro: [[0.36, 0.42, 0.47], 0.6, 0],
};
const PAREDES = { reboco: 'reboco', pedra: 'pedra', madeira: 'madeira', tijolo: 'tijolo', betao: 'betao' };
const COBERTURAS = { gravilha: 'gravilha', telha: 'telha', zinco: 'zinco' };
const PAVIMENTOS = { wc: 'mosaico', cozinha: 'mosaico', lavandaria: 'mosaico', garagem: 'betao', arrumos: 'betao' };

const ANDAR = 3.0;   // floor-to-floor height of the building around a flat (Imoplanta::ANDAR)
const num = (x, d) => (typeof x === 'number' && Number.isFinite(x) ? x : d);
const bbox = (pts) => {
    const us = pts.map((p) => p[0]), vs = pts.map((p) => p[1]);
    return [Math.min(...us), Math.min(...vs), Math.max(...us), Math.max(...vs)];
};
const area = (p) => p.reduce((s, a, i) => { const b = p[(i + 1) % p.length]; return s + a[0] * b[1] - b[0] * a[1]; }, 0) / 2;
function dentro([x, y], poly) {
    let c = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
        const [x1, y1] = poly[j], [x2, y2] = poly[i];
        if ((y1 > y) !== (y2 > y) && x < x1 + (y - y1) * (x2 - x1) / (y2 - y1)) c = !c;
    }
    return c;
}
/** Polygon offset inwards by d (mitred corners); fine for the orthogonal outlines houses have. */
function recuar(poly, d) {
    const s = area(poly) > 0 ? 1 : -1, n = poly.length;
    const nrm = (p, q) => { const L = Math.hypot(q[0] - p[0], q[1] - p[1]) || 1; return [-(q[1] - p[1]) / L * s, (q[0] - p[0]) / L * s]; };
    return poly.map((b, i) => {
        const a = poly[(i - 1 + n) % n], c = poly[(i + 1) % n], n1 = nrm(a, b), n2 = nrm(b, c), k = 1 + n1[0] * n2[0] + n1[1] * n2[1];
        return k < 0.1 ? b : [b[0] + d * (n1[0] + n2[0]) / k, b[1] + d * (n1[1] + n2[1]) / k];
    });
}

/** Largest rectangle [s1, o1, s2, o2] with its sides on the polygon's own coordinates that fits inside it
 * (the whole bounding box for a rectangular room, the bigger leg of an L). n <= ~12 vertices: brute force. */
function maiorRetangulo(p) {
    const uniq = (xs) => [...new Set(xs.map((x) => Math.round(x * 100) / 100))].sort((a, b) => a - b);
    const S = uniq(p.map((q) => q[0])), O = uniq(p.map((q) => q[1])), d = 0.05;
    let best = null, A = 0;
    for (let i = 0; i < S.length; i++) for (let j = i + 1; j < S.length; j++)
        for (let k = 0; k < O.length; k++) for (let l = k + 1; l < O.length; l++) {
            const [a, b, c, f] = [S[i], O[k], S[j], O[l]], ar = (c - a) * (f - b);
            if (ar <= A) continue;
            const pts = [[a + d, b + d], [c - d, b + d], [c - d, f - d], [a + d, f - d], [(a + c) / 2, (b + f) / 2]];
            if (pts.every((q) => dentro(q, p)) && !p.some(([x, y]) => x > a + d && x < c - d && y > b + d && y < f - d)) { best = [a, b, c, f]; A = ar; }
        }
    return best;
}
/** What sits on one side of a room (k = 0: side along s at o = fixo; 1: along o at s = fixo), in local coordinates:
 * mau = metres of doors/glazing (x3), windows (x1) and missing wall (x3); t = the wall's thickness;
 * vaos = its openings, x1..x2 measured along the side from its centre. */
function ocupacao(k, fixo, a, b, paredes, loc) {
    let coberto = 0, mau = 0, t = 0.12;
    const vaos = [], c = (a + b) / 2;
    for (const w of paredes) {
        const A = loc(w.de), B = loc(w.a), L = Math.hypot(B[0] - A[0], B[1] - A[1]);
        if (L < 0.05 || Math.abs(A[1 - k] - fixo) > 0.3 || Math.abs(B[1 - k] - fixo) > 0.3) continue;
        const sob = (x1, x2) => Math.min(Math.max(x1, x2), b) - Math.max(Math.min(x1, x2), a);
        const ov = sob(A[k], B[k]);
        if (ov < 0.05) continue;
        coberto += ov;
        t = Math.max(t, num(w.espessura, w.exterior === false ? 0.12 : 0.3));
        for (const o of w.aberturas || []) {
            const x = (s) => A[k] + (B[k] - A[k]) * s / L, p1 = x(o.pos), p2 = x(o.pos + o.largura), v = sob(p1, p2);
            if (v <= 0) continue;
            mau += v * (o.tipo === 'janela' ? 1 : 3);
            vaos.push({ x1: Math.min(p1, p2) - c, x2: Math.max(p1, p2) - c, tipo: o.tipo, topo: num(o.peitoril, 0) + num(o.altura, 2.1) });
        }
    }
    return { mau: mau + 3 * Math.max(0, b - a - coberto), t, vaos };
}

/** The house's bounding box in plan metres [u1, v1, u2, v2] (walls only), as ImoplantaCasa::limites(). */
export function limites(casa) {
    return bbox(casa.niveis.flatMap((n) => n.paredes.flatMap((w) => [w.de, w.a])));
}

export function buildCasa(casa, opts = {}) {
    const baldes = new Map();          // "layer|nivel|material" -> geometries in world space
    let camada = 'lote', nivel = '';
    const add = (g, m) => {
        if (g.index) g = g.toNonIndexed();
        for (const k of Object.keys(g.attributes)) if (k !== 'position' && k !== 'normal') g.deleteAttribute(k);
        g.clearGroups();
        const k = `${camada}|${nivel}|${m}`;
        (baldes.get(k) || baldes.set(k, []).get(k)).push(g);
    };

    // ---------- primitives: plan coordinates in, three.js out ----------
    const caixa = (u1, v1, z1, u2, v2, z2, m) => {
        const du = Math.abs(u2 - u1), dv = Math.abs(v2 - v1), dz = Math.abs(z2 - z1);
        if (du > 1e-4 && dv > 1e-4 && dz > 1e-4) add(new THREE.BoxGeometry(du, dz, dv).translate((u1 + u2) / 2, (z1 + z2) / 2, (v1 + v2) / 2), m);
    };
    /** Box in a line's frame: along d from s1 to s2, across from o1 to o2 (metres from point p). */
    const caixaOrientada = (p, d, s1, s2, o1, o2, z1, z2, m) => {
        if (s2 - s1 < 1e-4 || o2 - o1 < 1e-4 || z2 - z1 < 1e-4) return;
        const n = [-d[1], d[0]], sm = (s1 + s2) / 2, om = (o1 + o2) / 2;
        add(new THREE.BoxGeometry(s2 - s1, z2 - z1, o2 - o1).rotateY(Math.atan2(-d[1], d[0]))
            .translate(p[0] + d[0] * sm + n[0] * om, (z1 + z2) / 2, p[1] + d[1] * sm + n[1] * om), m);
    };
    const segmento = (a, b, t, z1, z2, m) => {
        const L = Math.hypot(b[0] - a[0], b[1] - a[1]);
        if (L > 1e-3) caixaOrientada(a, [(b[0] - a[0]) / L, (b[1] - a[1]) / L], 0, L, -t / 2, t / 2, z1, z2, m);
    };
    /** Extruded (u, v) polygon between z1 and z2; `topo` = another material for the upper face. */
    const laje = (poly, z1, z2, m, topo) => {
        if (!poly || poly.length < 3 || z2 - z1 < 1e-4) return;
        const g = new THREE.ExtrudeGeometry(new THREE.Shape(poly.map(([u, v]) => new THREE.Vector2(u, v))), { depth: z2 - z1, bevelEnabled: false })
            .rotateX(Math.PI / 2).translate(0, z2, 0);
        if (!topo || topo === m) return add(g, m);
        const ng = g.index ? g.toNonIndexed() : g, p = ng.attributes.position.array, nn = ng.attributes.normal.array, cima = [], resto = [];
        for (let i = 0; i < p.length; i += 9) (nn[i + 1] > 0.9 ? cima : resto).push(...p.slice(i, i + 9));
        for (const [arr, mm] of [[resto, m], [cima, topo]]) {
            const h = new THREE.BufferGeometry();
            h.setAttribute('position', new THREE.Float32BufferAttribute(arr, 3));
            h.computeVertexNormals(); add(h, mm);
        }
    };
    const cilindro = (u, v, z1, z2, r, m, seg = 12) => add(new THREE.CylinderGeometry(r, r, z2 - z1, seg).translate(u, (z1 + z2) / 2, v), m);
    const bola = (u, v, z, r, sz, m) => add(new THREE.IcosahedronGeometry(r, 2).scale(1, sz, 1).translate(u, z, v), m);
    /** Convex solid from [u, v, z] points and faces (index lists, any winding): faces turned outwards, flat
     *  shaded; faces facing up (the slopes) get `encosta`, the others `m`. */
    const solido = (pts, faces, m, encosta) => {
        const V = pts.map(([u, v, z]) => new THREE.Vector3(u, z, v));
        const c = V.reduce((a, b) => a.add(b), new THREE.Vector3()).divideScalar(V.length);
        const tris = { [m]: [], [encosta]: [] };
        for (let f of faces) {
            const a = V[f[0]], n = new THREE.Vector3().subVectors(V[f[1]], a).cross(new THREE.Vector3().subVectors(V[f[2]], a)).normalize();
            const fc = f.reduce((s, i) => s.add(V[i]), new THREE.Vector3()).divideScalar(f.length);
            if (n.dot(fc.sub(c)) < 0) { f = [...f].reverse(); n.negate(); }
            const alvo = tris[n.y > 0.2 ? encosta : m];
            for (let i = 1; i < f.length - 1; i++) for (const k of [f[0], f[i], f[i + 1]]) alvo.push(V[k].x, V[k].y, V[k].z);
        }
        for (const [mm, arr] of Object.entries(tris)) {
            if (!arr.length) continue;
            const g = new THREE.BufferGeometry(); g.setAttribute('position', new THREE.Float32BufferAttribute(arr, 3));
            g.computeVertexNormals(); add(g, mm);
        }
    };

    // ---------- walls with openings, any direction ----------
    const parede = (w, z0, ztop, k) => {
        const [u1, v1] = w.de, [u2, v2] = w.a, L = Math.hypot(u2 - u1, v2 - v1);
        if (L < 0.05) return;
        const d = [(u2 - u1) / L, (v2 - v1) / L], esp = num(w.espessura, w.exterior === false ? 0.12 : 0.3);
        const t = esp + 0.012 * (k % 3);                  // jitter: collinear walls never share a face
        const e = esp / 2 - 0.01;                          // run into the crossing wall at corners and T's
        const zt = ztop + 0.003 * (k % 4), zb = z0 - 0.02;
        const m = PAREDES[w.material] || 'reboco';
        const fina = (a, b, z1, z2, th, mm) => caixaOrientada(w.de, d, a, b, -th / 2, th / 2, z1, z2, mm);

        // the openings that fit, as build.py clipped them
        const vaos = [];
        let cur = -e;
        for (const o of [...(w.aberturas || [])].sort((a, b) => a.pos - b.pos)) {
            const a = Math.max(o.pos, cur), b = Math.min(o.pos + o.largura, L);
            if (b - a < 0.2) continue;
            const peitoril = num(o.peitoril, 0), z1 = z0 + peitoril, z2 = Math.min(z1 + num(o.altura, 2.1), zt - 0.15);
            if (z2 - z1 < 0.1) continue;
            vaos.push({ a, b, z1, z2, chao: peitoril < 0.01, tipo: o.tipo });
            cur = b;
        }

        // One elevation outline (s along the wall, z up): doors are notches in its bottom edge (thresholds
        // buried with it), windows are holes. Openings closer than 2 cm to each other or to the wall's ends
        // can't be holes/notches of a valid outline: those walls fall back to box pieces.
        const ok = vaos.every((o, i) => o.a > -e + 0.02 && o.b < L + e - 0.02 && (i === 0 || o.a > vaos[i - 1].b + 0.02)
            && (o.chao || o.z1 > zb + 0.02));
        if (ok) {
            const pts = [[-e, zb]];
            for (const o of vaos.filter((x) => x.chao)) pts.push([o.a, zb], [o.a, o.z2], [o.b, o.z2], [o.b, zb]);
            pts.push([L + e, zb], [L + e, zt], [-e, zt]);
            const s = new THREE.Shape(pts.map(([a, b]) => new THREE.Vector2(a, b)));
            s.holes = vaos.filter((x) => !x.chao).map((o) => new THREE.Path([[o.a, o.z1], [o.a, o.z2], [o.b, o.z2], [o.b, o.z1]].map(([a, b]) => new THREE.Vector2(a, b))));
            const g = new THREE.ExtrudeGeometry(s, { depth: t, bevelEnabled: false })   // x along, y = z, z across 0..t
                .translate(0, 0, -t / 2).rotateY(Math.atan2(-d[1], d[0])).translate(u1, 0, v1);
            add(g, m);
        } else {
            let c = -e;
            for (const o of vaos) {
                fina(c, o.a, zb, zt, t, m);
                if (!o.chao) fina(o.a, o.b, zb, o.z1, t, m);
                fina(o.a, o.b, o.z2, zt, t, m);
                c = o.b;
            }
            fina(c, L + e, zb, zt, t, m);
        }

        for (const { a, b, z1: zz1, z2, chao, tipo } of vaos) {
            const z1 = chao ? zz1 - 0.02 : zz1;
            if (tipo === 'vao') continue;
            if (tipo === 'garagem' || tipo === 'porta') { fina(a, b, z1, z2, 0.05, tipo === 'garagem' ? 'portao' : 'porta'); continue; }
            const f = 0.06;
            fina(a + f, b - f, z1 + f, z2 - f, 0.02, 'vidro');
            for (const [p, q, r1, r2] of [[a, a + f, z1, z2], [b - f, b, z1, z2], [a + f, b - f, z1, z1 + f], [a + f, b - f, z2 - f, z2]]) fina(p, q, r1, r2, 0.08, 'caixilho');
            const nm = Math.max(1, Math.round((b - a) / (tipo === 'envidracado' ? 1.6 : 1.1)));
            for (let i = 1; i < nm; i++) { const xm = a + (b - a) * i / nm; fina(xm - 0.03, xm + 0.03, z1 + f, z2 - f, 0.08, 'caixilho'); }
        }
    };

    /** Dark windows along every edge of a building outline, on n storeys from zBase (one per 3 m, 1.2 x 1.3 m). */
    const fachada = (poly, zBase, n) => {
        poly.forEach((a, i) => {
            const b = poly[(i + 1) % poly.length], L = Math.hypot(b[0] - a[0], b[1] - a[1]);
            if (L < 2) return;
            const d = [(b[0] - a[0]) / L, (b[1] - a[1]) / L], m = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
            const fora = dentro([m[0] - d[1] * 0.3, m[1] + d[0] * 0.3], poly) ? -1 : 1;   // which side of the edge is outside
            const k = Math.floor((L - 0.6) / 3), s0 = (L - (k - 1) * 3 - 1.2) / 2;
            for (let f = 0; f < n; f++) {
                for (let j = 0; j < k; j++) {
                    const s = s0 + j * 3, z = zBase + f * ANDAR + 0.9;
                    caixaOrientada(a, d, s, s + 1.2, Math.min(0.01 * fora, 0.04 * fora), Math.max(0.01 * fora, 0.04 * fora), z, z + 1.3, 'vidro_vizinho');
                }
            }
        });
    };

    // ---------- low-poly furniture by room use, square to the room and backed against its quietest wall ----------
    // Room frame: its longest edge (plans are often drawn rotated). The room's largest rectangle in that frame
    // holds the furniture; each side is scored by what sits on it (doors, glazing, windows, no wall at all)
    // and the bed head, the sofa back and the kitchen run go against the least busy side that fits.
    const mobilar = (div, z, paredes) => {
        const poly = div.poligono, n = poly.length;
        let L0 = 0, e = [1, 0];
        poly.forEach((a, i) => {
            const b = poly[(i + 1) % n], L = Math.hypot(b[0] - a[0], b[1] - a[1]);
            if (L > L0) { L0 = L; e = [(b[0] - a[0]) / L, (b[1] - a[1]) / L]; }
        });
        const loc = (p) => [p[0] * e[0] + p[1] * e[1], -p[0] * e[1] + p[1] * e[0]];   // (along e, across e)
        const r = maiorRetangulo(poly.map(loc));
        if (!r) return;
        const [s1, o1, s2, o2] = r;
        // sides: along s at o = o1 / o2, along o at s = s1 / s2; dentro = the way into the room
        const lados = [[0, o1, s1, s2, 1], [0, o2, s1, s2, -1], [1, s1, o1, o2, 1], [1, s2, o1, o2, -1]]
            .map(([k, fixo, a, b, dentro]) => ({ k, fixo, a, b, dentro, len: b - a, fundo: k ? s2 - s1 : o2 - o1, ...ocupacao(k, fixo, a, b, paredes, loc) }));
        const lado = (ok) => lados.filter(ok).sort((x, y) => x.mau - y.mau || y.len - x.len)[0];
        const ocupado = [];   // footprints [s1, o1, s2, o2] of what stands on the floor (and the doors' swing)
        // a piece in a side's frame: x along the side from its centre, y from the wall face into the room
        const rect = (l, x1, x2, y1, y2) => {
            const c = (l.a + l.b) / 2, q = l.fixo + l.dentro * (l.t / 2 + 0.02), p1 = q + l.dentro * y1, p2 = q + l.dentro * y2;
            const [m1, m2] = [Math.min(p1, p2), Math.max(p1, p2)];
            return l.k === 0 ? [c + x1, m1, c + x2, m2] : [m1, c + x1, m2, c + x2];
        };
        const caixaLocal = ([a, b, c, d], za, zb, m) => caixaOrientada([0, 0], e, a, c, b, d, za, zb, m);
        const peca = (l, x1, x2, y1, y2, za, zb, m) => { const q = rect(l, x1, x2, y1, y2); ocupado.push(q); caixaLocal(q, za, zb, m); };
        const enfeite = (l, x1, x2, y1, y2, za, zb, m) => caixaLocal(rect(l, x1, x2, y1, y2), za, zb, m);   // on the wall / a rug: not in the way
        const espelho = (l, x1, x2, ...resto) => { peca(l, x1, x2, ...resto); peca(l, -x2, -x1, ...resto); };
        const livre = (q) => q[0] >= s1 - 0.01 && q[2] <= s2 + 0.01 && q[1] >= o1 - 0.01 && q[3] <= o2 + 0.01
            && !ocupado.some((o) => q[0] < o[2] && q[2] > o[0] && q[1] < o[3] && q[3] > o[1]);
        const semVao = (l, x1, x2) => !l.vaos.some((v) => v.x1 < x2 && v.x2 > x1);
        /**
         * First free spot for a w x d piece against a wall, least busy walls first, from each wall's middle out.
         * frente: the floor kept clear in front of it to use it (open a wardrobe, sit at a desk); that strip is
         * reserved too, so nothing placed later stands in front of it.
         */
        const encaixar = (w, d, ok = () => true, frente = 0) => {
            for (const l of [...lados].sort((x, y) => x.mau - y.mau)) {
                const m = l.len / 2 - 0.12 - w / 2;
                if (m < 0 || l.fundo < d + Math.max(0.4, frente) || !ok(l)) continue;
                for (let off = 0; off <= m + 1e-6; off += 0.1) for (const x of off ? [off, -off] : [0]) {
                    if (!livre(rect(l, x - w / 2, x + w / 2, 0, d + frente))) continue;
                    if (frente) ocupado.push(rect(l, x - w / 2, x + w / 2, d, d + frente));
                    return { l, x };
                }
            }
            return null;
        };
        const mundo = (sl, ol) => [sl * e[0] - ol * e[1], sl * e[1] + ol * e[0]];
        const cadeira = (l, x, y, costas) => {   // seat centred at (x, y); its back on the y side given by costas (+1 / -1)
            peca(l, x - 0.22, x + 0.22, y - 0.22, y + 0.22, z, z + 0.46, 'tecido');
            enfeite(l, x - 0.22, x + 0.22, y + costas * 0.17, y + costas * 0.22, z + 0.46, z + 0.9, 'tecido');
        };
        const mesa = (l, x, y, w, d, n) => {     // table centred at (x, y), w along the wall, n chairs on each long side
            peca(l, x - w / 2, x + w / 2, y - d / 2, y + d / 2, z + 0.72, z + 0.76, 'nogueira');
            for (const sx of [-1, 1]) peca(l, x + sx * (w / 2 - 0.08) - 0.025, x + sx * (w / 2 - 0.08) + 0.025, y - d / 2 + 0.05, y + d / 2 - 0.05, z, z + 0.72, 'nogueira');
            for (let j = 0; j < n; j++) {
                const cx = x - w / 2 + w * (j + 0.5) / n;
                cadeira(l, cx, y - d / 2 - 0.3, -1);
                cadeira(l, cx, y + d / 2 + 0.3, 1);
            }
        };
        const planta = () => {                    // a potted plant in the first free corner
            for (const [a, b] of [[s1, o1], [s2, o1], [s2, o2], [s1, o2]]) {
                const cs = a + (a === s1 ? 0.38 : -0.38), co = b + (b === o1 ? 0.38 : -0.38), q = [cs - 0.25, co - 0.25, cs + 0.25, co + 0.25];
                if (s2 - s1 < 1.3 || o2 - o1 < 1.3 || !livre(q)) continue;
                ocupado.push(q);
                const [u, v] = mundo(cs, co);
                cilindro(u, v, z, z + 0.42, 0.19, 'vaso', 10);
                bola(u, v, z + 0.95, 0.36, 1.5, 'folha');
                return;
            }
        };
        const quadro = (l, x, zc, w = 0.9) => { if (l && l.len >= w + 0.4 && semVao(l, x - w / 2, x + w / 2)) enfeite(l, x - w / 2, x + w / 2, 0, 0.03, zc, zc + 0.6, 'quadro'); };
        const cortinas = () => {                  // a drape either side of each window and glass door
            for (const l of lados) for (const v of l.vaos) {
                if (v.tipo !== 'janela' && v.tipo !== 'envidracado') continue;
                for (let [x1, x2] of [[v.x1 - 0.32, v.x1 - 0.03], [v.x2 + 0.03, v.x2 + 0.32]]) {
                    [x1, x2] = [Math.max(x1, -l.len / 2 + 0.08), Math.min(x2, l.len / 2 - 0.08)];
                    if (x2 - x1 > 0.1) enfeite(l, x1, x2, 0.02, 0.1, z + 0.02, z + Math.min(2.6, v.topo + 0.25), 'cortina');
                }
            }
        };
        const tapete = (w, d) => {                // a rug in the middle, w along the room's long axis
            const cs = (s1 + s2) / 2, co = (o1 + o2) / 2, [hs, ho] = s2 - s1 >= o2 - o1 ? [w / 2, d / 2] : [d / 2, w / 2];
            if (s2 - s1 > 2 * hs + 0.4 && o2 - o1 > 2 * ho + 0.4) caixaLocal([cs - hs, co - ho, cs + hs, co + ho], z, z + 0.012, 'tapete');
        };
        // keep the doors' swing clear
        for (const l of lados) for (const v of l.vaos) if (v.tipo !== 'janela') ocupado.push(rect(l, v.x1 - 0.05, v.x2 + 0.05, 0, 0.9));
        const longo = Math.max(s2 - s1, o2 - o1);

        if (div.uso === 'quarto') {
            const casal = Math.min(s2 - s1, o2 - o1) >= 2.7, bw = casal ? 1.6 : 0.9, bl = casal ? 2.0 : 1.9;
            const zonaCama = (l) => rect(l, -bw / 2 - 0.6, bw / 2 + 0.6, 0.06, bl + 0.7);   // the bed and room to get in and round its foot
            // the bed's wall: the least busy one that still leaves a wardrobe a place with its doors free
            const paredes = lados.filter((x) => x.len >= bw + 0.5 && x.fundo >= bl + 0.7).sort((x, y) => x.mau - y.mau || y.len - x.len);
            const temRoupeiro = (l) => {
                const n = ocupado.length;
                ocupado.push(zonaCama(l));
                const ok = [1.6, 1.2, 0.9].some((ww) => encaixar(ww, 0.6, () => true, 0.8));
                ocupado.length = n;
                return ok;
            };
            const l = paredes.find(temRoupeiro) || paredes[0];
            if (l) {
                peca(l, -bw / 2 - 0.05, bw / 2 + 0.05, 0, 0.06, z, z + 1.0, 'nogueira');              // headboard
                peca(l, -bw / 2, bw / 2, 0.06, bl, z, z + 0.42, 'nogueira');
                enfeite(l, -bw / 2 + 0.02, bw / 2 - 0.02, 0.08, bl - 0.02, z + 0.42, z + 0.6, 'linho');
                enfeite(l, -bw / 2 + 0.03, bw / 2 - 0.03, 0.1, 0.55, z + 0.6, z + 0.7, 'linho');      // pillows
                enfeite(l, -bw / 2 - 0.02, bw / 2 + 0.02, bl * 0.62, bl + 0.02, z + 0.43, z + 0.62, 'tapete');   // throw
                if (l.len >= bw + 1.3) espelho(l, bw / 2 + 0.12, bw / 2 + 0.57, 0, 0.42, z, z + 0.5, 'nogueira');
                enfeite(l, -bw / 2 - 0.3, bw / 2 + 0.3, bl + 0.05, bl + 0.95, z, z + 0.012, 'tapete');
                quadro(l, 0, z + 1.2, Math.min(1.2, bw));
                ocupado.push(zonaCama(l));
            }
            // wardrobe: its doors need 0.8 m clear in front; narrower if it must be, none rather than in the way
            for (const ww of [1.6, 1.2, 0.9]) {
                const r = encaixar(ww, 0.6, () => true, 0.8);
                if (r) { peca(r.l, r.x - ww / 2, r.x + ww / 2, 0, 0.6, z, z + 2.2, 'lacado'); break; }
            }
            planta();
        } else if (div.uso === 'sala') {
            const l = lado((x) => x.len >= 2.6 && x.fundo >= 3.0);
            if (l) {
                enfeite(l, -1.4, 1.4, 0.55, 2.55, z, z + 0.012, 'tapete');
                peca(l, -1.1, 1.1, 0, 0.92, z, z + 0.42, 'tecido');                                     // seat
                enfeite(l, -1.1, 1.1, 0, 0.22, z + 0.42, z + 0.82, 'tecido');                           // back
                espelho(l, 0.92, 1.1, 0.22, 0.92, z + 0.42, z + 0.6, 'tecido');                         // arms
                enfeite(l, -0.85, -0.45, 0.25, 0.4, z + 0.6, z + 0.95, 'linho');                        // cushions
                enfeite(l, 0.45, 0.85, 0.25, 0.4, z + 0.6, z + 0.95, 'linho');
                peca(l, -0.55, 0.55, 1.3, 1.9, z + 0.012, z + 0.4, 'nogueira');                         // coffee table
                quadro(l, 0, z + 1.25, 1.2);
                const op = lados.find((o) => o.k === l.k && o !== l);
                if (l.fundo <= 5.5 && livre(rect(op, -0.9, 0.9, 0, 0.42))) {                             // TV wall
                    peca(op, -0.9, 0.9, 0, 0.42, z, z + 0.5, 'nogueira');
                    enfeite(op, -0.6, 0.6, 0, 0.05, z + 0.85, z + 1.55, 'vidro_vizinho');
                } else if (l.fundo > 5.5 && l.len >= 2.6) mesa(l, 0, l.fundo - 1.6, 1.8, 0.9, 3);       // dining end
            }
            planta();
            planta();
        } else if (div.uso === 'wc') {
            // along the quietest wall, left to right: bath (long rooms) or shower, vanity with mirror, toilet
            const l = lado((x) => x.len >= 1.4 && x.fundo >= 1.2);
            if (!l) return;
            const meio = l.len / 2 - 0.1;                                  // clear of the side walls
            const banho = l.len >= 3.3 && l.fundo >= 1.7 ? 1.7 : l.len >= 2.3 ? 0.9 : 0;
            const folga = (2 * meio - banho - 0.7 - 0.45) / (banho ? 2 : 1); // spread the pieces evenly
            let x = -meio;
            if (banho === 1.7) {
                peca(l, x, x + 1.7, 0, 0.75, z, z + 0.55, 'ceramica');
            } else if (banho) {
                peca(l, x, x + 0.9, 0, 0.9, z, z + 0.05, 'ceramica');                             // shower tray
                enfeite(l, x, x + 0.9, 0.89, 0.9, z + 0.05, z + 2.0, 'vidro');                    // glass screen
            }
            x += banho + (banho ? folga : 0);
            peca(l, x, x + 0.7, 0, 0.46, z + 0.3, z + 0.8, 'nogueira');                           // vanity
            enfeite(l, x - 0.01, x + 0.71, 0, 0.5, z + 0.8, z + 0.85, 'ceramica');                // basin top
            enfeite(l, x + 0.05, x + 0.65, 0, 0.02, z + 1.1, z + 1.9, 'vidro_vizinho');           // mirror
            x += 0.7 + folga;
            peca(l, x, x + 0.4, 0, 0.18, z, z + 0.8, 'ceramica');                                 // cistern
            peca(l, x + 0.02, x + 0.38, 0.18, 0.66, z, z + 0.4, 'ceramica');                      // bowl
            // wall too short for a shower beside them: a corner shower on the opposite wall, if it has no door
            const op = lados.find((o) => o.k === l.k && o !== l);
            if (!banho && l.fundo >= 2.0 && op.mau < 2 && op.len >= 1.1) {
                const m = op.len / 2 - 0.1;
                peca(op, m - 0.9, m, 0, 0.9, z, z + 0.05, 'ceramica');
                enfeite(op, m - 0.9, m, 0.89, 0.9, z + 0.05, z + 2.0, 'vidro');
                enfeite(op, m - 0.9, m - 0.89, 0, 0.89, z + 0.05, z + 2.0, 'vidro');
            }
            if (l.fundo >= 1.6) enfeite(l, -0.4, 0.4, 0.75, 1.3, z, z + 0.012, 'tapete');          // bath mat
            const t = encaixar(0.5, 0.05, (o) => o !== l);
            if (t) enfeite(t.l, t.x - 0.25, t.x + 0.25, 0, 0.05, z + 1.0, z + 1.5, 'linho');      // towel
        } else if (div.uso === 'cozinha') {
            // fitted units along the longest wall that is least broken up (openings per metre), split around its
            // doors; a galley at least 2.4 m wide gets a second run opposite; a table only in a big kitchen
            const bancada = (l, principal) => {
                const fim = l.len / 2 - 0.15, trechos = [];
                let ini = -fim;
                for (const [a, b] of l.vaos.filter((v) => v.tipo !== 'janela').map((v) => [v.x1 - 0.1, v.x2 + 0.1]).sort((x, y) => x[0] - y[0])) {
                    if (Math.min(a, fim) - ini >= 0.6) trechos.push([ini, Math.min(a, fim)]);
                    ini = Math.max(ini, b);
                }
                if (fim - ini >= 0.6) trechos.push([ini, fim]);
                trechos.sort((x, y) => (y[1] - y[0]) - (x[1] - x[0])).forEach(([a, b], j) => {
                    const frigo = principal && j === 0 && b - a >= 2.4, bb = frigo ? b - 0.7 : b;
                    peca(l, a, bb, 0, 0.6, z, z + 0.86, 'lacado');
                    enfeite(l, a - 0.01, bb + 0.01, 0, 0.63, z + 0.86, z + 0.9, 'ceramica');
                    if (frigo) peca(l, b - 0.65, b, 0, 0.65, z, z + 2.0, 'lacado');                    // fridge column
                    if (semVao(l, a, bb)) enfeite(l, a, bb, 0, 0.35, z + 1.45, z + 2.15, 'lacado');   // wall units
                    if (principal && j === 0 && bb - a >= 1.6) {
                        enfeite(l, a + 0.3, a + 0.9, 0.06, 0.56, z + 0.9, z + 0.906, 'vidro_vizinho'); // hob
                        enfeite(l, bb - 0.9, bb - 0.4, 0.1, 0.5, z + 0.9, z + 0.906, 'caixilho');      // sink
                    }
                });
            };
            const l = lados.filter((x) => x.len >= 1.8 && x.fundo >= 1.6).sort((x, y) => x.mau / x.len - y.mau / y.len || y.len - x.len)
                .sort((x, y) => (y.len >= 2.4) - (x.len >= 2.4))[0];   // a wall long enough for a real run first
            if (l) {
                bancada(l, true);
                const op = lados.find((o) => o.k === l.k && o !== l);
                if (l.fundo >= 2.4 && l.fundo < 3.6) bancada(op, false);
                if (l.fundo >= 3.6 && l.len >= 2.2) mesa(l, 0, 0.63 + (l.fundo - 0.63) / 2, 1.2, 0.8, 2);
            }
            planta();
        } else if (div.uso === 'escritorio') {
            const r = encaixar(1.4, 0.7, () => true, 0.8);   // room for the chair
            if (r) {
                peca(r.l, r.x - 0.7, r.x + 0.7, 0, 0.7, z + 0.72, z + 0.76, 'nogueira');
                for (const sx of [-1, 1]) peca(r.l, r.x + sx * 0.62 - 0.025, r.x + sx * 0.62 + 0.025, 0.05, 0.65, z, z + 0.72, 'nogueira');
                cadeira(r.l, r.x, 1.05, 1);
                quadro(r.l, r.x, z + 1.3);
            }
            const b = encaixar(1.0, 0.35, () => true, 0.6);
            if (b) peca(b.l, b.x - 0.5, b.x + 0.5, 0, 0.35, z, z + 2.0, 'nogueira');               // bookcase
            tapete(1.6, 1.2);
            planta();
        } else if (div.uso === 'lavandaria') {
            const r = encaixar(1.3, 0.62, () => true, 0.7);
            if (r) {
                peca(r.l, r.x - 0.65, r.x - 0.03, 0, 0.62, z, z + 0.85, 'lacado');                    // washer
                peca(r.l, r.x + 0.03, r.x + 0.65, 0, 0.62, z, z + 0.85, 'lacado');                    // dryer
                enfeite(r.l, r.x - 0.67, r.x + 0.67, 0, 0.64, z + 0.85, z + 0.89, 'nogueira');        // worktop
                enfeite(r.l, r.x - 0.6, r.x + 0.6, 0, 0.3, z + 1.5, z + 2.1, 'lacado');               // cupboard
            }
            const c = encaixar(0.6, 0.6, () => true, 0.6);
            if (c) peca(c.l, c.x - 0.3, c.x + 0.3, 0, 0.6, z, z + 2.0, 'lacado');
        } else if (div.uso === 'arrumos' || div.uso === 'garagem') {
            const fundo = div.uso === 'garagem' ? 0.5 : 0.4, w = Math.min(div.uso === 'garagem' ? 3.0 : 2.4, longo - 0.4);
            const r = encaixar(w, fundo, () => true, 0.6);
            if (r) {
                espelho(r.l, w / 2 - 0.04, w / 2, 0, fundo, z, z + 2.0, 'nogueira');
                for (let h = 0.15; h < 2.0; h += 0.45) enfeite(r.l, r.x - w / 2, r.x + w / 2, 0, fundo, z + h, z + h + 0.03, 'nogueira');
                for (let j = 0; j < Math.floor(w / 0.5); j++) {                                       // boxes on the shelves
                    const bx = r.x - w / 2 + 0.1 + j * 0.5, h = 0.18 + (j % 4) * 0.45;
                    enfeite(r.l, bx, bx + 0.35, 0.05, fundo - 0.08, z + h, z + h + 0.25, j % 2 ? 'linho' : 'tecido');
                }
            }
        } else if (div.uso === 'hall') {
            const r = encaixar(1.0, 0.35, () => true, 0.5);
            if (r) {
                peca(r.l, r.x - 0.5, r.x + 0.5, 0, 0.35, z, z + 0.8, 'nogueira');                    // console
                enfeite(r.l, r.x - 0.4, r.x + 0.4, 0, 0.02, z + 1.15, z + 2.0, 'vidro_vizinho');      // mirror
            }
            tapete(1.8, 1.0);
            planta();
        } else if (div.uso === 'circulacao') {
            tapete(Math.max(0, longo - 0.8), 0.7);                                                    // runner
            const l = [...lados].sort((x, y) => y.len - x.len)[0];
            quadro(l, 0, z + 1.3);
            planta();
        } else {
            tapete(1.6, 1.2);
            planta();
        }
        if (['sala', 'quarto', 'escritorio', 'outro', 'hall'].includes(div.uso)) cortinas();
    };

    const niveis = casa.niveis || [];
    const hb = limites(casa);
    const lote = casa.lote || [[hb[0] - 6, hb[1] - 6], [hb[2] + 6, hb[1] - 6], [hb[2] + 6, hb[3] + 6], [hb[0] - 6, hb[3] + 6]];

    // ---------- site ----------
    camada = 'lote';
    caixa(-300, -300, -0.4, 300, 300, -0.06, 'campo');
    laje(lote, -0.3, 0, 'calcada');
    (casa.relvado || []).forEach((p, k) => laje(p, -0.2, 0.03 + 0.003 * k, 'relva'));
    // a flat (casa.tipo 'apartamento') sits on floor casa.piso of a building: lifted by elev, its terraces are balconies
    const apto = casa.tipo === 'apartamento', elev = apto ? num(casa.piso, 0) * ANDAR : 0;
    for (const t of casa.terracos || []) {
        const m = t.material === 'pedra' ? 'calcada' : 'deck', h = num(t.altura, 0.12);
        if (elev > 0) laje(t.poligono, elev - 0.1, elev + 0.13, m);
        else if (h > 1) laje(t.poligono, h - 0.25, h, m);          // an upper-floor balcony / roof terrace: a slab, not a block to the ground
        else laje(t.poligono, -0.02, h, m);
    }
    if (casa.piscina?.poligono) {
        const [u1, v1, u2, v2] = bbox(casa.piscina.poligono), c = 0.25;
        for (const [a, b, cc, dd] of [[u1, v1, u2, v1 + c], [u1, v2 - c, u2, v2], [u1, v1 + c, u1 + c, v2 - c], [u2 - c, v1 + c, u2, v2 - c]]) caixa(a, b, -0.05, cc, dd, 0.06, 'capeamento');
        caixa(u1 + c, v1 + c, -0.2, u2 - c, v2 - c, 0.045, 'agua');   // above any lawn it sits on (≤ 0.04)
    }
    if (casa.rua) {
        const { de, a } = casa.rua, L = Math.hypot(a[0] - de[0], a[1] - de[1]), h = num(casa.rua.largura, 7) / 2;
        if (L > 1e-3) {
            const d = [(a[0] - de[0]) / L, (a[1] - de[1]) / L];
            caixaOrientada(de, d, -25, L + 25, -h, h, -0.3, -0.03, 'asfalto');
            for (const [o1, o2] of [[-h - 1.6, -h], [h, h + 1.6]]) caixaOrientada(de, d, -25, L + 25, o1, o2, -0.3, 0.12, 'calcada');
        }
    }
    for (const m of casa.muros || []) segmento(m.de, m.a, 0.2, -0.2, num(m.altura, 1.5), m.material === 'pedra' ? 'pedra' : 'reboco');

    camada = 'vegetacao';
    for (const [u, v] of casa.arvores || []) { cilindro(u, v, 0, 3.0, 0.15, 'casca', 8); bola(u, v, 4.2, 1.8, 1.25, 'folha'); }

    camada = 'vizinhos';
    for (const nb of casa.vizinhos || []) {
        const h = num(nb.altura, 6), [u1, v1, u2, v2] = bbox(nb.poligono);
        laje(nb.poligono, -0.1, h, 'vizinho');
        if (nb.cobertura !== 'plana') {
            const aoLongoU = u2 - u1 >= v2 - v1, sobe = 0.5 * (aoLongoU ? v2 - v1 : u2 - u1) * Math.tan(Math.PI / 6), o = 0.3;
            const pts = aoLongoU
                ? [[u1 - o, v1 - o, h], [u1 - o, v2 + o, h], [u1 - o, (v1 + v2) / 2, h + sobe], [u2 + o, v1 - o, h], [u2 + o, v2 + o, h], [u2 + o, (v1 + v2) / 2, h + sobe]]
                : [[u1 - o, v1 - o, h], [u2 + o, v1 - o, h], [(u1 + u2) / 2, v1 - o, h + sobe], [u1 - o, v2 + o, h], [u2 + o, v2 + o, h], [(u1 + u2) / 2, v2 + o, h + sobe]];
            solido(pts, [[0, 1, 2], [5, 4, 3], [0, 3, 4, 1], [1, 4, 5, 2], [2, 5, 3, 0]], 'vizinho', 'telha');
        }
        if (nb.poligono.length === 4) {           // a few dark windows on the long faces of a box neighbour
            const aoLongoU = u2 - u1 >= v2 - v1, vao = aoLongoU ? u2 - u1 : v2 - v1;
            for (let k = 0; k < Math.floor(vao / 3); k++) {
                for (let fl = 0; fl < Math.max(1, Math.floor(h / 2.9)); fl++) {
                    const z = 0.9 + fl * 2.9;
                    if (aoLongoU) for (const [vv, s] of [[v1, -1], [v2, 1]]) caixa(u1 + 1 + k * 3, vv + s * 0.01, z, u1 + 2.2 + k * 3, vv + s * 0.03, z + 1.2, 'vidro_vizinho');
                    else for (const [uu, s] of [[u1, -1], [u2, 1]]) caixa(uu + s * 0.01, v1 + 1 + k * 3, z, uu + s * 0.03, v1 + 2.2 + k * 3, z + 1.2, 'vidro_vizinho');
                }
            }
        }
    }

    // ---------- storeys ----------
    camada = 'casa';
    let wk = 0, cotaAnterior = null;
    const cotas = niveis.map((lv, i) => {
        const prev = i > 0 ? niveis[i - 1] : null;
        const c = num(lv.cota, i === 0 ? 0.15 : cotaAnterior + num(prev.pe_direito, 2.8) + num(lv.laje, 0.3));
        cotaAnterior = c;
        return c + elev;
    });
    let contorno0 = null;
    niveis.forEach((lv, i) => {
        nivel = String(i);
        const z0 = cotas[i], H = num(lv.pe_direito, 2.8), nx = niveis[i + 1];
        const teto = nx ? cotas[i + 1] - num(nx.laje, 0.3) : z0 + H;
        let contorno = lv.contorno;
        if (!contorno) {
            const ext = lv.paredes.filter((w) => w.exterior !== false);
            const [a1, b1, a2, b2] = bbox((ext.length ? ext : lv.paredes).flatMap((w) => [w.de, w.a]));
            contorno = [[a1 - 0.15, b1 - 0.15], [a2 + 0.15, b1 - 0.15], [a2 + 0.15, b2 + 0.15], [a1 - 0.15, b2 + 0.15]];
        }
        if (i === 0) contorno0 = contorno;
        laje(recuar(contorno, 0.02), z0 - num(lv.laje, 0.15) - (i === 0 ? 0.05 : 0), z0 - 0.01, 'betao');
        (lv.divisoes || []).forEach((div, k) => {
            laje(div.poligono, z0 - 0.012, z0 - 0.003 * (k % 3), PAVIMENTOS[div.uso] || 'carvalho');
            mobilar(div, z0, lv.paredes);
        });
        for (const w of lv.paredes) parede(w, z0, teto + 0.01, wk++);
        // a ceiling over every room, its underside a few mm under the slab/roof above and the walls running into it:
        // no daylight between a wall top and a roof outline that doesn't quite cover the room.
        // It goes with the storey above (or the roof), so "hide the roof" / "up to floor i" still open the rooms.
        const [cam0, niv0] = [camada, nivel];
        [camada, nivel] = nx ? ['casa', String(i + 1)] : ['cobertura', ''];
        // each room's ceiling runs 15 cm past its outline into the walls (room outlines read off a plan can stop
        // short of them), plus one over the storey's slab outline when the plan gives it
        const tetos = (lv.divisoes || []).map((d) => recuar(d.poligono, -0.15));
        if (lv.contorno || !tetos.length) tetos.push(recuar(contorno, 0.02));
        tetos.forEach((t, k) => laje(t, teto - 0.004 - 0.002 * (k % 3), teto + 0.25, 'reboco'));   // thick: a clean shadow caster
        [camada, nivel] = [cam0, niv0];
    });
    nivel = '';

    // ---------- roof ----------
    camada = 'cobertura';
    const topoCasa = Math.max(...niveis.map((lv, i) => cotas[i] + num(lv.pe_direito, 2.8)));
    // ponytail: a flat gets 2 storeys above it, whatever the real building has; ask for the storey count if it matters
    const ACIMA = 2, TOPO = apto ? topoCasa + 0.005 + ACIMA * ANDAR + 0.3 : topoCasa;
    const contornos = niveis.map((lv) => lv.contorno).filter(Boolean);
    const inclinada = (c, z0) => {
        const b = num(c.beirado, 0.4), [a1, b1, a2, b2] = bbox(c.poligono), [u1, v1, u2, v2] = [a1 - b, b1 - b, a2 + b, b2 + b];
        const tg = Math.tan(num(c.inclinacao, 30) * Math.PI / 180), m = COBERTURAS[c.material] || 'telha';
        const aoLongoU = c.cumeeira !== 'v';
        const [S1, S2, R1, R2] = aoLongoU ? [u1, u2, v1, v2] : [v1, v2, u1, u2];
        const Q = aoLongoU ? (s, r, z) => [s, r, z] : (s, r, z) => [r, s, z];
        const vao = R2 - R1, rm = (R1 + R2) / 2;
        if (c.tipo === 'uma_agua') {
            const h = vao * tg;
            solido([Q(S1, R1, z0), Q(S2, R1, z0), Q(S2, R2, z0), Q(S1, R2, z0), Q(S1, R1, z0 + h), Q(S2, R1, z0 + h)],
                [[0, 1, 2, 3], [0, 4, 5, 1], [4, 3, 2, 5], [0, 3, 4], [1, 5, 2]], 'reboco', m);
        } else {
            const h = vao / 2 * tg, anca = c.tipo === 'quatro_aguas' ? Math.min(vao / 2, (S2 - S1) / 2 - 0.01) : 0;
            solido([Q(S1, R1, z0), Q(S2, R1, z0), Q(S2, R2, z0), Q(S1, R2, z0), Q(S1 + anca, rm, z0 + h), Q(S2 - anca, rm, z0 + h)],
                [[0, 1, 2, 3], [0, 4, 5, 1], [3, 2, 5, 4], [0, 3, 4], [1, 5, 2]], 'reboco', m);
        }
    };
    if (apto) {
        // the building around the flat: its floors above (this layer, so "hide the roof" opens the flat) and below.
        // Its outline: the slab outline if it holds every wall, else the walls' rectangle square to the longest wall.
        const pts = niveis[0].paredes.flatMap((w) => [w.de, w.a]);
        if (!pts.every((q) => dentro(q, recuar(contorno0, -0.05)))) {
            const w = niveis[0].paredes.reduce((m, x) => (Math.hypot(x.a[0] - x.de[0], x.a[1] - x.de[1]) > Math.hypot(m.a[0] - m.de[0], m.a[1] - m.de[1]) ? x : m));
            const L = Math.hypot(w.a[0] - w.de[0], w.a[1] - w.de[1]), e = [(w.a[0] - w.de[0]) / L, (w.a[1] - w.de[1]) / L];
            const loc = pts.map(([u, v]) => [u * e[0] + v * e[1], -u * e[1] + v * e[0]]);
            const [s1, o1, s2, o2] = bbox(loc), g = 0.15;
            contorno0 = [[s1 - g, o1 - g], [s2 + g, o1 - g], [s2 + g, o2 + g], [s1 - g, o2 + g]].map(([sx, ox]) => [sx * e[0] - ox * e[1], sx * e[1] + ox * e[0]]);
        }
        const base = topoCasa + 0.005;
        laje(contorno0, base, base + ACIMA * ANDAR, 'reboco');
        laje(contorno0, base + ACIMA * ANDAR, TOPO, 'reboco', 'gravilha');
        fachada(contorno0, base, ACIMA);
        if (elev > 0) {
            camada = 'vizinhos';
            laje(contorno0, -0.05, cotas[0] - 0.17, 'reboco');
            fachada(contorno0, 0, num(casa.piso, 0));
            camada = 'cobertura';
        }
    }
    if (!apto) (casa.cobertura || []).forEach((c, k) => {
        const z0 = num(c.cota, TOPO) + 0.002 * k;
        if (c.tipo !== 'plana') return inclinada(c, z0);
        laje(c.poligono, z0, z0 + 0.3, 'reboco', COBERTURAS[c.material] || 'gravilha');
        // every element supported: a slim steel column under each free corner of an overhang (covered terraces, carports)
        for (const [u, v] of c.poligono) {
            const perto = [-0.8, 0, 0.8].flatMap((du) => [-0.8, 0, 0.8].map((dv) => [u + du, v + dv]));
            if (contornos.length && !perto.some((q) => contornos.some((o) => dentro(q, o)))) {
                const sx = dentro([u + 0.15, v], c.poligono) ? 0.15 : -0.15, sy = dentro([u, v + 0.15], c.poligono) ? 0.15 : -0.15;
                caixa(u + sx - 0.06, v + sy - 0.06, -0.02, u + sx + 0.06, v + sy + 0.06, z0 + 0.01, 'caixilho');
            }
        }
    });
    if (!apto && !(casa.cobertura || []).length) {   // no roof read: a flat slab over the top storey
        const lv = niveis[niveis.length - 1];
        laje(lv.contorno || [[hb[0] - 0.2, hb[1] - 0.2], [hb[2] + 0.2, hb[1] - 0.2], [hb[2] + 0.2, hb[3] + 0.2], [hb[0] - 0.2, hb[3] + 0.2]], TOPO, TOPO + 0.3, 'reboco', 'gravilha');
    }

    // ---------- terraces: a roof over those against the house (not rooftops, not under the house roof),
    // slim steel posts at its free corners, and a glass railing on the open edges of any raised one ----------
    const paredesTodas = niveis.flatMap((lv) => lv.paredes);
    const distSeg = ([px, py], [ax, ay], [bx, by]) => {
        const dx = bx - ax, dy = by - ay, t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy || 1)));
        return Math.hypot(px - ax - t * dx, py - ay - t * dy);
    };
    const junto = (p, d = 0.35) => paredesTodas.some((w) => distSeg(p, w.de, w.a) < d + num(w.espessura, 0.3) / 2);
    for (const t of casa.terracos || []) {
        const pol = t.poligono, n = pol.length;
        const cen = [pol.reduce((a, p) => a + p[0], 0) / n, pol.reduce((a, p) => a + p[1], 0) / n];
        const topoT = elev > 0 ? elev + 0.13 : num(t.altura, 0.12);
        const naCobertura = !apto && topoT >= topoCasa - 0.5;                     // a rooftop terrace
        const bordas = pol.map((a, i) => [a, pol[(i + 1) % n]]);
        const meio = ([a, b]) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
        const encostada = bordas.some((e) => junto(meio(e)));
        const sobTelhado = !apto && (casa.cobertura || []).some((c) => dentro(cen, c.poligono));
        // a paved yard around the house (it holds house walls) is never roofed; unasked, only terraces up to 40 m²
        const dentroT = recuar(pol, 0.3);                                         // ignore walls on its edge
        const rodeia = paredesTodas.some((w) => dentro(w.de, dentroT) && dentro(w.a, dentroT));
        if ((t.coberto ?? (encostada && Math.abs(area(pol)) <= 40)) && !rodeia && !naCobertura && !sobTelhado) {
            const k = cotas.reduce((j, c, i) => (c <= topoT + 0.3 ? i : j), 0);      // the storey it opens from
            const zr = cotas[k] + num(niveis[k].pe_direito, 2.8);
            camada = 'cobertura';
            laje(recuar(pol, -0.15), zr, zr + 0.22, 'reboco', 'gravilha');
            for (const p of pol) {
                if (junto(p, 0.6)) continue;
                const L = Math.hypot(cen[0] - p[0], cen[1] - p[1]) || 1, q = [p[0] + (cen[0] - p[0]) / L * 0.12, p[1] + (cen[1] - p[1]) / L * 0.12];
                caixa(q[0] - 0.05, q[1] - 0.05, topoT - 0.01, q[0] + 0.05, q[1] + 0.05, zr + 0.01, 'caixilho');
            }
            camada = 'lote';
        }
        if (topoT >= 0.4 || naCobertura) {
            for (const e of bordas) {
                const [a, b] = e, L = Math.hypot(b[0] - a[0], b[1] - a[1]);
                if (L < 0.2 || junto(meio(e))) continue;
                const d = [(b[0] - a[0]) / L, (b[1] - a[1]) / L], m = meio(e);
                const s = dentro([m[0] - d[1] * 0.1, m[1] + d[0] * 0.1], pol) ? 1 : -1;   // across d, into the terrace
                const o = (x1, x2) => [Math.min(s * x1, s * x2), Math.max(s * x1, s * x2)];
                caixaOrientada(a, d, 0.02, L - 0.02, ...o(0.05, 0.07), topoT, topoT + 1.0, 'vidro');
                caixaOrientada(a, d, 0, L, ...o(0.03, 0.09), topoT + 1.0, topoT + 1.04, 'caixilho');
            }
        }
    }
    camada = 'lote';

    // ---------- merge per layer and material ----------
    const mats = {};
    const raiz = new THREE.Group();
    raiz.name = 'imoplanta';
    const grupos = {};
    const grupo = (nome, pai, extra = {}) => {
        if (!grupos[nome]) {
            grupos[nome] = new THREE.Group();
            Object.assign(grupos[nome], { name: nome });
            Object.assign(grupos[nome].userData, extra);
            pai.add(grupos[nome]);
        }
        return grupos[nome];
    };
    for (const l of ['lote', 'vegetacao', 'vizinhos', 'casa', 'cobertura']) grupo(l, raiz, { layer: l });
    // texture axes follow the house's longest wall (mod 90°): boards, tiles and slats run square to a plan drawn rotated
    const longa = niveis.flatMap((lv) => lv.paredes).reduce((m, w) => {
        const L = Math.hypot(w.a[0] - w.de[0], w.a[1] - w.de[1]);
        return L > m.L ? { L, ang: Math.atan2(w.a[1] - w.de[1], w.a[0] - w.de[0]) } : m;
    }, { L: 0, ang: 0 });
    const eixo = ((longa.ang % (Math.PI / 2)) + Math.PI / 2) % (Math.PI / 2);
    for (const [k, geos] of baldes) {
        const [l, nv, m] = k.split('|');
        const g = mergeGeometries(geos, false);
        geos.forEach((x) => x.dispose());
        uvMundo(g, MATERIAIS[m][3]?.pattern, eixo);
        const mesh = new THREE.Mesh(g, mats[m] ||= material(m, opts));
        mesh.name = `${l}${nv !== '' ? nv : ''}:${m}`;
        mesh.castShadow = !mesh.material.transparent;
        mesh.receiveShadow = true;
        const pai = nv === '' ? grupos[l] : grupo(`nivel${nv}`, grupos.casa, { layer: 'casa', nivel: +nv });
        pai.add(mesh);
    }
    grupos.casa.children.sort((a, b) => (a.userData.nivel ?? -1) - (b.userData.nivel ?? -1));
    raiz.userData.niveis = niveis.length;
    raiz.userData.topo = TOPO;
    raiz.userData.chao = apto ? cotas[0] : 0;   // a flat's cameras stand on its floor
    raiz.userData.eixo = eixo;                    // the house's main wall direction (radians, mod 90°)
    return raiz;
}

// UVs in world metres (axes turned by ang), one texture tile = TILE x TILE cells of w x h.
const TILE = 4;
function uvMundo(g, pat, ang = 0) {
    const c = Math.cos(ang), sn = Math.sin(ang);
    const p = g.attributes.position.array, n = p.length / 3, uv = new Float32Array(n * 2);
    const parede = pat?.map === 'wall', sx = pat ? 1 / (pat.w >= 10 ? pat.w : pat.w * TILE) : 1, sy = pat ? 1 / (pat.h * TILE) : 1;
    for (let i = 0; i < n; i++) {
        const x0 = p[i * 3], y = p[i * 3 + 1], z0 = p[i * 3 + 2], x = x0 * c + z0 * sn, z = -x0 * sn + z0 * c;
        uv[i * 2] = (parede ? x - z : x) * sx;
        uv[i * 2 + 1] = (parede ? y : -z) * sy;
    }
    g.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
}

const srgb = (c) => {
    const f = (x) => (x <= 0.0031308 ? 12.92 * x : 1.055 * Math.pow(x, 1 / 2.4) - 0.055);
    return `rgb(${c.map((x) => Math.round(255 * f(Math.min(1, Math.max(0, x))))).join(',')})`;
};
/** Tiles / stripes on a 256 px canvas; null without a canvas (Node). */
function textura(pat, c1) {
    const N = 256;
    const cv = typeof OffscreenCanvas !== 'undefined' ? new OffscreenCanvas(N, N)
        : typeof document !== 'undefined' ? Object.assign(document.createElement('canvas'), { width: N, height: N }) : null;
    const ctx = cv?.getContext('2d');
    if (!ctx) return null;
    const riscas = pat.w >= 10, nx = riscas ? 1 : TILE, ny = TILE, cw = N / nx, ch = N / ny;
    let seed = 7;
    const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
    ctx.fillStyle = srgb(pat.gap); ctx.fillRect(0, 0, N, N);
    const mx = riscas ? 0 : Math.max(1, Math.round(pat.mortar / pat.w * cw)), my = Math.max(1, Math.round(pat.mortar / pat.h * ch));
    for (let j = 0; j < ny; j++) {
        for (let i = 0; i < nx; i++) {
            const t = rnd();
            ctx.fillStyle = srgb(c1.map((v, k) => v + (pat.color2[k] - v) * t));
            ctx.fillRect(i * cw + mx / 2, j * ch + my / 2, cw - mx, ch - my);
        }
    }
    const tex = new THREE.CanvasTexture(cv);
    tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.anisotropy = 8;
    return tex;
}
function material(nome, opts) {
    const [cor, rough, metal, extra = {}] = MATERIAIS[nome];
    const m = new THREE.MeshStandardMaterial({ name: nome, color: new THREE.Color().setRGB(...cor), roughness: rough, metalness: metal });
    if (extra.opacity !== undefined) Object.assign(m, { transparent: true, opacity: extra.opacity, depthWrite: false });
    if (extra.pattern && opts.textures !== false) {
        const tex = textura(extra.pattern, cor);
        if (tex) { m.map = tex; m.color.setRGB(1, 1, 1); }
    }
    return m;
}
