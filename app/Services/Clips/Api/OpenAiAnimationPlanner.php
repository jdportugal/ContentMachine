<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\AnimationPlanner;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiAnimationPlanner implements AnimationPlanner
{
    use BuildsAnimationPrompt;

    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY missing for the planner.');
        }

        $response = Http::withToken($key)
            ->timeout(180)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('contentmachine.clips.openai_model'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.5,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($mode, (bool) ($options['overlay'] ?? false), $options['presents'] ?? [], (bool) ($options['can_generate_images'] ?? true))],
                    ['role' => 'user', 'content' => $this->userPrompt($transcript, $mode, (float) ($transcript['duration'] ?? 0.0), $options['facts'] ?? [], $options['images'] ?? [])],
                ],
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $decoded = $this->extractJson($content);

        return $this->envelope($transcript, $mode, $options, $decoded['scenes'] ?? [], is_string($decoded['background'] ?? null) ? $decoded['background'] : null);
    }
}
