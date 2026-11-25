<?php

namespace App\Services\Imoplanta;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The house description an LLM reads from the plans, and that
 * resources/js/imoplanta/builder.js turns into a 3D model (three.js, in the
 * customer's browser). Plain JSON, in plan metres:
 * u grows east (right on the drawing), v grows south (down on the drawing),
 * origin at the plot's top-left corner. Heights z are metres above the ground.
 *
 * {
 *   "medidas": ["Piso 0, u: 0.15 | 3.00 quarto | 0.12 | 2.81 quarto | 0.12 | 3.77 sala | 0.15 → 10.12 total", …],  // working notes: every cota chain
 *                                             // read, wall thicknesses included, and the wall-axis coordinates it gives
 *   "descricao": "Moradia T4 térrea, cobertura plana, reboco branco, pedra na entrada",  // used in the image prompts
 *   "escala_m_por_px": 0.037,                 // the scale read from the drawing (info only)
 *   "lote": [[u,v], …],                       // plot boundary polygon
 *   "niveis": [{                              // storeys, bottom first (cave = negative cota)
 *     "nome": "Piso 0",
 *     "cota": 0.15,                           // finished floor level
 *     "pe_direito": 2.8,                      // floor to ceiling
 *     "laje": 0.3,                            // slab under this floor
 *     "contorno": [[u,v], …],                 // floor slab outline (outer faces of the outer walls)
 *     "paredes": [{
 *       "de": [u,v], "a": [u,v],              // centre line
 *       "espessura": 0.3,                     // 0.3 outer, 0.12 inner
 *       "exterior": true,
 *       "material": "reboco|pedra|madeira|tijolo|betao",
 *       "aberturas": [{"pos": 1.2,            // metres from "de" to the opening's near edge
 *                      "largura": 0.9, "tipo": "janela|envidracado|porta|garagem|vao",
 *                      "peitoril": 0.9,         // sill above the floor (0 for doors)
 *                      "altura": 1.2}]
 *     }],
 *     "divisoes": [{"nome": "Sala", "uso": "sala|cozinha|quarto|wc|garagem|hall|escritorio|lavandaria|arrumos|circulacao|outro",
 *                   "poligono": [[u,v], …]}]
 *   }],
 *   "cobertura": [{"tipo": "plana|uma_agua|duas_aguas|quatro_aguas",
 *                  "poligono": [[u,v], …],      // pitched types use its bounding rectangle
 *                  "cumeeira": "u|v",           // ridge direction (pitched)
 *                  "inclinacao": 30, "beirado": 0.4,
 *                  "material": "gravilha|telha|zinco",
 *                  "cota": 6.1}],                // base height (optional: top of the highest storey)
 *   "terracos": [{"poligono": [[u,v], …], "material": "deck|pedra",
 *                 "altura": 0.12,             // top of its floor above the ground (an upper-floor balcony: that floor's level)
 *                 "coberto": true}],          // has a roof over it: true for porches, covered terraces and balconies against
 *                                             // the house; false for rooftop terraces, open patios and pool decks
 *   "piscina": {"poligono": [[u,v], …]} | null,
 *   "relvado": [[[u,v], …], …],
 *   "muros": [{"de": [u,v], "a": [u,v], "altura": 1.5, "material": "reboco|pedra"}],
 *   "arvores": [[u,v], …],
 *   "vizinhos": [{"poligono": [[u,v], …], "altura": 6, "cobertura": "plana|duas_aguas"}],
 *   "rua": {"de": [u,v], "a": [u,v], "largura": 7} | null
 * }
 *
 * A camera (the shot plan): {"u", "v"} plan metres, "altura" eye height above
 * the ground, "yaw" compass bearing it looks along (0 = north/up on the plan,
 * 90 = east, clockwise), "fov" horizontal field of view in degrees.
 */
class ImoplantaCasa
{
    public const USOS = ['sala', 'cozinha', 'quarto', 'wc', 'garagem', 'hall', 'escritorio', 'lavandaria', 'arrumos', 'circulacao', 'outro'];

    public const ABERTURAS = ['janela', 'envidracado', 'porta', 'garagem', 'vao'];

    public const COBERTURAS = ['plana', 'uma_agua', 'duas_aguas', 'quatro_aguas'];

    /** The schema above, for the LLM prompts. */
    public static function esquema(): string
    {
        $doc = (new \ReflectionClass(self::class))->getDocComment();

        return trim(preg_replace('/^\s*\* ?/m', '', substr($doc, 3, -2)));
    }

    /**
     * Checks an LLM's house and fills the defaults, so the builder gets a
     * well-formed description. Throws when there is nothing buildable.
     */
    public static function normalizar(mixed $casa): array
    {
        if (! is_array($casa) || empty($casa['niveis']) || ! is_array($casa['niveis'])) {
            throw new RuntimeException('A descrição da casa não tem pisos.');
        }

        $niveis = [];
        foreach (array_values($casa['niveis']) as $i => $n) {
            $paredes = [];
            foreach ((array) ($n['paredes'] ?? []) as $p) {
                $de = self::ponto($p['de'] ?? null);
                $a = self::ponto($p['a'] ?? null);
                if (! $de || ! $a || hypot($a[0] - $de[0], $a[1] - $de[1]) < 0.05) {
                    continue;
                }
                $comprimento = hypot($a[0] - $de[0], $a[1] - $de[1]);
                $aberturas = [];
                foreach ((array) ($p['aberturas'] ?? []) as $o) {
                    $tipo = in_array($o['tipo'] ?? '', self::ABERTURAS, true) ? $o['tipo'] : 'janela';
                    $porta = in_array($tipo, ['porta', 'garagem', 'vao'], true) || $tipo === 'envidracado';
                    $pos = self::num($o['pos'] ?? 0, 0, $comprimento);
                    if ($comprimento - $pos < 0.3) {
                        continue;
                    }
                    $largura = self::num($o['largura'] ?? 0.9, 0.3, $comprimento - $pos);
                    $aberturas[] = [
                        'pos' => $pos, 'largura' => $largura, 'tipo' => $tipo,
                        'peitoril' => self::num($o['peitoril'] ?? ($porta ? 0 : 0.9), 0, 2.5),
                        'altura' => self::num($o['altura'] ?? ($porta ? 2.1 : 1.2), 0.3, 3.5),
                    ];
                }
                $paredes[] = [
                    'de' => $de, 'a' => $a,
                    'espessura' => self::num($p['espessura'] ?? (($p['exterior'] ?? true) ? 0.3 : 0.12), 0.05, 0.8),
                    'exterior' => (bool) ($p['exterior'] ?? true),
                    'material' => (string) ($p['material'] ?? 'reboco'),
                    'aberturas' => $aberturas,
                ];
            }
            $divisoes = [];
            foreach ((array) ($n['divisoes'] ?? []) as $d) {
                if ($pol = self::poligono($d['poligono'] ?? null)) {
                    $divisoes[] = ['nome' => (string) ($d['nome'] ?? ''), 'uso' => in_array($d['uso'] ?? '', self::USOS, true) ? $d['uso'] : 'outro', 'poligono' => $pol];
                }
            }
            if ($paredes === []) {
                continue;
            }
            $niveis[] = [
                'nome' => (string) ($n['nome'] ?? 'Piso '.$i),
                'cota' => self::num($n['cota'] ?? ($i === 0 ? 0.15 : null), -6, 40),
                'pe_direito' => self::num($n['pe_direito'] ?? 2.8, 2.2, 6),
                'laje' => self::num($n['laje'] ?? ($i === 0 ? 0.15 : 0.3), 0.1, 0.6),
                'contorno' => self::poligono($n['contorno'] ?? null),
                'paredes' => $paredes,
                'divisoes' => $divisoes,
            ];
        }
        if ($niveis === []) {
            throw new RuntimeException('A descrição da casa não tem paredes.');
        }
        // Storeys without a level stack on the one below.
        for ($i = 1; $i < count($niveis); $i++) {
            $baixo = $niveis[$i - 1];
            $niveis[$i]['cota'] ??= $baixo['cota'] + $baixo['pe_direito'] + $niveis[$i]['laje'];
        }

        $coberturas = [];
        foreach ((array) ($casa['cobertura'] ?? []) as $c) {
            if ($pol = self::poligono($c['poligono'] ?? null)) {
                $coberturas[] = [
                    'tipo' => in_array($c['tipo'] ?? '', self::COBERTURAS, true) ? $c['tipo'] : 'plana',
                    'poligono' => $pol,
                    'cumeeira' => ($c['cumeeira'] ?? 'u') === 'v' ? 'v' : 'u',
                    'inclinacao' => self::num($c['inclinacao'] ?? 30, 5, 60),
                    'beirado' => self::num($c['beirado'] ?? 0.4, 0, 2),
                    'material' => (string) ($c['material'] ?? 'gravilha'),
                    'cota' => self::num($c['cota'] ?? null, -6, 60),
                ];
            }
        }

        $poligonos = fn ($l) => array_values(array_filter(array_map(fn ($x) => self::poligono($x), (array) $l)));
        $segmentos = fn ($l, $extra) => array_values(array_filter(array_map(function ($m) use ($extra) {
            $de = self::ponto($m['de'] ?? null);
            $a = self::ponto($m['a'] ?? null);

            return $de && $a ? ['de' => $de, 'a' => $a] + $extra($m) : null;
        }, (array) $l)));

        return [
            'descricao' => mb_substr((string) ($casa['descricao'] ?? ''), 0, 600),
            'escala_m_por_px' => is_numeric($casa['escala_m_por_px'] ?? null) ? (float) $casa['escala_m_por_px'] : null,
            'lote' => self::poligono($casa['lote'] ?? null),
            'niveis' => $niveis,
            'cobertura' => $coberturas,
            'terracos' => array_values(array_filter(array_map(fn ($t) => ($pol = self::poligono($t['poligono'] ?? null))
                ? ['poligono' => $pol, 'material' => ($t['material'] ?? 'deck') === 'pedra' ? 'pedra' : 'deck', 'altura' => self::num($t['altura'] ?? 0.12, 0, 40)]
                    + (isset($t['coberto']) ? ['coberto' => (bool) $t['coberto']] : [])
                : null, (array) ($casa['terracos'] ?? [])))),
            'piscina' => ($pol = self::poligono($casa['piscina']['poligono'] ?? null)) ? ['poligono' => $pol] : null,
            'relvado' => $poligonos($casa['relvado'] ?? []),
            'muros' => $segmentos($casa['muros'] ?? [], fn ($m) => ['altura' => self::num($m['altura'] ?? 1.5, 0.3, 4), 'material' => ($m['material'] ?? '') === 'pedra' ? 'pedra' : 'reboco']),
            'arvores' => array_values(array_filter(array_map(fn ($p) => self::ponto($p), (array) ($casa['arvores'] ?? [])))),
            'vizinhos' => array_values(array_filter(array_map(fn ($v) => ($pol = self::poligono($v['poligono'] ?? null))
                ? ['poligono' => $pol, 'altura' => self::num($v['altura'] ?? 6, 2.5, 40), 'cobertura' => ($v['cobertura'] ?? '') === 'plana' ? 'plana' : 'duas_aguas']
                : null, (array) ($casa['vizinhos'] ?? [])))),
            'rua' => ($r = $segmentos([$casa['rua'] ?? null], fn ($m) => ['largura' => self::num($m['largura'] ?? 7, 3, 30)])) ? $r[0] : null,
        ];
    }

    /**
     * What a house reading obviously got wrong, for a second look by the LLM: rooms with a part too narrow
     * to walk through (a corridor squeezed by walls in the wrong place), and rooms with no way in — no door,
     * archway or glass door on their walls, and no open side (open plan).
     *
     * @return list<string>
     */
    public static function problemas(array $casa): array
    {
        $erros = [];
        foreach ($casa['niveis'] ?? [] as $n) {
            $portas = [];
            foreach ($n['paredes'] as $p) {
                [$du, $dv] = [$p['a'][0] - $p['de'][0], $p['a'][1] - $p['de'][1]];
                $l = hypot($du, $dv);
                foreach ($p['aberturas'] as $o) {
                    if ($o['tipo'] !== 'janela') {
                        $t = ($o['pos'] + $o['largura'] / 2) / $l;
                        $portas[] = [$p['de'][0] + $du * $t, $p['de'][1] + $dv * $t, $p['espessura'] / 2 + 0.25];
                    }
                }
            }
            foreach ($n['divisoes'] as $d) {
                $nome = "«{$d['nome']}» ({$n['nome']})";
                if (self::estreito($d['poligono']) >= 0.1) {
                    $erros[] = "{$nome} has a part narrower than 0.7 m (nobody can walk there): a wall is in the wrong place, check the cotas.";
                }
                if (! self::temEntrada($d['poligono'], $portas, $n['paredes'])) {
                    $erros[] = "{$nome} has no door, archway or glass door on any of its walls: there is no way in or out.";
                }
            }
        }

        return $erros;
    }

    /** Area (m²) of a room no 0.7 m wide person fits in: what a 0.35 m disc rolled around inside can't reach, corners aside. */
    private static function estreito(array $pol): float
    {
        $r = 0.35;
        $g = 0.1;
        [$u0, $v0] = [min(array_column($pol, 0)), min(array_column($pol, 1))];
        $nu = (int) ceil((max(array_column($pol, 0)) - $u0) / $g);
        $nv = (int) ceil((max(array_column($pol, 1)) - $v0) / $g);
        $dentro = $centro = [];
        for ($i = 0; $i < $nu; $i++) {
            for ($j = 0; $j < $nv; $j++) {
                [$u, $v] = [$u0 + ($i + 0.5) * $g, $v0 + ($j + 0.5) * $g];
                if (self::dentro($u, $v, $pol)) {
                    $dentro[] = [$i, $j];
                    $longe = true;
                    for ($a = 0, $b = count($pol) - 1; $a < count($pol) && $longe; $b = $a++) {
                        $longe = self::distancia($u, $v, $pol[$b], $pol[$a]) >= $r - 0.01;
                    }
                    if ($longe) {
                        $centro["$i,$j"] = true;
                    }
                }
            }
        }
        $k = (int) ceil($r / $g);
        $falta = 0;
        foreach ($dentro as [$i, $j]) {
            $coberto = false;
            for ($a = -$k; $a <= $k && ! $coberto; $a++) {
                for ($b = -$k; $b <= $k && ! $coberto; $b++) {
                    $coberto = $a * $a + $b * $b <= $k * $k && isset($centro[($i + $a).','.($j + $b)]);
                }
            }
            if (! $coberto) {   // a corner's tip is always out of reach: count only cells away from every corner
                [$u, $v] = [$u0 + ($i + 0.5) * $g, $v0 + ($j + 0.5) * $g];
                $falta += min(array_map(fn ($q) => hypot($u - $q[0], $v - $q[1]), $pol)) > $r * 1.5 ? 1 : 0;
            }
        }

        return $falta * $g * $g;
    }

    /** A door's middle on one of the room's edges, or 0.6 m or more of an edge with no wall on it (open plan). */
    private static function temEntrada(array $pol, array $portas, array $paredes): bool
    {
        for ($i = 0, $j = count($pol) - 1; $i < count($pol); $j = $i++) {
            [$a, $b] = [$pol[$j], $pol[$i]];
            foreach ($portas as [$u, $v, $tol]) {
                if (self::distancia($u, $v, $a, $b) <= $tol) {
                    return true;
                }
            }
            $l = hypot($b[0] - $a[0], $b[1] - $a[1]);
            $aberto = 0;
            for ($t = 0.3; $t <= $l - 0.3; $t += 0.1) {
                [$u, $v] = [$a[0] + ($b[0] - $a[0]) * $t / $l, $a[1] + ($b[1] - $a[1]) * $t / $l];
                $parede = false;
                foreach ($paredes as $p) {
                    if (self::distancia($u, $v, $p['de'], $p['a']) <= $p['espessura'] / 2 + 0.2) {
                        $parede = true;
                        break;
                    }
                }
                $aberto = $parede ? 0 : $aberto + 0.1;
                if ($aberto >= 0.6) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function distancia(float $u, float $v, array $a, array $b): float
    {
        [$du, $dv] = [$b[0] - $a[0], $b[1] - $a[1]];
        $t = ($du * $du + $dv * $dv) > 0 ? max(0, min(1, (($u - $a[0]) * $du + ($v - $a[1]) * $dv) / ($du * $du + $dv * $dv))) : 0;

        return hypot($u - $a[0] - $t * $du, $v - $a[1] - $t * $dv);
    }

    /** The house's bounding box in plan metres: [u1, v1, u2, v2]. */
    public static function limites(array $casa): array
    {
        $pts = [];
        foreach ($casa['niveis'] as $n) {
            foreach ($n['paredes'] as $p) {
                $pts[] = $p['de'];
                $pts[] = $p['a'];
            }
        }

        return [min(array_column($pts, 0)), min(array_column($pts, 1)), max(array_column($pts, 0)), max(array_column($pts, 1))];
    }

    /**
     * The planner's top view: a square around the plot and the house, 4 m of
     * margin, in plan metres. Cameras are validated against it.
     *
     * @return array{u1:float,v1:float,u2:float,v2:float}
     */
    public static function vista(array $casa): array
    {
        [$u1, $v1, $u2, $v2] = self::limites($casa);
        foreach ($casa['lote'] ?? [] as [$u, $v]) {
            [$u1, $v1, $u2, $v2] = [min($u1, $u), min($v1, $v), max($u2, $u), max($v2, $v)];
        }
        $s = max($u2 - $u1, $v2 - $v1) + 8;
        $cu = ($u1 + $u2) / 2;
        $cv = ($v1 + $v2) / 2;

        return ['u1' => round($cu - $s / 2, 3), 'v1' => round($cv - $s / 2, 3), 'u2' => round($cu + $s / 2, 3), 'v2' => round($cv + $s / 2, 3)];
    }

    /** The lens of every camera the plan starts with (and of one added in the editor): the widest the planner allows. */
    public const FOV = 110;

    /**
     * A first shot plan: the outside from the front (a detached house: off its two front corners; a semi-detached
     * or terraced one, whose sides touch the neighbours: straight on, front and back), then inside the living room,
     * kitchen, main bedroom and main bathroom (a flat: inside only). The front is the side facing the street, or
     * the bottom of the drawing when no street is drawn. Outside cameras stand inside the plot (1.5 m in from its
     * edge), so the boundary walls don't fill the picture.
     */
    public static function camerasIniciais(array $casa, string $tipologia = 'isolada'): array
    {
        $dentro = self::camerasInteriores($casa);
        if ($tipologia === 'apartamento' && $dentro) {
            return $dentro;
        }
        [$u1, $v1, $u2, $v2] = self::limites($casa);
        $cu = ($u1 + $u2) / 2;
        $cv = ($v1 + $v2) / 2;
        $d = max(4.0, 0.35 * max($u2 - $u1, $v2 - $v1));
        $lote = $casa['lote'] ?? null;

        // The front: unit axis towards the street's middle, or down the drawing.
        [$fu, $fv] = [0, 1];
        if ($r = $casa['rua'] ?? null) {
            [$ru, $rv] = [($r['de'][0] + $r['a'][0]) / 2 - $cu, ($r['de'][1] + $r['a'][1]) / 2 - $cv];
            [$fu, $fv] = abs($ru) > abs($rv) ? [$ru <=> 0, 0] : [0, $rv <=> 0];
        }
        $frente = [$cu + $fu * (($u2 - $u1) / 2 + $d), $cv + $fv * (($v2 - $v1) / 2 + $d)];
        $fora = in_array($tipologia, ['geminada', 'banda'], true)
            ? [$frente, [$cu - $fu * (($u2 - $u1) / 2 + $d), $cv - $fv * (($v2 - $v1) / 2 + $d)]]
            : array_values(array_filter([[$u2 + $d, $v2 + $d], [$u1 - $d, $v2 + $d], [$u1 - $d, $v1 - $d], [$u2 + $d, $v1 - $d]],
                fn ($c) => ($c[0] - $cu) * $fu + ($c[1] - $cv) * $fv > 0));

        $fora = array_map(function ($c) use ($cu, $cv, $lote) {
            [$u, $v] = $c;
            if ($lote && ! self::dentro($u, $v, $lote)) {   // walk towards the house until inside, then 1.5 m more
                $l = hypot($cu - $u, $cv - $v);
                for ($t = 0; $t < $l && ! self::dentro($u + ($cu - $u) * $t / $l, $v + ($cv - $v) * $t / $l, $lote); $t += 0.25);
                $t = min($l, $t + 1.5);
                [$u, $v] = [$u + ($cu - $u) * $t / $l, $v + ($cv - $v) * $t / $l];
            }

            return ['u' => round($u, 2), 'v' => round($v, 2), 'altura' => 1.6, 'yaw' => round(self::rumo($u, $v, $cu, $cv), 1), 'fov' => self::FOV];
        }, $fora);

        // An outside camera that lands in an outbuilding (a garage, a shed in the plot) is no outside view.
        $fora = array_filter($fora, fn ($c) => ! self::divisaoDa($casa, $c));

        return array_slice([...$fora, ...$dentro], 0, (int) config('contentmachine.imoplanta.max_cameras'));
    }

    /**
     * Cameras inside the main rooms: the biggest living room, kitchen, bedroom and bathroom, on any storey.
     * Each stands 0.7 m in from the room corner nearest the house's middle (the door side) and looks across the
     * room towards its outer walls and windows, eye height 1.5 m above that storey's floor.
     */
    private static function camerasInteriores(array $casa): array
    {
        [$u1, $v1, $u2, $v2] = self::limites($casa);
        [$mu, $mv] = [($u1 + $u2) / 2, ($v1 + $v2) / 2];
        $area = function (array $pol) {
            $a = 0;
            for ($i = 0, $j = count($pol) - 1; $i < count($pol); $j = $i++) {
                $a += $pol[$j][0] * $pol[$i][1] - $pol[$i][0] * $pol[$j][1];
            }

            return abs($a) / 2;
        };
        $melhor = [];
        foreach ($casa['niveis'] ?? [] as $n) {
            foreach ($n['divisoes'] ?? [] as $d) {
                $uso = $d['uso'] ?? '';
                if (in_array($uso, ['sala', 'cozinha', 'quarto', 'wc'], true) && count($d['poligono'] ?? []) >= 3
                    && $area($d['poligono']) > ($melhor[$uso]['area'] ?? 0)) {
                    $melhor[$uso] = ['area' => $area($d['poligono']), 'poligono' => $d['poligono'], 'chao' => (float) ($n['cota'] ?? 0.15)];
                }
            }
        }

        return array_values(array_map(function ($uso) use ($melhor, $mu, $mv) {
            $pol = $melhor[$uso]['poligono'];
            [$cu, $cv] = [array_sum(array_column($pol, 0)) / count($pol), array_sum(array_column($pol, 1)) / count($pol)];
            usort($pol, fn ($a, $b) => hypot($a[0] - $mu, $a[1] - $mv) <=> hypot($b[0] - $mu, $b[1] - $mv));
            [$pu, $pv] = $pol[0];
            $l = max(0.01, hypot($cu - $pu, $cv - $pv));
            $t = min(0.7, $l / 2);
            [$u, $v] = [$pu + ($cu - $pu) * $t / $l, $pv + ($cv - $pv) * $t / $l];

            return ['u' => round($u, 2), 'v' => round($v, 2), 'altura' => round($melhor[$uso]['chao'] + 1.5, 2), 'yaw' => round(self::rumo($u, $v, $cu, $cv), 1), 'fov' => self::FOV];
        }, array_values(array_filter(['sala', 'cozinha', 'quarto', 'wc'], fn ($u) => isset($melhor[$u])))));
    }

    /** The storey the top-view reference shows: the one with the most rooms (the ground floor on a tie). */
    public static function nivelPrincipal(array $casa): int
    {
        $melhor = 0;
        foreach ($casa['niveis'] ?? [] as $i => $n) {
            if (count($n['divisoes'] ?? []) > count($casa['niveis'][$melhor]['divisoes'] ?? [])) {
                $melhor = $i;
            }
        }

        return $melhor;
    }

    /** Compass bearing (0 = north/−v, clockwise) from one plan point towards another. */
    public static function rumo(float $u, float $v, float $tu, float $tv): float
    {
        return fmod(rad2deg(atan2($tu - $u, -($tv - $v))) + 360, 360);
    }

    /**
     * Validates a shot plan from the browser and returns it clean.
     *
     * @throws ValidationException
     */
    public static function validarCameras(mixed $cameras, array $casa): array
    {
        $topo = self::vista($casa);
        $m = 40;   // cameras may stand a little outside the top view (street, drone)
        $v = Validator::make(['cameras' => $cameras], [
            'cameras' => ['required', 'array', 'min:1', 'max:'.config('contentmachine.imoplanta.max_cameras')],
            'cameras.*.u' => ['required', 'numeric', 'between:'.($topo['u1'] - $m).','.($topo['u2'] + $m)],
            'cameras.*.v' => ['required', 'numeric', 'between:'.($topo['v1'] - $m).','.($topo['v2'] + $m)],
            'cameras.*.altura' => ['required', 'numeric', 'between:0.5,120'],
            'cameras.*.yaw' => ['required', 'numeric', 'between:-360,720'],
            'cameras.*.fov' => ['required', 'numeric', 'between:20,110'],
        ], [
            'cameras.required' => 'Coloque pelo menos uma câmara.',
            'cameras.min' => 'Coloque pelo menos uma câmara.',
            'cameras.max' => 'No máximo :max câmaras por imóvel.',
            'cameras.*.u.between' => 'Há uma câmara demasiado longe da casa.',
            'cameras.*.v.between' => 'Há uma câmara demasiado longe da casa.',
            'cameras.*.altura.between' => 'A altura da câmara tem de estar entre 0,5 e 120 m.',
            'cameras.*.fov.between' => 'O ângulo de visão tem de estar entre 20° e 110°.',
        ]);
        $v->validate();

        return array_map(fn ($c) => [
            'u' => round((float) $c['u'], 2), 'v' => round((float) $c['v'], 2), 'altura' => round((float) $c['altura'], 2),
            'yaw' => round(fmod((float) $c['yaw'] + 720, 360), 1), 'fov' => round((float) $c['fov'], 1),
        ], array_values($cameras));
    }

    /**
     * The room a camera stands in (null outside the house): on the storey at its eye height (a flat's cameras
     * count from the flat's floor, so its only storey), and only below 8 m (higher is a drone view).
     */
    public static function divisaoDa(array $casa, array $camera, bool $apartamento = false): ?array
    {
        $i = self::nivelDa($casa, $camera, $apartamento);

        return $i === null ? null : self::salaDa($casa['niveis'][$i], $camera);
    }

    /** The storey (index) of the room a camera stands in; null outside the house or above 8 m. */
    public static function nivelDa(array $casa, array $camera, bool $apartamento = false): ?int
    {
        $altura = (float) ($camera['altura'] ?? 1.6);
        $niveis = $casa['niveis'] ?? [];
        if ($altura > 8 || $niveis === []) {
            return null;
        }
        $i = 0;
        if (! $apartamento) {
            foreach ($niveis as $k => $n) {
                if ((float) ($n['cota'] ?? 0.15) <= $altura) {
                    $i = $k;
                }
            }
        }

        return self::salaDa($niveis[$i], $camera) ? $i : null;
    }

    private static function salaDa(array $nivel, array $camera): ?array
    {
        foreach ($nivel['divisoes'] ?? [] as $d) {
            if (count($d['poligono'] ?? []) >= 3 && self::dentro((float) $camera['u'], (float) $camera['v'], $d['poligono'])) {
                return $d;
            }
        }

        return null;
    }

    private static function dentro(float $u, float $v, array $poly): bool
    {
        $c = false;
        for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
            [$x1, $y1] = $poly[$j];
            [$x2, $y2] = $poly[$i];
            if (($y1 > $v) !== ($y2 > $v) && $u < $x1 + ($v - $y1) * ($x2 - $x1) / ($y2 - $y1)) {
                $c = ! $c;
            }
        }

        return $c;
    }

    private static function num(mixed $x, float $min, ?float $max): ?float
    {
        if (! is_numeric($x)) {
            return $x === null ? null : $min;
        }

        return round(max($min, $max === null ? (float) $x : min($max, (float) $x)), 3);
    }

    private static function ponto(mixed $p): ?array
    {
        return is_array($p) && count($p) >= 2 && is_numeric($p[0] ?? null) && is_numeric($p[1] ?? null)
            && abs($p[0]) < 2000 && abs($p[1]) < 2000 ? [round((float) $p[0], 3), round((float) $p[1], 3)] : null;
    }

    private static function poligono(mixed $p): ?array
    {
        $pts = array_values(array_filter(array_map(fn ($x) => self::ponto($x), is_array($p) ? $p : [])));

        return count($pts) >= 3 ? $pts : null;
    }
}
