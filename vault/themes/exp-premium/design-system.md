# Morada — Real Estate Video Design System

An original design system for **short vertical (9:16) real-estate videos made by agents in Portugal**: Reels, TikTok and Shorts listing tours, open-house teasers, agent intros. 16:9 is supported as a secondary export. *Morada* is a working name. The real brand on screen is always the agent's own agency logo, which goes in a reserved slot.

## Sources & inspiration
- The general look of a Portuguese real-estate portal (https://www.expportugal.com/) and three screenshots in `uploads/` (hero, search results, homepage cards).
- **It's an original system, not a copy of that brand.** No logo, wordmark, or proprietary asset from it is used. It only borrows broad qualities: a graphite-ink palette, light wide-tracked headlines, full-bleed scrimmed photography and pill price tags. Those are re-tuned for 1920×1080 and 1080×1920 video.

## Index
- `styles.css`: entry point (imports only) → `tokens/` fonts, colors, typography, spacing, effects, motion
- `guidelines/*.card.html`: foundation specimens (Brand, Colors, Type, Spacing, Effects, Motion)
- `components/core`: Icon, Reveal, Eyebrow, Heading, Button, Badge, PriceTag (+formatPrice), SpecRow, Photo
- `components/overlays`: Hook, LowerThird, AgentCard, StatCallout, Caption
- `components/frames`: TitleCard, FeatureFrame, EndCard (full frames, `format="9x16"` by default)
- `ui_kits/listing-video/`: a playable 5-scene listing video
- `ds-loader.js`: helper for cards/kits; uses the compiled bundle, or compiles sources in-browser if the bundle isn't there
- `SKILL.md`: Agent Skill manifest

### Intentional additions
No source component library existed, so the set is made for video: **Reveal** (brand entrance motion), **Photo** (placeholder + Ken Burns), **Caption** (burned-in subtitles), **Hook** (scroll-stopping opening line for vertical) and **frames/** (fixed-size video frames).

---

## CONTENT FUNDAMENTALS
- **Language:** European Portuguese (pt-PT) first, EN second. Use PT-PT terms: *casa de banho / WC*, *moradia*, *apartamento T2*, *escritura*. Avoid Brazilian forms like *banheiro*.
- **Address:** formal-warm second person through possessives: *o seu*, *a sua*, *Encontre o seu lugar*, *Agende a sua visita*. Don't use *tu* or *você*.
- **Headlines:** Title Case for short hero lines (*A Sua Nova Casa*, *O Seu Futuro Começa Aqui*). Put the 1–2 emotional or key words in Heavy weight. Sentence case for everything else.
- **Tone:** confident, calm and premium. Lead with place and feeling, then the facts. No hype, no exclamation marks, no ALL-CAPS shouting (tracked caps are for small labels only).
- **CTAs:** short imperative verb + object: *Agendar visita*, *Fale Connosco*, *Saber Mais*, *Ver imóvel*. One CTA per frame.
- **Numbers:** prices pt-PT with a thin space and trailing euro sign: **425 000 €**. Area in **m²**. Typology as **T0–T5** / **V3**. Facts read as figure + unit: **3** Quartos · **2** WC · **323** m². References in mono: `Ref# 87517081`.
- **Emoji:** never in frames. Unicode is limited to ·, —, € and ².
- **Length per beat:** hook ≤ 7 words, headline ≤ 5 words, subtitle ≤ 12, caption ≤ 2 lines, feature lines ≤ 5 words.

Examples: *Para venda · Lousada* (eyebrow) → *A Sua **Nova Casa*** (title) → *Moradia térrea T3 em loteamento exclusivo* (subtitle) → *Viver **lá fora*** → *Agende a sua **visita***.

## VISUAL FOUNDATIONS
- **Color:** strictly monochrome: graphite ink and white, with **no blue and no orange anywhere**. Ink is a hue-free graphite ramp from #0E0E0F to #F7F7F7. Ink 900 (#161617) carries text, buttons, price pills and end cards. White, Ink 100 and Calçada (#F5F4F1, a barely-warm off-white) are the grounds. There's no coloured accent: the accent is the *inverse of the ground* (white CTA and kicker on dark or footage, ink on light). The only hues are the listing status colours: Novo green, Vendido red, and Reservado in neutral grey. Colour in a video comes from the photography.
- **Type:** Manrope only, plus JetBrains Mono for refs and codes. Headlines are **Light 300 with +0.06em tracking**, and the emphasis words are **Heavy 800**. This light/heavy contrast is the signature. Labels are SemiBold uppercase, +0.24em. Body text is Regular at 1.4 line-height. On screen nothing goes below 24px at 1080 wide. In 9:16 use h1 (96px) as the largest title and `--fs-hook` (84px) for hooks. Display (128px) is for 16:9 only.
- **Signature motif:** a short **128×3 rule** under hero and section headings, centred or left-aligned with the text. It draws in from the centre.
- **Backgrounds:** full-bleed photography and footage almost always. Graphic frames use only flat white, Ink 100, Calçada (warm off-white) or Ink 900. No gradients as decoration and no patterns or textures. The only gradients are protection scrims.
- **Imagery:** warm, natural light: golden hour, aerial establishing shots of the neighbourhood, wide interiors with daylight. Natural colour, never B&W or heavy grain. Gentle Ken Burns (1 → 1.08 over 9s).
- **Protection:** text over photos always sits on a scrim: `--scrim-hero` (flat 48%) behind centred titles, `--scrim-bottom` / `--scrim-left` gradients for edge-anchored text, `--scrim-strong` for stat beats. Otherwise text sits in a solid white panel (LowerThird) or an ink box (Caption).
- **Transparency & blur:** only the **glass** button (12% white, 55% white stroke, 16px blur) over footage. Nothing else is translucent.
- **Corners:** buttons and badges are nearly square (4px). Cards and lower thirds 10px, large panels 16px. Price tags and headshots are fully round (pills/circles).
- **Cards:** white, no border, soft two-layer shadow (`--shadow-card`). Over footage they use `--shadow-float`. No coloured left borders and no outlines.
- **Borders:** 1px Ink 200 hairlines to divide (spec rows, headers). 1px dashed for placeholder slots.
- **Layout (vertical-first):** author at 1080×1920. Safe areas: 220px top (header), 380px bottom (caption, handle, CTA bar), 160px right (like/comment/share rail) and 64px left. Edge-anchored text (Hook, LowerThird) runs from 64px left to 160px right, so it's max 856px wide. Centred beats (title, end card) inset 160px on both sides. Split frames put the photo on top (760px) with the panel below. In the secondary 16:9 format, safe areas are 96/72px and split frames are 56/44 side by side.
- **Vertical rhythm:** a hook in the first 1–2s (top-left, ≤ 7 words). One idea per beat, beats of 2–5s, and a total of 15–30s. Keep key text in the upper-middle band (roughly y 220–1100), because viewers' eyes and the platform caption crowd the bottom.
- **Motion:** everything *enters*: rise (32px + fade, 640ms, `cubic-bezier(.22,1,.36,1)`), siblings staggered 120ms, rules draw in over 1.2s. No bounce, no spin, no zoom-pop. Scene changes are hard cuts or short crossfades.
- **Hover/press (interactive surfaces only):** primary darkens to Ink 950; secondary and glass raise the fill (white 20%); links show an underline. Press goes 2% darker, with no scale.

## ICONOGRAPHY
- **Lucide** line icons (CDN `lucide-static@0.460.0`), drawn through the `Icon` component as a CSS mask so they inherit text colour. This is a substitute: it was chosen as the closest open set to the thin, rounded line icons seen in the reference (search, chevrons, arrow, menu, home).
- Common icons: `arrow-right` (link CTAs), `calendar` (book a visit), `phone`, `mail`, `bed-double`, `bath`, `ruler`, `check` (feature lists), `map-pin`, `play`.
- Icons are always monochrome and match the text size (≈1×). Never filled, never inside coloured circles. No emoji, no PNG icons.
- **Logo:** none is supplied, and none should be drawn. The system name is set in plain type (Mo**rada**), and every end card has a dashed **AGENCY LOGO** slot for the agent's real logo.

## Fonts
Manrope and JetBrains Mono load from Google Fonts. These were picked to match the geometric sans in the reference. If you have licensed brand fonts, drop them in and update `tokens/fonts.css`.
