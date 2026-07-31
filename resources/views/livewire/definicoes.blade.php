<div>
    <x-page-header
        eyebrow="Tomus · VIII"
        title="Settings"
        cota="005.1 · ACM · '26"
        lead="The house variables — brand, social profiles and the sources the aggregator will crawl. Stored in the vault." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap gap-1.5 border-b border-ink-soft/10 pb-4">
            @foreach ([
                'geral'  => '◆ General',
                'fontes' => '☙ Sources',
                'social' => '❧ Social & Publishing',
                'motor'  => '⚙ AI & Engine',
                'chaves' => '🔑 API Keys',
                'sistema' => '↻ Updates',
            ] as $id => $rotulo)
                <button type="button" wire:click="$set('secao', '{{ $id }}')"
                        class="font-mono text-[0.7rem] px-3.5 py-2 rounded-sm border transition {{ $secao === $id ? 'border-teal/60 text-teal bg-teal/5' : 'border-ink-soft/20 text-ink-soft hover:text-ink hover:border-ink-soft/40' }}">
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>

        {{-- ══════════════════════════ GENERAL ══════════════════════════ --}}
        @if ($secao === 'geral')
            <x-panel eyebrow="House" title="General" glyph="◆">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    <div>
                        <label class="eyebrow block mb-1.5">Project language</label>
                        <select wire:model="idioma"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                            <option value="en">English</option>
                            <option value="pt">Português</option>
                        </select>
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">Used for this project's generated content.</p>
                    </div>
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ SOURCES ══════════════════════════ --}}
        @if ($secao === 'fontes')
            <x-panel eyebrow="Aggregator" title="Sources to crawl" glyph="☙">
                <p class="text-ink-soft -mt-2 mb-4">One entry per line — channels, subreddits, accounts or links that feed the news report.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
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

            <x-panel eyebrow="Aggregator" title="Channels to aggregate" glyph="▶">
                <p class="text-ink-soft -mt-2 mb-4">Links to channels/profiles the aggregator crawls via yt-dlp — you can add <span class="text-ink">several per platform</span> with «+ add channel». YouTube and TikTok work without credentials; Instagram and LinkedIn are <span class="text-ink">best-effort</span> (may require authentication).</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
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
        @endif

        {{-- ══════════════════════ SOCIAL & PUBLISHING ══════════════════════ --}}
        @if ($secao === 'social')
            <x-panel eyebrow="Networks" title="Social profiles" glyph="❧">
                <p class="text-ink-soft -mt-2 mb-4">Handle (@handle) and link for each profile we monitor.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
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

            <x-panel eyebrow="Publishing" title="Blotato accounts" glyph="📡">
                <p class="text-ink-soft -mt-2 mb-4">
                    Paste the connected-account id per platform from your
                    <span class="text-teal">Blotato dashboard</span>. A platform with no id can't be posted to.
                    Needs the Blotato key (API Keys tab).
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ([
                        'youtube' => 'YouTube',
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok',
                        'linkedin' => 'LinkedIn',
                        'threads' => 'Threads',
                    ] as $plat => $rotulo)
                        <div>
                            <label class="eyebrow block mb-1.5">{{ $rotulo }} account id</label>
                            <input type="text" autocomplete="off" wire:model="blotato.{{ $plat }}" placeholder="e.g. 12345"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ AI & ENGINE ══════════════════════════ --}}
        @if ($secao === 'motor')
            <x-panel eyebrow="Engine" title="Models & limits" glyph="⚙">
                <p class="text-ink-soft -mt-2 mb-4">Empty → uses the config/.env default.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="eyebrow block mb-1.5">LLM provider</label>
                        <input type="text" wire:model="modelos.llm_provider" placeholder="auto | anthropic | openai | gemini | none"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Anthropic model</label>
                        <input type="text" wire:model="modelos.anthropic_model" placeholder="claude-opus-4-8"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">OpenAI model</label>
                        <input type="text" wire:model="modelos.openai_model" placeholder="gpt-4o-mini"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Gemini model</label>
                        <input type="text" wire:model="modelos.gemini_model" placeholder="gemini-1.5-flash"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Videos per channel</label>
                        <input type="number" min="1" wire:model="modelos.aggregation_limit" placeholder="5"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">yt-dlp timeout (s)</label>
                        <input type="number" min="5" wire:model="modelos.aggregation_timeout" placeholder="45"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">ElevenLabs voice ID</label>
                        <input type="text" wire:model="modelos.elevenlabs_voice" placeholder="EXAVITQu4vr4xnSDxMaL"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">Voice for the clip voiceover. Empty = default.</p>
                    </div>
                </div>
            </x-panel>

            <x-panel eyebrow="Workshop" title="Clip Generator" glyph="✂">
                <p class="text-ink-soft -mt-2 mb-4">Address of the ShortsCreator API that cuts the videos and records the subtitles.</p>
                <div>
                    <label class="eyebrow block mb-1.5">API URL</label>
                    <input type="url" wire:model="shorts.api_url" placeholder="http://localhost:5000"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <p class="mt-1.5 font-mono text-xs text-ink-faint">Empty → uses <span class="text-ink-soft">SHORTS_API_URL</span> from .env.</p>
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ API KEYS ══════════════════════════ --}}
        @if ($secao === 'chaves')
            <x-panel eyebrow="Credentials" title="API keys" glyph="🔑">
                <p class="text-ink-soft -mt-2 mb-4">
                    Stored in the vault for local use. Empty → falls back to the matching <span class="font-mono text-ink-soft">.env</span> value.
                    Setting the <span class="text-teal">Anthropic</span> key routes all Claude features through the API.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ([
                        'anthropic' => 'Anthropic (Claude)',
                        'openai' => 'OpenAI',
                        'gemini' => 'Gemini',
                        'apify' => 'Apify token',
                        'kie' => 'kie.ai',
                        'elevenlabs' => 'ElevenLabs',
                        'youtube' => 'YouTube Data',
                        'tubelab' => 'TubeLab',
                        'reddit_client_id' => 'Reddit client id',
                        'reddit_client_secret' => 'Reddit client secret',
                        'blotato' => 'Blotato (publishing)',
                    ] as $chave => $rotulo)
                        <div>
                            <label class="eyebrow block mb-1.5">{{ $rotulo }}</label>
                            <input type="password" autocomplete="off" wire:model="chaves.{{ $chave }}" placeholder="•••• (from .env)"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ UPDATES ══════════════════════════ --}}
        @if ($secao === 'sistema')
            <x-panel eyebrow="Deployment" title="Updates" glyph="↻">
                <div class="flex items-center gap-3 mb-4">
                    <span class="eyebrow">Running version</span>
                    <span class="font-mono text-sm text-ink">{{ $versaoAtual }}</span>
                </div>

                @unless ($podeAtualizar)
                    <p class="text-ink-soft text-sm mb-3">One-click update isn't wired on this install (no Watchtower sidecar). You can still check below; to update manually, run <span class="font-mono text-ink-soft">docker compose pull &amp;&amp; docker compose up -d</span> on the host.</p>
                @endunless

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="verificarAtualizacoes" wire:loading.attr="disabled" wire:target="verificarAtualizacoes"
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                        <span wire:loading.remove wire:target="verificarAtualizacoes">↻ Check for updates</span>
                        <span wire:loading wire:target="verificarAtualizacoes">checking…</span>
                    </button>

                    @if ($atualizacao === 'available' && $podeAtualizar)
                        <button type="button" wire:click="instalarAtualizacao"
                                wire:confirm="Install the new version now? The app will restart — reload this page in ~30 seconds."
                                class="font-display text-lg px-6 py-2 rounded-sm bg-teal text-papyrus hover:bg-teal-deep transition shadow-engraved">
                            ⬇ Install &amp; restart
                        </button>
                    @endif
                </div>

                <div class="mt-4 font-mono text-sm">
                    @if ($atualizacao === 'uptodate')
                        <span class="text-good">✓ You're on the latest version.</span>
                    @elseif ($atualizacao === 'available')
                        <span class="text-gold">● A new version is available.</span>
                    @elseif ($atualizacao === 'updating')
                        <span class="text-teal">↻ Update triggered — pulling the new image and restarting. Reload this page in ~30 seconds. (It also auto-updates within 30 min if anything blocks this.)</span>
                    @elseif ($atualizacao === 'update-failed')
                        <span class="text-warn">⚠ Couldn't reach the updater sidecar — its setup may be missing on this host. Re-run the installer on the host to repair it and update now: <span class="text-ink">curl -fsSL https://raw.githubusercontent.com/jdportugal/ContentMachine/production/install.sh | bash</span></span>
                    @elseif ($atualizacao === 'error')
                        <span class="text-ink-faint">Couldn't check right now (dev build, or the registry was unreachable).</span>
                    @endif
                </div>
            </x-panel>
        @endif

        {{-- ── Action bar (saves every settings tab) ────────────────────────── --}}
        @if ($secao !== 'sistema')
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
            🔒 Keys are saved in the vault (<span class="text-ink-soft">definicoes/definicoes.md</span>) for local use. Don't sync the vault to a public location with real keys in it. Saving persists <span class="text-ink-soft">every tab</span>, not just the open one.
        </p>
        @endif
    </form>
</div>
