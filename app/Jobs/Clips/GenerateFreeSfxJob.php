<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\FreeSfxGenerator;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Symfony\Component\Process\Process;

/**
 * "Free SFX": swap this clip's stock animations for components the model writes
 * from the scenes themselves. Runs between finalisation and the render.
 *
 * Failure is never fatal. A component that breaks the design-system guard, or that
 * does not compile, is dropped and its scene keeps the layer the planner gave it —
 * so the worst case is an ordinary clip, never a broken render. The clip goes to
 * RenderJob either way.
 */
class GenerateFreeSfxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    /** Several LLM calls at up to llm_timeout each, plus a compile pass. */
    public int $timeout = 2400;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, FreeSfxGenerator $generator, EffectStore $effects, EffectLibrary $library, SceneVisualFiller $filler): void
    {
        $this->activateProject();

        $p = $store->find($this->projectId);
        if (! $p) {
            return;
        }

        try {
            $this->apagarAnteriores($p, $effects);

            $plan = $p->plan ?? [];
            $alvos = $this->cenasElegiveis($plan);

            if ($alvos !== []) {
                $pedidos = array_map(fn (array $a) => [
                    'index' => $a['scene'],
                    'start' => $a['start'],
                    'end' => $a['end'],
                    'text' => $a['text'] !== '' ? $a['text'] : $filler->spokenText($p->transcript ?? [], $a['start'], $a['end']),
                    'hint' => $a['hint'],
                ], $alvos);

                $written = $generator->generate(
                    $pedidos,
                    (string) ($p->meta['suggested']['title'] ?? $p->title ?? ''),
                    ['width' => (int) ($plan['width'] ?? 1080), 'height' => (int) ($plan['height'] ?? 1920)],
                    $p->meta['research'] ?? [],
                );

                $plan = $this->aplicar($p, $plan, $alvos, $written, $effects, $library);
                $p->update(['plan' => $plan]);
            }
        } catch (\Throwable $e) {
            // A clip that cannot get bespoke animations still gets its normal ones.
            report($e);
        }

        RenderJob::dispatch($p->id);
    }

    /**
     * Scenes a bespoke component may take over: the scene's main (non-ambient)
     * layer, when it is not already showing media. Image scenes keep their
     * image-reveal — the picture IS the visual, and the user chose or paid for it —
     * and a `present: video` scene has no layers on screen at all.
     *
     * @param  array<string,mixed>  $plan
     * @return array<int,array{scene:int,layer:int,start:float,end:float,text:string,hint:string}>
     */
    private function cenasElegiveis(array $plan): array
    {
        $out = [];
        foreach ($plan['scenes'] ?? [] as $si => $scene) {
            if (! is_array($scene) || ($scene['present'] ?? null) === 'video') {
                continue;
            }
            foreach ($scene['layers'] ?? [] as $li => $layer) {
                if (! is_array($layer) || ($layer['type'] ?? '') === 'ambient') {
                    continue;
                }
                $params = is_array($layer['params'] ?? null) ? $layer['params'] : [];
                if (! empty($params['src']) || ! empty($params['generate']) || ! empty($params['site'])) {
                    break; // this scene shows media — leave it alone
                }
                $out[] = [
                    'scene' => (int) $si,
                    'layer' => (int) $li,
                    'start' => (float) ($scene['start'] ?? 0),
                    'end' => (float) ($scene['end'] ?? 0),
                    'text' => trim((string) ($layer['text'] ?? '')),
                    'hint' => (string) ($layer['type'] ?? ''),
                ];
                break; // one bespoke component per scene
            }
        }

        return $out;
    }

    /**
     * Register the written components and point their scenes at them.
     *
     * Everything is written first, then compiled ONCE — a single tsc pass names
     * every broken file, which is far cheaper than test-rendering each component in
     * isolation the way GenerateEffectJob does for a library SFX.
     *
     * @param  array<string,mixed>  $plan
     * @param  array<int,array{scene:int,layer:int,start:float,end:float,text:string,hint:string}>  $alvos
     * @param  array<int,array<string,mixed>>  $written
     * @return array<string,mixed>
     */
    private function aplicar(ClipRecord $p, array $plan, array $alvos, array $written, EffectStore $effects, EffectLibrary $library): array
    {
        $porCena = collect($alvos)->keyBy('scene');
        $prefixo = 'fx-'.substr(md5($p->id), 0, 6);
        $criados = [];

        foreach (array_values($written) as $n => $c) {
            $alvo = $porCena->get((int) $c['index']);
            if ($alvo === null) {
                continue;
            }
            $slug = $prefixo.'-'.($n + 1);
            $effects->create([
                'slug' => $slug,
                'clip_id' => $p->id,          // clip-scoped: never in the SFX library
                'enabled' => false,           // registered for the renderer, invisible to the planner
                'status' => EffectRecord::STATUS_ACTIVE,
                'display_name' => $c['displayName'],
                'description' => $c['description'],
                'param_schema' => $c['paramSchema'],
                'prompt' => 'Free SFX — written for this clip’s scene '.((int) $c['index'] + 1).'.',
                'sample_text' => $alvo['text'],
                'sample_params' => $c['params'],
                'tsx' => $c['tsx'],
            ]);
            $criados[$slug] = ['alvo' => $alvo, 'params' => $c['params']];
        }

        if ($criados === []) {
            return $plan;
        }

        $library->syncFilesystem();
        foreach ($this->naoCompilam(array_keys($criados)) as $slug) {
            if ($rec = $effects->records()->firstWhere('slug', $slug)) {
                $library->remove($rec);   // drops the record AND its .tsx
            }
            unset($criados[$slug]);
        }
        $library->syncFilesystem();

        foreach ($criados as $slug => $c) {
            $si = $c['alvo']['scene'];
            $li = $c['alvo']['layer'];
            if (! isset($plan['scenes'][$si]['layers'][$li])) {
                continue;
            }
            $plan['scenes'][$si]['layers'][$li] = [
                'type' => $slug,
                'text' => $c['alvo']['text'],
                'params' => $c['params'],
            ];
        }

        return $plan;
    }

    /**
     * Which of these slugs fail to compile. One `tsc --noEmit` over the whole
     * Remotion project (~1s, and clean today), reading back only the errors whose
     * file is one of OURS — a pre-existing error elsewhere must not condemn a
     * component that is fine.
     *
     * @param  string[]  $slugs
     * @return string[]
     */
    private function naoCompilam(array $slugs): array
    {
        $dir = (string) config('contentmachine.clips.remotion_path');
        $tsc = $dir.'/node_modules/.bin/tsc';
        if (! is_file($tsc)) {
            return []; // no compiler available — the render itself stays the gate
        }

        $process = new Process([$tsc, '--noEmit', '-p', 'tsconfig.json'], $dir);
        $process->setTimeout(300);
        $process->run();
        if ($process->isSuccessful()) {
            return [];
        }

        $saida = $process->getOutput().$process->getErrorOutput();

        return array_values(array_filter(
            $slugs,
            fn (string $slug) => str_contains($saida, "effects/{$slug}.tsx"),
        ));
    }

    /** Re-running Free SFX (a re-plan, a retry) must not leave the old components behind. */
    private function apagarAnteriores(ClipRecord $p, EffectStore $effects): void
    {
        $library = app(EffectLibrary::class);
        foreach ($effects->forClip($p->id) as $old) {
            $library->remove($old);
        }
    }
}
