# Finished hub + Blotato publishing — design

Date: 2026-07-28

## Goal

Rename the current **Drafts** section to **Finished**, restructure it into four
tabs, and let the user publish/schedule finished posts and videos to social
platforms through the **Blotato** API. Add a Blotato API key and per-platform
account IDs to Settings.

## Current state (what exists)

- `App\Livewire\Rascunhos` (`/rascunhos`, nav label "Drafts", title "Drafts and
  Scheduling") auto-collects three sources of ready content and offers a single
  date field to schedule each:
  - **Posts** — vault `rascunhos/*` notes with `estado` in `pronto|agendado`.
  - **Shorts** — vault `clips/*` notes, `tipo: clip`, `estado` in `pronto|agendado`.
  - **Animated clips** — `ClipStore` records with `status = done`, `scheduled_for` (a date).
- `Rascunhos::itens()` normalizes all three into a common array shape
  (`id, source, ref, kind, title, cover, excerpt, scheduled`).
- Settings (`App\Livewire\Definicoes` + `SettingsRepository`) store API keys under
  `chaves` (global, via `SharedKeys`) and other config per project in a vault note.
- Nav entry: `resources/views/components/layouts/app.blade.php:39`.

## Scope of change

### 1. Rename Drafts → Finished

- Nav label `Drafts` → `Finished` (keep the "Scheduling" sub-label or change to
  "Publishing"), `app.blade.php:39`.
- Route `/rascunhos` → `/finished`, name `rascunhos` → `finished`. Update the one
  nav reference. (Component/file may keep the `Rascunhos` class name to minimize
  churn, or be renamed to `Finalizados` — implementer's call, not user-visible.)
- Page title `Drafts and Scheduling` → `Finished`.

### 2. Explicit "Send to Finished" promotion

Content no longer auto-appears. A **"Send to Finished"** button on each editor
promotes an item into the Unpublished tab:

- **Posts** (`App\Livewire\Publicacoes\Oficina`) → set frontmatter `estado: pronto`.
- **Animated clips** (`App\Livewire\ClipsAnimados`) → set record `finished: true`
  (new attribute; `status` stays `done`).
- **Shorts** (wherever a rendered short is finalized) → set `estado: pronto`.

The Finished hub then lists only items that have been explicitly promoted:
posts/shorts with `estado ∈ {pronto, agendado, publicado}`, and clips with
`finished = true`.

### 3. Four tabs

The Finished page is restructured into four tabs. Item classification is derived,
not stored as a separate enum:

| Tab | Filter | Actions per item |
|---|---|---|
| **Unpublished** | promoted, not scheduled, not posted | choose **platforms** (multi-select) + **when** (specific date-time \| next free Blotato slot \| post now) → **Schedule** / **Post now** |
| **Scheduled** | `scheduled_for` in the future, not posted | Cancel scheduling (best-effort) / reschedule |
| **Calendar** | same set as Scheduled | month grid; each scheduled item on its day; click day → list |
| **Posted** | posted, OR `scheduled_for` in the past | platform badges + timestamp, read-only |

**Posted vs Scheduled is derived by elapsed time** (once `scheduled_for` passes,
the item is treated as Posted). No polling of Blotato. *Ceiling: no real
confirmation the post went live; add polling later if needed.*

### 4. Blotato client — `App\Services\Publishing\BlotatoClient`

Base URL `https://backend.blotato.com`, header `blotato-api-key: <key>`.

- `uploadMedia(string $pathOrUrl): string`
  - If already an `http(s)` URL → return as-is (Blotato fetches it).
  - If a local file →
    1. `POST /v2/media/uploads { filename }` → `{ presignedUrl, publicUrl }`
    2. `PUT` file bytes to `presignedUrl` (immediately; it expires fast)
    3. return `publicUrl`.
  - Called once per item; the resulting URL is reused across all target platforms.
- `publish(string $accountId, string $targetType, string $text, array $mediaUrls, ?string $scheduledTime, bool $useNextFreeSlot): array`
  - `POST /v2/posts`:
    ```json
    {
      "post": {
        "accountId": "<accountId>",
        "content": { "text": "<text>", "mediaUrls": [...], "platform": "<targetType>" },
        "target": { "targetType": "<targetType>", ...platformExtras }
      },
      "scheduledTime": "<iso8601>"        // omit for immediate / slot
      // OR "useNextFreeSlot": true       // "slots" option
    }
    ```
  - Returns Blotato's response (post id/status), stored on the item.

**Platform extras** (defaults, marked `ponytail:` in code):
- `youtube`: `title` = item title, `privacyStatus` = `public`, `shouldNotifySubscribers` = `false`.
- `tiktok`: required fields with safe defaults (`privacyLevel: PUBLIC_TO_EVERYONE`,
  `disabledComments: false`, `disabledDuet: false`, `disabledStitch: false`,
  `isBrandContent: false`, `isYourBrand: false`, `isAiGenerated: true`).
- `instagram`, `linkedin`, `threads`: `targetType` only.

Supported platforms v1: **youtube, instagram, tiktok, linkedin, threads**.

### 5. Publish flow (in the Finished component)

On Schedule / Post now:
1. Resolve the item's media file/URL (post cover images / short mp4 / clip
   `output_path`).
2. `uploadMedia()` once → `publicUrl`.
3. For each selected platform: look up its `accountId` from settings; call
   `publish(...)` with the chosen `when`.
4. Store `platforms[]`, `scheduled_for` (date-time; immediate = now), and
   `blotato_ids[]` on the item; set state:
   - immediate → posted (`estado: publicado` / clip `posted_at`)
   - scheduled/slot → scheduled (`estado: agendado` / clip `scheduled_for`)
5. Surface Blotato errors inline (per platform); a failed platform doesn't block
   the others.

### 6. Settings additions (`Definicoes` + `SettingsRepository`)

- `chaves.blotato` — one password field in the existing "API keys" panel.
- New `blotato` config group: `accounts` = `{ youtube, instagram, tiktok, linkedin, threads }`
  account-id strings. New "Blotato accounts" panel in the settings view. IDs are
  copied manually from the Blotato dashboard. *Ceiling: not fetched via Blotato's
  accounts API.*
- Map `chaves.blotato` onto config (like other keys) so `BlotatoClient` reads it
  via `config()`.

## Data model changes

- **Posts / shorts** (vault frontmatter): extend `estado` lifecycle with
  `publicado`; store `agendado_para` as **date-time** (was date); add
  `plataformas` (array) and `blotato_ids` (array).
- **Animated clips** (`ClipRecord` attributes): add `finished` (bool),
  `scheduled_for` (date-time), `posted_at`, `plataformas`, `blotato_ids`.
- `Rascunhos::deNota()` / animado mapping extended to carry the new fields into the
  normalized shape used by the tabs.

## Testing

- `BlotatoClient` unit test with a faked HTTP client (`Http::fake`): asserts the
  presigned-upload sequence, the `/v2/posts` body for immediate / scheduledTime /
  useNextFreeSlot, and the `blotato-api-key` header.
- Feature test on the Finished component: promote → appears in Unpublished;
  schedule with a future time → moves to Scheduled; time in the past → shows in
  Posted; per-platform publish calls happen with the right account IDs.
- Follows the repo's existing `tests/Feature` style.

## Out of scope (v1)

- Real publish-status confirmation / polling.
- Fetching Blotato account list into settings.
- Per-post platform-specific overrides in the UI (privacy toggles, YT category).
- Facebook / Twitter / Pinterest / Bluesky targets.
