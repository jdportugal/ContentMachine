<?php

namespace Tests\Unit;

use App\Services\Aggregation\AggregatedItem;
use App\Services\Aggregation\LlmClient;
use App\Services\Aggregation\TopicsBuilder;
use Tests\TestCase;

class TopicsBuilderTest extends TestCase
{
    private function item(string $id, string $titulo, array $tags, string $transcricao): AggregatedItem
    {
        return new AggregatedItem(
            id: $id,
            plataforma: 'youtube',
            titulo: $titulo,
            canal: '@canal',
            data: '2026-07-21',
            url: "https://exemplo/{$id}",
            transcricao: $transcricao,
            tags: $tags,
        );
    }

    public function test_a_transcricao_alimenta_os_topicos_sem_llm(): void
    {
        // Dois vídeos SEM tags em comum, mas ambos falam repetidamente de
        // "orcamento" na transcrição → devem agrupar-se por esse tema.
        $itens = [
            $this->item('a', 'Dica rápida', ['dicas'], 'hoje falamos de orcamento orcamento orcamento e poupança orcamento'),
            $this->item('b', 'Conversa longa', ['entrevista'], 'o orcamento familiar orcamento é chave orcamento planeamento orcamento'),
        ];

        // Sem qualquer LLM configurado → cai na heurística.
        $semLlm = new class extends LlmClient
        {
            public function disponivel(): bool
            {
                return false;
            }
        };

        $resultado = (new TopicsBuilder($semLlm))->build($itens);

        $this->assertSame('heuristica', $resultado['metodo']);

        $rotulos = array_map(fn ($t) => mb_strtolower($t['topico']), $resultado['topicos']);
        $this->assertContains('orcamento', $rotulos, 'Esperava um tópico derivado da transcrição («orcamento»).');

        // Esse tópico junta os dois itens.
        $topico = collect($resultado['topicos'])->firstWhere('topico', 'Orcamento');
        $this->assertNotNull($topico);
        $this->assertCount(2, $topico['itens']);
    }
}
