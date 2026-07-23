<?php

namespace App\Services\Clips\Contracts;

interface MetadataService
{
    /**
     * Suggest publishing metadata (title, description, tags) for a clip from
     * its transcript — the equivalent of the shorts pipeline's título/descrição/tags.
     *
     * @return array{title:string,description:string,tags:array<int,string>}
     */
    public function suggest(array $transcript): array;
}
