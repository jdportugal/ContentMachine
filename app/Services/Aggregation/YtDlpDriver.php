<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Str;

/**
 * Driver de agregação assente no yt-dlp. Suporta plenamente YouTube e TikTok;
 * para Instagram e LinkedIn tenta na mesma, mas degrada graciosamente quando o
 * yt-dlp não consegue extrair (normalmente por exigir autenticação).
 */
class YtDlpDriver implements AggregatorDriver
{
    /** @var array<int,string> */
    private array $subLangs;

    public function __construct(
        private readonly YtDlpRunnerContract $runner,
        private readonly TranscriptParser $parser,
        private readonly string $plataforma,
    ) {
        $this->subLangs = (array) config('contentmachine.aggregation.sub_langs', ['pt', 'en']);
    }

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    public function collect(array $canais, int $limitePorCanal): array
    {
        $itens = [];

        foreach ($canais as $canal) {
            $canal = trim($canal);
            if ($canal === '') {
                continue;
            }

            foreach ($this->itensDoCanal($canal, $limitePorCanal) as $item) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * @return array<int,AggregatedItem>
     */
    private function itensDoCanal(string $canal, int $limite): array
    {
        try {
            $listagem = $this->runner->listing($this->normalizarCanal($canal), $limite);
        } catch (\Throwable) {
            return [];
        }

        $entradas = $listagem['entries'] ?? [];
        $itens = [];

        foreach (array_slice($entradas, 0, $limite) as $entrada) {
            $url = $this->urlDaEntrada($entrada);
            if ($url === null) {
                continue;
            }

            try {
                $meta = $this->runner->metadata($url);
            } catch (\Throwable) {
                continue;
            }

            if ($meta === []) {
                continue;
            }

            $item = $this->normalizar($meta);
            if ($item !== null) {
                $itens[] = $item;
            }
        }

        return $itens;
    }

    /**
     * Normaliza o URL de um canal. No YouTube, um URL de canal "nu" (@handle,
     * /channel/ID, /c/nome, /user/nome) lista os SEPARADORES do canal (Vídeos,
     * Shorts, Live…) em vez dos vídeos — apontamos explicitamente ao separador
     * /videos para obter publicações reais.
     */
    private function normalizarCanal(string $canal): string
    {
        if ($this->plataforma !== 'youtube') {
            return $canal;
        }

        $canal = rtrim($canal, '/');

        // Já aponta a um separador ou a um vídeo/playlist específico.
        if (preg_match('#/(videos|shorts|streams|playlists|featured|community|podcasts)$#i', $canal)
            || str_contains($canal, 'watch?v=')
            || str_contains($canal, '/playlist')) {
            return $canal;
        }

        if (preg_match('#youtube\.com/(@[^/]+|channel/[^/]+|c/[^/]+|user/[^/]+)$#i', $canal)) {
            return $canal.'/videos';
        }

        return $canal;
    }

    /** @param array<string,mixed> $entrada */
    private function urlDaEntrada(array $entrada): ?string
    {
        return $entrada['url'] ?? $entrada['webpage_url'] ?? (isset($entrada['id']) ? (string) $entrada['id'] : null);
    }

    /** @param array<string,mixed> $meta */
    private function normalizar(array $meta): ?AggregatedItem
    {
        $id = (string) ($meta['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $descricao = (string) ($meta['description'] ?? '');
        $transcricao = $this->transcricao($meta);
        $tags = array_values(array_filter(array_map('strval', (array) ($meta['tags'] ?? []))));

        return new AggregatedItem(
            id: Str::slug($id) !== '' ? Str::slug($id) : md5($id),
            plataforma: $this->plataforma,
            titulo: (string) ($meta['title'] ?? 'Sem título'),
            canal: (string) ($meta['channel'] ?? $meta['uploader'] ?? ''),
            data: $this->data($meta),
            url: (string) ($meta['webpage_url'] ?? ''),
            thumbnail: (string) ($meta['thumbnail'] ?? ''),
            descricao: $descricao,
            transcricao: $transcricao,
            tags: $tags,
            fontes: $this->extrairFontes($descricao."\n".$transcricao),
        );
    }

    /** @param array<string,mixed> $meta */
    private function data(array $meta): string
    {
        $upload = (string) ($meta['upload_date'] ?? '');
        if (preg_match('/^\d{8}$/', $upload)) {
            return substr($upload, 0, 4).'-'.substr($upload, 4, 2).'-'.substr($upload, 6, 2);
        }

        if (! empty($meta['timestamp']) && is_numeric($meta['timestamp'])) {
            return date('Y-m-d', (int) $meta['timestamp']);
        }

        return now()->toDateString();
    }

    /** @param array<string,mixed> $meta */
    private function transcricao(array $meta): string
    {
        // Tenta cada legenda candidata por ordem de preferência até obter texto —
        // as auto-legendas TRADUZIDAS (ex.: pt) são frequentemente limitadas
        // (HTTP 429), pelo que é essencial recuar para o idioma original.
        foreach ($this->urlsLegenda($meta) as $url) {
            $vtt = $this->runner->fetch($url);

            if ($vtt !== null && trim($vtt) !== '') {
                $texto = $this->parser->vttToText($vtt);
                if ($texto !== '') {
                    return $texto;
                }
            }
        }

        return '';
    }

    /**
     * URLs VTT candidatos, por ordem de preferência: legendas manuais primeiro,
     * depois auto-legendas, seguindo a ordem de idiomas configurada.
     *
     * @param  array<string,mixed>  $meta
     * @return array<int,string>
     */
    private function urlsLegenda(array $meta): array
    {
        $urls = [];

        foreach (['subtitles', 'automatic_captions'] as $chave) {
            $conjunto = (array) ($meta[$chave] ?? []);

            foreach ($this->subLangs as $lang) {
                $formatos = $conjunto[$lang] ?? null;
                if (! is_array($formatos)) {
                    continue;
                }

                foreach ($formatos as $formato) {
                    if (($formato['ext'] ?? null) === 'vtt' && ! empty($formato['url'])) {
                        $urls[] = (string) $formato['url'];
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Extrai ligações (fontes) do texto, sem duplicados.
     *
     * @return array<int,string>
     */
    private function extrairFontes(string $texto): array
    {
        if (! preg_match_all('#https?://[^\s<>"\')]+#i', $texto, $m)) {
            return [];
        }

        $limpas = array_map(fn (string $u) => rtrim($u, '.,);'), $m[0]);

        return array_values(array_slice(array_unique($limpas), 0, 15));
    }
}
