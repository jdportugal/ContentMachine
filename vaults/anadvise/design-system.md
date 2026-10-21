# ANAdvise Imóveis — Design Tokens

Extracted from anadvise.com (property page + "Sobre Nós"). Tokens only, applied to Instagram 9:16 formats (Stories, Reel covers, Reel overlays).

## Colour

| Token | Hex | Use |
|---|---|---|
| `navy` | `#0A347F` | Primary brand. Logo "ANA", search button, primary square buttons, round nav buttons, active nav underline |
| `navy-deep` | `#06204F` | Photo scrims, pressed states |
| `gold` | `#C6A13C` | Accent. Pill CTAs ("VER FOTOS"), price highlights, eyebrows on dark |
| `gold-light` | `#D9BE6A` | Logo "DVISE" highlight; thin rules on navy |
| `ink` | `#333333` | Nav and body text on white |
| `grey-700` | `#505050` | Large headings on light grey ("A satisfação de quem nos procura") |
| `grey-500` | `#6E6E6E` | Eyebrows on light ("QUEM SOMOS"), secondary text |
| `grey-100` | `#EEEEEE` | Section background (light) |
| `white` | `#FFFFFF` | Header, secondary button, text on photo/navy |
| `navy-tint` | `#DCE3F0` | Body text on navy |

Photo scrim: `linear-gradient(180deg, rgba(6,32,79,0) 35%, rgba(6,32,79,.75) 100%)`

## Typography

Family: **Lato** (Google Fonts), weights 400 / 700 / 900. Fallback: `"Helvetica Neue", Arial, sans-serif`.

| Role | Size (1080px canvas) | Weight | Case / tracking |
|---|---|---|---|
| Display (property title) | 104–140px / 1.0 | 700 | Sentence, -0.01em |
| Price | 88–120px / 1.0 | 400 | `€ 1.100.000` (euro sign first, dot thousands) |
| Headline | 72–96px / 1.1 | 700 | Sentence |
| Body | 40–48px / 1.4 | 400 | Sentence |
| Eyebrow | 30–34px | 700 | UPPERCASE, +0.18em |
| Location | 32–34px | 700 | UPPERCASE, +0.12em, `VISEU / VISEU` |
| Button | 34–38px | 900 | UPPERCASE, +0.02em |

## Spacing & shape

- Base unit 8px. Scale: 8 · 16 · 24 · 32 · 48 · 64 · 80 · 120
- Frame side margin: **80px**
- Pill CTA: radius 999px, height 96px, padding 0 64px, gold bg, white text
- Square button (site): radius 0, navy bg / white text, or white bg / grey-700 text
- Logo chip: white bg, radius 0, padding 24×32, logo height 88px
- Cards: radius 0 (site uses square edges), no shadow

## Instagram formats (1080 × 1920)

| Format | Reserved (keep free of text) |
|---|---|
| Story | top 250px, bottom 340px |
| Reel overlay | top 220px, bottom 420px, right 140px |
| Reel cover | keep key content inside the centre 1080×1440 (y 240–1680) so the 3:4 profile-grid crop stays readable |

## Voice (copy tokens)

Portuguese (PT-PT). Labels: `NOVO IMÓVEL` · `BAIXA DE PREÇO` · `VENDIDO` · `OPORTUNIDADE` · `TESTEMUNHOS` · `DICA DE MERCADO`. CTAs: `VER IMÓVEL` · `SOU PROPRIETÁRIO` · `LINK NA BIO`.
