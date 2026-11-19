<?php

namespace App\Support;

/**
 * schema.org JSON-LD for search engines and AI answers: who Imocena is (on the home page)
 * and, for each guide, the article, its breadcrumb and its questions and answers.
 */
class Esquema
{
    public static function organizacao(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organizacao',
            'name' => 'Imocena',
            'url' => url('/'),
            'logo' => asset('images/imocena-simbolo.png'),
            'email' => 'info@imocena.com',
            'description' => 'Vídeos de imóveis para consultores imobiliários em Portugal, feitos a partir do link do anúncio, com narração em português de Portugal.',
            'areaServed' => ['@type' => 'Country', 'name' => 'Portugal'],
        ];
    }

    /** @return list<array> */
    public static function site(): array
    {
        return [
            ['@context' => 'https://schema.org'] + self::organizacao(),
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => url('/').'#site',
                'name' => 'Imocena',
                'url' => url('/'),
                'inLanguage' => 'pt-PT',
                'publisher' => ['@id' => url('/').'#organizacao'],
            ],
        ];
    }

    /** @return list<array> */
    public static function guia(array $guia): array
    {
        $url = route('guias.ver', $guia['slug']);
        $esquemas = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $guia['titulo'],
                'description' => $guia['descricao'],
                'abstract' => $guia['resumo'],
                'keywords' => $guia['palavra'],
                'inLanguage' => 'pt-PT',
                'datePublished' => $guia['publicado'],
                'dateModified' => $guia['atualizado'],
                'wordCount' => $guia['palavras'],
                'mainEntityOfPage' => $url,
                'url' => $url,
                'image' => array_values(array_filter([\App\Services\Guias\Guias::fotos($guia['slug'])[0] ?? null, asset('images/og/'.$guia['slug'].'.png')])),
                'author' => self::organizacao(),
                'publisher' => self::organizacao(),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Imocena', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Guias', 'item' => route('guias')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $guia['titulo'], 'item' => $url],
                ],
            ],
        ];
        if ($guia['faq'] !== []) {
            $esquemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($p) => [
                    '@type' => 'Question',
                    'name' => $p['pergunta'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p['resposta']],
                ], $guia['faq']),
            ];
        }

        return $esquemas;
    }
}
