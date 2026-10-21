<?php

namespace App\Services\RealEstate;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Reads an idealista.pt listing. The site answers plain HTTP with a bot check
 * (Cloudflare/DataDome), so a real Chrome loads the page and
 * remotion/scripts/fetch-idealista.mjs hands back what it shows as JSON.
 */
class IdealistaListingScraper
{
    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ($host === 'idealista.pt' || str_ends_with($host, '.idealista.pt'))
            && str_contains((string) parse_url($url, PHP_URL_PATH), '/imovel/');
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $dir = rtrim((string) config('contentmachine.clips.remotion_path'), '/');
        $process = new Process(['node', $dir.'/scripts/fetch-idealista.mjs', '--url', $url], $dir);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Could not read the idealista listing: '.(trim($process->getErrorOutput()) ?: 'Chrome failed'));
        }

        return $this->parse(json_decode($process->getOutput(), true) ?: [], $url);
    }

    /** @param  array<string,mixed>  $d  what fetch-idealista.mjs printed */
    public function parse(array $d, string $url): array
    {
        if (empty($d['photos'])) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page).');
        }
        $price = ! empty($d['price']) ? (int) $d['price'] : null;
        // "Ana Mação - KW": the agent, then the agency. A bare name is an agency advertising itself.
        $advertiser = trim((string) ($d['advertiser'] ?? ''));
        [$name, $agency] = str_contains($advertiser, ' - ')
            ? array_map('trim', explode(' - ', $advertiser, 2))
            : [$advertiser, $advertiser];
        preg_match('/AMI\s*(\d+)/i', implode(' ', $d['advertiserInfo'] ?? []), $ami);

        return [
            'url' => $url,
            'title' => trim((string) ($d['title'] ?? '')),
            'description' => trim((string) ($d['description'] ?? '')),
            'price' => $price,
            'details' => $this->details($d, $price),
            'photos' => array_values(array_map('strval', $d['photos'])),
            'agent' => [
                'name' => $name,
                // idealista shows the phone only after a contact form.
                'phone' => '',
                'photo' => null,
            ],
            'agency' => $agency,
            'ami' => $ami[1] ?? '',
        ];
    }

    private function details(array $d, ?int $price): array
    {
        $features = array_values(array_filter(array_map('strval', $d['features'] ?? [])));
        $all = implode(' | ', $features);
        $match = fn (string $re) => preg_match($re, $all, $m) ? $m[1] : null;

        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Localização' => implode(', ', $d['location'] ?? []),
            'Referência' => $d['reference'] ?? null,
            'Área Bruta m²' => $match('/(\d[\d.]*) m² área bruta/u'),
            'Área Útil m²' => $match('/(\d[\d.]*) m² úteis/u'),
            'Quartos' => $match('/(?:^|\| )T(\d+)\b/'),
            'Certificado Energético' => strtoupper(trim((string) ($d['energy'] ?? ''))),
            'Características' => implode(', ', $features),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
