<?php

namespace App\Services\RealEstate;

use App\Services\Clips\Api\RunsClaudeCli;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes the pt-PT voiceover, scenes, flags and WhatsApp message for a listing
 * video, following resources/prompts/real-estate-voiceover.md.
 */
class ListingScriptWriter
{
    use RunsClaudeCli;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    /**
     * @param  array<string,mixed>  $listing  RemaxListingScraper::scrape() output
     * @return array{title:string,hook:string,voiceover:string,scenes:array,flags:list<string>,message:string,agent_gender:string}
     */
    public function write(array $listing, ?float $seconds = null): array
    {
        // Over a reel that's already cut: about 2.3 spoken words a second, a breath at each end.
        $words = $seconds ? (int) round(max(8, $seconds - 1.5) * 2.3) : null;
        $length = $words ? 'At most '.$words.' words (it is spoken over a '.round($seconds).'-second reel and must end before it does).' : '70–110 words.';
        $guide = (string) file_get_contents(resource_path('prompts/real-estate-voiceover.md'));
        $details = collect($listing['details'])->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");
        $price = $listing['price'] !== null
            ? number_format($listing['price'], 0, ',', '.').' €'
            : 'NOT PUBLISHED — end the voiceover with only "Para visitar, fale com…" (no placeholder: it is spoken) and add a flag';
        $ami = $listing['ami'] !== '' ? $listing['ami'] : 'not published — leave it off the end card, no flag';
        $greeting = (int) now('Europe/Lisbon')->format('G') < 13 ? 'Bom dia' : 'Boa tarde';

        $system = <<<SYS
        You write voiceovers for 30–40 second vertical real estate videos in European Portuguese (pt-PT).

        Follow this guide strictly:

        {$guide}
        SYS;

        $user = <<<PROMPT
        Write the voiceover, scenes, flags and outreach message for the listing below.

        - Structure: hook → where → inside → standout features → (use) → close.
        - {$length} Numbers written as words. No hype words. No Brazilian Portuguese.
        - Only facts present in the listing. If the listing contradicts itself, or mentions an unverifiable famous name, or the price looks wrong, leave it out and add a flag.
        - 3D/project images must be captioned "Imagem 3D do projeto".
        - Close exactly: "[preço por extenso]. Para visitar, fale com [o/a] [Nome Apelido], da [Agência]."
        - The photos are not described: name each scene's photo by the shot it needs (fachada, sala, cozinha…). Do not flag that.
        - Infer the market (interior / cidade / luxo / algarve) from the location and price.
        - "agent_gender" is "f" or "m", from the agent's first name (it picks the narrator's voice, o/a, and Sr./Sra. in the message).
        - "title" is a short video title in pt-PT, e.g. "Moradia em Valpaços com piscina e 6 quartos | 495 mil €".

        Return ONLY JSON, no markdown:
        {
          "title": "...",
          "hook": "...",
          "voiceover": "...",
          "scenes": [{"photo": "...", "vo": "...", "caption": "..."}],
          "flags": ["..."],
          "message": "...",
          "agent_gender": "f"
        }

        Listing ({$listing['title']}):
        {$listing['description']}

        Details:
        {$details}

        Price: {$price}
        Agent: {$listing['agent']['name']}
        Agency: {$listing['agency']}
        AMI: {$ami}
        Slogan:
        Time of day greeting: {$greeting}
        PROMPT;
        // The user's notes: what the listing page gets wrong or leaves out. They win.
        if (($notes = trim((string) ($listing['notes'] ?? ''))) !== '') {
            $user .= "\n\nNOTES FROM THE USER — they OVERRIDE the listing above wherever they differ (price, features, "
                ."areas, wording, what to stress or leave out); follow them:\n{$notes}";
        }

        $out = $this->json((string) ($this->runClaude($user, $system, ['maxTurns' => 1])['result'] ?? ''));
        // The voiceover is spoken: a "[PREÇO]"-style placeholder would be read out. One retry, told so.
        if (preg_match('/\[[^\]]*\]/u', (string) ($out['voiceover'] ?? ''), $m)) {
            $out = $this->json((string) ($this->runClaude($user."\n\nYour previous voiceover contained the placeholder \"{$m[0]}\". It is read aloud: write the real value in words"
                .($listing['price'] !== null ? ' (the price is '.number_format($listing['price'], 0, ',', '.').' €)' : '').', or leave that part out. No square brackets anywhere in the voiceover.', $system, ['maxTurns' => 1])['result'] ?? ''));
            if (preg_match('/\[[^\]]*\]/u', (string) ($out['voiceover'] ?? ''))) {
                throw new RuntimeException('The script writer kept a placeholder in the voiceover: '.Str::limit((string) $out['voiceover'], 200));
            }
        }
        if (trim((string) ($out['voiceover'] ?? '')) === '') {
            throw new RuntimeException('The script writer returned no voiceover.');
        }

        return [
            'title' => trim((string) ($out['title'] ?? $listing['title'])),
            'hook' => trim((string) ($out['hook'] ?? '')),
            // Spoken text: "RE/MAX" is read out with the slash ("re barra max").
            'voiceover' => self::iadFalado(self::semBarra(trim((string) $out['voiceover']))),
            'scenes' => is_array($out['scenes'] ?? null) ? $out['scenes'] : [],
            'flags' => array_values(array_filter(array_map('strval', (array) ($out['flags'] ?? [])))),
            'message' => trim((string) ($out['message'] ?? '')),
            'agent_gender' => ($out['agent_gender'] ?? 'm') === 'f' ? 'f' : 'm',
        ];
    }

    /**
     * The after-intro line ("Se queres vender casa, fala com…") said right after
     * the intro — the voiceover's first sentence — where its video plays. Once.
     */
    public static function withAfterIntroLine(string $voiceover, string $line): string
    {
        $line = trim($line);
        if ($line === '' || str_contains(Str::lower($voiceover), Str::lower(rtrim($line, '.!?')))) {
            return $voiceover;
        }
        $line = preg_match('/[.!?…]$/u', $line) ? $line : $line.'.';

        return preg_match('/^(.+?[.!?…])\s+(.*)$/us', $voiceover, $m) ? "{$m[1]} {$line} {$m[2]}" : "{$voiceover} {$line}";
    }

    /** "RE/MAX" → "REMAX", however the slash is spaced or cased. */
    public static function semBarra(string $text): string
    {
        return preg_replace('#\bRE\s*/\s*MAX\b#i', 'REMAX', $text);
    }

    /**
     * Spoken iad: "IAD Portugal". Lowercase "iad" is voiced "IA de Portugal"
     * (heard as "IA"); in capitals ElevenLabs says the letters.
     */
    public static function iadFalado(string $text): string
    {
        return preg_replace('/\biad\b(?:\s+(?:de\s+)?Portugal\b)?/iu', 'IAD Portugal', $text);
    }

    private function json(string $content): array
    {
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = $m[1];
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        return $start !== false && $end > $start ? (json_decode(substr($content, $start, $end - $start + 1), true) ?: []) : [];
    }
}
