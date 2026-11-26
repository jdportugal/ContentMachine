# Ferramentas IA — design

Date: 2026-11-26 · Branch `ferramentas-ia` (from `upstream/imocena-production`)

## Intent

Real-estate agents buy short AI videos made from one of their own photos (or a floor plan). There are five tools:

| Key | Name shown | What the video shows |
|---|---|---|
| `drone` | Vista drone | a silent drone orbit around the house, seen from the air (changed 2026-11-26: the user wants it rotating and with no sound) |
| `limpeza` | Limpeza IA | unwanted objects and clutter vanish, leaving the space clean |
| `remodelacao` | Remodelação IA | a timelapse-like remodel, from the current state to a renovated one |
| `construcao` | Construção IA | a timelapse of workers building the house, from an empty plot to the finished house |
| `planta` | Planta animada | a 2D floor plan becomes a 3D plan that builds itself and fills with furniture (Imoplanta's plan video, standalone) |

Each video has a **fixed price**, so we cap what a video can cost us and price it above that worst case.

## Goals

- **Start and end frames first.** The customer sees and approves them before any video is made. Videos are the expensive part ($1.51–$2.27 a run).
- **Some image edits.** The customer can ask for a change to the AI-made frame in text, up to 3 times per video.
- **Video redos.** After the video, the customer gets up to 2 redos, each with an optional text note.
- **Sold as packs of videos, not credits.** The customer buys "vídeos IA" and the page says "Restam N vídeos IA". Any tool can be used, and 1 bought video is 1 finished video, whichever tool.
- **Prices.** 1 video is €25, 10 are €225 (€22.50 each) and 20 are €400 (€20 each). The bulk discount is deliberately smaller than Imoplanta's.
- **Built one tool at a time.** The shared machinery comes with the first tool (Drone). Each later tool is mostly its own frame recipe and prompts.

## Non-goals and anti-goals

- **No abstract credits** and no wallet maths. The user said people are fed up with credits.
- **The Imoplanta plan video add-on stays as it is.** The standalone `planta` tool reuses its code but doesn't replace it.
- **No admin cost page for now.** The `custos` rows are recorded so it can be added later.
- **The caps never get lifted.** Lifting them silently would break the fixed-price promise. The caps live in config, so changing one is a deliberate choice.

## Costs and caps (kie.ai, 2026-11-26)

- **Nano Banana Pro, 2K:** $0.09 per image (`contentmachine.custos.kie_modelos`).
- **Kling 3.0 `mode: pro` (1080p):** $0.151/s with sound, $0.101/s silent ([kie.ai/kling-3-0](https://kie.ai/kling-3-0)).
- **Failed kie tasks aren't charged.** So `Fotografo::comAlternativa` retries add no cost. *To verify on a kie invoice.*

**Caps per video:**
- 1 AI frame made, plus `editar_imagem = 3` edits, so at most 4 images.
- 1 video run, plus `refazer_video = 2` redos, so at most 3 runs.

| Tool | AI frame | Clip | Worst case (USD) | ≈ EUR |
|---|---|---|---|---|
| drone | both frames: the aerial view and the view after orbiting ~45° (3 changes shared) | 10 s, **silent** | 5×0.09 + 3×1.01 = 3.48 | 3.20 |
| limpeza, remodelacao | end frame | 10 s, sound | 4×0.09 + 3×1.51 = 4.89 | 4.50 |
| planta | end frame (3D plan); the start (empty plot) is drawn by code, free | 10 s, sound | 4.89 | 4.50 |
| construcao | start frame (empty plot); the end frame is the customer's photo | **15 s**, sound (one clip, so it counts as 1 video) | 4×0.09 + 3×2.265 = 7.16 | 6.60 |

**Worst-case margins:** at €20 a video (the 20-pack), Stripe takes about €0.55, so the margin is about €14.90 per standard video and €12.85 per Construction.

### Existing bug fixed on the way

`KieClient::pollVideo` books every Kling clip at a flat $0.40. The real price is seconds × the per-second price for the mode and sound, so Imoplanta's 10 s plan clips are really $1.51 each. The fix is for `pollVideo` to take the cost from the caller, worked out by a new `KieClient::precoKling(int $segundos, bool $som)`. The per-second prices go in config as `contentmachine.custos.kling3_pro_s = ['som' => 0.151, 'mudo' => 0.101]`.

## Approaches considered (for the purchase model)

1. **Credits (1 credit = 1 standard video, Construction = 2).** Rejected: the user doesn't want credits.
2. **A pack per tool** ("10 Drone videos"). Rejected: 15 Payment Links, and an agent who bought one tool is stuck when they want another.
3. **One shared pack of "vídeos IA", any tool. Chosen.** This is the same model as Imocena's `videos` and Imoplanta's `imoveis`. To keep 1 = 1 honest, Construction is one 15 s clip instead of two 10 s clips.

## How it works

### Data

**`compras` gets two columns:** `videos_ia` (int, default 0) and `videos_ia_usados` (int, default 0).
- `Compras::registar` reads `metadata.videos_ia`.
- `User::videosIaDisponiveis()` sums them, the same way as `imoveisImoplantaDisponiveis`.

**New table `ferramentas_ia`, with model `FerramentaIa`:**

| Column | Holds |
|---|---|
| `id`, `user_id` | |
| `compra_id` | nullable; the purchase the video was taken from |
| `ferramenta` | one of the five keys |
| `estado` | see the states below |
| `original` | the customer's upload (photo or plan) |
| `inicio`, `fim` | paths to the current start and end frames |
| `edicoes` | tinyint; image edits used |
| `video` | path |
| `refeitos` | tinyint; video redos used |
| `pedido` | text; the customer's last change note |
| `erro` | |
| timestamps | |

Files go on the Imoplanta disk under `ferramentas/{id}/`.

**States:**
- `rascunho`: uploaded, nothing spent yet.
- `a_enquadrar`: the frame job is running.
- `a_aprovar`: frames are ready for the customer's approval and edits.
- `a_animar`: the video job is running.
- `pronto`: the video is ready, and the customer can redo it while `refeitos < 2`.
- `falhou`: our side failed after all retries.

### Spending a video

Pressing «Criar imagens» on a draft spends one video, in a transaction like `Imoplantas::iniciar`:
1. Lock the oldest purchase that has `videos_ia_usados < videos_ia`.
2. Increment its `videos_ia_usados` and set `compra_id`.
3. Admins and `imoplantaGratis` accounts spend nothing.

With nothing left, the page shows the three packs instead of the button.

**Refund:** if the first frames or the first video fail after all retries (`falhou` before the customer ever had a video), `videos_ia_usados` is decremented once and `compra_id` is cleared. A failed edit or redo doesn't refund the video; it just doesn't count against the edit or redo caps.

### Frame recipes

Every recipe uses `Fotografo::gerar`-style calls: Nano Banana Pro at 2K with the nano-banana-edit fallback, and the ratio nearest to the photo's.

| Tool | `inicio` | `fim` | Clip |
|---|---|---|---|
| drone | the original photo | NB Pro: «the same property and surroundings, seen from a low drone ~6–8 m up, slightly looking down, same daylight» | 10 s; slow smooth crane-up, no cuts; sound: light wind, distant birds |
| limpeza | original | NB Pro: remove clutter and unwanted objects, keep every fixed element, finish and the framing | 10 s; locked-off, objects fade or vanish one by one; soft sounds |
| remodelacao | original | NB Pro: renovated version, same layout and framing (optional customer style note) | 10 s; locked-off timelapse, works happen, ends renovated |
| construcao | NB Pro from the original: empty levelled plot, same surroundings, neighbours, framing | original | **15 s**; locked-off timelapse with construction workers: foundations, structure, walls, roof, finishes |
| planta | `Fotografo::lote($fim, $original)`: the footprint from the plan's non-white bounding box | NB Pro: photoreal 3D top-down floor plan from the 2D plan | 10 s; `Fotografo::PROMPT_CONSTRUCAO` + `SOM_CONSTRUCAO` + `NEGATIVO_MOBILIA` |

**Edits** change the AI frame only (`fim`, or `inicio` for Construction). The edit is a NB Pro edit of the current frame, with the customer's text and the original as a second reference. The customer's own photo is never edited.

### Pages (Livewire, customer layout, light pages like Imoplanta's customer pages)

**`/ferramentas`** (`Ferramentas\Lista`)
- The five tools as cards: name, one-line description, a sample GIF later.
- "Restam N vídeos IA" in a dark box.
- The customer's videos (thumbnail, tool, state).
- The three packs, linking to the Payment Links.

**`/ferramentas/nova/{ferramenta}`, then `/ferramentas/{id}`** (`Ferramentas\Video`), in this order:
1. Upload a photo (JPG/PNG/WebP, max 15 MB) and press «Criar imagens».
2. Start and end side by side, with "Pedir alteração" (a textarea, "Restam N alterações") and «Aprovar e criar vídeo».
3. The video player plus downloads (the video, the start frame, the end frame), and "Refazer vídeo" (an optional note, "Restam N").

Pages poll while a job runs, like Imoplanta.

**Access:** a new gate `ferramentas`, the same people as `imoplanta` for now (`veImoplanta()` or admin). A sidebar item, «Ferramentas IA», appears for them.

### Jobs

- **`FerramentaFramesJob(id, ?pedido)`** makes or edits the AI frame.
- **`FerramentaVideoJob(id)`** uploads the frames, then calls `submitFramesVideo` and `pollVideo` with the right cost, downloads the result and records it.

Both are tagged in the cost ledger with `tipo` 'ferramenta', `peca` = the video's id, and `passo` frames / frames_edit / video / video_redo. Both handle the `falhou` refund rule.

### Payment

- **Three Payment Links:** `STRIPE_LINK_IA1/IA10/IA20`, with `metadata.videos_ia` = 1/10/20.
- Buying from a draft passes `client_reference_id=ferramenta-<id>`. `/obrigado` then sends the customer back to that video (or to `/ferramentas`), and the draft can be started at once.
- The Payment Links are created by the user in the Stripe dashboard. Until they exist, the page shows the pack without a link.

## Built in this order

1. **Shared machinery plus Drone**, then the user tests and gives feedback.
2. Limpeza.
3. Remodelação.
4. Construção.
5. Planta.

Each later tool is its own commit, and the user tests it with real photos before the next one starts.

## Rules settled after the builder check (Claude's calls; the user can overturn them)

- **Frames lock on approval.** Once «Aprovar e criar vídeo» is pressed, the frames can't be edited. A video redo re-runs Kling on the approved frames, with the customer's note added to the prompt. Unused image edits are simply lost.
- **Failures:**
  - If the *first* frames fail after all retries, the video is refunded once (a guard on `compra_id` not being null), and the draft goes back to `rascunho` with the upload kept. Pressing «Criar imagens» again spends a video again.
  - Any other failure (an edit, the first video, a redo) returns to the previous state. The old frame or video is kept, the error shows, and nothing is counted against the caps. A finished video never moves to `falhou`.
- **No auto-start after paying.** `client_reference_id=ferramenta-<id>` only makes `/obrigado` send the customer back to that video, and they press «Criar imagens» themselves. Nothing is spent without a click, so there's no webhook hook and no new `compras` column.
- **Retries while kie's failure billing is unconfirmed** (no public kie.ai policy was found): images keep `comAlternativa`'s 4 attempts (at worst 4 × $0.09). The Kling clip gets **2 attempts per run** instead of Imoplanta's 4. Once the kie usage log confirms failed tasks are free, this can go back up.
- **Ferramentas pin `kling-3.0/video`.** They don't use the `PROPERTY_VIDEO_MODEL` env setting, because 15 s and the price table depend on Kling 3.0. `pollVideo` takes its cost from each caller (`AnimatePropertyJob`, Imoplanta `VideoJob`, `Fotografo`, Ferramentas).

## Left to the builder

- Exact prompt wording (iterate on real photos).
- Page copy in European Portuguese.
- Polling intervals.
- File names.
- Thumbnail generation.
