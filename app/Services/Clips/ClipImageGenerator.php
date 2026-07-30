<?php

namespace App\Services\Clips;

use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates an on-brand image for an animated clip from a text prompt, using
 * kie.ai's Nano Banana (Google Gemini image) model. Produces a clip-image entry
 * ({id, path, description, tone, transparent}) matching uploaded images, so the
 * rest of the pipeline (RenderJob::resolveImages → Remotion staging) is unchanged.
 */
class ClipImageGenerator
{
    public function __construct(private KieClient $kie) {}

    private const OUT_KEY = 'clips.images_unavailable';

    public function configured(): bool
    {
        return $this->kie->configurado();
    }

    /**
     * Whether images can actually be generated right now: configured AND not
     * recently rejected for credits. A 402 flips this off for a while so the
     * planner is told not to make image scenes (uses provided images / other
     * content instead) — it re-checks after the window.
     */
    public function available(): bool
    {
        return $this->configured() && ! Cache::get(self::OUT_KEY, false);
    }

    /**
     * @return array{id:string,path:string,description:string,tone:string,transparent:bool,generated:bool}|null
     */
    public function generate(string $prompt, string $style = ''): ?array
    {
        $prompt = trim($prompt);
        if (! $this->configured() || $prompt === '') {
            return null;
        }

        try {
            $styled = trim(($style !== '' ? $style.' — ' : '').$prompt);
            $aspect = (string) config('contentmachine.clips.image_aspect', '9:16');

            $url = $this->kie->generate($styled, $aspect);
            $bytes = Http::timeout(60)->get($url)->body();
            if ($bytes === '') {
                return null;
            }

            $disk = Storage::disk(config('contentmachine.clips.disk'));
            $rel = 'clips/generated/'.Str::lower(Str::random(12)).'.png';
            $disk->put($rel, $bytes);
            $abs = $disk->path($rel);

            return [
                'id' => 'gen_'.substr(md5($rel), 0, 8),
                'path' => $rel,
                'description' => Str::limit($prompt, 200),
                'tone' => ImageProbe::tone($abs),
                'transparent' => ImageProbe::hasAlpha($abs),
                'generated' => true,
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Out of credits / rate-limited → mark images unavailable for a while
            // so we stop trying and plan around it.
            if (str_contains($msg, '402') || stripos($msg, 'insufficient') !== false || stripos($msg, 'credit') !== false) {
                Cache::put(self::OUT_KEY, true, now()->addMinutes(30));
            }
            Log::warning('Clip image generation failed: '.$msg);

            return null;
        }
    }
}
