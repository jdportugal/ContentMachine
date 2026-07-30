<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\TranscriptionService;

class FakeTranscriptionService implements TranscriptionService
{
    public function transcribe(string $audioPath): array
    {
        return [
            'duration' => 3.0,
            'text' => 'Olá mundo Brand Machine',
            'words' => [
                ['word' => 'Olá', 'start' => 0.0, 'end' => 1.0],
                ['word' => 'mundo', 'start' => 1.0, 'end' => 2.0],
                ['word' => 'Brand Machine', 'start' => 2.0, 'end' => 3.0],
            ],
            'segments' => [
                ['start' => 0.0, 'end' => 3.0, 'text' => 'Olá mundo Brand Machine'],
            ],
        ];
    }
}
