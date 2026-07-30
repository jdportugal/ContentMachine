<?php

namespace App\Services\Clips\Contracts;

interface ResearchService
{
    /**
     * Research the transcript's topic (deep/web search) and return structured
     * facts to enrich the video: timeline, stats, comparisons, key points.
     *
     * @return array{topic:string,summary:string,timeline:array,stats:array,comparisons:array,keyPoints:array,sources:array}
     */
    public function research(array $transcript): array;
}
