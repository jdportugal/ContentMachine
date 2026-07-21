# IATECA — Sistema de Identidade Visual

> Manual portátil. Cole este ficheiro em qualquer projecto (`CLAUDE.md`, `README.md`, brief para um designer, prompt para um modelo) e estará tudo aqui — paleta, tipografia, voz, motivos, regras de composição.

---

## 1. Espírito

IATECA é uma **biblioteca para a era das máquinas que pensam**. O sistema visual nasce daí: papiro envelhecido, lombadas de couro, tinta sépia, ornamentos eruditos — mas com uma cor primária inequivocamente moderna (verde-azulado).

| Sim ✓ | Não ✗ |
|---|---|
| Erudito mas convidativo | Hype tecnológico |
| Metáforas literárias (capítulo, ofício, cota) | Anglicismos sem tradução |
| Pausado, com vagar | Emojis, exclamações, urgência |
| Português europeu | "Tech-bro" tone |

**Audiência:** principiantes totais em IA. Tudo deve poder ser lido por quem nunca viu um modelo.

---

## 2. Paleta

### Primária

| Token | HEX | Uso |
|---|---|---|
| `--papyrus` | `#f4ead5` | Fundo principal — o "papel" |
| `--vellum` | `#faf3e0` | Fundo claro alternativo (cartões, fichas) |
| `--ink` | `#241a12` | Texto, fundo escuro |
| `--ink-soft` | `#5b4636` | Texto secundário, filetes |
| `--teal` | `#1f7a7a` | **Cor primária** — acentos, links, ênfase |
| `--teal-bright` | `#2dbab4` | Cor primária sobre fundo escuro |

### Acentos

| Token | HEX | Uso |
|---|---|---|
| `--leather` | `#8b3a2a` | Selos, etiquetas, corpos editoriais |
| `--gold` | `#c89b3c` | Filetes ornamentais, lombadas |
| `--shelf` | `#5a3a22` | Madeira de estante |

### Variações de paleta (para Tweaks)

- **Biblioteca** — predefinida acima
- **Papiro** — `teal: #0e6b6b`, `leather: #3a2a1c`, `gold: #a08358` (mais terroso)
- **Nocturna** — `papyrus: #1a1410`, `teal: #2dbab4` (escura)
- **Mínima** — só verde-azulado + tinta + papiro (sem couro/ouro)

> **Regra:** verde-azulado é dominante; couro e ouro são **ornamento**, nunca corpo de texto.

---

## 3. Tipografia

| Papel | Família | Notas |
|---|---|---|
| Display / títulos | **Cormorant Garamond** (500–600) | Para tudo que é grande. Use itálico generosamente. |
| Pequenas capitais / etiquetas | **Cormorant SC** | `letter-spacing: 0.32em` em rótulos curtos ("VOL · I", "EX · LIBRIS") |
| Corpo | **EB Garamond** (400) | Texto longo, legendas, descrições |
| Margem / técnico | **JetBrains Mono** (400) | Cotas (`006.3 · IAT · '26`), notas de rodapé, código |

**Importação Google Fonts:**
```html
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Cormorant+SC:wght@500;600&family=EB+Garamond:ital,wght@0,400;0,500;1,400&family=JetBrains+Mono:wght@400&display=swap" rel="stylesheet">
```

**Escalas-base:**
- Display herói: 96–240 px
- H1: 60–96
- H2: 36–48
- Corpo: 18–22 (decks: ≥24)
- Mono / margem: 11–14

**Hábitos:**
- `text-wrap: balance` em títulos
- `text-wrap: pretty` em corpo
- *Drop cap* no primeiro parágrafo de posts e artigos longos (cor `--teal`)
- Latim ornamental aceitável: `EX · LIBRIS`, `VOL · I`, `MMXXVI`, `OPUS`, `LIBER`

---

## 4. Motivos visuais

1. **Lombadas de livros** — colunas, cabeçalhos, ilustrações de fundo. Cores: couro, índigo, verde-azulado profundo. Bandas douradas, títulos em pequenas capitais verticais (`OPUS`, `CODEX`, `ARS`).
2. **Papiro envelhecido** — manchas (*foxing*) suaves nos cantos, linhas de "fibra" muito subtis. CSS-only com `radial-gradient` em camadas.
3. **Fleurões / ornamentos** — divisórias finas com um glifo central (losango ornamental). Substitui linhas planas em cabeçalhos e separadores.
4. **Selos circulares** — estilo carimbo de biblioteca. Rotação de `-7°`, opacidade `~0.85`, cor couro ou verde-azulado.
5. **Cotas (call-numbers)** — pequenos cartões à máquina-de-escrever, três linhas: `006.3 / IAT / '26`.
6. **Diagramas desenhados à mão** — caixas com `box-shadow` deslocada (efeito "feito a lápis"), linhas tracejadas, anotações em itálico.
7. **Bordas gravadas** — duas linhas a 4–8 px de distância, cor `--ink-soft`. Substitui a moldura única.

---

## 5. Componentes (recipes)

### 5.1 Texturas de fundo

```css
.bg-papyrus {
  background-color: #f4ead5;
  background-image:
    radial-gradient(ellipse 120% 80% at 50% 0%, rgba(255,255,255,.5), transparent 60%),
    radial-gradient(ellipse 120% 80% at 50% 100%, rgba(90,58,34,.18), transparent 60%),
    radial-gradient(circle at 18% 30%, rgba(110,70,40,.06) 0 1px, transparent 1.5px),
    radial-gradient(circle at 72% 60%, rgba(110,70,40,.05) 0 1px, transparent 1.5px),
    repeating-linear-gradient(92deg, transparent 0 23px, rgba(110,70,40,.025) 23px 24px);
}
.foxing::before {
  content:''; position:absolute; inset:0; pointer-events:none;
  background-image:
    radial-gradient(ellipse 30px 18px at 12% 22%, rgba(140,80,40,.10), transparent 70%),
    radial-gradient(ellipse 18px 12px at 88% 14%, rgba(140,80,40,.08), transparent 70%),
    radial-gradient(ellipse 22px 14px at 78% 82%, rgba(140,80,40,.09), transparent 70%);
}
```

### 5.2 Filete com fleurão

Linha — losango — linha. Cor `--ink-soft`, opacidade 0.45.

### 5.3 Lombada de livro (SVG ou divs)

- Largura 14–36 px, altura 200–400 px
- Gradiente vertical: escuro → cor → realce → cor → escuro
- Bandas douradas a 18 % e 82 % da altura
- Título em escrita vertical, pequenas capitais, ouro ou papiro

### 5.4 Selo de biblioteca

```
borda exterior 2 px + borda interior 1 px (4 px de offset)
rotação -7°
opacidade 0.85
fonte Cormorant SC, letter-spacing 0.16em
3 linhas: rótulo, sub-rótulo, data em romano (MMXXVI)
```

### 5.5 Moldura gravada (capas, posts)

```css
.frame-engraved {
  position: relative;
  outline: 1px solid var(--ink-soft);
  outline-offset: 8px;
}
.frame-engraved::before {
  content:''; position:absolute; inset:4px;
  border:1px solid var(--ink-soft); opacity:.5;
}
```

---

## 6. Formatos & dimensões

| Tipo | Tamanho | Notas |
|---|---|---|
| Capa / banner | 1600 × 600 | Hero escuro, papiro, ou citação minimal |
| Post quadrado | 1080 × 1080 | "Sabia que…", anúncios |
| Story | 1080 × 1920 | Topo papiro, fundo escuro com estante |
| Carrossel | 1080 × 1080 × N | Capa → conteúdo → despedida |
| Slide deck | 1920 × 1080 | Páginas estilizadas como capítulos |
| Diagrama | 1080 × 720 | Fundo vellum, fig. número em pequenas capitais |

---

## 7. Composição — receita de uma peça

1. **Fundo papiro** com `foxing`.
2. **Moldura gravada** dupla a 28–40 px do bordo.
3. **Cabeçalho:** logotipo à esquerda, cota / numeração à direita. Pequenas capitais.
4. **Filete com fleurão** abaixo.
5. **Conteúdo:** título grande em Cormorant; corpo em EB Garamond com drop-cap.
6. **Filete com fleurão** acima do rodapé.
7. **Rodapé:** mono, cinzento, três células (capítulo · ornamento · cota).

---

## 8. Logotipo

- **Wordmark completo** "IATECA" em Cormorant Garamond 600, `letter-spacing: 0.06em`, cor `--teal`.
- **Tagline opcional** abaixo: pequenas capitais, `letter-spacing: 0.32em`, "BIBLIOTECA · DE · INTELIGÊNCIA · ARTIFICIAL".
- **Margem de respiro:** equivalente à altura do "I".
- **Sobre fundo escuro:** usar `--teal-bright` (#2dbab4).
- **Versão miniatura:** wordmark sem tagline.

---

## 9. Voz & copy

**Léxico recomendado:** capítulo, ofício, sessão, manual, conversa, biblioteca, estante, cota, vol., livro, leitura, sabedoria, com vagar, eis, afinal.

**Padrões úteis:**
- *Eyebrow:* `TOMUS · I` · `NOTA · BENE` · `GLOSSÁRIO` · `FIG · III`
- *Headlines com itálico:* "Como falar *com* uma máquina." (verbo-chave em itálico)
- *Citações:* aspas francesas «…»
- *Datas:* numerais romanos (`MMXXVI`) ou português europeu ("14 de Maio de 2026")

---

## 10. Anti-padrões (não fazer)

- ❌ Gradientes saturados de fundo (roxo/azul néon)
- ❌ Emojis em peças de marca
- ❌ Títulos em maiúsculas sem `letter-spacing`
- ❌ Sombras `box-shadow` modernas com blur grande — preferir `2px 2px 0 rgba(36,26,18,.2)` (efeito gravura)
- ❌ Ícones de linha "tech" — usar os símbolos de biblioteca (livro, pena, selo, ampulheta)
- ❌ Inglês não traduzido em interface

---

## 11. Stack de implementação (referência)

- React 18 + Babel standalone
- Inline JSX, scripts globais expostos via `Object.assign(window, ...)`
- Tokens em CSS custom properties no `:root`
- Sem framework de UI; tudo é HTML/CSS de raiz
- Para variações, expor um painel "Tweaks" (paleta, tipografia, foxing on/off)

---

*IATECA · Manual da Casa · v1 · MMXXVI*
