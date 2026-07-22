# Publicações de vários tipos + Carrosséis — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring AdsMaker's "different kinds of posts + carousels + AI planning + generative slide rendering" capability into ContentMachine, adapted to its vault-based Markdown architecture and made fully verifiable offline (no API keys).

**Architecture:** A **config-driven kinds registry** (mirrors AdsMaker's `PostTemplate`/`strategy` extension point) is the single source of truth for every publication format. One generic **`Oficina`** Livewire component renders any kind (single-body or multi-card carousel) from that registry. A **`PublicacaoPlanner`** turns a free-text brief into a structured plan (title + caption + ordered slides) by prompting the existing `LlmClient` for STRICT JSON (mirrors AdsMaker `PlanPostJob`), with a deterministic heuristic fallback so it works with no LLM. A **`SlideRenderer`** contract renders each slide to an on-brand image; the default `SvgSlideRenderer` produces deterministic, dependency-free, viewable SVG cards (the offline-verifiable analogue of AdsMaker's kie.ai renderer), with a `KieSlideRenderer` driver as the production path. Everything persists to the vault as one Markdown note per publication.

**Tech Stack:** PHP 8.4 / Laravel 13 / Livewire 4 / Tailwind v4 / PHPUnit. Reuses `App\Services\Aggregation\LlmClient` and `App\Services\Vault\VaultContract`.

## Global Constraints

- European-Portuguese UI copy; **no emojis** (IATECA "nocturna" design system; use glyphs like ❦ ☰ ❧).
- No new Composer/NPM dependencies. SVG rendering only — no Puppeteer/Satori/Sharp/GD.
- Everything must work with `LLM_PROVIDER=none` and no API keys (deterministic fallback).
- Follow existing patterns: contract + driver + fake; `tipo` frontmatter discriminator; `x-panel`/`x-page-header`/`x-fleuron` Blade components; vault note per piece.
- All new services under `App\Services\Publicacoes`. Livewire under `App\Livewire\Publicacoes`.
- `php artisan test` must stay green (baseline: 40 tests).

---

## File Structure

- `config/contentmachine.php` — add `publicacoes` block (kinds registry + render driver).
- `app/Services/Publicacoes/PublicacaoKinds.php` — registry accessor (all/get/exists/formato).
- `app/Services/Publicacoes/Dto/PublicacaoPlan.php` — plan DTO (titulo, legenda, tags, slides).
- `app/Services/Publicacoes/Dto/SlidePlano.php` — one slide (ordem, titulo, texto).
- `app/Services/Publicacoes/PublicacaoPlanner.php` — brief → plan via LlmClient + heuristic fallback.
- `app/Services/Publicacoes/Rendering/SlideRenderer.php` — interface: `render(PublicacaoPlan, array $kind): array<int,string>`.
- `app/Services/Publicacoes/Rendering/SvgSlideRenderer.php` — default; returns SVG markup strings per gabarito.
- `app/Services/Publicacoes/Rendering/KieSlideRenderer.php` — production driver stub (kie.ai), untested offline.
- `app/Providers/AppServiceProvider.php` — bind `SlideRenderer` by `publicacoes.render_driver`.
- `app/Livewire/Publicacoes/Oficina.php` — generic kind-driven workshop.
- `resources/views/livewire/publicacoes/oficina.blade.php` — its view (form + AI + render preview).
- `resources/views/livewire/publicacoes/index.blade.php` — list all kinds from registry.
- `routes/web.php` — `/publicacoes/{tipo}` → Oficina (replaces posts/carrosseis routes).
- `app/Livewire/Rascunhos.php` + view — show kind label + slide count.
- Delete: `app/Livewire/Publicacoes/Posts.php`, `Carrosseis.php`, `posts.blade.php`, `carrosseis.blade.php`.
- Tests: `tests/Unit/PublicacaoKindsTest.php`, `PublicacaoPlannerTest.php`, `SvgSlideRendererTest.php`; update `tests/Feature/FluxoRascunhosTest.php`, `PaginasTest.php`; add `tests/Feature/OficinaTest.php`.

---

## Kinds Registry (canonical spec)

Six kinds. `formato`: `single` (one body) or `carousel` (N cards). `gabarito`: SVG template id.

| tipo | label | formato | proporcao | cartões | gabarito |
|------|-------|---------|-----------|---------|----------|
| `post` | Posts de página única | single | 1:1 | 1 | quadrado |
| `citacao` | Citações | single | 1:1 | 1 | citacao |
| `dica` | Dicas rápidas | single | 4:5 | 1 | dica |
| `carrossel` | Carrosséis | carousel | 4:5 | 2–10 | capa-conteudo |
| `lista` | Listas numeradas | carousel | 4:5 | 3–8 | lista |
| `resumo-semana` | Resumo da semana | carousel | 4:5 | 3–6 | capa-conteudo |

Each kind also carries `glifo`, `descricao`, `plano_prompt` (IA guidance), `plataforma_padrao`.

---

## Task 1: Kinds registry + accessor

**Files:** Modify `config/contentmachine.php`; Create `app/Services/Publicacoes/PublicacaoKinds.php`; Test `tests/Unit/PublicacaoKindsTest.php`.

**Produces:** `PublicacaoKinds::all(): array`, `::get(string $tipo): ?array`, `::exists(string $tipo): bool`, `::formato(string $tipo): string`. Config key `contentmachine.publicacoes.tipos` (assoc, keyed by tipo) and `contentmachine.publicacoes.render_driver`.

- [ ] Write `PublicacaoKindsTest`: `all()` returns ≥6 kinds keyed by tipo; `get('carrossel')['formato']==='carousel'`; `get('post')['formato']==='single'`; `exists('post')` true, `exists('x')` false; every kind has label/glifo/formato/proporcao/gabarito/plano_prompt.
- [ ] Run → fail (class missing).
- [ ] Add `publicacoes` block to config with the 6 kinds + `render_driver => env('PUBLICACOES_RENDER','svg')`.
- [ ] Implement `PublicacaoKinds` reading `config('contentmachine.publicacoes.tipos')`.
- [ ] Run → pass. Commit.

## Task 2: Plan DTOs

**Files:** Create `Dto/PublicacaoPlan.php`, `Dto/SlidePlano.php`. Tested via Task 3.

**Produces:**
- `SlidePlano { int $ordem; string $titulo; string $texto; }` + `fromArray(array): self`.
- `PublicacaoPlan { string $titulo; string $legenda; array $tags; SlidePlano[] $slides; }` + `fromArray(array): self` (defensive: coerces missing keys, orders slides) + `toBody(): string` (serialize slides to Markdown using `## Cartão N` + `\n\n---\n\n` for carousels, or plain text for single).

- [ ] Implement both DTOs (no test yet; covered by planner test).

## Task 3: PublicacaoPlanner

**Files:** Create `PublicacaoPlanner.php`; Test `tests/Unit/PublicacaoPlannerTest.php`.

**Consumes:** `LlmClient` (constructor-injected, mockable), `PublicacaoKinds`. **Produces:** `planear(string $tipo, string $brief, string $plataforma): PublicacaoPlan`.

Behaviour: builds a kind-specific prompt (IATECA voice + kind `plano_prompt` + card-count bounds + "responde SÓ com JSON") → `LlmClient::texto()`. Strip ```json fences, `json_decode`, hydrate `PublicacaoPlan::fromArray`. If LLM returns null OR JSON invalid → `heuristica($tipo,$brief,$plataforma)`: single → one slide = brief; carousel → split brief into sentences/lines, clamp to kind's card bounds, first card = title cover. Never throws.

- [ ] Write test: bind a fake `LlmClient` returning fenced JSON `{"titulo":..,"legenda":..,"tags":[..],"slides":[{"ordem":1,"titulo":..,"texto":..}, ...]}` → assert plan hydrated. Second test: fake returns `null` → heuristic produces a valid plan with slide count within the kind's bounds.
- [ ] Run → fail.
- [ ] Implement planner + `stripFences`.
- [ ] Run → pass. Commit.

## Task 4: SlideRenderer contract + SvgSlideRenderer

**Files:** Create `Rendering/SlideRenderer.php`, `Rendering/SvgSlideRenderer.php`; Test `tests/Unit/SvgSlideRendererTest.php`.

**Produces:** `SlideRenderer::render(PublicacaoPlan $plan, array $kind): array` → `array<int,string>` of SVG markup strings (one per slide). `SvgSlideRenderer` picks dimensions from `proporcao`, draws an on-brand card per `gabarito` (background, border frame, title, body/fleuron, platform accent, index badge for carousels). Pure function, no filesystem. XML-escapes all text.

- [ ] Write test: build a 3-slide carousel plan, render with the `carrossel` kind → assert 3 strings, each starts with `<svg` and contains the slide title (escaped) and correct `viewBox` for 4:5.
- [ ] Run → fail.
- [ ] Implement renderer (gabarito switch; shared palette constant; `htmlspecialchars` + a simple word-wrap helper).
- [ ] Run → pass. Commit.

## Task 5: Kie driver stub + binding

**Files:** Create `Rendering/KieSlideRenderer.php`; Modify `AppServiceProvider.php`.

`KieSlideRenderer implements SlideRenderer`: faithful synchronous submit+poll against kie.ai (`config('services.kie.*')`), returns image URLs; throws `DriverNotConfiguredException` when no key. Bind in provider: `render_driver==='kie' && key set → KieSlideRenderer, else SvgSlideRenderer`. Default path stays SVG.

- [ ] Implement `KieSlideRenderer` (mirrors AdsMaker KieClient submit/poll; marked untested — no key offline).
- [ ] Add `services.kie` config + `PUBLICACOES_RENDER`/`KIE_*` to `.env.example`.
- [ ] Bind `SlideRenderer` in `AppServiceProvider`.
- [ ] Run full suite → green. Commit.

## Task 6: Oficina Livewire component + view

**Files:** Create `app/Livewire/Publicacoes/Oficina.php`, `resources/views/livewire/publicacoes/oficina.blade.php`.

`mount(string $tipo)` — 404 if `!PublicacaoKinds::exists($tipo)`. State: `titulo`, `plataforma`, `brief`, `legenda` (single), `slides[]` (carousel), `previews[]` (rendered SVG strings), `guardado`. Methods:
- `redigirComIa(PublicacaoPlanner)` — plan from brief; fills titulo/legenda/slides.
- `gerarImagens(SlideRenderer)` — build a plan from current fields, render → `previews`.
- `adicionarSlide`/`removerSlide` (carousel only, respects card bounds).
- `criarRascunho(VaultContract, SlideRenderer)` — validate; build plan; render + write SVG files to `public/media/publicacoes/{slug}/{n}.svg`; `vault->create('rascunhos', frontmatter, body)` with `tipo`, `formato`, `plataforma`, `cartoes`, `gabarito`, `imagens` (paths), tags; set `guardado`.
- The view is driven entirely by the kind: single kinds show one body textarea; carousel kinds show the repeatable card list; both show brief + "Redigir com IA" + "Gerar imagens" + preview gallery.

- [ ] Implement component + view. (Behaviour covered by Task 7 tests.)

## Task 7: Routes, index, nav + test updates

**Files:** `routes/web.php`, `resources/views/livewire/publicacoes/index.blade.php`, `resources/views/components/layouts/app.blade.php` (nav sub text only); update `tests/Feature/PaginasTest.php`, `tests/Feature/FluxoRascunhosTest.php`; add `tests/Feature/OficinaTest.php`; delete legacy Posts/Carrosseis component+view.

- [ ] Replace `posts`/`carrosseis` routes with `Route::livewire('/publicacoes/{tipo}', Oficina::class)->name('publicacoes.oficina')`. Remove Posts/Carrosseis imports.
- [ ] Rewrite `index.blade.php` to loop `PublicacaoKinds::all()` → one card per kind linking `route('publicacoes.oficina', $tipo)`.
- [ ] Delete legacy files.
- [ ] Update `PaginasTest::rotas()`: replace posts/carrosseis rows with `'/publicacoes/post' → 'Posts de página única'` and `'/publicacoes/carrossel' → 'Carrosséis'`; add `'/publicacoes/citacao'`, `/dica`, `/lista`, `/resumo-semana`.
- [ ] Rewrite `FluxoRascunhosTest::test_criar_rascunho_grava_no_vault` to drive `Oficina` with `tipo='post'`.
- [ ] Add `OficinaTest`: (a) carousel draft via `Oficina` tipo=`carrossel` writes note with `tipo:carrossel` + `cartoes>=2`; (b) `gerarImagens` populates `previews` with SVG; (c) `redigirComIa` with fake LlmClient(null) fills slides via heuristic; (d) unknown tipo → 404.
- [ ] Run full suite → green. Commit.

## Task 8: Rascunhos display + media gitignore

**Files:** `app/Livewire/Rascunhos.php` view (kind label + card count badge), `.gitignore`.

- [ ] Show `tipo` label + `cartoes` on each draft row (read from frontmatter; map via `PublicacaoKinds`).
- [ ] Add `/public/media/` to `.gitignore`.
- [ ] Run suite → green. Commit.

## Task 9: End-to-end verification

- [ ] `php artisan test` — all green, no skips.
- [ ] Start `php artisan serve`; drive with Playwright: open `/publicacoes`, open each kind, on `carrossel` type a brief → "Redigir com IA" (offline heuristic) → "Gerar imagens" → see SVG previews → "Guardar rascunho" → confirm draft in `/rascunhos`. Screenshot.
- [ ] `git commit` final.

---

## Self-Review

- Spec coverage: kinds registry (T1), AI planning strict-JSON+fallback (T2/T3), carousel multi-card + single (T3/T6), generative rendering with fake+production driver (T4/T5), unified kind-driven UI (T6/T7), vault persistence + Rascunhos (T6/T8), verification (T9). ✔
- Backward compat: legacy routes intentionally replaced; tests updated to match (not skipped). ✔
- Offline-first: planner heuristic + SVG renderer need no keys. ✔
