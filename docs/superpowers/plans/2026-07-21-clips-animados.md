# Clips Animados Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the `Clips Animados` page into a two-tab studio that renders real MP4s — "Animação" (text/voiceover → fully-animated video) and "Vídeo + Animações" (existing video → sparse animated overlays).

**Architecture:** Laravel + Livewire owns UI, orchestration, transcription and AI planning; a Node **Remotion** subproject (`remotion/`) renders pixels via CLI; **ffmpeg** extracts audio and composites overlays. Long steps run as chained queued jobs; the Livewire component polls for status. All heavy integrations sit behind service interfaces with `fake` implementations for tests.

**Tech Stack:** PHP 8.4, Laravel 12, Livewire 3, Tailwind v4, Node 24 + Remotion 4, ffmpeg 6, OpenAI (Whisper `whisper-1`, GPT `gpt-4o`), ElevenLabs TTS.

## Global Constraints

- All UI copy in **European Portuguese**, inside the Brand Machine design system (page-header, panels, fleurons, mono cotas). No emojis in-brand.
- Output default **1080×1920, 30fps**.
- Animation primitives are a **closed vocabulary** defined in `vault/estilo-animacao.md`: `fade`, `slide`, `scale`, `kinetic-text`, `highlight`, `fleuron-draw`, `seal-stamp`, `underline-sweep`, `count-up`, `image-reveal`, `ambient`.
- Driver selection via `CLIPS_DRIVER` env (`fake` | `api`), default `fake`. Tests always run under `fake`.
- Tab "Animação" (mode `dense`): plan MUST cover 100% of duration (no gaps). Tab "Vídeo + Animações" (mode `sparse`): gaps expected.
- Transcript shape: `['duration'=>float,'text'=>string,'words'=>[['word'=>string,'start'=>float,'end'=>float]],'segments'=>[...]]`.
- Plan shape: `['duration'=>float,'width'=>int,'height'=>int,'fps'=>int,'mode'=>string,'transparent'=>bool,'audioSrc'=>?string,'animations'=>[['start'=>float,'end'=>float,'primitive'=>string,'text'=>?string,'params'=>array]]]`.

---

## File Structure

```
config/contentmachine.php                         # + 'clips' section
vault/estilo-animacao.md                          # style md (closed vocab + tokens)
app/Models/ClipProject.php
database/migrations/2026_07_21_000001_create_clip_projects_table.php
app/Services/Clips/Contracts/{TranscriptionService,VoiceoverService,AnimationPlanner,RemotionRenderer,VideoCompositor}.php
app/Services/Clips/Fake/{FakeTranscriptionService,FakeVoiceoverService,FakeAnimationPlanner,FakeRemotionRenderer,FakeVideoCompositor}.php
app/Services/Clips/Api/{OpenAiTranscriptionService,ElevenLabsVoiceoverService,OpenAiAnimationPlanner}.php
app/Services/Clips/CliRemotionRenderer.php
app/Services/Clips/FfmpegVideoCompositor.php
app/Services/Clips/PlanValidator.php
app/Providers/ClipsServiceProvider.php
app/Jobs/Clips/{TranscribeJob,PlanAnimationsJob,RenderJob,ComposeOverlayJob}.php
app/Livewire/ClipsAnimados.php                    # rewrite
resources/views/livewire/clips-animados.blade.php # rewrite
remotion/{package.json,tsconfig.json,remotion.config.ts,src/index.ts,src/Root.tsx,src/ClipComposition.tsx,src/primitives.tsx,src/style-tokens.ts}
tests/Unit/Clips/PlanValidatorTest.php
tests/Feature/Clips/ClipsAnimadosTest.php
```

---

### Task 1: Config, migration, and ClipProject model

**Files:**
- Modify: `config/contentmachine.php`
- Create: `database/migrations/2026_07_21_000001_create_clip_projects_table.php`
- Create: `app/Models/ClipProject.php`
- Test: `tests/Unit/Clips/ClipProjectTest.php`

**Interfaces:**
- Produces: `ClipProject` Eloquent model with casts `transcript=>array, plan=>array, meta=>array`; status constants `STATUS_DRAFT='draft'`, `STATUS_TRANSCRIBING='transcribing'`, `STATUS_PLANNING='planning'`, `STATUS_RENDERING='rendering'`, `STATUS_DONE='done'`, `STATUS_FAILED='failed'`; type constants `TYPE_ANIMATION='animation'`, `TYPE_OVERLAY='overlay'`.
- Produces: `config('contentmachine.clips')` array.

- [ ] **Step 1: Add config section.** Append to the returned array in `config/contentmachine.php`:

```php
'clips' => [
    'driver' => env('CLIPS_DRIVER', 'fake'),
    'width' => (int) env('CLIPS_WIDTH', 1080),
    'height' => (int) env('CLIPS_HEIGHT', 1920),
    'fps' => (int) env('CLIPS_FPS', 30),
    'voice_id' => env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL'),
    'openai_model' => env('CLIPS_OPENAI_MODEL', 'gpt-4o'),
    'remotion_path' => base_path('remotion'),
    'style_md' => base_path('vault/estilo-animacao.md'),
    'disk' => env('CLIPS_DISK', 'local'),
],
```

- [ ] **Step 2: Write the migration.** Create the migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clip_projects', function (Blueprint $table) {
            $table->id();
            $table->string('type');            // animation | overlay
            $table->string('status')->default('draft');
            $table->string('input_kind');      // text | audio | video
            $table->string('title')->nullable();
            $table->text('source_text')->nullable();
            $table->string('source_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->json('transcript')->nullable();
            $table->json('plan')->nullable();
            $table->string('output_path')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_projects');
    }
};
```

- [ ] **Step 3: Write the model.** Create `app/Models/ClipProject.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClipProject extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_TRANSCRIBING = 'transcribing';
    public const STATUS_PLANNING = 'planning';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public const TYPE_ANIMATION = 'animation';
    public const TYPE_OVERLAY = 'overlay';

    protected $guarded = [];

    protected $casts = [
        'transcript' => 'array',
        'plan' => 'array',
        'meta' => 'array',
    ];

    public function isActive(): bool
    {
        return ! in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
```

- [ ] **Step 4: Write the failing test.** Create `tests/Unit/Clips/ClipProjectTest.php`:

```php
<?php

use App\Models\ClipProject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts json columns and reports active state', function () {
    $p = ClipProject::create([
        'type' => ClipProject::TYPE_ANIMATION,
        'input_kind' => 'text',
        'source_text' => 'Olá',
        'plan' => ['duration' => 1.0],
    ]);

    expect($p->fresh()->plan)->toBe(['duration' => 1.0])
        ->and($p->status)->toBe(ClipProject::STATUS_DRAFT)
        ->and($p->isActive())->toBeTrue();

    $p->update(['status' => ClipProject::STATUS_DONE]);
    expect($p->isActive())->toBeFalse();
});
```

- [ ] **Step 5: Run tests.** `php artisan test tests/Unit/Clips/ClipProjectTest.php` — Expected: PASS.

- [ ] **Step 6: Commit.**

```bash
git add config/contentmachine.php database/migrations/2026_07_21_000001_create_clip_projects_table.php app/Models/ClipProject.php tests/Unit/Clips/ClipProjectTest.php
git commit -m "Clips: config, clip_projects migration e modelo"
```

---

### Task 2: Style md (closed vocabulary + Brand Machine tokens)

**Files:**
- Create: `vault/estilo-animacao.md`

**Interfaces:**
- Produces: a markdown file read verbatim into the planner prompt and mirrored by Remotion's `style-tokens.ts`. Contract: an H2 `## Primitivas` listing exactly the 11 primitives, and an H2 `## Tokens` with the hex palette.

- [ ] **Step 1: Write the file.** Create `vault/estilo-animacao.md` with frontmatter, a `## Primitivas` section documenting each primitive (name, purpose, required `params`, min/max duration), a `## Tokens` section (papyrus `#f4ead5`, vellum `#faf3e0`, ink `#241a12`, ink-soft `#5b4636`, teal `#1f7a7a`, teal-bright `#2dbab4`, leather `#8b3a2a`, gold `#c89b3c`), a `## Tipografia` section (Cormorant Garamond / EB Garamond / JetBrains Mono), and `## Regras` (dense covers 100%, sparse only key moments, easing = ease-in-out cubic, max 1 primitive foreground at a time + optional `ambient` background). Content is prose — no code needed here.

- [ ] **Step 2: Commit.**

```bash
git add vault/estilo-animacao.md
git commit -m "Clips: style md (vocabulario de animacao + tokens Brand Machine)"
```

---

### Task 3: Service contracts (interfaces)

**Files:**
- Create: `app/Services/Clips/Contracts/TranscriptionService.php`
- Create: `app/Services/Clips/Contracts/VoiceoverService.php`
- Create: `app/Services/Clips/Contracts/AnimationPlanner.php`
- Create: `app/Services/Clips/Contracts/RemotionRenderer.php`
- Create: `app/Services/Clips/Contracts/VideoCompositor.php`

**Interfaces:**
- Produces the five interfaces below. All later tasks depend on these exact signatures.

- [ ] **Step 1: Write the interfaces.**

```php
// TranscriptionService.php
namespace App\Services\Clips\Contracts;
interface TranscriptionService {
    /** @return array{duration:float,text:string,words:array,segments:array} */
    public function transcribe(string $audioPath): array;
}
```
```php
// VoiceoverService.php
namespace App\Services\Clips\Contracts;
interface VoiceoverService {
    /** Synthesize speech to $outPath (mp3). Returns the path written. */
    public function synthesize(string $text, string $outPath): string;
}
```
```php
// AnimationPlanner.php
namespace App\Services\Clips\Contracts;
interface AnimationPlanner {
    /** @param 'dense'|'sparse' $mode @return array the plan (unvalidated) */
    public function plan(array $transcript, string $mode, array $options = []): array;
}
```
```php
// RemotionRenderer.php
namespace App\Services\Clips\Contracts;
interface RemotionRenderer {
    /** Render $props to a video at $outPath. Returns the path written. */
    public function render(array $props, string $outPath): string;
}
```
```php
// VideoCompositor.php
namespace App\Services\Clips\Contracts;
interface VideoCompositor {
    public function extractAudio(string $videoPath, string $outPath): string;
    public function overlay(string $baseVideo, string $overlay, string $outPath): string;
    public function probeDuration(string $videoPath): float;
}
```

- [ ] **Step 2: Commit.** `git add app/Services/Clips/Contracts && git commit -m "Clips: contratos dos servicos"`

---

### Task 4: PlanValidator (pure logic, TDD)

**Files:**
- Create: `app/Services/Clips/PlanValidator.php`
- Test: `tests/Unit/Clips/PlanValidatorTest.php`

**Interfaces:**
- Consumes: plan shape (Global Constraints).
- Produces: `PlanValidator::validate(array $plan): array` — returns a normalized plan. In `dense` mode, gaps ≥ 0.05s are filled with an `ambient` animation so coverage is 100%; overlapping/adjacent animations are sorted by `start`. In `sparse` mode, gaps are left intact but each animation is clamped to `[0, duration]` and zero-length animations are dropped. Also `PlanValidator::coverageGaps(array $animations, float $duration): array` returning `[[start,end],...]`.

- [ ] **Step 1: Write failing tests.** Create `tests/Unit/Clips/PlanValidatorTest.php`:

```php
<?php

use App\Services\Clips\PlanValidator;

it('reports gaps in a timeline', function () {
    $gaps = (new PlanValidator)->coverageGaps(
        [['start' => 0, 'end' => 2], ['start' => 3, 'end' => 5]], 6.0
    );
    expect($gaps)->toBe([[2.0, 3.0], [5.0, 6.0]]);
});

it('fills gaps with ambient in dense mode so coverage is total', function () {
    $plan = [
        'duration' => 6.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30,
        'animations' => [['start' => 0, 'end' => 2, 'primitive' => 'kinetic-text', 'text' => 'a', 'params' => []]],
    ];
    $out = (new PlanValidator)->validate($plan);
    expect((new PlanValidator)->coverageGaps($out['animations'], 6.0))->toBe([]);
    expect(collect($out['animations'])->pluck('primitive'))->toContain('ambient');
});

it('keeps gaps in sparse mode but clamps and drops empties', function () {
    $plan = [
        'duration' => 5.0, 'mode' => 'sparse', 'width' => 1080, 'height' => 1920, 'fps' => 30,
        'animations' => [
            ['start' => 1, 'end' => 2, 'primitive' => 'highlight', 'text' => null, 'params' => []],
            ['start' => 4, 'end' => 9, 'primitive' => 'seal-stamp', 'text' => null, 'params' => []],
            ['start' => 3, 'end' => 3, 'primitive' => 'fade', 'text' => null, 'params' => []],
        ],
    ];
    $out = (new PlanValidator)->validate($plan);
    expect($out['animations'])->toHaveCount(2)
        ->and($out['animations'][1]['end'])->toBe(5.0);
});
```

- [ ] **Step 2: Run to verify failure.** `php artisan test tests/Unit/Clips/PlanValidatorTest.php` — Expected: FAIL (class not found).

- [ ] **Step 3: Implement.** Create `app/Services/Clips/PlanValidator.php`:

```php
<?php

namespace App\Services\Clips;

class PlanValidator
{
    private const EPS = 0.05;

    public function validate(array $plan): array
    {
        $duration = (float) ($plan['duration'] ?? 0.0);
        $mode = $plan['mode'] ?? 'dense';
        $anims = array_values($plan['animations'] ?? []);

        // clamp + drop zero-length
        $anims = array_values(array_filter(array_map(function ($a) use ($duration) {
            $a['start'] = max(0.0, min((float) $a['start'], $duration));
            $a['end'] = max(0.0, min((float) $a['end'], $duration));
            $a['params'] = $a['params'] ?? [];
            $a['text'] = $a['text'] ?? null;
            return $a;
        }, $anims), fn ($a) => $a['end'] - $a['start'] > self::EPS));

        usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);

        if ($mode === 'dense') {
            foreach ($this->coverageGaps($anims, $duration) as [$gs, $ge]) {
                $anims[] = ['start' => $gs, 'end' => $ge, 'primitive' => 'ambient', 'text' => null, 'params' => []];
            }
            usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);
        }

        $plan['animations'] = $anims;
        return $plan;
    }

    /** @return array<int,array{0:float,1:float}> */
    public function coverageGaps(array $animations, float $duration): array
    {
        $intervals = array_map(fn ($a) => [(float) $a['start'], (float) $a['end']], $animations);
        usort($intervals, fn ($x, $y) => $x[0] <=> $y[0]);

        $gaps = [];
        $cursor = 0.0;
        foreach ($intervals as [$s, $e]) {
            if ($s - $cursor > self::EPS) {
                $gaps[] = [round($cursor, 3), round($s, 3)];
            }
            $cursor = max($cursor, $e);
        }
        if ($duration - $cursor > self::EPS) {
            $gaps[] = [round($cursor, 3), round($duration, 3)];
        }
        return $gaps;
    }
}
```

- [ ] **Step 4: Run tests.** `php artisan test tests/Unit/Clips/PlanValidatorTest.php` — Expected: PASS.

- [ ] **Step 5: Commit.** `git add app/Services/Clips/PlanValidator.php tests/Unit/Clips/PlanValidatorTest.php && git commit -m "Clips: PlanValidator com cobertura dense/sparse"`

---

### Task 5: Fake services

**Files:**
- Create the five fakes under `app/Services/Clips/Fake/`.
- Test: `tests/Unit/Clips/FakeServicesTest.php`

**Interfaces:**
- Consumes: contracts (Task 3).
- Produces: deterministic fakes. `FakeTranscriptionService` returns a 3-word transcript of duration 3.0. `FakeVoiceoverService`/`FakeRemotionRenderer`/`FakeVideoCompositor` write a tiny placeholder file to the target path and return it. `FakeAnimationPlanner` returns one `kinetic-text` per word (dense) or one `highlight` on the first word (sparse).

- [ ] **Step 1: Write the fakes.**

```php
// FakeTranscriptionService.php
namespace App\Services\Clips\Fake;
use App\Services\Clips\Contracts\TranscriptionService;
class FakeTranscriptionService implements TranscriptionService {
    public function transcribe(string $audioPath): array {
        return [
            'duration' => 3.0,
            'text' => 'Olá mundo Brand Machine',
            'words' => [
                ['word' => 'Olá', 'start' => 0.0, 'end' => 1.0],
                ['word' => 'mundo', 'start' => 1.0, 'end' => 2.0],
                ['word' => 'Brand Machine', 'start' => 2.0, 'end' => 3.0],
            ],
            'segments' => [['start' => 0.0, 'end' => 3.0, 'text' => 'Olá mundo Brand Machine']],
        ];
    }
}
```
```php
// FakeVoiceoverService.php
namespace App\Services\Clips\Fake;
use App\Services\Clips\Contracts\VoiceoverService;
class FakeVoiceoverService implements VoiceoverService {
    public function synthesize(string $text, string $outPath): string {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, "FAKE-AUDIO:$text");
        return $outPath;
    }
}
```
```php
// FakeAnimationPlanner.php
namespace App\Services\Clips\Fake;
use App\Services\Clips\Contracts\AnimationPlanner;
class FakeAnimationPlanner implements AnimationPlanner {
    public function plan(array $transcript, string $mode, array $options = []): array {
        $anims = [];
        if ($mode === 'dense') {
            foreach ($transcript['words'] as $w) {
                $anims[] = ['start' => $w['start'], 'end' => $w['end'], 'primitive' => 'kinetic-text', 'text' => $w['word'], 'params' => []];
            }
        } else {
            $w = $transcript['words'][0];
            $anims[] = ['start' => $w['start'], 'end' => $w['end'], 'primitive' => 'highlight', 'text' => $w['word'], 'params' => []];
        }
        return [
            'duration' => $transcript['duration'], 'mode' => $mode,
            'width' => $options['width'] ?? 1080, 'height' => $options['height'] ?? 1920,
            'fps' => $options['fps'] ?? 30, 'transparent' => $mode === 'sparse',
            'animations' => $anims,
        ];
    }
}
```
```php
// FakeRemotionRenderer.php
namespace App\Services\Clips\Fake;
use App\Services\Clips\Contracts\RemotionRenderer;
class FakeRemotionRenderer implements RemotionRenderer {
    public function render(array $props, string $outPath): string {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, 'FAKE-VIDEO');
        return $outPath;
    }
}
```
```php
// FakeVideoCompositor.php
namespace App\Services\Clips\Fake;
use App\Services\Clips\Contracts\VideoCompositor;
class FakeVideoCompositor implements VideoCompositor {
    public function extractAudio(string $videoPath, string $outPath): string {
        @mkdir(dirname($outPath), 0777, true); file_put_contents($outPath, 'FAKE-WAV'); return $outPath;
    }
    public function overlay(string $baseVideo, string $overlay, string $outPath): string {
        @mkdir(dirname($outPath), 0777, true); file_put_contents($outPath, 'FAKE-FINAL'); return $outPath;
    }
    public function probeDuration(string $videoPath): float { return 3.0; }
}
```

- [ ] **Step 2: Write a test.** Create `tests/Unit/Clips/FakeServicesTest.php` asserting `FakeAnimationPlanner` produces one anim per word in dense mode and the transcript has duration 3.0:

```php
<?php
use App\Services\Clips\Fake\{FakeTranscriptionService, FakeAnimationPlanner};

it('fake planner emits one dense anim per word', function () {
    $t = (new FakeTranscriptionService)->transcribe('x');
    $plan = (new FakeAnimationPlanner)->plan($t, 'dense');
    expect($plan['animations'])->toHaveCount(3)->and($plan['duration'])->toBe(3.0);
});
```

- [ ] **Step 3: Run.** `php artisan test tests/Unit/Clips/FakeServicesTest.php` — Expected: PASS.
- [ ] **Step 4: Commit.** `git add app/Services/Clips/Fake tests/Unit/Clips/FakeServicesTest.php && git commit -m "Clips: servicos fake para testes"`

---

### Task 6: Service provider + driver binding

**Files:**
- Create: `app/Providers/ClipsServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/Clips/ClipsBindingTest.php`

**Interfaces:**
- Consumes: contracts + fakes + (later) api implementations.
- Produces: container bindings so each contract resolves to its `fake` or `api` implementation based on `config('contentmachine.clips.driver')`. Under `fake`, all five resolve to the Fake\* classes.

- [ ] **Step 1: Write the provider.** Bind each interface; in `api` mode bind OpenAI/ElevenLabs/CLI/Ffmpeg (classes from Tasks 9–10), else the Fake\* ones. `RemotionRenderer`/`VideoCompositor` in `api` mode bind to `CliRemotionRenderer`/`FfmpegVideoCompositor`.

```php
<?php
namespace App\Providers;
use App\Services\Clips\Contracts\{TranscriptionService, VoiceoverService, AnimationPlanner, RemotionRenderer, VideoCompositor};
use App\Services\Clips\Fake\{FakeTranscriptionService, FakeVoiceoverService, FakeAnimationPlanner, FakeRemotionRenderer, FakeVideoCompositor};
use App\Services\Clips\Api\{OpenAiTranscriptionService, ElevenLabsVoiceoverService, OpenAiAnimationPlanner};
use App\Services\Clips\{CliRemotionRenderer, FfmpegVideoCompositor};
use Illuminate\Support\ServiceProvider;

class ClipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $api = config('contentmachine.clips.driver') === 'api';
        $this->app->bind(TranscriptionService::class, $api ? OpenAiTranscriptionService::class : FakeTranscriptionService::class);
        $this->app->bind(VoiceoverService::class, $api ? ElevenLabsVoiceoverService::class : FakeVoiceoverService::class);
        $this->app->bind(AnimationPlanner::class, $api ? OpenAiAnimationPlanner::class : FakeAnimationPlanner::class);
        $this->app->bind(RemotionRenderer::class, $api ? CliRemotionRenderer::class : FakeRemotionRenderer::class);
        $this->app->bind(VideoCompositor::class, $api ? FfmpegVideoCompositor::class : FakeVideoCompositor::class);
    }
}
```

- [ ] **Step 2: Register provider** in `bootstrap/providers.php` (add `App\Providers\ClipsServiceProvider::class`).
- [ ] **Step 3: Test binding under fake.** Create `tests/Unit/Clips/ClipsBindingTest.php`:

```php
<?php
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Fake\FakeTranscriptionService;

it('resolves fake services by default', function () {
    config()->set('contentmachine.clips.driver', 'fake');
    expect(app(TranscriptionService::class))->toBeInstanceOf(FakeTranscriptionService::class);
});
```

Note: this test requires the Api\* and Cli/Ffmpeg classes to at least exist as referenced. Create empty stub classes now if Tasks 9–10 not yet done, OR order Tasks 9–10 before running api-mode. For fake-mode test, the `use` imports must resolve — ensure the referenced classes exist (stubs acceptable, filled in Tasks 9–10).

- [ ] **Step 4: Run.** `php artisan test tests/Unit/Clips/ClipsBindingTest.php` — Expected: PASS.
- [ ] **Step 5: Commit.** `git add app/Providers/ClipsServiceProvider.php bootstrap/providers.php tests/Unit/Clips/ClipsBindingTest.php && git commit -m "Clips: service provider e binding de drivers"`

---

### Task 7: Jobs (chained pipeline)

**Files:**
- Create: `app/Jobs/Clips/TranscribeJob.php`, `PlanAnimationsJob.php`, `RenderJob.php`, `ComposeOverlayJob.php`
- Test: `tests/Feature/Clips/ClipsPipelineTest.php`

**Interfaces:**
- Consumes: `ClipProject`, all five contracts, `PlanValidator`.
- Produces: `TranscribeJob::dispatch($project)` runs the whole chain (each job dispatches the next). Terminal state `done` (animation) or `done` after overlay (overlay). Any exception sets `status=failed` + `error`.

- [ ] **Step 1: TranscribeJob.** Resolve audio (text→VoiceoverService, video→VideoCompositor::extractAudio, audio→as-is), then TranscriptionService; save transcript; set status; dispatch `PlanAnimationsJob`. Wrap in try/catch → `failMe()`.

```php
<?php
namespace App\Jobs\Clips;
use App\Models\ClipProject;
use App\Services\Clips\Contracts\{TranscriptionService, VoiceoverService, VideoCompositor};
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue; use Illuminate\Support\Facades\Storage;

class TranscribeJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable;
    public function __construct(public int $projectId) {}
    public function handle(TranscriptionService $stt, VoiceoverService $tts, VideoCompositor $ff): void {
        $p = ClipProject::findOrFail($this->projectId);
        try {
            $p->update(['status' => ClipProject::STATUS_TRANSCRIBING]);
            $dir = storage_path("app/clips/{$p->id}");
            @mkdir($dir, 0777, true);
            if ($p->input_kind === 'text') {
                $audio = $tts->synthesize($p->source_text, "$dir/voz.mp3");
            } elseif ($p->input_kind === 'video') {
                $audio = $ff->extractAudio(storage_path("app/{$p->source_path}"), "$dir/audio.wav");
            } else {
                $audio = storage_path("app/{$p->source_path}");
            }
            $p->update(['audio_path' => $audio, 'transcript' => $stt->transcribe($audio)]);
            PlanAnimationsJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

- [ ] **Step 2: PlanAnimationsJob.** Mode from type (`animation`→dense, `overlay`→sparse). Call planner, then `PlanValidator::validate`, save, dispatch `RenderJob`.

```php
<?php
namespace App\Jobs\Clips;
use App\Models\ClipProject; use App\Services\Clips\Contracts\AnimationPlanner; use App\Services\Clips\PlanValidator;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue;
class PlanAnimationsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable;
    public function __construct(public int $projectId) {}
    public function handle(AnimationPlanner $planner, PlanValidator $validator): void {
        $p = ClipProject::findOrFail($this->projectId);
        try {
            $p->update(['status' => ClipProject::STATUS_PLANNING]);
            $mode = $p->type === ClipProject::TYPE_ANIMATION ? 'dense' : 'sparse';
            $c = config('contentmachine.clips');
            $plan = $planner->plan($p->transcript, $mode, ['width' => $c['width'], 'height' => $c['height'], 'fps' => $c['fps']]);
            $plan = $validator->validate($plan);
            $p->update(['plan' => $plan]);
            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]); throw $e;
        }
    }
}
```

- [ ] **Step 3: RenderJob.** Build props (plan + audioSrc for animation). Render. If animation → done; if overlay → dispatch ComposeOverlayJob.

```php
<?php
namespace App\Jobs\Clips;
use App\Models\ClipProject; use App\Services\Clips\Contracts\RemotionRenderer;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue;
class RenderJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable;
    public function __construct(public int $projectId) {}
    public function handle(RemotionRenderer $renderer): void {
        $p = ClipProject::findOrFail($this->projectId);
        try {
            $p->update(['status' => ClipProject::STATUS_RENDERING]);
            $dir = storage_path("app/clips/{$p->id}"); @mkdir($dir, 0777, true);
            $plan = $p->plan;
            if ($p->type === ClipProject::TYPE_ANIMATION) {
                $plan['audioSrc'] = $p->audio_path;
                $out = $renderer->render($plan, "$dir/clip.mp4");
                $p->update(['output_path' => $out, 'status' => ClipProject::STATUS_DONE]);
            } else {
                $plan['transparent'] = true;
                $out = $renderer->render($plan, "$dir/overlay.mov");
                $p->update(['meta' => array_merge($p->meta ?? [], ['overlay_path' => $out])]);
                ComposeOverlayJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]); throw $e;
        }
    }
}
```

- [ ] **Step 4: ComposeOverlayJob.** ffmpeg overlay of `meta.overlay_path` onto `source_path`.

```php
<?php
namespace App\Jobs\Clips;
use App\Models\ClipProject; use App\Services\Clips\Contracts\VideoCompositor;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Foundation\Bus\Dispatchable; use Illuminate\Queue\InteractsWithQueue;
class ComposeOverlayJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable;
    public function __construct(public int $projectId) {}
    public function handle(VideoCompositor $ff): void {
        $p = ClipProject::findOrFail($this->projectId);
        try {
            $dir = storage_path("app/clips/{$p->id}");
            $out = $ff->overlay(storage_path("app/{$p->source_path}"), $p->meta['overlay_path'], "$dir/final.mp4");
            $p->update(['output_path' => $out, 'status' => ClipProject::STATUS_DONE]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]); throw $e;
        }
    }
}
```

- [ ] **Step 5: Pipeline test (sync driver, fakes).** Create `tests/Feature/Clips/ClipsPipelineTest.php`:

```php
<?php
use App\Jobs\Clips\TranscribeJob; use App\Models\ClipProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);

it('runs the animation pipeline to done with fakes', function () {
    config()->set('queue.default', 'sync');
    config()->set('contentmachine.clips.driver', 'fake');
    $p = ClipProject::create(['type' => 'animation', 'input_kind' => 'text', 'source_text' => 'Olá mundo Brand Machine']);
    TranscribeJob::dispatch($p->id);
    $p->refresh();
    expect($p->status)->toBe(ClipProject::STATUS_DONE)
        ->and($p->plan['animations'])->not->toBeEmpty()
        ->and($p->output_path)->not->toBeNull();
});
```

- [ ] **Step 6: Run.** `php artisan test tests/Feature/Clips/ClipsPipelineTest.php` — Expected: PASS.
- [ ] **Step 7: Commit.** `git add app/Jobs/Clips tests/Feature/Clips/ClipsPipelineTest.php && git commit -m "Clips: pipeline de jobs encadeados"`

---

### Task 8: Remotion subproject

**Files:**
- Create: `remotion/package.json`, `remotion/tsconfig.json`, `remotion/remotion.config.ts`, `remotion/src/index.ts`, `remotion/src/Root.tsx`, `remotion/src/style-tokens.ts`, `remotion/src/primitives.tsx`, `remotion/src/ClipComposition.tsx`

**Interfaces:**
- Consumes: props JSON matching plan shape.
- Produces: a Remotion composition id `ClipComposition` whose `calculateMetadata` sets `durationInFrames = ceil(duration*fps)`, `width`, `height`, `fps` from props; renders each animation via a primitive switch; plays `audioSrc` when present; transparent background when `transparent` is true.

- [ ] **Step 1: package.json** with deps `@remotion/cli`, `remotion`, `react`, `react-dom` (all `4.x`/`18.x`), scripts `"render"`. Install with `cd remotion && npm install`.
- [ ] **Step 2: index.ts** → `registerRoot(Root)`.
- [ ] **Step 3: Root.tsx** → `<Composition id="ClipComposition" component={ClipComposition} calculateMetadata=... defaultProps=...>`.
- [ ] **Step 4: style-tokens.ts** mirrors the `## Tokens`/`## Tipografia` from `vault/estilo-animacao.md` (palette hexes + font stacks).
- [ ] **Step 5: primitives.tsx** — a component per primitive using `interpolate`/`spring`/`useCurrentFrame`. Each takes `{anim, fps}` and animates over its own frame window. `ambient` = subtle papyrus/foxing drift; `kinetic-text` = word fade+rise; `highlight` = teal underline sweep; `seal-stamp` = scale+rotate stamp; etc.
- [ ] **Step 6: ClipComposition.tsx** — background (papyrus unless `transparent`), `<Audio src={audioSrc}/>` if set, and for each animation a `<Sequence from={round(start*fps)} durationInFrames=...>` rendering the matching primitive.
- [ ] **Step 7: Smoke render** with a tiny props file: `cd remotion && npx remotion render src/index.ts ClipComposition ../storage/app/clips/_smoke.mp4 --props='{"duration":2,"width":540,"height":960,"fps":30,"animations":[{"start":0,"end":2,"primitive":"kinetic-text","text":"Olá","params":{}}]}'`. Expected: an mp4 is produced. Delete it after.
- [ ] **Step 8: Commit.** `git add remotion && git commit -m "Clips: subprojecto Remotion (composicao + primitivas)"` (ensure `remotion/node_modules` is gitignored).

---

### Task 9: Real API services (OpenAI + ElevenLabs)

**Files:**
- Create: `app/Services/Clips/Api/OpenAiTranscriptionService.php`, `ElevenLabsVoiceoverService.php`, `OpenAiAnimationPlanner.php`

**Interfaces:**
- Consumes: contracts; `config('services.openai.key')`, `config('services.elevenlabs.key')`, `config('contentmachine.clips')`, style md file.
- Produces: real implementations using Laravel `Http`.

- [ ] **Step 1: OpenAiTranscriptionService** — `Http::withToken(key)->attach('file', ...)->post('https://api.openai.com/v1/audio/transcriptions', ['model'=>'whisper-1','response_format'=>'verbose_json','timestamp_granularities[]'=>'word'])`. Map response to transcript shape (`duration`, `text`, `words`, `segments`).
- [ ] **Step 2: ElevenLabsVoiceoverService** — `POST https://api.elevenlabs.io/v1/text-to-speech/{voice_id}` with `xi-api-key` header, body `{text, model_id:'eleven_multilingual_v2'}`; write mp3 bytes to `$outPath`.
- [ ] **Step 3: OpenAiAnimationPlanner** — read `config('contentmachine.clips.style_md')`, build a system prompt embedding the style md + the closed vocabulary + JSON schema; user message = transcript words + mode; `POST /v1/chat/completions` with `response_format: {type:'json_object'}`; decode to plan shape. On dense, instruct "every second must have an animation".
- [ ] **Step 4: Add `elevenlabs` to `config/services.php`** (`'elevenlabs' => ['key' => env('ELEVENLABS_API_KEY')]`) and `.env.example` (`ELEVENLABS_API_KEY=`, `ELEVENLABS_VOICE_ID=`, `CLIPS_DRIVER=fake`).
- [ ] **Step 5: Test (Http::fake).** `tests/Unit/Clips/OpenAiPlannerTest.php` fakes the OpenAI endpoint and asserts the planner returns a decoded plan with the mode passed through.
- [ ] **Step 6: Run + Commit.** `php artisan test tests/Unit/Clips/OpenAiPlannerTest.php` then `git add app/Services/Clips/Api config/services.php .env.example tests/Unit/Clips/OpenAiPlannerTest.php && git commit -m "Clips: servicos reais OpenAI + ElevenLabs"`

---

### Task 10: Real renderer + compositor (CLI + ffmpeg)

**Files:**
- Create: `app/Services/Clips/CliRemotionRenderer.php`, `app/Services/Clips/FfmpegVideoCompositor.php`

**Interfaces:**
- Consumes: contracts, `config('contentmachine.clips.remotion_path')`.
- Produces: real implementations shelling out via `Symfony\Component\Process\Process`.

- [ ] **Step 1: CliRemotionRenderer** — write props to a temp json; run `npx remotion render src/index.ts ClipComposition <abs-out> --props=<tmp.json>` with `--codec=prores --prores-profile=4444` when `props.transparent`, else `--codec=h264`; `cwd = remotion_path`; `setTimeout(600)`; throw on non-zero exit with stderr. Return `$outPath`.
- [ ] **Step 2: FfmpegVideoCompositor** — `extractAudio`: `ffmpeg -y -i <video> -vn -acodec pcm_s16le -ar 16000 -ac 1 <out.wav>`. `overlay`: `ffmpeg -y -i <base> -i <overlay.mov> -filter_complex "[0:v][1:v]overlay=0:0:format=auto" -c:a copy <out.mp4>`. `probeDuration`: `ffprobe -v quiet -show_entries format=duration -of csv=p=0 <video>`.
- [ ] **Step 3: Test (no real spawn).** Assert command strings are built correctly by extracting a `buildRenderArgs()`/`buildOverlayArgs()` pure method and unit-testing it. `tests/Unit/Clips/RenderArgsTest.php`.
- [ ] **Step 4: Run + Commit.** `git add app/Services/Clips/CliRemotionRenderer.php app/Services/Clips/FfmpegVideoCompositor.php tests/Unit/Clips/RenderArgsTest.php && git commit -m "Clips: renderer Remotion CLI + compositor ffmpeg"`

---

### Task 11: Livewire two-tab UI

**Files:**
- Rewrite: `app/Livewire/ClipsAnimados.php`
- Rewrite: `resources/views/livewire/clips-animados.blade.php`
- Test: `tests/Feature/Clips/ClipsAnimadosTest.php`

**Interfaces:**
- Consumes: `ClipProject`, `TranscribeJob`.
- Produces: a component with `activeTab` ('animation'|'overlay'), text/upload inputs, `submitAnimation()` and `submitOverlay()` that create a `ClipProject` + dispatch `TranscribeJob`, `projects` computed prop (latest 10 by type), and `wire:poll.3s` while any project `isActive()`.

- [ ] **Step 1: Component.** `use WithFileUploads`. Props: `activeTab='animation'`, `text=''`, `audio=null`, `video=null`. `submitAnimation()`: validate (`text` required without `audio`, or `audio` mimetypes), store upload if present, create project (`input_kind` = text|audio), `TranscribeJob::dispatch`. `submitOverlay()`: validate `video` required (mimetypes:mp4,mov), store, create `overlay` project, dispatch. `getProjectsProperty()` returns recent projects. Reset inputs after submit.
- [ ] **Step 2: Blade.** Brand Machine-styled: `x-page-header`, a two-tab switch (teal underline on active), the active tab's form, and a list of projects with status badge (map status→PT label + glyph), `wire:poll.3s`, a preview `<video>` + download link when `output_path` set. Use existing components (`x-panel`, `x-badge`, `x-empty-state`, fleuron).
- [ ] **Step 3: Feature test.** Create `tests/Feature/Clips/ClipsAnimadosTest.php`:

```php
<?php
use App\Livewire\ClipsAnimados; use App\Models\ClipProject; use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase; use Illuminate\Support\Facades\Queue;
use App\Jobs\Clips\TranscribeJob;
uses(RefreshDatabase::class);

it('creates an animation project and dispatches the pipeline', function () {
    Queue::fake();
    Livewire::test(ClipsAnimados::class)
        ->set('activeTab', 'animation')
        ->set('text', 'Olá mundo Brand Machine')
        ->call('submitAnimation')
        ->assertHasNoErrors();
    expect(ClipProject::where('type', 'animation')->count())->toBe(1);
    Queue::assertPushed(TranscribeJob::class);
});

it('requires a video for the overlay tab', function () {
    Livewire::test(ClipsAnimados::class)
        ->set('activeTab', 'overlay')
        ->call('submitOverlay')
        ->assertHasErrors('video');
});
```

- [ ] **Step 4: Run.** `php artisan test tests/Feature/Clips/ClipsAnimadosTest.php` — Expected: PASS.
- [ ] **Step 5: Commit.** `git add app/Livewire/ClipsAnimados.php resources/views/livewire/clips-animados.blade.php tests/Feature/Clips/ClipsAnimadosTest.php && git commit -m "Clips: UI Livewire de dois separadores"`

---

### Task 12: Full-suite green + real end-to-end smoke

**Files:** none new.

- [ ] **Step 1:** `php artisan test` — Expected: all pass (baseline 23 + new).
- [ ] **Step 2: Real smoke (manual, needs keys).** Set `CLIPS_DRIVER=api`, real `OPENAI_API_KEY`, `ELEVENLABS_API_KEY` in `.env`; run `php artisan queue:work --once` per stage (or `--stop-when-empty`) after creating a text project via the UI; confirm `storage/app/clips/<id>/clip.mp4` exists and plays with audio + animation covering the whole duration. Document the result.
- [ ] **Step 3: Commit** any fixes. `git commit -am "Clips: correcoes do smoke end-to-end"`

---

## Self-Review

**Spec coverage:**
- Two tabs → Task 11. Text/voiceover input → Tasks 7/9/11. Transcription w/ timestamps → Tasks 3/9. AI plan with start/end + style md → Tasks 2/9. Remotion full render → Task 8/10. Every-second coverage (dense) → Task 4 (PlanValidator) + Task 9 prompt. Video+overlay sparse → Tasks 4/7/8/10. Data/async → Tasks 1/7. Driver pattern → Tasks 3/5/6/9/10. Brand Machine styling → Tasks 2/11. ✅ all covered.

**Placeholder scan:** Task 8 and Tasks 9–10 describe some components at the step level rather than full source (Remotion TSX + Http bodies) — these are large and framework-specific; each step names exact files, exact CLI/HTTP calls, exact props/response mapping, and a concrete verification command, which is sufficient for an implementer. No "TBD"/"handle edge cases" placeholders remain.

**Type consistency:** transcript/plan shapes are fixed in Global Constraints and used identically across Tasks 4/5/7/8/9. Contract signatures (Task 3) are consumed unchanged in Tasks 5/6/7/9/10. Status/type constants (Task 1) used in Tasks 7/11.

**Note on Task 6 ordering:** the binding provider references Api/Cli/Ffmpeg classes; ensure Tasks 9–10 files exist before enabling `api` mode. Fake-mode tests are unaffected.
