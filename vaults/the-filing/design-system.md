# THE FILING — Design System v1.0

Corporate fines, bankruptcies, and business scams — explained the day they break.
Issued 2026-08-24. Fonts: Archivo, Archivo Narrow, Archivo Black, Space Mono (all Google Fonts).

---

## 1. Position & voice

**The promise:** the receipt exists, we read it out loud.

Tone is **loud but documented**. Loud is earned by evidence, never by adjectives. Every claim on screen traces to a docket, a filing, or an enforcement press release.

| Write like this | Not like this |
| --- | --- |
| $4.3 billion. Nobody was fired. | You won't BELIEVE what they did |
| They knew in 2019. Page 41 says so. | Shocking scandal EXPOSED 🤯 |
| The fine is a rounding error. | Is this the end of capitalism? |

Rules: allegations stay allegations ("alleges", "charged", "settled without admitting"). Numbers carry a source. No emoji. No question-mark headlines.

---

## 2. Wordmark

Wordmark only — "THE FILING" set in Archivo Black, tracking −4.5%, always uppercase.

- **Primary (horizontal)** — "THE" in Space Mono above, 0.52em tracking, brick red; "FILING" oversized below; alarm-red rule beneath. 2400 × 800.
- **Stacked invert** — for dark grounds, banners, end cards. 800 × 800.
- **TF monogram** — favicons and anything under 48px. 800 × 800.
- **Outline** — ink keyline on newsprint, for light grounds. 800 × 800.

Clear space equals the cap height of the "F" on all sides.

**Never:** stretch or condense · add outline or drop shadow · tint the wordmark (olive, red, or otherwise) · rotate · place on a photo without a flat ground behind it.

---

## 3. Ink set — five plates, no more

| Name | Hex | Use |
| --- | --- | --- |
| **Alarm** | `#CE4B33` | Thumbnail grounds, stamps, the single loudest thing on screen |
| Alarm deep | `#8E2E1C` | Only permitted shade of Alarm — small type on light grounds |
| **Ink** | `#17130E` | All body copy, rules, redaction bars, lower thirds |
| Ink soft | `#4A423A` | Hairline dividers on ink grounds |
| **Newsprint** | `#F0E8D8` / `#E3D9C4` | Default page and document ground — never pure white |
| **Money** | `#5E7038` | Fills for figures: fines, payouts, losses. Nothing else |
| Money light | `#8C9E5A` | Money olive as *small type on ink grounds* only |
| **Press grey** | `#96958C` | Machinery, secondary metadata, disabled states |

**Coverage per frame:** Newsprint ~46% · Ink ~30% · Alarm ~16% · Money ~6% · Press grey ~3%.

Rules: two grounds maximum per frame · money olive never sits on alarm red (separate with ink or newsprint) · money olive appears once per frame — twice means one is decoration.

---

## 4. Typography

| Role | Face | Spec | Used for |
| --- | --- | --- | --- |
| Display | **Archivo Black** | tracking −4.5%, caps | Wordmark, thumbnail hero, dollar figures |
| Headline | **Archivo Narrow Bold** | line-height 1.0–1.06, caps | Video titles, callouts, Shorts captions |
| Body | **Archivo** 400/600 | 15–19px, line-height 1.6 | Descriptions, scripts, posts |
| Evidence | **Space Mono** | 0.16–0.24em tracking, caps | Docket numbers, dates, sources, labels |

Space Mono is reserved for evidence — case numbers, filing dates, exhibit references. It is what makes "loud" read as documented rather than clickbait. Never set body copy in it.

Minimum sizes: 24px on a 1920 × 1080 frame; 12pt in print; thumbnail hero type must be legible at 250px wide.

---

## 5. Print texture

The look is a mid-century spot-colour press — flat inks, visible dots, imperfect registration.

- **Halftone** — 8–9px radial dot grid, ink at 20–30% opacity, over large flats.
- **Line screen** — 6–7px vertical stripe, for image placeholders and empty grounds.
- **Misregistration** — 5px offset duplicate in a second ink. Hero words only, never body copy.
- **Rubber stamp** — 5px keyline box, rotated −7°, ~86% opacity (SETTLED, FILED, EXHIBIT, CHAPTER 11). One per frame, maximum.

Never: gradients, glows, drop shadows, bevels, rounded corners over 0px.

---

## 6. Thumbnails — 1280 × 720

Four templates. Six words maximum, four is better; the date tag doesn't count. One number, one verb, one accusation — never all three at full size.

- **A — Number hero** — alarm ground, halftone, giant figure in newsprint, Archivo Narrow kicker beneath. For fines, payouts, write-downs.
- **B — Redacted document** — newsprint page with ink redaction bars and one Space Mono quote, ink panel on the right carrying the headline, stamp across the seam. For quotes from filings.
- **C — Logo + verdict** — ink ground, company logo slot at left, verdict headline right, Space Mono category chip in alarm red. For named-company stories.
- **D — Statement** — newsprint with line screen, Archivo Black statement, one phrase highlighted in an alarm-red box. For scams, patterns, explainers.

Geometry: 68px safe margin, nothing crosses it. Keep the lower-right 25% quiet — the duration chip lands there. Read every thumbnail at 250px wide before uploading.

---

## 7. Shorts — 1080 × 1920

Four beats, in order, every time:

1. **Hook** (0–2s) — alarm ground, Archivo Black, the number and the outrage in one breath.
2. **Evidence** (2–20s) — newsprint document with redaction bars, one highlighted quote in money olive, ink caption block at the bottom carrying the claim plus the case number.
3. **Number** (20–35s) — newsprint ground, the figure at display size, then a two-bar scale comparison (revenue vs fine) so the number means something.
4. **End card** (last 3s, no voiceover) — ink ground with alarm keyline, stacked wordmark, "A new filing every day", subscribe bar.

Safe band: 86px top, 108px bottom, 66px right (UI chrome). The caption slot is fixed at the lower third so subtitles never collide with the hook.

---

## 8. On-screen graphics

| Element | Spec |
| --- | --- |
| **Lower third** | Ink block with a 12px alarm-red spine. Slides in from the left, 180ms. Name in Archivo Narrow, role in Space Mono. |
| **Figure card** | Money olive ground, ink type, right-aligned. Number counts up over 400ms then holds. Always carries a ratio line ("= 0.6% of 2025 revenue"). |
| **Quote block** | Newsprint card, 3px ink keyline, Archivo Narrow caps. Always cites page and exhibit. |
| **Docket bar** | Persistent 52px ink strip at the bottom: alarm-red "THE FILING" tab plus case number, filing date, and party names in Space Mono. Holds for the whole segment. |
| **Company card** | Ink ground, "NOW FILING" label in money light, company name in Archivo Narrow, ticker and date in Space Mono. |

Motion: cuts and hard slides only, 150–200ms. No easing longer than 250ms, no fades on type, no parallax.

---

## 9. Community & social

- **Story card (1:1)** — ink ground, Archivo Narrow statement with the figure in alarm red, "The Filing" + date in Space Mono at the foot.
- **Community poll** — newsprint with 3px ink keyline, options as bordered rows, the leading option filled money olive.
- **Correction post** — alarm-red ground, halftone, body copy in newsprint Archivo. Corrections get their own post within 24 hours, always naming the old figure, the new figure, and the page it comes from.

### Asset sizes

| Platform | Avatar | Cover / banner |
| --- | --- | --- |
| YouTube | 1024² | 2560 × 1440 (TV-safe 1546 × 423 centred) |
| X / Twitter | 400² | 1500 × 500 (avatar covers lower-left 200px) |
| Instagram | 320² | Post 1080² · Story 1080 × 1920 |
| TikTok | 200² | Video 1080 × 1920 |
| LinkedIn | 400² | 1128 × 191 (left 300px sits under the logo) |
| Facebook | 320² | 820 × 312 |
| Podcast / Spotify | — | 3000² |
| Newsletter | — | 1200 × 400 |

Alarm red is the default avatar ground so the channel reads as one red dot in any feed. The dark alt is for surfaces that are already red or orange.

---

## 10. Daily publish checklist

1. One template, unmodified. No new layout on a deadline.
2. Every figure on screen has a page number in the description.
3. Money olive appears once. If it appears twice, one is decoration.
4. Read the thumbnail at 250px wide before you upload it.
5. Allegations stay allegations.
6. Corrections get their own post, in alarm red, within 24 hours.

---

## Files

- `The Filing - Design System.dc.html` — full visual system, ten sections.
- `The Filing - Logo & Social Kit.dc.html` — logo lockups, avatars, and every banner as a true-size artboard.
- `exports/` — 13 ready-to-upload PNGs.
