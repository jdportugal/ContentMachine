# Nuno Narciso — Design System

Personal brand system for **Nuno Narciso, Consultor Imobiliário** (real-estate consultant, Portugal; operates under the iad Portugal network, Licença AMI 11220). Main outputs: Instagram/Facebook posts, flyers, listing material, contact cards.

## Sources
Provided as uploads (copies in `assets/reference/`):
- `flyer-cpcv-assinado.jpg` — "Hoje mais um CPCV assinado!" deal-milestone flyer
- `flyer-vender-comprar.jpg` — "Se queres vender ou comprar Casa — Fala comigo!" agent promo with portrait
- `logo-original.jpg` — Nuno Narciso N-house monogram + wordmark

No codebase, Figma or font files were provided. The flyers carry iad brokerage marks; this system **does not reproduce them** — the personal logo takes their place. Add brokerage marks only from official iad files if required.

## Index
- `styles.css` — entry point (imports only)
- `tokens/` — `colors.css`, `typography.css`, `spacing.css`, `effects.css`, `fonts.css`, `icons.css`
- `guidelines/` — foundation specimen cards (Colors, Type, Spacing, Brand)
- `components/core/` — Button, Badge
- `components/brand/` — Logo, ScriptAccent, GoldPlate, BrushPanel
- `components/content/` — IconDisc, ValuePillars, ContactBlock, PropertyCard
- `ui_kits/social/` — 1080×1350 post templates: DealPost, AgentPost, ListingPost (click-through `index.html`)
- `assets/logo/` — `logo-full(.png|-white.png)`, `monogram(.png|-white.png)` (transparent, extracted from the original)
- `assets/imagery/` — `agent-portrait.png`, `keys-contract.png` (cropped from the flyers)
- `assets/js/ds-fallback.js` — dev-only loader if the compiled bundle is absent
- `SKILL.md` — Agent Skill entry

**Intentional additions** (no source counterpart): `PropertyCard`, `Badge` status tones, `ListingPost` — real-estate work needs listings; built from the same vocabulary.

---

## CONTENT FUNDAMENTALS
- **Language:** European Portuguese (pt-PT). "Casa", "imóvel", "CPCV", "escritura", "AMI".
- **Two registers of address:**
  - *Formal "você"* (3rd person) for corporate/milestone posts — "Está a pensar vender ou comprar casa? Conte comigo para o ajudar…", "O seu imóvel em boas mãos!"
  - *Informal "tu"* for personal, direct-pitch posts — "Se queres vender ou comprar casa, fala comigo!", "Vou ajudar-te a encontrar a casa dos teus sonhos!"
  - Never mix in one piece. Default to *você* unless the piece is a personal call-to-action.
- **Voice:** first-person singular, warm, confident, service-led ("Conte comigo", "Fala comigo"). "Nós" only for team/network claims ("aos nossos clientes").
- **Casing:** headlines and pillar titles in ALL CAPS; sub-lines and body in sentence case; script phrases in sentence case with "!".
- **Rhythm:** triads and repetition — "Mais um passo concretizado, mais um negócio a avançar e mais um sonho a ganhar forma!"
- **Emphasis:** bold the key noun phrase inside a regular sentence (vender / comprar casa / passo concretizado). Never italic, never colour-only.
- **Punctuation:** exclamation marks are part of the voice on headlines and script lines (one per line max). Body ends with "!" or "?" when it's a pitch.
- **Vocabulary themes:** sonho, confiança, dedicação, acompanhamento, resultados, segurança, visibilidade, mercado nacional e internacional.
- **Numbers:** phone as `+351 933 197 327` (spaced triplets). Prices `285 000 €` (space thousands, € after). Typologies `T0–T5`.
- **Legal:** every outbound piece carries "Licença AMI 11220".
- **Emoji:** none. Icons do that job.

## VISUAL FOUNDATIONS
- **Navy + gold is the pairing.** Blue carries the surfaces; **gold is the second brand colour, present in every composition** — never just decoration on the milestone word.
  - *Signature* — logo charcoal-navy `--nn-ink #24313D` + muted gold `--nn-gold #A08A5C` (`--nn-gold-light #C9AD76` on dark, `--nn-gold-dark #7E6A42` for gold text on white, `--nn-gold-tint #F4EEE2` champagne wash).
  - *Marketing blues* — `--nn-navy-800 #03406F`, `--nn-blue-600 #005998`, `--nn-blue-500 #0379CA`, `--nn-sky-400 #4FA8E8`.
  - *Gold roles:* pillar dividers, script swooshes, footer top rule & divider, contact icons, eyebrows/sublines, price underline, gold halo on icon discs, one highlighted gold disc per pillar row, script sign-off on navy. Glossy **plate gradient** (`--grad-gold-plate`) for milestone words and hero CTAs; `--grad-gold-line` for fading hairlines.
  - Rough balance: ~60% white/ice, ~30% navy/blue, ~10% gold.
- **Type.** Montserrat in heavy weights (800/900) for caps headlines, tight leading (0.9). A brush **script** (Kaushan Script) for 1–2 emotional words, tilted −6° to −10°, often with a swoosh underline. Wordmark-style wide tracking (0.24–0.32em) for taglines. Cormorant Garamond echoes the monogram's serif — use sparingly (stationery, quotes).
- **Backgrounds.** Light ice-white (`#F5F9FA`) or deep navy grounds; warm, shallow-focus photography (keys, contracts, house models, the agent) bleeding off one side, fading via mask into the ground. No textures/patterns except the **brush panel** — a torn-edge navy slab behind hero type.
- **Gradients.** Radial "sphere" on icon discs; diagonal navy→blue on footers; left-to-right navy protection over photos. No purple, no rainbow.
- **Layout.** Fixed formats (1080×1350 posts). Zones stack top→bottom: logo + script tagline → hero (brush panel or photo) → 4–5 value pillars with thin blue vertical rules → question/CTA → navy footer band (curved top) with oversized phone, email, AMI, white logo.
- **Corner radii.** Photos square/bleed; gold plates 14px; cards 8px; buttons & badges pill; icons circle.
- **Cards.** White, 8px radius, navy-tinted shadow (`--shadow-md`), no borders; lift 3px + `--shadow-lg` on hover.
- **Shadows.** Always navy-tinted (rgba(1,35,66,…)). Hero type gets a soft drop (`--shadow-text-hero`) on photos. Plates get an inner highlight.
- **Borders/dividers.** 2px **gold** vertical rules between pillars; 4px gold-light top rule on footers and listing panels; fading gold hairlines (`--grad-gold-line`); 3px rings on outlined icon discs (gold-light on navy).
- **Transparency/blur.** Only in photo masks and protection gradients. No glassmorphism.
- **Imagery vibe.** Bright, warm daylight, soft bokeh, wood and white tones, polished brass/gold accents. Professional portrait in navy suit + light-blue tie — matches the palette.
- **Motion (web/stories).** Subtle: fades and 3px lifts, `--ease-out`, 140–420ms. Optional gold sparkle glow on milestone plates. No bounces.
- **Hover/press.** Hover: darker navy or brighter blue fill; gold gets a glow. Press: scale .97.

## ICONOGRAPHY
- Flyers use solid, rounded pictograms (house, people group, shield-check, handshake, globe, signed document) set in **circles** — either filled blue spheres with white glyph, or 3px blue rings with blue glyph.
- **Substitution:** no icon files were provided; the system uses **Font Awesome 6 Free** (CDN, linked in `tokens/icons.css`) — closest match in weight and shape. Common names: `house`, `users`, `shield-halved`, `handshake`, `globe`, `file-signature`, `phone`, `envelope` (regular), `id-card` (regular), `location-dot`, `bed`, `bath`, `ruler-combined`, `car`; brands `whatsapp`, `facebook-f`, `instagram`, `linkedin-in`.
- Always wrap pillar icons in `IconDisc` (blue sphere with gold halo; one `gold` disc may highlight a key promise). Contact-row icons sit bare in gold-light on navy. WhatsApp glyph in its green `#25D366`.
- No emoji, no unicode symbols as icons.

## Logo usage
- Use only the PNGs in `assets/logo/` (extracted from the supplied file — never redraw).
- Light grounds: `logo-full.png`. Navy/photo grounds: `logo-full-white.png` (white ink, light gold).
- Monogram alone for avatars, watermarks, key tags.
- Clear space ≥ the house-window height on all sides. No effects, no recolouring beyond the two provided versions.
