<div>
    <x-page-header
        eyebrow="Tomus · VIII"
        title="Definições"
        cota="005.1 · ACM · '26"
        lead="As variáveis da casa — marca, perfis sociais e as fontes que o agregador vai recolher. Guardadas no cofre." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
        <div class="flex flex-wrap gap-1.5 border-b border-ink-soft/10 pb-4">
            @foreach ([
                'geral'  => '◆ Geral',
                'fontes' => '☙ Fontes',
                'social' => '❧ Redes e publicação',
                'motor'  => '⚙ IA e motor',
                'chaves' => '🔑 Chaves de API',
                'passos' => '⇄ Chaves por passo',
                'conta'  => '👤 Conta',
                'workers' => '⚒ Workers',
                'sistema' => '↻ Atualizações',
            ] as $id => $rotulo)
                <button type="button" wire:click="$set('secao', '{{ $id }}')"
                        class="font-mono text-[0.7rem] px-3.5 py-2 rounded-sm border transition {{ $secao === $id ? 'border-teal/60 text-teal bg-teal/5' : 'border-ink-soft/20 text-ink-soft hover:text-ink hover:border-ink-soft/40' }}">
                    {{ $rotulo }}
                </button>
            @endforeach
        </div>

        {{-- ══════════════════════════ GENERAL ══════════════════════════ --}}
        @if ($secao === 'geral')
            <x-panel eyebrow="Casa" title="Geral" glyph="◆">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="eyebrow block mb-1.5">Nome da marca</label>
                        <input type="text" wire:model="geral.nome_marca"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Site</label>
                        <input type="url" wire:model="geral.sitio" placeholder="https://…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Idioma do projeto</label>
                        <select wire:model="idioma"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                            <option value="en">Inglês</option>
                            <option value="pt">Português</option>
                        </select>
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">Usado no conteúdo gerado deste projeto.</p>
                    </div>
                </div>
            </x-panel>

            <x-panel eyebrow="Automático" title="Clip diário automático" glyph="✦">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="auto.ligado"
                                   class="accent-teal w-4 h-4">
                            <span class="font-body text-ink">Modo automático</span>
                        </label>
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1.5">Todos os dias à hora escolhida: recolhe as notícias, escreve o relatório, transforma as 3 principais num clip animado (voz ElevenLabs) e agenda-o na próxima vaga livre do Blotato — em todas as plataformas com conta configurada.</p>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Executar às</label>
                        <input type="time" wire:model="auto.hora"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                    </div>
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ SOURCES ══════════════════════════ --}}
        @if ($secao === 'fontes')
            <x-panel eyebrow="Agregador" title="Fontes a recolher" glyph="☙">
                <div class="flex items-start justify-between gap-3 -mt-2 mb-4">
                    <p class="text-ink-soft">Uma entrada por linha — canais, subreddits, contas ou links que alimentam o relatório de notícias.</p>
                    <button type="button" wire:click="exportarFontes" title="Descarregar todas as fontes e canais do agregador em JSON"
                            class="shrink-0 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-3 py-1 font-mono text-xs transition">⇩ Exportar</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @php
                        $rotulos = [
                            'youtube' => ['Canais do YouTube', 'canal ou URL por linha'],
                            'reddit' => ['Subreddits', 'r/nome por linha'],
                            'twitter' => ['Contas do X / Twitter', '@conta por linha'],
                            'tiktok' => ['Contas do TikTok', '@conta por linha'],
                        ];
                    @endphp
                    @foreach ($fontes as $fonte => $texto)
                        @php $r = $rotulos[$fonte] ?? [ucfirst($fonte), 'uma por linha']; @endphp
                        <div>
                            <label class="eyebrow block mb-1.5">{{ $r[0] }}</label>
                            <textarea wire:model="fontes.{{ $fonte }}" rows="4" placeholder="{{ $r[1] }}"
                                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none"></textarea>
                        </div>
                    @endforeach
                </div>
            </x-panel>

            <x-panel eyebrow="Agregador" title="Canais a agregar" glyph="▶">
                <p class="text-ink-soft -mt-2 mb-4">Links de canais/perfis que o agregador recolhe via yt-dlp — pode adicionar <span class="text-ink">vários por plataforma</span> com «+ adicionar canal». YouTube e TikTok funcionam sem credenciais; Instagram e LinkedIn são <span class="text-ink">sem garantias</span> (podem exigir autenticação).</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @php
                        $rotulosCanais = [
                            'youtube' => ['Canais do YouTube', 'https://www.youtube.com/@channel'],
                            'instagram' => ['Perfis do Instagram', 'https://www.instagram.com/profile/'],
                            'tiktok' => ['Contas do TikTok', 'https://www.tiktok.com/@account'],
                            'linkedin' => ['Perfis do LinkedIn', 'https://www.linkedin.com/in/profile/'],
                        ];
                    @endphp
                    @foreach ($canais as $plataforma => $lista)
                        @php
                            $rc = $rotulosCanais[$plataforma] ?? [ucfirst($plataforma), 'https://…'];
                            $m = $plataformasMeta[$plataforma] ?? ['cor' => '#2F5A85', 'glifo' => '•'];
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
                                                class="shrink-0 text-ink-faint hover:text-bad px-2 text-lg" title="Remover">×</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" wire:click="adicionarCanal('{{ $plataforma }}')"
                                    class="mt-2 border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-3 py-1 font-mono text-xs transition">
                                + adicionar canal
                            </button>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════ SOCIAL & PUBLISHING ══════════════════════ --}}
        @if ($secao === 'social')
            <x-panel eyebrow="Redes" title="Perfis sociais" glyph="❧">
                <p class="text-ink-soft -mt-2 mb-4">Identificador (@handle) e link de cada perfil que monitorizamos.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @foreach ($perfis as $rede => $dados)
                        @php $m = $plataformasMeta[$rede] ?? ['label' => ucfirst($rede), 'cor' => '#2F5A85', 'glifo' => '•']; @endphp
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

            <x-panel eyebrow="Publicação" title="Contas Blotato" glyph="📡">
                <p class="text-ink-soft -mt-2 mb-4">
                    Cole o id da conta ligada de cada plataforma, a partir do
                    <span class="text-teal">painel do Blotato</span>. Sem id não é possível publicar nessa plataforma.
                    Requer a chave do Blotato (separador Chaves de API).
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
                            <label class="eyebrow block mb-1.5">id da conta {{ $rotulo }}</label>
                            <input type="text" autocomplete="off" wire:model="blotato.{{ $plat }}" placeholder="ex. 12345"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        </div>
                    @endforeach
                </div>
            </x-panel>

            <x-panel eyebrow="Publicação" title="Automações de DM Zernio" glyph="📬">
                <p class="text-ink-soft -mt-2 mb-4">
                    Permite o <span class="text-teal">“Envie-me GUIA por DM e eu mando o link”</span> num post publicado.
                    O Instagram é a única plataforma onde publicamos em que o Zernio pode comentar/enviar DM.
                    Com a chave do Zernio definida (separador Chaves de API), «Obter do Zernio» preenche os dois ids a partir da
                    conta lá ligada.
                </p>
                <div class="-mt-2 mb-4">
                    <button type="button" wire:click="buscarZernio" title="Pedir ao Zernio a conta de Instagram ligada e preencher os dois ids"
                            class="border border-ink-soft/25 text-ink-soft hover:text-ink hover:border-teal/40 rounded-sm px-3 py-1 font-mono text-xs transition">⇣ Obter do Zernio</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach (['profile' => 'Id do perfil', 'instagram' => 'Id da conta de Instagram'] as $campo => $rotulo)
                        <div>
                            <label class="eyebrow block mb-1.5">{{ $rotulo }}</label>
                            <input type="text" autocomplete="off" wire:model="zernio.{{ $campo }}" placeholder="ex. profile_abc123"
                                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ AI & ENGINE ══════════════════════════ --}}
        @if ($secao === 'motor')
            <x-panel eyebrow="Motor" title="Modelos e limites" glyph="⚙">
                <p class="text-ink-soft -mt-2 mb-4">Vazio → usa o valor por omissão do config/.env.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="eyebrow block mb-1.5">Fornecedor de LLM</label>
                        <input type="text" wire:model="modelos.llm_provider" placeholder="auto | anthropic | openai | tensorx | gemini | none"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Modelo Anthropic</label>
                        <input type="text" wire:model="modelos.anthropic_model" placeholder="claude-opus-4-8"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Modelo OpenAI</label>
                        <input type="text" wire:model="modelos.openai_model" placeholder="gpt-4o-mini"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Modelo Gemini</label>
                        <input type="text" wire:model="modelos.gemini_model" placeholder="gemini-1.5-flash"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Fornecedor de LLM dos clips</label>
                        <select wire:model="modelos.clip_provider"
                                class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                            <option value="">claude → gpt → deepseek (por omissão)</option>
                            <option value="claude">claude</option>
                            <option value="openai">openai (gpt)</option>
                            <option value="tensorx">tensorx (deepseek)</option>
                        </select>
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">Motor de planeamento/metadados/pesquisa. O escolhido é tentado PRIMEIRO; os outros ficam como alternativa. Cada um precisa da sua chave.</p>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Modelo Tensorix</label>
                        <input type="text" wire:model="modelos.tensorx_model" placeholder="deepseek/deepseek-r1-0528"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">ex. deepseek/deepseek-r1-0528, z-ai/glm-5.2. Vazio = por omissão.</p>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Vídeos por canal</label>
                        <input type="number" min="1" wire:model="modelos.aggregation_limit" placeholder="5"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Tempo limite do yt-dlp (s)</label>
                        <input type="number" min="5" wire:model="modelos.aggregation_timeout" placeholder="45"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">ID da voz ElevenLabs</label>
                        <input type="text" wire:model="modelos.elevenlabs_voice" placeholder="EXAVITQu4vr4xnSDxMaL"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <p class="font-mono text-[0.55rem] text-ink-faint mt-1">Voz da locução dos clips. Vazio = por omissão.</p>
                    </div>
                </div>
            </x-panel>

            <x-panel eyebrow="Oficina" title="Gerador de Shorts" glyph="✂">
                <p class="text-ink-soft -mt-2 mb-4">Endereço da API ShortsCreator que corta os vídeos e grava as legendas.</p>
                <div>
                    <label class="eyebrow block mb-1.5">URL da API</label>
                    <input type="url" wire:model="shorts.api_url" placeholder="http://localhost:5000"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    <p class="mt-1.5 font-mono text-xs text-ink-faint">Vazio → usa <span class="text-ink-soft">SHORTS_API_URL</span> do .env.</p>
                </div>
                <div class="mt-4">
                    <label class="eyebrow block mb-1.5">Modelo Whisper local</label>
                    <select wire:model="shorts.whisper_model"
                            class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <option value="">por omissão (tiny)</option>
                        @foreach (['tiny', 'base', 'small', 'medium', 'large-v3'] as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 font-mono text-xs text-ink-faint">
                        Transcrição que corre nesta máquina — <span class="text-teal">não precisa de chave OpenAI</span>. Maior = mais preciso e mais lento;
                        o modelo é descarregado na primeira utilização. Usado sempre que não há chave OpenAI, ou quando um passo está fixado em
                        <span class="text-ink-soft">Whisper local</span> em Chaves por passo.
                    </p>
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ API KEYS ══════════════════════════ --}}
        @if ($secao === 'chaves')
            <x-panel eyebrow="Credenciais" title="Chaves de API" glyph="🔑">
                <p class="text-ink-soft -mt-2 mb-4">
                    Guardadas no servidor e nunca devolvidas ao navegador — uma chave guardada aparece como <span class="text-teal">● guardada</span>, não com o seu valor.
                    Um fornecedor pode ter <strong>várias</strong> chaves: escreva uma e Guarde para a adicionar; dê-lhe um nome se as quiser distinguir.
                    A <strong>primeira</strong> chave de um fornecedor é a predefinida; fixe uma chave a um passo específico em <span class="font-mono text-ink-soft">Chaves por passo</span>.
                    Definir a chave <span class="text-teal">Anthropic</span> faz todas as funções do Claude passarem pela API.
                    Uma chave <span class="text-teal">Tensorix</span> é usada como alternativa automática se o Claude falhar — ou como LLM principal dos clips (defina o fornecedor como <span class="font-mono text-ink-soft">tensorx</span> em IA e motor).
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ([
                        'anthropic' => 'Anthropic (Claude)',
                        'tensorx' => 'Tensorix (tensorx.ai / DeepSeek)',
                        'openai' => 'OpenAI',
                        'gemini' => 'Gemini',
                        'apify' => 'Apify token',
                        'kie' => 'kie.ai',
                        'fal' => 'fal.ai (sincronização labial do avatar)',
                        'elevenlabs' => 'ElevenLabs',
                        'youtube' => 'YouTube Data',
                        'tubelab' => 'TubeLab',
                        'reddit_client_id' => 'Reddit client id',
                        'reddit_client_secret' => 'Reddit client secret',
                        'blotato' => 'Blotato (publicação)',
                        'zernio' => 'Zernio (automações de DM)',
                    ] as $chave => $rotulo)
                        <div>
                            <label class="eyebrow block mb-1.5 flex items-center gap-2">
                                {{ $rotulo }}
                                @if ($chavesDefinidas[$chave] ?? false)
                                    <span class="text-teal font-mono text-[0.6rem]">● guardada</span>
                                    <button type="button" wire:click="limparChave('{{ $chave }}')"
                                            wire:confirm="Remover TODAS as chaves {{ $rotulo }} guardadas?"
                                            class="ml-auto font-mono text-[0.6rem] text-ink-faint hover:text-rust underline">remover todas</button>
                                @endif
                            </label>

                            {{-- Stored keys: id + name only, never the secret. --}}
                            @foreach ($chavesGuardadas[$chave] ?? [] as $i => $guardada)
                                <div class="flex items-center gap-2 mb-1.5 font-mono text-[0.65rem] text-ink-soft">
                                    <span class="text-teal">●</span>
                                    <span>{{ $guardada['label'] ?: 'chave ' . ($i + 1) }}</span>
                                    @if ($i === 0)<span class="text-ink-faint">(predefinida)</span>@endif
                                    <span class="text-ink-faint">{{ $guardada['id'] }}</span>
                                    <button type="button" wire:click="removerChave('{{ $guardada['id'] }}')"
                                            wire:confirm="Remover esta chave {{ $rotulo }}?"
                                            class="ml-auto text-ink-faint hover:text-rust underline">remover</button>
                                </div>
                            @endforeach

                            <div class="flex gap-2">
                                <input type="password" autocomplete="off" wire:model="chaves.{{ $chave }}"
                                       placeholder="{{ ($chavesDefinidas[$chave] ?? false) ? 'adicionar outra chave' : 'não definida (usa o .env)' }}"
                                       class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                <input type="text" wire:model="rotulos.{{ $chave }}" placeholder="nome (opcional)"
                                       class="w-32 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-xs focus:border-teal focus:outline-none">
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ KEYS PER STEP ══════════════════════════ --}}
        @if ($secao === 'passos')
            <x-panel eyebrow="Encaminhamento" title="Chaves por passo" glyph="⇄">
                <p class="text-ink-soft -mt-2 mb-4">
                    Que chave guardada cada passo do pipeline usa. <span class="font-mono text-ink-soft">auto</span> mantém o
                    comportamento normal (a cadeia de fornecedores, a chave predefinida de cada um); escolha uma chave para fixar esse passo —
                    isso também faz com que o seu fornecedor seja o primeiro a ser tentado. Adicione as chaves em <span class="font-mono text-ink-soft">Chaves de API</span>.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($passosMeta as $passo => $meta)
                        <div>
                            <label class="eyebrow block mb-1.5">{{ $meta['label'] }}</label>
                            <select wire:model="passos.{{ $passo }}"
                                    class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                                @foreach ($passosOpcoes[$passo] ?? [] as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ ACCOUNT ══════════════════════════ --}}
        @if ($secao === 'conta')
            <x-panel eyebrow="Acesso" title="Conta" glyph="👤">
                <p class="text-ink-soft -mt-2 mb-4">
                    A única conta que entra nesta instalação. Se ainda usa a
                    palavra-passe gerada na instalação, altere-a aqui e depois apague
                    <span class="font-mono text-ink-soft">storage/app/admin_password</span>.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="eyebrow block mb-1.5">Email</label>
                        <input type="email" autocomplete="username" wire:model="conta.email"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('conta.email') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Palavra-passe atual</label>
                        <input type="password" autocomplete="current-password" wire:model="conta.atual" placeholder="obrigatória para guardar"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('conta.atual') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Nova palavra-passe</label>
                        <input type="password" autocomplete="new-password" wire:model="conta.nova" placeholder="deixe em branco para manter"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        @error('conta.nova') <p class="mt-1.5 font-mono text-[0.65rem] text-rust">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="eyebrow block mb-1.5">Confirmar nova palavra-passe</label>
                        <input type="password" autocomplete="new-password" wire:model="conta.nova_confirmation"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <button type="button" wire:click="guardarConta"
                            class="font-mono text-xs uppercase tracking-wider px-3.5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                        Atualizar conta
                    </button>
                    @if ($contaGuardada)
                        <span class="font-mono text-[0.65rem] text-teal">✓ guardado às {{ $contaGuardada }}</span>
                    @endif
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ UPDATES ══════════════════════════ --}}
        @if ($secao === 'sistema')
            <x-panel eyebrow="Instalação" title="Atualizações" glyph="↻">
                <div class="flex items-center gap-3 mb-4">
                    <span class="eyebrow">Versão em execução</span>
                    <span class="font-mono text-sm text-ink">{{ $versaoAtual }}</span>
                </div>

                @unless ($podeAtualizar)
                    <p class="text-ink-soft text-sm mb-3">As atualizações instalam-se <span class="text-teal">automaticamente a cada ~5 min</span> — não há nada a clicar. Para atualizar já, execute <span class="font-mono text-ink-soft">cd /opt/brand-machine &amp;&amp; docker compose pull &amp;&amp; docker compose up -d</span> no servidor.</p>
                @endunless

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="verificarAtualizacoes" wire:loading.attr="disabled" wire:target="verificarAtualizacoes"
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                        <span wire:loading.remove wire:target="verificarAtualizacoes">↻ Procurar atualizações</span>
                        <span wire:loading wire:target="verificarAtualizacoes">a verificar…</span>
                    </button>

                    @if ($atualizacao === 'available' && $podeAtualizar)
                        <button type="button" wire:click="instalarAtualizacao"
                                wire:confirm="Instalar a nova versão agora? A aplicação vai reiniciar — recarregue esta página daqui a ~30 segundos."
                                class="font-display text-lg px-6 py-2 rounded-sm bg-teal text-papyrus hover:bg-teal-deep transition shadow-engraved">
                            ⬇ Instalar e reiniciar
                        </button>
                    @endif
                </div>

                <div class="mt-4 font-mono text-sm">
                    @if ($atualizacao === 'uptodate')
                        <span class="text-good">✓ Tem a versão mais recente.</span>
                    @elseif ($atualizacao === 'available')
                        <span class="text-gold">● Há uma nova versão disponível — instala-se automaticamente em ~5 min (ou execute o comando acima para a obter já).</span>
                    @elseif ($atualizacao === 'updating')
                        <span class="text-teal">↻ Atualização iniciada — a descarregar a nova imagem e a reiniciar. Recarregue esta página daqui a ~30 segundos. (Também se atualiza sozinha em 30 min se algo bloquear isto.)</span>
                    @elseif ($atualizacao === 'update-failed')
                        <span class="text-warn">⚠ Não foi possível contactar o serviço de atualização — pode não estar configurado neste servidor. Volte a correr o instalador no servidor para o reparar e atualizar já: <span class="text-ink">curl -fsSL https://raw.githubusercontent.com/jdportugal/ContentMachine/production/install.sh | bash</span></span>
                    @elseif ($atualizacao === 'error')
                        <span class="text-ink-faint">Não foi possível verificar agora (versão de desenvolvimento, ou o registo está inacessível).</span>
                    @endif
                </div>
            </x-panel>
        @endif

        {{-- ══════════════════════════ WORKERS ══════════════════════════ --}}
        @if ($secao === 'workers')
            {{-- Poll only while something is moving: a worker mid-job, or work
                 waiting with nobody to do it. Idle and all-stopped are steady. --}}
            <div @if ($this->workersBusy) wire:poll.5s @endif>
                <x-panel eyebrow="Casa das máquinas" title="Workers das filas" glyph="⚒">
                    <p class="text-ink-soft -mt-2 mb-4">
                        Cada fila precisa do seu próprio processo worker. Uma fila sem worker aceita tarefas e nunca as
                        executa — ficam à espera, em silêncio, para sempre. Um worker carrega o código uma vez ao arrancar, por isso
                        é o <span class="text-ink">Reiniciar</span> que faz uma alteração de código ter efeito.
                    </p>

                    @if (! $workersGeriveis)
                        <div class="rounded-sm border border-warn/40 bg-warn/5 px-4 py-3 font-mono text-xs text-warn">
                            ⚠ Não é possível inspecionar os workers daqui — o processo web não consegue ler a tabela de processos
                            (na instalação Docker os workers estão noutro contentor). Faça a gestão onde eles correm.
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach ($workers as $w)
                                <div class="flex flex-wrap items-center gap-3 rounded-sm border border-ink-soft/15 bg-papyrus/40 px-4 py-3"
                                     wire:key="worker-{{ $w['queue'] }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm text-ink">{{ $w['queue'] }}</span>
                                            @if ($w['running'])
                                                <span class="font-mono text-[0.6rem] px-2 py-0.5 rounded-sm border border-good/40 text-good">● a correr</span>
                                                @if ($w['busy'] === true)
                                                    <span class="font-mono text-[0.6rem] px-2 py-0.5 rounded-sm border border-teal/40 text-teal">◐ ocupado</span>
                                                @elseif ($w['busy'] === false)
                                                    <span class="font-mono text-[0.6rem] px-2 py-0.5 rounded-sm border border-ink-soft/25 text-ink-faint">○ livre</span>
                                                @else
                                                    <span class="font-mono text-[0.6rem] px-2 py-0.5 rounded-sm border border-ink-soft/25 text-ink-faint">— desconhecido</span>
                                                @endif
                                            @else
                                                <span class="font-mono text-[0.6rem] px-2 py-0.5 rounded-sm border border-bad/40 text-bad">■ parado</span>
                                            @endif
                                        </div>
                                        <p class="font-mono text-[0.6rem] text-ink-faint mt-1">
                                            {{ $w['label'] }}
                                            @if ($w['running'])
                                                · pid {{ implode(', ', $w['pids']) }}@if ($w['uptime']) · ativo há {{ $w['uptime'] }}@endif
                                            @endif
                                        </p>
                                    </div>

                                    <div class="font-mono text-[0.65rem] text-right shrink-0">
                                        <div class="{{ $w['pending'] > 0 && ! $w['running'] ? 'text-warn' : 'text-ink-soft' }}">
                                            {{ $w['pending'] }} em espera
                                        </div>
                                        @if ($w['reserved'] > 0)
                                            <div class="text-teal">{{ $w['reserved'] }} em curso</div>
                                        @endif
                                    </div>

                                    <div class="flex gap-1.5 shrink-0">
                                        @if ($w['running'])
                                            <button type="button" wire:click="reiniciarWorker('{{ $w['queue'] }}')"
                                                    wire:loading.attr="disabled" wire:target="reiniciarWorker('{{ $w['queue'] }}')"
                                                    class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">↻ Reiniciar</button>
                                            <button type="button" wire:click="pararWorker('{{ $w['queue'] }}')"
                                                    wire:confirm="Parar o worker «{{ $w['queue'] }}»? Termina primeiro a tarefa em curso."
                                                    wire:loading.attr="disabled" wire:target="pararWorker('{{ $w['queue'] }}')"
                                                    class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-bad hover:border-bad/40 transition">■ Parar</button>
                                        @else
                                            <button type="button" wire:click="iniciarWorker('{{ $w['queue'] }}')"
                                                    wire:loading.attr="disabled" wire:target="iniciarWorker('{{ $w['queue'] }}')"
                                                    class="font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border border-good/50 text-good hover:bg-good/10 transition">▶ Iniciar</button>
                                        @endif
                                    </div>
                                </div>

                                @if ($w['pending'] > 0 && ! $w['running'])
                                    <p class="font-mono text-[0.6rem] text-warn pl-4 -mt-1">
                                        ↑ {{ $w['pending'] }} tarefa{{ $w['pending'] === 1 ? '' : 's' }} em espera sem worker para as executar.
                                    </p>
                                @endif
                            @endforeach
                        </div>

                        <p class="font-mono text-[0.6rem] text-ink-faint mt-4 pt-3 border-t border-ink-soft/10">
                            @if ($workersSupervisionados)
                                O supervisord gere-os e reinicia-os automaticamente, por isso
                                <span class="text-ink-soft">Parar é temporário</span> — um worker parado volta em segundos.
                                Use-o para libertar a máquina por momentos, não para manter um desligado.
                            @else
                                Iniciado daqui, um worker sobrevive a esta página mas <span class="text-ink-soft">não a um reinício</span> —
                                é um simples processo em segundo plano, não um serviço supervisionado.
                            @endif
                        </p>
                    @endif
                </x-panel>
            </div>
        @endif

        {{-- ── Action bar (saves every settings tab) ────────────────────────── --}}
        @if (! in_array($secao, ['sistema', 'workers'], true))
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-teal text-papyrus font-display text-xl px-7 py-2.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                Guardar definições
            </button>
            @if ($guardado)
                <span class="text-good font-mono text-sm">✓ Guardado às {{ $guardado }} no cofre (definicoes/definicoes.md).</span>
            @endif
        </div>

        <p class="font-mono text-xs text-ink-faint pt-2 border-t border-ink-soft/10">
            🔒 As chaves são guardadas no cofre (<span class="text-ink-soft">definicoes/definicoes.md</span>) para uso local. Não sincronize o cofre para um local público com chaves reais. Guardar grava <span class="text-ink-soft">todos os separadores</span>, não só o aberto.
        </p>
        @endif
    </form>
</div>
