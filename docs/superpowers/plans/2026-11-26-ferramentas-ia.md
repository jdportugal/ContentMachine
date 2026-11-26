# Ferramentas IA — Implementation Plan (shared machinery + Drone)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Customers buy packs of "vídeos IA", upload a photo, approve AI start/end frames (≤3 edits), get a Kling clip (≤2 redos). This plan covers the shared machinery and the Drone tool. Limpeza, Remodelação, Construção and Planta each get their own plan after the user tests Drone.

**Architecture:**
- One `ferramentas_ia` row per video, with a small state machine.
- `App\Services\Ferramentas\Ferramentas` holds the rules: spending, caps and refunds.
- `App\Services\Ferramentas\Realizador` makes the frames and the clip, calling `KieClient`.
- Two queued jobs, two Livewire pages (light customer pages like Imoplanta's), and the existing Payment Link + `Compras::registar` billing.

**Tech Stack:** Laravel 12, Livewire 4, PHPUnit 12 (`php artisan test`), kie.ai (Nano Banana Pro, Kling 3.0).

**Spec:** `docs/superpowers/specs/2026-11-26-ferramentas-ia-design.md`

## Global Constraints

- Packs and prices come from `contentmachine.ferramentas.packs`:
  - `ia1`: 1 video, 2500 cents.
  - `ia10`: 10 videos, 22500 cents.
  - `ia20`: 20 videos, 40000 cents.
- Payment Links are `contentmachine.stripe.links.ia1|ia10|ia20`, with `metadata.videos_ia`.
- The caps come from config: `editar_imagem` = 3 and `refazer_video` = 2. Never hard-code them.
- Kling clips use `kling-3.0/video`, `mode` `pro`, with sound. Drone, Limpeza, Remodelação and Planta are 10 s; Construção is 15 s.
- Kling gets 2 attempts per run (`Fotografo::comAlternativa`-style). Images keep `comAlternativa`'s 4.
- Admins and `imoplantaGratis()` accounts spend nothing.
- Access: gate `ferramentas` = `veImoplanta() || is_admin`.
- Customer copy is European Portuguese. Customers never see kie's error text.
- Costs go in `custos` with tipo `ferramenta`, peca = id, passo `frames` | `frames_edit` | `video` | `video_redo`.

## Review Focus

1. Two quick clicks on «Criar imagens» spend one video, not two: the state guard runs inside the transaction.
2. A customer opens or downloads another customer's video by id: 403 or 404.
3. An upload that isn't an image, or is over 15 MB, gives a validation error and spends nothing.
4. A refund happens at most once, even if the frames job fails twice.
5. A failed redo keeps the earlier video, and the state goes back to `pronto`.

---

### Task 1: Kling clips booked at their real price

**Files:**
- Modify: `app/Services/Publicacoes/Rendering/KieClient.php`, `config/contentmachine.php` (`custos.kling3_pro_s`), `app/Services/Imoplanta/Fotografo.php` (clipPiso), `app/Jobs/Clips/AnimatePropertyJob.php`
- Test: `tests/Feature/KieClientPollingTest.php`

**Interfaces:**
- Produces:
  - `KieClient::precoKling(int $segundos, bool $som): float`, which is the seconds × `kling3_pro_s[som|mudo]` (0.151 / 0.101).
  - `KieClient::pollVideo(string $taskId, ?float $custo = null): string`. With no cost given, it keeps the old `kie_video` default.

- [ ] Test `test_a_kling_3_clip_is_booked_at_seconds_times_the_per_second_price`:
  - `precoKling(10, true)` = 1.51 and `precoKling(15, true)` ≈ 2.265.
  - Polling a faked successful task with `custo: 1.51` writes a `custos` row with custo 1.51.
- [ ] Implement it. `Fotografo::clipPiso` passes `precoKling(10, true)`. `AnimatePropertyJob` passes `precoKling($seconds, config sound)`.
- [ ] Run `php artisan test --filter=KieClientPolling`. Expected: PASS.
- [ ] Commit.

### Task 2: Packs of AI videos (data, purchase, spending)

**Files:**
- Create: migration `2026_11_26_000001_create_ferramentas_ia.php`, `app/Models/FerramentaIa.php`
- Modify: `app/Models/User.php`, `app/Services/Pagamentos/Compras.php`, `routes/web.php` (/obrigado), `app/Services/Ferramentas/Ferramentas.php`
- Test: `tests/Feature/FerramentasTest.php`

**Interfaces:**
- Produces:
  - `compras.videos_ia` and `compras.videos_ia_usados`.
  - `User::videosIaDisponiveis(): int`.
  - `FerramentaIa`:
    - columns: id, user_id, compra_id, ferramenta, estado, original, inicio, fim, versoes (json list of frame paths), edicoes, video, refeitos, pedido, erro;
    - consts `RASCUNHO`, `A_ENQUADRAR`, `A_APROVAR`, `A_ANIMAR`, `PRONTO`;
    - `pasta(): string` = `ferramentas/{id}`.
  - `Ferramentas` (instance methods):
    - `criar(User, string $ferramenta, UploadedFile): FerramentaIa`
    - `iniciar(FerramentaIa): bool`, which spends a video and dispatches `FerramentaFramesJob`
    - `devolver(FerramentaIa): void`, the once-only refund
    - `alterar(FerramentaIa, string $pedido): bool`
    - `aprovar(FerramentaIa): bool`
    - `refazer(FerramentaIa, string $nota): bool`
    - `static link(User, string $pack, ?FerramentaIa = null): string`
  - `Compras::ferramentaId(string $ref): ?int` parses `ferramenta-<id>`.

- [ ] Tests:
  - `test_a_paid_pack_becomes_ai_videos`: `metadata.videos_ia=10` → `videosIaDisponiveis()` = 10.
  - `test_starting_spends_one_video_and_only_once`: two `iniciar` calls → usados 1, one job queued.
  - `test_without_videos_nothing_starts`.
  - `test_admins_and_free_accounts_spend_nothing`.
  - `test_a_refund_happens_once`.
  - `test_three_frame_changes_and_two_redos_then_no_more`.
  - `test_frames_lock_once_approved`: `alterar` after `aprovar` returns false.
  - `test_the_pack_link_carries_the_video_and_the_email`.
  - `test_back_from_paying_goes_to_the_video`.
- [ ] Implement it. `iniciar` follows `Imoplantas::iniciar`: lock the oldest compra with `videos_ia_usados < videos_ia`, and guard with `whereKey()->where('estado', RASCUNHO)->update(...) === 1`.
- [ ] Run `php artisan test --filter=FerramentasTest`. Expected: PASS.
- [ ] Commit.

### Task 3: Making the frames and the clip (Drone)

**Files:**
- Create: `app/Services/Ferramentas/Realizador.php`, `app/Jobs/Ferramentas/FerramentaFramesJob.php`, `app/Jobs/Ferramentas/FerramentaVideoJob.php`
- Test: `tests/Feature/FerramentasTest.php`

**Interfaces:**
- Consumes: `FerramentaIa`, `KieClient::submitResolucao`, `KieClient::poll`, `submitAlternativo`, `submitFramesVideo` (with model `kling-3.0/video` pinned via a new optional `$modelo` param), `pollVideo($t, precoKling(...))`, `Fotografo::comAlternativa`, `Fotografo::proporcao`.
- Produces:
  - `Realizador::enquadrar(FerramentaIa, ?string $pedido): string`, the new AI frame's path.
  - `Realizador::animar(FerramentaIa, ?string $nota): string`, the video's path.
  - `Realizador::PROMPTS` per tool: `frame`, `video`, `som`. Only `drone` is filled in this plan.
  - `FerramentaFramesJob(int $id, bool $alteracao)` and `FerramentaVideoJob(int $id, bool $refazer)`.

- [ ] Tests (kie faked with `Http::fake`):
  - `test_drone_frames_keep_the_photo_as_start_and_make_the_end_with_nano_banana_pro`: after the job, estado `a_aprovar`, inicio = original, fim set, 1 `custos` row passo `frames` at 0.09.
  - `test_failed_first_frames_refund_and_go_back_to_draft`: kie fails every attempt → estado `rascunho`, usados 0, erro set in Portuguese.
  - `test_a_failed_change_keeps_the_frame_and_does_not_count`.
  - `test_the_clip_animates_from_start_to_end_with_kling_3_and_sound`: assert the request body has model `kling-3.0/video`, both image_urls, duration "10" and sound true, and that the cost row is 1.51.
  - `test_a_failed_redo_keeps_the_video`.
- [ ] Implement it.
- [ ] Run the tests. Expected: PASS.
- [ ] Commit.

### Task 4: Customer pages

**Files:**
- Create: `app/Livewire/Ferramentas/Lista.php` + view, `app/Livewire/Ferramentas/Video.php` + view
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (gate), `resources/views/components/layouts/app.blade.php` (menu item «Ferramentas IA»), `resources/views/ferramentas/landing.blade.php` (CTA → `route('ferramentas')`)
- Test: `tests/Feature/FerramentasTest.php`

**Interfaces:**
- Routes:
  - `ferramentas` → `/ferramentas`
  - `ferramentas.nova` → `/ferramentas/nova/{ferramenta}`
  - `ferramentas.video` → `/ferramentas/{video}`
  - `ferramentas.ficheiro` → `/ferramentas/{video}/ficheiro/{qual}`, where `qual` is original, inicio, fim, versao or video. Owner or admin only.

- [ ] Tests:
  - `test_only_the_listed_accounts_see_the_tools`.
  - `test_customers_see_only_their_own_videos`: another user's id → 403.
  - `test_upload_validates_type_and_size_and_spends_nothing`.
  - `test_the_page_offers_packs_when_no_videos_are_left`.
- [ ] Implement it. The pages follow the approved prototype (https://claude.ai/artifact/JtyA5r7yqzRc8fbbFVJ6Vo): poll every 5 s while a job runs, show versions of the AI frame, and offer three downloads when ready.
- [ ] Run the full suite: `php artisan test`. Expected: no new failures.
- [ ] Check by hand in the browser at :8651 with the fake driver.
- [ ] Commit.
