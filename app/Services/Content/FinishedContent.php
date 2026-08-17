<?php

namespace App\Services\Content;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What counts as "finished" content, in ONE place.
 *
 * The rule lives here because two features need the same answer: the Finished
 * hub (Rascunhos) lists it for publishing, and the Content Transformer's
 * repurpose tab lists it for converting between formats. Duplicating the state
 * filter would let the two drift the first time the rule changes.
 *
 * This deliberately exposes the raw notes/records, not a presentation shape —
 * each caller maps them for its own screen.
 */
class FinishedContent
{
    /** Vault states that mean "promoted to Finished". */
    public const PRONTOS = ['pronto', 'agendado', 'publicado'];

    public function __construct(private VaultContract $vault) {}

    /** Posts / carousels promoted to Finished. @return Collection<int,VaultNote> */
    public function posts(): Collection
    {
        return $this->vault->all('rascunhos')
            ->filter(fn (VaultNote $n) => in_array($n->get('estado'), self::PRONTOS, true))
            ->values();
    }

    /** Subtitled shorts cut from a long video. @return Collection<int,VaultNote> */
    public function shorts(): Collection
    {
        return $this->vault->all('clips')
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'clip' && in_array($n->get('estado'), self::PRONTOS, true))
            ->values();
    }

    /** Animated clips explicitly promoted (finished = true). @return Collection<int,ClipRecord> */
    public function animated(): Collection
    {
        return app(ClipStore::class)->all()
            ->filter(fn (ClipRecord $p) => $p->status === ClipRecord::STATUS_DONE && (bool) $p->get('finished'))
            ->values();
    }

    // ── text extraction (what a repurpose seeds the target editor with) ──

    /**
     * The words spoken in a short, recovered from its shifted subtitle data.
     * Falls back to the description/title when the clip has no subtitles yet.
     */
    public function shortText(VaultNote $short): string
    {
        $words = json_decode((string) $short->get('subtitle_data'), true);
        if (is_array($words)) {
            $spoken = collect($words)
                ->map(fn ($w) => is_array($w) ? ($w['word'] ?? $w['text'] ?? '') : (string) $w)
                ->filter()
                ->implode(' ');
            if (trim($spoken) !== '') {
                return $this->compose($short->title(), trim($spoken));
            }
        }

        return $this->compose($short->title(), (string) $short->get('descricao'));
    }

    /** An animated clip's script — what it was generated from. */
    public function animatedText(ClipRecord $clip): string
    {
        return $this->compose(
            (string) ($clip->title ?: 'Animated clip'),
            (string) ($clip->get('source_text') ?: '')
        );
    }

    /** A post's body, as plain text, for seeding a video script. */
    public function postText(VaultNote $post): string
    {
        return $this->compose($post->title(), trim(strip_tags($post->html())));
    }

    /** Title + body, trimmed to the editors' brief/script limit. */
    private function compose(string $title, string $body): string
    {
        $text = trim($title."\n\n".$body);

        // Oficina's brief caps at 6000 and the animated-clip script at 5000;
        // stay under the smaller one so either target accepts the seed.
        return Str::limit($text, 5000, '');
    }
}
