<?php

namespace App\Services\News;

interface NewsDriver
{
    /**
     * Gera (ou obtém) um relatório personalizado a partir das fontes indicadas.
     *
     * @param  array<int,string>  $fontes  ex.: ['youtube','reddit','twitter','tiktok']
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
