<?php

namespace App\Services\Capture;

use App\Services\Aggregation\LlmClient;
use Illuminate\Support\Facades\Http;

/**
 * Works out WHICH page an effect is talking about.
 *
 * The user describes an effect ("show the Claude Code site scrolling") and this
 * asks the model for the URL. A guessed URL is sometimes wrong or dead, so the
 * answer is validated for shape and then actually requested — a capture of a
 * 404 page is worse than no capture, because it looks deliberate.
 *
 * Returns null rather than throwing: no URL simply means no site capture.
 */
class SiteResolver
{
    public function __construct(private readonly LlmClient $llm) {}

    /** The URL for this description, or null if there isn't a usable one. */
    public function resolve(string $descricao): ?string
    {
        if (! $this->llm->disponivel()) {
            return null;
        }

        $resposta = $this->llm->paraPasso('vfx_site')->texto($this->prompt($descricao));
        $url = $this->normalizar((string) ($resposta ?? ''));

        return $url !== null && $this->responde($url) ? $url : null;
    }

    /** Validate a URL supplied by someone else (e.g. the clip planner): shape, SSRF guard, and it must respond. */
    public function validar(string $url): ?string
    {
        $limpo = $this->normalizar($url);

        return $limpo !== null && $this->responde($limpo) ? $limpo : null;
    }

    /** Shape check. Rejects anything that is not a plain public http(s) URL. */
    public function normalizar(string $raw): ?string
    {
        $texto = trim($raw);

        // The model sometimes wraps it in prose, quotes or markdown — take the
        // first URL and shed whatever punctuation came along with it.
        if (preg_match('#https?://[^\s"\'`<>)\]]+#i', $texto, $m)) {
            $texto = rtrim($m[0], ".,;:!?`'\"");
        }

        if ($texto === '' || strcasecmp($texto, 'none') === 0) {
            return null;
        }
        if (! filter_var($texto, FILTER_VALIDATE_URL)) {
            return null;
        }

        $partes = parse_url($texto);
        $host = strtolower((string) ($partes['host'] ?? ''));

        if (! in_array(strtolower((string) ($partes['scheme'] ?? '')), ['http', 'https'], true) || $host === '') {
            return null;
        }

        // Never let a generated URL reach the private network — this drives a
        // real browser inside the container, so localhost/LAN would be an SSRF.
        if ($this->privado($host)) {
            return null;
        }

        return $texto;
    }

    /** Does it actually serve a page? A capture of a dead link looks deliberate. */
    private function responde(string $url): bool
    {
        try {
            $r = Http::timeout(15)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);

            return $r->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function privado(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true) || str_ends_with($host, '.local')) {
            return true;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function prompt(string $descricao): string
    {
        return <<<PROMPT
        An animation is being built from the description below. If it refers to a
        specific product, company, tool or website whose actual page should be shown
        on screen, reply with that page's URL.

        Rules:
        - Reply with ONLY the URL, nothing else. No prose, no markdown.
        - Prefer the official product or documentation page over a blog post.
        - Reply exactly NONE if the description names no specific site, or if you
          are not confident of the real URL. A wrong page is worse than none.

        DESCRIPTION:
        {$descricao}
        PROMPT;
    }
}
