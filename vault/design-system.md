# Nebula — Futuristic Blue Design System

A deep-space visual language for **AI & automation education**. Starlit navy gradients, molten-gold headlines, and heavy condensed type. Built for course thumbnails, banners, hero slides, and a full social kit (feed / story / YouTube).

---

## 1. Brand Personality

| Trait | Expression |
|---|---|
| **Futuristic** | Deep-space gradients, starfields, subtle glow, electric blue |
| **Bold** | Anton display type — heavy, condensed, all-caps |
| **Energetic** | Molten gold→orange gradient on every headline |
| **Trustworthy** | Dark, calm navy base; restrained palette; generous space |

Voice: direct, encouraging, no jargon. Copy is short and action-led ("Start learning", "Swipe to learn →", "Automate everything").

---

## 2. Color

### Core palette
| Token | Hex | Use |
|---|---|---|
| `void` | `#05060E` | Page background, deepest point of gradients |
| `deep-navy` | `#0A1030` | Mid-gradient, section fields |
| `panel` | `#0C1225` | Cards, component surfaces |
| `panel-alt` | `#0A0E1E` | Inner component wells |
| `electric-blue` | `#2A3BEB` | Primary solid accent (buttons, PRO badge) — **tweakable** |
| `star-white` | `#EAF0FF` | Primary text, star dots |
| `success` | `#12B76A` | 100% / complete states |

### Signature gradients
| Name | Value | Use |
|---|---|---|
| **Molten gold** | `linear-gradient(100deg, #FFE59A 0%, #FFB347 55%, #FF7A3D 100%)` | All display headlines (clipped to text), primary buttons |
| **Nebula (cover)** | `linear-gradient(120deg, #05060E 0%, #0A1030 55%, #1a2ba8 140%)` | Full-bleed hero / cover backgrounds |
| **Deep field** | `radial-gradient(120% 80% at 80% -10%, #16204d, transparent 55%)` layered over `void` | Ambient page glow |
| **Card glow base** | `radial-gradient(130% 100% at 50% 130%, <hue-glow>, transparent 60%), linear-gradient(180deg,#0B0F22,#070A16)` | Thumbnail card backgrounds |

### Glitter / topic hues
Each course card gets a colored particle field. Assign one hue family per topic:

| Family | Palette | Glow |
|---|---|---|
| Gold | `#FFD98A #FFB347 #FF8A4D` | `rgba(255,150,70,.55)` |
| Purple | `#C77DFF #E0AAFF #9C7DFF` | `rgba(160,110,255,.5)` |
| Blue | `#5A7BFF #8A6BFF #C77DFF` | `rgba(90,110,255,.55)` |
| Teal | `#4DE0E0 #4DA6FF #8AE0FF` | `rgba(77,200,224,.45)` |
| Red | `#FF8A4D #FF5C7A #FFC14D` | `rgba(255,110,90,.5)` |
| Green | `#4DE08A #8AE04D #4DD0A6` | `rgba(80,220,140,.45)` |

**Rule:** max one glitter hue per artifact. The base stays navy/void; color only enters through the particle field + bottom glow.

### Text colors
- Primary `#EAF0FF` · Muted `#A9B6D6` / `#B8C4E2` · Dim/meta `#8090B5` / `#6C7AA6` · Eyebrow-blue `#7DA2FF` / `#9FB4E8` · Warm sub-headline `#F2A64D`

---

## 3. Typography

Two families only.

| Role | Font | Weight | Treatment |
|---|---|---|---|
| **Display** | Anton | 400 (only weight) | UPPERCASE, `line-height: .9`, molten-gold clip or star-white |
| **UI / body** | Space Grotesk | 400–700 | Sentence or UPPERCASE for labels |

### Scale
| Style | Font / size | Notes |
|---|---|---|
| Cover display | Anton `clamp(56px,9vw,132px)` / .9 | Gradient clip |
| Hero headline | Space Grotesk 600 `clamp(30px,5.4vw,72px)` | Gradient clip; last word 700 |
| Card / poster title | Anton 34px / .92 | Star-white + text-shadow over glitter |
| Section heading | Space Grotesk 700 32px | `#EAF0FF` |
| Body | Space Grotesk 400 16px / 1.6 | `#B8C4E2` |
| Eyebrow / label | Space Grotesk 600 12–14px | `letter-spacing: .2–.24em`, UPPERCASE |
| Meta / caption | Space Grotesk 500 12px | `letter-spacing: .14em`, dim |

**Gradient headline recipe** (reuse everywhere):
```css
font-family: 'Anton'; /* or Space Grotesk 600 for refined banners */
text-transform: uppercase;
background: linear-gradient(100deg,#FFE59A,#FFB347 55%,#FF7A3D);
-webkit-background-clip: text; background-clip: text; color: transparent;
```

---

## 4. Layout & Spacing

- Content column: `max-width: 1120px`, centered, `padding: 0 48px`.
- Section rhythm: `~72–80px` top padding; eyebrow → heading (`8px` gap) → content (`28px` gap).
- Radii: cards `16px`, buttons `11px`, pills `999px`, large media `18px`.
- Grids use `display:grid` + `gap` (16–24px), auto-fill `minmax(300px,1fr)` for cards. Never margin-spaced siblings.
- Borders: `1px solid rgba(255,255,255,.07–.12)`. Card shadow: `0 20px 50px rgba(0,0,0,.4)`.

---

## 5. Components

### Buttons
| Variant | Style |
|---|---|
| **Primary** | Molten-gold gradient bg, text `#0A0714`, `box-shadow:0 6px 24px rgba(255,138,61,.32)` |
| **Electric** | Solid `electric-blue`, white text, blue glow shadow |
| **Ghost** | `rgba(255,255,255,.04)` bg, `1px` white border, text `#DCE4FA` |
| **Play** | 46px circle, translucent white bg + border, CSS triangle |

All: `padding:13px 24px`, radius `11px`, Space Grotesk 600 15px.

### Badges & tags
- **Pills:** `DRAFT` (neutral translucent), `NEW` (gold gradient, dark text), `PRO` (electric blue), `COMPLETE` (green tint + border). Radius `999px`, 600/12px, `.06–.08em` tracking.
- **Tags:** `rgba(125,162,255,.12)` bg, blue border, radius `8px`, 500/12px — e.g. Automation · No-code · Beginner.

### Progress bar
- Track: `rgba(255,255,255,.07)`, height 10–12px, radius `999px`.
- Fill: molten-gold gradient in progress; **green gradient at 100%**. Optional `%` label to the right, 600/12–14px `#9AA8CC`.

### Course card
Media (16:8.4) with glitter field + optional top-left badge + centered Anton title → body block: title (SG 600/16), 2-line description (SG 400/13, `#8090B5`), progress bar + `%`. Radius `16px`, panel bg, big soft shadow.

---

## 6. Effects

- **Starfield:** absolutely-positioned circular dots (1–2.4px), hues `#ffffff #CFE0FF #AFC6FF`, opacity .3–1.0, `neb-twinkle` keyframe (2.5–6.5s). Density is tweakable; generated from a seeded RNG so positions stay stable across renders.
- **Float:** `neb-float` (±7px, 3.5–4s) on play buttons and swipe-up CTAs.
- **Glow:** gold/blue box-shadows on primary/electric buttons; radial glow at card bottoms.
- **Glass:** `rgba(20,26,48,.72)` + `backdrop-filter: blur(4px)` on badges over imagery.
- **Selection:** orange `#FF7A3D` on void.

---

## 7. Artifact Specs

| Artifact | Ratio / size | Layout |
|---|---|---|
| Cover | full-bleed | Nebula gradient + starfield, dual-line Anton (white + gold) |
| Course card | 16:8.4 media | Glitter + centered Anton title; body + progress below |
| Course hero | 16:9 | Centered gradient headline, floating play, warm subline |
| Wide banner | 1280 × 260 | Single centered gradient headline |
| Section title | 16:9 split | Left image (portrait/render), right electric-blue panel + numbered Anton headline |
| Feed post | 1:1 | Brand tag → lesson label → Anton headline → body → CTA |
| Story | 9:16 | Progress ticker, centered stacked Anton, floating swipe-up |
| YouTube thumb | 16:9 split | Bold Anton hook (left) + creator face-cam slot (right) |

**Imagery:** use striped placeholders with a monospace label (e.g. `PORTRAIT / 3D RENDER`, `CREATOR FACE-CAM`) until real assets are dropped in. Never fake photos with SVG.

---

## 8. Tweakable Props

| Prop | Type | Default | Effect |
|---|---|---|---|
| `showStars` | boolean | true | Toggles all starfields |
| `starDensity` | int 20–180 | 80 | Star count across all fields |
| `accentBlue` | color | `#2A3BEB` | Electric-blue accent (buttons, PRO badge, section panel, swatch) |

---

## 9. Do & Don't

**Do** — keep backgrounds navy/void; let color arrive through gold headlines + one glitter hue; use Anton uppercase for impact lines; keep copy short; space with grid + gap.

**Don't** — mix multiple glitter hues in one artifact; use Anton for body text; drop headlines below 24px on 1920-wide slides; add extra accent colors; recreate third-party logos (use text + simple node shapes).
