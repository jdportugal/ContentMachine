<div>
    <x-page-header
        eyebrow="Tomus · VIII"
        title="Settings"
        cota="005.1 · IAT · '26"
        lead="The house variables — brand, social profiles and the sources the aggregator will crawl. Stored in the vault." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- General --}}
        <x-panel eyebrow="House" title="General" glyph="◆">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="eyebrow block mb-1.5">Brand name</label>
                    <input type="text" wire:model="geral.nome_marca"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5">Site / website</label>
                    <input type="url" wire:model="geral.sitio" placeholder="https://…"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                </div>
            </div>
        </x-panel>

        {{-- Social profiles --}}
        <x-panel eyebrow="Networks" title="Social profiles" glyph="❧">
            <p class="text-ink-soft -mt-2 mb-4">Handle (@handle) and link for each profile we monitor.</p>
            <div class="grid sm:grid-cols-2 gap-5">
                @foreach ($perfis as $rede => $dados)
                    @php $m = $plataformasMeta[$rede] ?? ['label' => ucfirst($rede), 'cor' => '#5A7BFF', 'glifo' => '•']; @endphp
                    <div class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xl" style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span>
                            <span class="font-display text-xl text-ink">{{ $m['label'] }}</span>
                        </div>
                        <label class="eyebrow block mb-1">Handle</label>
                        <input type="text" wire:model="perfis.{{ $rede }}.handle" placeholder="@ateca"
                               class="w-full mb-3 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <label class="eyebrow block mb-1">Link</label>
                        <input type="url" wire:model="perfis.{{ $rede }}.url" placeholder="https://…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Aggregator sources --}}
        <x-panel eyebrow="Aggregator" title="Sources to crawl" glyph="☙">
            <p class="text-ink-soft -mt-2 mb-4">One entry per line — channels, subreddits, accounts or links that feed the news report.</p>
            <div class="grid sm:grid-cols-2 gap-5">
                @php
                    $rotulos = [
                        'youtube' => ['YouTube channels', 'channel or URL per line'],
                        'reddit' => ['Subreddits', 'r/name per line'],
                        'twitter' => ['X / Twitter accounts', '@account per line'],
                        'tiktok' => ['TikTok accounts', '@account per line'],
                    ];
                @endphp
                @foreach ($fontes as $fonte => $texto)
                    @php $r = $rotulos[$fonte] ?? [ucfirst($fonte), 'one per line']; @endphp
                    <div>
                        <label class="eyebrow block mb-1.5">{{ $r[0] }}</label>
                        <textarea wire:model="fontes.{{ $fonte }}" rows="4" placeholder="{{ $r[1] }}"
                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none"></textarea>
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Channels to aggregate (yt-dlp) --}}
        <x-panel eyebrow="Aggregator" title="Channels to aggregate" glyph="▶">
            <p class="text-ink-soft -mt-2 mb-4">Links to channels/profiles the aggregator crawls via yt-dlp — you can add <span class="text-ink">several per platform</span> with «+ add channel». YouTube and TikTok work without credentials; Instagram and LinkedIn are <span class="text-ink">best-effort</span> (may require authentication).</p>
            <div class="grid sm:grid-cols-2 gap-5">
                @php
                    $rotulosCanais = [
                        'youtube' => ['YouTube channels', 'https://www.youtube.com/@channel'],
                        'instagram' => ['Instagram profiles', 'https://www.instagram.com/profile/'],
                        'tiktok' => ['TikTok accounts', 'https://www.tiktok.com/@account'],
                        'linkedin' => ['LinkedIn profiles', 'https://www.linkedin.com/in/profile/'],
                    ];
                @endphp
                @foreach ($canais as $plataforma => $lista)
                    @php
                        $rc = $rotulosCanais[$plataforma] ?? [ucfirst($plataforma), 'https://…'];
                        $m = $plataformasMeta[$plataforma] ?? ['cor' => '#5A7BFF', 'glifo' => '•'];
                    @endphp
                    <div class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30">
                        <label class="eyebrow block mb-2">
                            <span style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span> {{ $rc[0] }}
                        </label>
                        <div class="space-y-2">
                            @foreach ($lista as $i => $url)
                                <div class="flex gap-2" wire:key="canal-{{ $plataforma }}-{{ $i }}">
                                    <input type="url" wire:model="canais.{{ $plataforma }}.{{ $i }}" placeholder="{{ $rc[1] }}"
                                           class="flex-1 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                    <button type="button" wire:click="removerCanal('{{ $plataforma }}', {{ $i }})"
                                            class="shrink-0 text-ink-faint hover:text-bad px-2 text-lg" title="Remove">×</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="adicionarCanal('{{ $plataforma }}')"
                                class="mt-2 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-3 py-1 font-mono text-xs transition">
                            + add channel
                        </button>
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Clip Generator --}}
        <x-panel eyebrow="Workshop" title="Clip Generator" glyph="✂">
            <p class="text-ink-soft -mt-2 mb-4">Address of the ShortsCreator API that cuts the videos and records the subtitles.</p>
            <div>
                <label class="eyebrow block mb-1.5">API URL</label>
                <input type="url" wire:model="shorts.api_url" placeholder="http://localhost:5000"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                <p class="mt-1.5 font-mono text-xs text-ink-faint">Empty → uses <span class="text-ink-soft">SHORTS_API_URL</span> from .env.</p>
            </div>
        </x-panel>

        {{-- Action bar --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-teal text-papyrus font-display text-xl px-7 py-2.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                Save settings
            </button>
            @if ($guardado)
                <span class="text-good font-mono text-sm">✓ Saved at {{ $guardado }} to the vault (definicoes/definicoes.md).</span>
            @endif
        </div>

        <p class="font-mono text-xs text-ink-faint pt-2 border-t border-ink-soft/10">
            🔒 Note: API keys (Apify, TubeLab, Gemini…) live in <span class="text-ink-soft">.env</span>, for security — they are not managed here.
        </p>
    </form>
</div>
