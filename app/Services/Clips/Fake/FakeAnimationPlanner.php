<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\AnimationPlanner;

class FakeAnimationPlanner implements AnimationPlanner
{
    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $duration = (float) ($transcript['duration'] ?? 0.0);
        $words = $transcript['words'] ?? [];
        $firstWord = $words[0]['word'] ?? null;
        $overlay = (bool) ($options['overlay'] ?? false);
        $mid = $duration / 2;

        $scenes = [
            [
                'start' => 0.0, 'end' => $mid,
                'background' => 'papyrus', 'transitionIn' => 'cut', 'transitionOut' => 'cut',
                'karaoke' => true, 'punchWord' => $firstWord,
                'present' => $overlay ? 'video' : null,
                'layers' => $overlay ? [] : [['type' => 'kinetic-text', 'text' => $transcript['text'] ?? '', 'params' => []]],
            ],
            [
                'start' => $mid, 'end' => $duration,
                'background' => 'vellum', 'transitionIn' => 'crossfade', 'transitionOut' => 'cut',
                'karaoke' => true, 'punchWord' => null,
                'present' => $overlay ? 'over' : null,
                'layers' => [['type' => 'timeline', 'text' => null, 'params' => [
                    'items' => [['label' => 'A'], ['label' => 'B', 'highlight' => true]],
                ]]],
            ],
        ];

        return [
            'duration' => $duration,
            'mode' => $mode,
            'width' => $options['width'] ?? 1080,
            'height' => $options['height'] ?? 1920,
            'fps' => $options['fps'] ?? 30,
            'transparent' => false,
            'scenes' => $scenes,
        ];
    }
}
