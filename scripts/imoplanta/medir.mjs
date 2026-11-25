// node scripts/imoplanta/medir.mjs [casa.json] — builds a house headless (no textures) with the browser's
// builder, checks the result and prints the build time. Default: the sample house (also the PHP tests' fixture).
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { performance } from 'node:perf_hooks';
import { buildCasa } from '../../resources/js/imoplanta/builder.js';

const src = process.argv[2] ?? new URL('exemplo_moradia.json', import.meta.url).pathname;
const casa = JSON.parse(readFileSync(src, 'utf8'));

buildCasa(casa, { textures: false });   // warm-up (JIT)
const tempos = [];
let g;
for (let i = 0; i < 10; i++) {
    const t = performance.now();
    g = buildCasa(casa, { textures: false });
    tempos.push(performance.now() - t);
}

let malhas = 0, tris = 0;
g.traverse((o) => {
    if (!o.isMesh) return;
    malhas++;
    const p = o.geometry.attributes.position;
    tris += p.count / 3;
    assert.ok(p.array.every(Number.isFinite), `${o.name}: NaN in the geometry`);
});
const camadas = Object.fromEntries(g.children.map((c) => [c.userData.layer, c.children.length]));
for (const l of ['lote', 'vegetacao', 'vizinhos', 'casa', 'cobertura']) assert.ok(l in camadas, `layer ${l} missing`);
assert.equal(g.getObjectByName('casa').children.length, casa.niveis.length, 'one group per storey');
assert.ok(g.getObjectByName('casa').children.every((n) => n.children.some((m) => m.material.name === 'vidro')), 'every storey has glass');

tempos.sort((a, b) => a - b);
console.log(JSON.stringify({ mediana_ms: +tempos[5].toFixed(1), min_ms: +tempos[0].toFixed(1), malhas, triangulos: tris, camadas }));
