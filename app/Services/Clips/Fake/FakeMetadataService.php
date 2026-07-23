<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\MetadataService;
use Illuminate\Support\Str;

class FakeMetadataService implements MetadataService
{
    public function suggest(array $transcript): array
    {
        $text = trim((string) ($transcript['text'] ?? ''));

        return [
            'title' => $text === '' ? 'Clip de teste' : Str::limit($text, 60),
            'description' => 'Descrição simulada para desenvolvimento.',
            'tags' => ['exemplo', 'teste', 'clip'],
        ];
    }
}
