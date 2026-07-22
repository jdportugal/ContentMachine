---
titulo: Estilo de Animação — IATECA
cota: "778.5 · IAT · '26"
versao: 1
uso: >
  Fonte única de verdade para o planeador de animações (GPT) e para as
  composições Remotion. O planeador está RESTRINGIDO ao vocabulário de
  primitivas aqui definido; qualquer plano usa apenas estes nomes.
---

# Estilo de Animação — IATECA

Manual da casa para clips animados. As animações têm de respirar a identidade
IATECA: papiro envelhecido, tinta sépia, verde-azulado como acento, ornamentos
eruditos. Pausado, com vagar — nunca hype tecnológico, nunca néon, nunca emojis.

## Primitivas

Vocabulário **fechado**. O planeador só pode usar estes nomes no campo
`primitive`. Cada animação ocupa a sua janela `[start, end]` (em segundos).

| primitive | propósito | params | duração mín–máx (s) |
|---|---|---|---|
| `fade` | aparecer/desaparecer suave de um bloco | `text?` | 0.4 – 3.0 |
| `slide` | entrada deslizante (de uma margem) | `text?`, `from` (`left`\|`right`\|`top`\|`bottom`) | 0.4 – 3.0 |
| `scale` | zoom de ênfase sobre texto/figura | `text?`, `from` (0–1) | 0.4 – 3.0 |
| `kinetic-text` | revelação palavra a palavra (fade + subida) | `text` (obrigatório) | 0.3 – 4.0 |
| `highlight` | realce de uma palavra/frase (fundo teal ténue) | `text` | 0.4 – 2.5 |
| `fleuron-draw` | fleurão ornamental (◆/❧) a desenhar-se | `glyph?` | 0.6 – 2.5 |
| `seal-stamp` | selo circular de biblioteca a carimbar (rotação −7°) | `text?` | 0.5 – 2.0 |
| `underline-sweep` | sublinhado a varrer sob um título | `text` | 0.4 – 2.0 |
| `count-up` | número a subir (métricas, datas) | `to` (obrigatório), `prefix?`, `suffix?` | 0.6 – 3.0 |
| `image-reveal` | revelação com máscara de uma figura | `src?`, `caption?` | 0.6 – 4.0 |
| `ambient` | movimento de fundo subtil (deriva papiro/foxing) | — | qualquer |
| `timeline` | linha temporal com marcos; o mais recente destacado | `items[]`, `caption?` | 2.0 – 6.0 |
| `bar-chart` | barras a crescer com valores | `bars[]`, `title?`, `unit?` | 1.5 – 5.0 |
| `comparison` | duas colunas lado a lado (A vs B) | `left{}`, `right{}` | 2.0 – 6.0 |
| `bullet-list` | itens a aparecer um a um | `items[]`, `title?` | 1.5 – 6.0 |

### Visualizações de dados — esquemas de `params`

- `timeline` → `{ "items": [{ "label": str, "sublabel"?: str, "highlight"?: bool }], "caption"?: str }`
- `bar-chart` → `{ "title"?: str, "unit"?: str, "bars": [{ "label": str, "value": number, "highlight"?: bool }] }`
- `comparison` → `{ "left": { "title": str, "points": [str] }, "right": { "title": str, "points": [str] } }`
- `bullet-list` → `{ "title"?: str, "items": [str] }`

## Tokens

Paleta (hex), usada tanto no prompt como em `remotion/src/style-tokens.ts`:

- `papyrus` `#f4ead5` — fundo principal (o "papel")
- `vellum` `#faf3e0` — cartões/fichas
- `ink` `#241a12` — texto, tinta
- `ink-soft` `#5b4636` — texto secundário, filetes
- `teal` `#1f7a7a` — **acento primário**
- `teal-bright` `#2dbab4` — acento sobre fundo escuro
- `leather` `#8b3a2a` — selos, etiquetas
- `gold` `#c89b3c` — filetes ornamentais

## Tipografia

- Títulos / display: **Cormorant Garamond** (500–600), itálico generoso.
- Corpo / legendas: **EB Garamond** (400).
- Técnico / cotas: **JetBrains Mono** (400).

## Regras

- **Idioma**: todo o texto visível (campo `text` e rótulos dentro de `params`)
  no **mesmo idioma da fala/transcrição**. Nunca traduzir.
- **Pensar visualmente**: preferir visualizações quando o conteúdo o justifica —
  sequências/versões ao longo do tempo → `timeline` (mais recente com
  `highlight`); quantidades/benchmarks → `bar-chart`; contraste entre duas
  coisas → `comparison`; enumerações/passos → `bullet-list`. Os dados são
  gerados pelo planeador a partir do que sabe, com rótulos curtos.
- **Modo `dense`** (separador "Animação"): a linha temporal cobre **100%** da
  duração — cada segundo tem animação. Onde o planeador não colocar nada,
  preenche-se com `ambient` (feito automaticamente pelo validador).
- **Modo `sparse`** (separador "Vídeo + Animações"): animações **apenas** nos
  momentos que valem a pena (números, nomes próprios, viragens, ênfases). As
  lacunas são esperadas — o vídeo original preenche o resto.
- No máximo **uma primitiva de primeiro plano** ativa de cada vez; `ambient`
  pode coexistir por baixo.
- *Easing* padrão: ease-in-out cúbico. Sem saltos bruscos.
- Alinhar o `text` das primitivas às palavras da transcrição e aos seus
  timestamps, para a animação acompanhar a locução.
- Sombras de gravura (`2px 2px 0`), nunca blur moderno. Sem gradientes néon.
