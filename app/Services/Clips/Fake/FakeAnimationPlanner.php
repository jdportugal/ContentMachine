<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\AnimationPlanner;

class FakeAnimationPlanner implements AnimationPlanner
{
    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $anims = [];

        if ($mode === 'dense') {
            foreach ($transcript['words'] as $w) {
                $anims[] = [
                    'start' => $w['start'], 'end' => $w['end'],
                    'primitive' => 'kinetic-text', 'text' => $w['word'], 'params' => [],
                ];
            }
        } else {
            $w = $transcript['words'][0];
            $anims[] = [
                'start' => $w['start'], 'end' => $w['end'],
                'primitive' => 'highlight', 'text' => $w['word'], 'params' => [],
            ];
        }

        return [
            'duration' => $transcript['duration'],
            'mode' => $mode,
            'width' => $options['width'] ?? 1080,
            'height' => $options['height'] ?? 1920,
            'fps' => $options['fps'] ?? 30,
            'transparent' => $mode === 'sparse',
            'animations' => $anims,
        ];
    }
}
