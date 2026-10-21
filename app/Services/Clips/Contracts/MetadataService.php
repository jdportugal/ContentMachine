<?php

namespace App\Services\Clips\Contracts;

interface MetadataService
{
    /**
     * Suggest publishing metadata (title, description, tags) for a clip from
     * its transcript — the equivalent of the shorts pipeline's title/description/tags.
     * $context: facts to draw on (a listing's details) and the project's recent
     * captions, so this one says more and doesn't repeat them.
     *
     * @return array{title:string,description:string,tags:array<int,string>}
     */
    public function suggest(array $transcript, string $context = ''): array;
}
