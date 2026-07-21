# IATECA — Variante Nocturna (Dark)

> Adaptação escura do sistema de identidade **IATECA** (ver ficheiro fonte [`IATECA-design-system.md`](../IATECA-design-system.md)) para a aplicação *Máquina de Conteúdo*. Mantém o espírito «biblioteca para a era das máquinas que pensam», apenas em fundo escuro.

## Princípio

A variante **Nocturna** já está prevista no manual IATECA (secção 2 — «Variações de paleta»): `papyrus: #1a1410`, `teal: #2dbab4`. Esta app assenta toda nessa variante. Regra herdada: **verde-azulado é dominante; couro e ouro são ornamento**, nunca corpo de texto.

## Paleta (tokens Tailwind v4 · `resources/css/app.css`)

| Token | HEX | Uso |
|---|---|---|
| `papyrus` | `#1a1410` | Fundo principal |
| `vellum` | `#221a13` | Cartões / fichas |
| `surface` | `#2a1f16` | Superfície elevada |
| `ink` | `#f4ead5` | Texto principal (o «papiro» torna-se tinta) |
| `ink-soft` | `#b8a68c` | Texto secundário, filetes |
| `ink-faint` | `#6f5f4b` | Notas ténues, cotas |
| `teal` | `#2dbab4` | **Cor primária** — acentos, links |
| `teal-deep` | `#1f7a7a` | Lombadas, variação profunda |
| `leather` | `#b5533f` | Selos, etiquetas (aclarado p/ dark) |
| `gold` | `#c89b3c` | Filetes ornamentais, bandas |
| `good` / `warn` / `bad` | `#6fbf73` / `#d8a24a` / `#d76a5a` | Estados / deltas de métricas |

## Tipografia

Google Fonts carregadas em `resources/views/components/layouts/app.blade.php`:

- **Cormorant Garamond** (`font-display`) — títulos, números grandes
- **Cormorant SC** (`font-sc`) — pequenas capitais / etiquetas (`.eyebrow`, `letter-spacing: 0.32em`)
- **EB Garamond** (`font-body`) — corpo
- **JetBrains Mono** (`font-mono`) — cotas, deltas, notas técnicas

## Motivos & utilitários CSS

| Classe | Efeito |
|---|---|
| `.bg-nocturna` | Fundo papiro escuro com brilho teal ténue + fibra |
| `.foxing` | Manchas suaves nos cantos (couro/ouro) |
| `.frame-engraved` | Moldura gravada dupla |
| `.shadow-engraved` | Sombra `3px 3px 0` (efeito gravura, sem blur moderno) |
| `.dropcap` | Capitular no 1.º parágrafo (cor teal) |
| `.eyebrow` | Pequenas capitais espaçadas |
| `.book-spine` | Lombada de livro (gradiente vertical + bandas douradas) |
| `.selo` | Selo circular rodado `-7°` |

## Componentes Blade (`resources/views/components/`)

`x-layout` (estante lateral + navegação), `x-page-header`, `x-fleuron`, `x-panel` (ficha), `x-metric-card`, `x-cota`, `x-selo`, `x-badge`, `x-empty-state`.

## Anti-padrões (herdados)

- ❌ Gradientes néon · ❌ emojis de marca · ❌ inglês não traduzido na interface
- ❌ `box-shadow` moderno com blur grande — preferir a sombra de gravura
- ❌ ícones «tech» de linha — usar glifos de biblioteca (❦ ❧ ☙ ◆ ⌛ ✂)

---
*IATECA · Nocturna · v1 · MMXXVI*
