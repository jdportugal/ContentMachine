<?php

namespace App\Services\News;

interface NewsDriver
{
    /**
     * Generates (or fetches) a personalized report from the given sources.
     *
     * @param  array<int,string>  $fontes  e.g. ['youtube','reddit','twitter','tiktok']
     * @return array{
     *   titulo:string,
     *   gerado_em:string,
     *   resumo:string,
     *   destaques:array<int,array{fonte:string,titulo:string,url:string,angulo:string,relevancia:int}>,
     *   ideias_guiao:array<int,string>
     * }
     */
    public function relatorio(array $fontes): array;
}
