<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\ResearchService;

class FakeResearchService implements ResearchService
{
    public function research(array $transcript): array
    {
        return [
            'topic' => 'Tópico de teste',
            'summary' => 'Resumo simulado.',
            'timeline' => [['label' => 'A', 'sublabel' => '2020'], ['label' => 'B', 'sublabel' => '2024']],
            'stats' => [['label' => 'Exemplo', 'value' => 42, 'unit' => '%']],
            'comparisons' => [],
            'keyPoints' => ['ponto um', 'ponto dois'],
            'sources' => [],
        ];
    }
}
