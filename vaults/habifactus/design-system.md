# Habifactus — Design System

Extracted from habifactus.com, property detail pages and the brand's social posts.
Brand: **Habifactus – Sociedade de Mediação Imobiliária, Lda** (AMI 5132). Real estate agency in Viseu, Mangualde, Nelas and Tondela since 2001.
Tagline: **"Crescemos consigo"**.

---

## 1. Brand essence

- **Personality:** local, trustworthy, warm, professional. Not luxury-flashy, not discount-loud.
- **Promise:** "Os melhores negócios imobiliários da região de Viseu."
- **Visual signature:** one deep green, white type over darkened photography, sharp rectangles, thin outlines.
- **Language:** European Portuguese (pt-PT) first; EN/FR secondary.

---

## 2. Color

| Token | Hex | Use |
|---|---|---|
| `green-700` (primary) | `#1B6B4E` | Search bar, primary buttons, highlighted cards, links, icons |
| `green-800` | `#145A41` | Inset fields on green, hover/pressed state |
| `green-900` | `#0E3F2E` | Deep overlays, footer, text on light green |
| `green-overlay` | `#1B6B4E` @ 80–85% | Tinted photo headers (listing page) |
| `ink-overlay` | `#2B2622` @ 55–65% | Neutral photo headers (property page, hero) |
| `amber-500` (accent) | `#F5B400` | "Exclusivo" badge **only** |
| `white` | `#FFFFFF` | Text on photos/green, cards, button fill on green |
| `gray-50` | `#F8F8F8` | Section backgrounds |
| `gray-100` | `#EEEEEE` | Info panels (e.g. "Criar alerta"), card footers |
| `gray-300` | `#D6D6D6` | Input borders, dividers |
| `gray-500` | `#8A8A8A` | Placeholder, metadata icons |
| `gray-700` | `#555555` | Body text, section titles |
| `gray-900` | `#333333` | Strong text (also `msapplication-TileColor`) |

**Rules**
- Green is the only brand color. Amber is a signal, never decoration.
- On photos, always darken first (ink or green overlay) — white type must hit 4.5:1.
- Never put green text on a dark photo (the site's green price on the property header fails contrast — use white or amber instead).

---

## 3. Typography

| Role | Font | Weight | Notes |
|---|---|---|---|
| UI + headings + body | **Open Sans** | 300 / 400 / 600 / 700 | The whole site |
| Logo wordmark | Rounded geometric sans (Comfortaa-like) | — | Use logo file only; never retype |

**Scale (web)**

| Token | Size / line | Weight | Example |
|---|---|---|---|
| `display` | 64 / 1.15 | 300 Light | "Os melhores negócios imobiliários…" |
| `h1` | 36 / 1.25 | 600 | "Apartamento T3, Viseu, Viseu" |
| `h2` | 28 / 1.3 | 700, gray-700 | "Comprar ou Arrendar?" |
| `h3` | 20 / 1.3 | 600 | Card title "Moradia T1+1" |
| `body` | 15 / 1.5 | 400 | Descriptions |
| `small` | 13 / 1.4 | 400 | Location, metadata |
| `eyebrow` | 11 / 1.2 | 600, UPPERCASE | "IMÓVEL", "LOCALIZAÇÃO" |
| `kicker` | 18 / 1.3 | 400 | "Crescemos consigo" above display |

**Rules**
- Light 300 for big emotional headlines; Semibold 600 for factual titles (property names, prices).
- Headlines centered on hero/header; content left-aligned.
- Prices: `259.000 €` — dot thousands separator, space, euro sign after.
- Typologies: `T0–T5`, `T1+1`, `T3 DUPLEX`. Areas: `105m2` / `73,2m2` (comma decimal).

---

## 4. Logo

- Mark: two interlocking rounded "X/heart" strokes (house + handshake + heart). White on photo/green; green `#1B6B4E` on white.
- Lockup: mark + "Habifactus" + descriptor "Sociedade de Mediação Imobiliária".
- Mark alone: avatars, photo watermarks, small corners.
- Clear space = height of the mark's stroke loop. Minimum width: 120px (lockup), 24px (mark).
- Photo watermark: white mark + wordmark, ~60–70% opacity, centered lower third.

---

## 5. Layout & shape

- **Grid:** 1200px content, 4-column card grid, 10px gutters; sidebar 1 column on listing.
- **Spacing scale:** 4 · 8 · 12 · 16 · 24 · 32 · 48 · 64 · 96.
- **Corners:** **0px** everywhere (cards, inputs, buttons, bars). Max 4px on the white search button. Only exception: circular agent avatar and counter badges.
- **Borders:** 1px `gray-300` on inputs; 1–2px green or white outlines for ghost buttons.
- **Shadows:** none. Depth comes from overlays and color blocks.
- **Headers:** full-bleed photo + overlay, nav top-right, title centered, search bar as a solid green band overlapping the photo's bottom edge.

---

## 6. Components

**Buttons**
- Primary: green fill, white 600 text, 0 radius, 48px tall ("Enviar").
- On-green: white fill, gray-900 text + search icon ("Pesquisar").
- Ghost: 1px white or green outline, white text ("Área cliente", "Adicionar imóvel aos favoritos").
- Text link with arrow: green arrow ← + white/green 600 text ("Voltar para listagem").

**Property card**
- Photo 4:3 with centered watermark → info block (gray-50; green fill when exclusive/highlighted).
- Title h3, price, "Distrito, Concelho", heart outline top-right.
- Metadata row: outline icons (bed, bath, car, area brackets) with small number superscript.
- "Exclusivo ☆" amber badge top-left, white 600 text.

**Search bar**: green band → label (light 300, white) · darker green input · "Quero Comprar / Arrendar" select · white Pesquisar button.

**Info panel**: gray-100 block, green outline icon (bell), 600 title + small description ("Criar alerta para esta pesquisa").

**Agent card**: circular photo, "Consultora Imobiliária" small, name in green 20px, email, phone.

**Forms**: white inputs, 1px gray-300, 13px placeholder, 0 radius; radio groups inline.

**Icons**: thin outline (1.5px), gray-500 on light, white on dark, green for feature icons.

---

## 7. Imagery

- Real property photography: bright, wide, daylight or golden hour. Renders allowed for new builds.
- Regional context shots (Viseu cathedral, granite, vineyards of Dão, Serra da Estrela) for headers.
- Always watermark listing photos with the logo.
- People/events imagery (team, parties) is warm and candid — use for culture/recruitment, never for listings.

---

## 8. Voice & copy

- Formal "você" implied, warm and direct: "Crescemos consigo", "Quer receber todas as atualizações?"
- Lead with place: "Entre o Caramulo e a Serra da Estrela, uma casa desenhada para a paisagem."
- Facts in fixed order: **Tipologia → Localização → Quartos/WC → Área → Preço → Ref.**
- CTAs: "Saber mais", "Marque a sua visita", "Fale connosco", "Link na bio".
- No ALL-CAPS shouting, no "!!!", emoji sparingly (max 1–2, only in captions: 🏡 📍).

---

## 9. Instagram — shared rules

| | Post (feed) | Carousel | Reel / Short / Story |
|---|---|---|---|
| Size | **1080 × 1350** (4:5) — 1080×1080 allowed | 1080 × 1350 per slide | **1080 × 1920** (9:16) |
| Grid crop | Center 1080×1080 must still read | Slide 1 = cover | Profile grid shows center 1080×1440 (3:4) |
| Safe zone | 64px all sides | 64px | Top 250px & bottom 420px clear; right 140px clear (UI buttons) |

- **Fonts:** Open Sans only. Price 600–700, headlines 300 or 600.
- **Min sizes (at 1080 wide):** headline 64px, price 72px, body 32px, labels 24px.
- **Colors:** photo + ink/green overlay gradient, white type, green blocks, amber only for "Exclusivo".
- **Logo:** white mark top-center or top-left, 96–120px wide. Never on every corner.
- **0 radius** on all bars and blocks — matches the site.

### 9.1 Listing post (the signature format)

Derived from the brand's current social posts.

```
┌──────────────────────────┐
│        [logo mark]       │  top center, white, 110px
│                          │
│     full-bleed photo     │  bottom 40% darkened (ink 0→70% gradient)
│                          │
│ ┌──────────────────────┐ │  info bar: 2px white outline, 0 radius
│ │ ⌂ Moradia T4 │ ⌖ Viseu, Tondela │ ⛶4 🛁2 │ ▭ 346,3 m2 │
│ └──────────────────────┘ │  icons 40px outline, labels 24px
│       570.000 €          │  72px Open Sans 700, white, centered
│                 Ref: T0224│  22px italic, white 80%
└──────────────────────────┘
```

- Optional "Exclusivo ☆" amber tag top-left (0 radius, 28px 600).
- Optional "Baixa de Preço" / "Reservado" tag: green fill, white text, same position.

### 9.2 Other post types

1. **Destaque da semana** — photo top 65%, green block bottom 35% with title (h 56px 600), price, location; logo mark in block corner.
2. **Quote / testimonial** — solid `green-700` background, Open Sans Light 56px white quote, client name 28px 600, small mark bottom-center.
3. **Region / lifestyle** — full photo of Viseu/Dão, kicker "Crescemos consigo", Light 72px headline centered.
4. **Recruitment ("Junte-se à equipa")** — candid team photo, green overlay 80%, headline Light 72px, ghost white CTA "Saiba mais — link na bio".
5. **Carousel tour** — slide 1 = listing post (9.1); slides 2–8 = clean photos with small room label bottom-left (white 28px 600 on green chip); last slide = agent card (photo circle, name, phone) + "Marque a sua visita".

### 9.3 Reels / Shorts (1080 × 1920)

**Structure (15–30s)**
1. **Hook 0–2s:** best exterior shot + Light 80px headline ("Uma casa desenhada para a paisagem"). Logo mark top.
2. **Tour 2–20s:** 1.5–2.5s per clip, room label lower-left (green chip, white 32px 600). Smooth push-ins, no flashy transitions.
3. **Facts 20–25s:** info bar (9.1) + price 96px centered in the middle third.
4. **CTA 25–30s:** green-700 full screen, white logo lockup, "Marque a sua visita", phone **232 432 601**, `habifactus.com`.

**Type & motion**
- Captions: Open Sans 600, 44px, white on 70% ink bar, 0 radius, centered at y≈1350.
- Text animates in with 250ms fade + 24px rise; no bounces, no spins.
- Music: calm acoustic/ambient; voiceover in pt-PT.
- Cover frame: exterior + typology + location, readable in the 3:4 grid crop.

### 9.4 Stories
- Same 1080×1920 template; add link sticker in the bottom-clear zone above 420px.
- "Novo no mercado" / "Baixa de preço" stories: green top band 220px with label, photo below, price bottom-center.

### 9.5 Caption template

```
📍 [Concelho], [Distrito]
[Uma frase sobre o lugar ou a casa.]

🏡 [Tipologia] · [Quartos] quartos · [WC] WC · [Área] m²
💶 [Preço]
Ref: [Ref]

Marque a sua visita: 232 432 601 · link na bio
#habifactus #imobiliariaviseu #viseu #[concelho] #crescemosconsigo
```

### 9.6 Don'ts
- No rounded cards, drop shadows, or gradient fills other than photo darkening.
- No amber outside the "Exclusivo" tag.
- No green type on dark photos.
- No more than 3 text elements per frame (plus logo).
- Don't recolor, outline, or stretch the logo.

---

## 10. Tokens (CSS)

```css
:root {
  --hf-green-700: #1B6B4E;
  --hf-green-800: #145A41;
  --hf-green-900: #0E3F2E;
  --hf-amber-500: #F5B400;
  --hf-white: #FFFFFF;
  --hf-gray-50: #F8F8F8;
  --hf-gray-100: #EEEEEE;
  --hf-gray-300: #D6D6D6;
  --hf-gray-500: #8A8A8A;
  --hf-gray-700: #555555;
  --hf-gray-900: #333333;
  --hf-overlay-ink: rgba(43, 38, 34, .6);
  --hf-overlay-green: rgba(27, 107, 78, .82);
  --hf-font: "Open Sans", Helvetica, Arial, sans-serif;
  --hf-radius: 0;
}
```

---

## 11. Contacts (for templates)

- Rua Pintor António de Almeida 21, Viseu
- 232 432 601 · comercial@habifactus.com · habifactus.com
- Instagram: @habifactus.smi · Facebook: /imobiliariahabifactus
