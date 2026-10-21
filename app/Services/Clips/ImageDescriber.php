<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Projects\ProjectLanguage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the description of every clip image that has none, by LOOKING at it.
 *
 * The planner only ever sees images as text (id + description), so a bulk upload
 * with no descriptions would give it nothing to place them by. One vision pass per
 * batch fills them in — "kitchen with island, bright", "portrait of a man in a
 * suit" — before planning. A description the user typed is never touched.
 */
class ImageDescriber
{
    use RunsClaudeCli;

    /** Images per model call — each is read as pixels, so keep a batch modest. */
    private const BATCH = 10;

    /**
     * @param  array<int,array<string,mixed>>  $images  clip image entries {id, path, description, …}
     * @param  string  $context  what the clip is about, so descriptions use its vocabulary
     * @return array<int,array<string,mixed>> the same entries, empty descriptions filled where possible
     */
    public function describe(array $images, string $context = ''): array
    {
        $disk = Storage::disk(config('contentmachine.clips.disk'));

        $todo = [];
        foreach ($images as $i => $img) {
            if (trim((string) ($img['description'] ?? '')) !== '' || ! empty($img['video']) || empty($img['path'])) {
                continue;
            }
            $abs = $disk->path((string) $img['path']);
            if (is_file($abs)) {
                $todo[$i] = $abs;
            }
        }

        foreach (array_chunk($todo, self::BATCH, preserve_keys: true) as $batch) {
            $found = $this->batch($batch, $images, $context);
            foreach ($batch as $i => $_) {
                $images[$i]['description'] = $found[$images[$i]['id']] ?? '';
            }
        }

        return $images;
    }

    /**
     * @param  array<int,string>  $batch  entry index => absolute path
     * @param  array<int,array<string,mixed>>  $images
     * @return array<string,string> id => description
     */
    private function batch(array $batch, array $images, string $context): array
    {
        $lista = [];
        foreach ($batch as $i => $abs) {
            $lista[] = '- '.$images[$i]['id'].': '.$abs;
        }
        $language = ProjectLanguage::name();
        $sobre = trim($context) !== '' ? "\nThe video is about:\n".mb_substr($context, 0, 1500)."\n" : '';

        $prompt = "Describe each image below so a video editor who cannot see them can place them in a short video.\n"
            .$sobre
            ."\nImages, in order (id: file):\n".implode("\n", $lista)."\n\n"
            ."For each: ONE line, max 20 words, in {$language}. Say what it IS first (e.g. kitchen, living room,\n"
            ."bedroom, bathroom, facade, balcony view, floor plan, logo, portrait of a person), then what stands out.\n"
            ."A headshot/portrait of a person is almost certainly the presenter or agent — say so.\n\n"
            .'Return ONLY JSON, no markdown: {"<id>": "<description>", ...}';

        try {
            $envelope = $this->runClaude($prompt, null, [
                'images' => array_values($batch),
                'maxTurns' => count($batch) + 3,   // one Read per image on the CLI, then the answer
                'timeout' => 300,
            ]);
            $text = (string) ($envelope['result'] ?? '');
            if (preg_match('/\{.*\}/s', $text, $m) && is_array($data = json_decode($m[0], true))) {
                return array_map(fn ($d) => trim((string) (is_scalar($d) ? $d : '')), $data);
            }
            Log::warning('Image description returned no JSON: '.mb_substr($text, 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Image description failed: '.$e->getMessage());
        }

        return [];
    }
}
