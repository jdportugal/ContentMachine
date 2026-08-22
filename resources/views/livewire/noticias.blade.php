<div>
    <x-page-header
        eyebrow="Tomus · VII"
        title="News Aggregator"
        cota="070.4 · ACM · '26"
        lead="Gathers content from multiple sources and distills a personalized report, ready to become a script." />

    {{-- ============ Multi-platform aggregator ============ --}}
    <x-panel eyebrow="Multi-platform collection" title="Channel content" glyph="▶" class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
            <p class="text-ink-soft max-w-xl">
                Scans the channels configured in <a href="{{ route('definicoes') }}" class="text-teal hover:underline">Settings</a>
                (YouTube, TikTok, Instagram, LinkedIn) and archives each item in the vault <em>by day</em>, with a transcript and a list of topics.
            </p>
            <button wire:click="agregarAgora" @disabled($aAgregar || empty($plataformasSelecionadas))
                    class="shrink-0 bg-teal text-papyrus font-display text-lg px-6 py-2.5 rounded-sm hover:bg-teal-deep transition shadow-engraved disabled:opacity-50">
                {{ $aAgregar ? 'Scanning channels…' : 'Aggregate now' }}
            </button>
        </div>

        {{-- Platform selector: choose which channels to collect from. --}}
        @if (!empty($plataformasDisponiveis))
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="eyebrow shrink-0">Day:</span>
                <select wire:model="diaAgregacao" @disabled($aAgregar)
                        class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink font-mono text-xs focus:border-teal focus:outline-none disabled:opacity-50">
                    <option value="0">today</option>
                    <option value="1">1 day ago</option>
                    <option value="2">2 days ago</option>
                </select>
                <span class="eyebrow shrink-0 ml-3">Collect from:</span>
                @foreach ($plataformasDisponiveis as $p)
                    @php $pm = config('contentmachine.plataformas_meta.'.$p, ['label' => ucfirst($p), 'glifo' => '•', 'cor' => '#8AE0FF']); @endphp
                    <button type="button" wire:click="alternarPlataforma('{{ $p }}')" @disabled($aAgregar)
                            class="font-mono text-xs px-3 py-1 rounded-sm border transition disabled:opacity-50
                                   {{ in_array($p, $plataformasSelecionadas, true) ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                        <span style="color: {{ $pm['cor'] }}">{{ $pm['glifo'] }}</span> {{ $pm['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
        @if ($aAgregar)
            {{-- Loader card, not the full-screen overlay: the collection takes
                 minutes and the rest of the page stays usable meanwhile.
                 Polls the worker until it finishes. --}}
            <div wire:poll.2s="verificarAgregacao"
                 role="status" aria-live="polite" aria-busy="true"
                 class="mt-3 flex items-center gap-4 rounded-sm border border-teal/30 bg-teal/5 px-4 py-3 shadow-engraved">
                <div class="cm-loader__dots mt-0! shrink-0" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="min-w-0">
                    <p class="cm-loader__msg text-sm!">Scanning the channels…</p>
                    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">
                        Collecting in the queue… needs a worker: <span class="text-teal">php artisan queue:work</span>
                    </p>
                </div>
            </div>
        @endif

        @if ($resumoAgregacao)
            <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-metric-card label="Items collected" :value="$resumoAgregacao['total']" accent="#5A7BFF" />
                @foreach ($resumoAgregacao['por_plataforma'] as $plat => $n)
                    <x-metric-card :label="$plat" :value="$n" :accent="config('contentmachine.plataformas_meta.'.$plat.'.cor', '#8AE0FF')" />
                @endforeach
            </div>
            @if (!empty($resumoAgregacao['avisos']))
                <div class="mt-3 space-y-1">
                    @foreach ($resumoAgregacao['avisos'] as $aviso)
                        <div class="border border-warn/40 bg-warn/10 text-warn rounded-sm px-3 py-1.5 font-mono text-xs">⚠ {{ $aviso }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Day selector --}}
        @if ($dias->isNotEmpty())
            <x-fleuron glyph="☙" />
            <div class="flex flex-wrap items-center gap-2 mb-4">
                @foreach ($dias->take(3) as $d)
                    <button wire:click="focarDia('{{ $d }}')"
                            class="font-mono text-xs px-3 py-1 rounded-sm border transition
                                   {{ $d === $diaAtivo ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                        {{ \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') }}
                    </button>
                @endforeach
                @php $diasAntigos = $dias->slice(3)->values(); @endphp
                @if ($diasAntigos->isNotEmpty())
                    <select wire:change="focarDia($event.target.value)"
                            class="font-mono text-xs px-3 py-1 rounded-sm border bg-papyrus/60 focus:border-teal focus:outline-none
                                   {{ $diasAntigos->contains($diaAtivo) ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft' }}">
                        <option value="" disabled {{ $diasAntigos->contains($diaAtivo) ? '' : 'selected' }}>Older days…</option>
                        @foreach ($diasAntigos as $d)
                            <option value="{{ $d }}" {{ $d === $diaAtivo ? 'selected' : '' }}>
                                {{ \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M Y') }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Items of the day --}}
                <div class="lg:col-span-2 space-y-3 min-w-0">
                    <div class="eyebrow mb-1">Items from {{ \Illuminate\Support\Carbon::parse($diaAtivo)->translatedFormat('d M Y') }}</div>
                    @forelse ($itensDoDia as $nota)
                        @php $m = config('contentmachine.plataformas_meta.'.$nota->get('plataforma'), ['cor' => '#8AE0FF', 'glifo' => '•']); @endphp
                        <div class="flex gap-4 py-3 border-b border-ink-soft/10 last:border-0">
                            @if ($nota->get('thumbnail'))
                                <img src="{{ $nota->get('thumbnail') }}" alt="" loading="lazy"
                                     onerror="this.style.display='none'"
                                     class="shrink-0 w-28 h-16 object-cover rounded-sm border border-ink-soft/15">
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ $nota->get('url') }}" target="_blank" rel="noopener"
                                   class="font-body text-ink hover:text-teal transition line-clamp-2">{{ $nota->title() }}</a>
                                @php
                                    // Synopsis: AI summary (preferred) or the start of the transcript — never the promotional links.
                                    $sinopse = trim((string) $nota->get('resumo'));
                                    if ($sinopse === '' && preg_match('/##\s*Transcri[cç][aã]o\s*\n+(.*)$/isu', $nota->body, $mm)) {
                                        $sinopse = \Illuminate\Support\Str::of($mm[1])->squish()->limit(200)->toString();
                                        $sinopse = $sinopse === '_Sem transcrição disponível._' ? '' : $sinopse;
                                    }
                                @endphp
                                @if ($sinopse !== '')
                                    <p class="mt-1 text-sm text-ink-soft line-clamp-2">{{ $sinopse }}</p>
                                @endif
                                <div class="mt-1 flex items-center gap-2 flex-wrap">
                                    <x-badge tone="leather"><span style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span> {{ $nota->get('plataforma') }}</x-badge>
                                    <span class="font-mono text-[0.62rem] text-ink-faint">{{ $nota->get('canal') }}</span>
                                    @if (!empty($nota->get('fontes')))
                                        <x-badge tone="gold">☍ {{ count($nota->get('fontes')) }} sources</x-badge>
                                    @endif
                                </div>
                                @if (!empty($nota->get('tags')))
                                    <div class="mt-1 font-mono text-[0.6rem] text-ink-faint truncate">
                                        {{ collect($nota->get('tags'))->take(6)->map(fn ($t) => '#'.$t)->implode(' ') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-ink-soft italic">No items archived for this day.</p>
                    @endforelse
                </div>

                {{-- Topics of the day --}}
                <div class="min-w-0">
                    <div class="eyebrow mb-1">Topics «on air»</div>
                    @if ($topicosHtml)
                        <div class="prose-nocturna text-ink-soft text-sm leading-relaxed [&_h2]:font-display [&_h2]:text-ink [&_h2]:text-lg [&_h2]:mt-4 [&_h2]:mb-1 [&_a]:text-teal [&_ul]:list-disc [&_ul]:pl-4 [&_li]:my-0.5">
                            {!! $topicosHtml !!}
                        </div>
                    @else
                        <p class="text-ink-soft italic">No topics for this day yet.</p>
                    @endif
                </div>
            </div>
        @else
            <div class="mt-4 border border-ink-soft/15 rounded-sm px-4 py-6 text-center text-ink-soft italic">
                Nothing aggregated yet. Click «Aggregate now» to collect the configured channels.
            </div>
        @endif
    </x-panel>

    {{-- ============ Report by period ============ --}}
    @php($ehDicas = $tipoRelatorio === 'dicas')
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Generator --}}
        <div class="lg:col-span-1 min-w-0">
            <x-panel eyebrow="{{ $ehDicas ? 'Tool tips' : 'Report' }}"
                     title="{{ $ehDicas ? 'Create tip scripts' : 'Create report' }}"
                     glyph="{{ $ehDicas ? '✦' : '☙' }}">
                <p class="text-ink-soft text-sm -mt-2 mb-4">
                    @if ($ehDicas)
                        Mines the aggregated items for the practical tricks buried in them — a setting, a flag, a workflow —
                        and writes each as its own short-form script.
                    @else
                        Distills the already aggregated items into a report, ready to become a script.
                    @endif
                </p>

                <label class="eyebrow block mb-1.5">Write</label>
                <div class="flex gap-2 mb-4">
                    @foreach (['noticias' => 'News', 'dicas' => 'Tool tips'] as $tipo => $rotulo)
                        <button type="button" wire:click="$set('tipoRelatorio', '{{ $tipo }}')"
                                class="flex-1 px-3 py-1.5 rounded-sm border font-mono text-xs transition
                                       {{ $tipoRelatorio === $tipo ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                <label class="eyebrow block mb-1.5">Period</label>
                <div class="flex gap-2 mb-4">
                    @foreach (['dia' => 'Day', 'semana' => 'Week'] as $modo => $rotulo)
                        <button type="button" wire:click="$set('modoRelatorio', '{{ $modo }}')"
                                class="flex-1 px-3 py-1.5 rounded-sm border font-mono text-xs transition
                                       {{ $modoRelatorio === $modo ? 'border-teal text-teal bg-teal/10' : 'border-ink-soft/25 text-ink-soft hover:text-ink' }}">
                            {{ $rotulo }}
                        </button>
                    @endforeach
                </div>

                <label class="eyebrow block mb-1.5">{{ $modoRelatorio === 'semana' ? '7 days up to' : 'Day' }}</label>
                <input type="date" wire:model="dataRelatorio"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">

                <label class="eyebrow block mb-1.5 mt-3">Language</label>
                <select wire:model="idiomaRelatorio"
                        class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    @foreach (['English', 'European Portuguese', 'Spanish', 'French', 'German'] as $lang)
                        <option value="{{ $lang }}">{{ $lang }}</option>
                    @endforeach
                </select>

                <button wire:click="criarRelatorio" @disabled($aGerar)
                        class="mt-4 w-full bg-teal text-papyrus font-display text-base px-4 py-2 rounded-sm hover:bg-teal-deep transition shadow-engraved disabled:opacity-50">
                    @if ($aGerar)
                        {{ $ehDicas ? 'Mining for tips…' : 'Collecting and writing…' }}
                    @else
                        {{ $ehDicas ? 'Create tip scripts' : 'Create news report' }}
                    @endif
                </button>

                @if ($aGerar)
                    {{-- Poll the worker until the report is ready. --}}
                    <div wire:poll.2s="verificarRelatorio" class="mt-2 font-mono text-[0.6rem] text-ink-faint">
                        Running in the queue… needs a worker: <span class="text-teal">php artisan queue:work</span>
                    </div>
                @endif

                @if ($avisoRelatorio)
                    <div class="mt-3 border border-bad/40 bg-bad/10 text-bad rounded-sm px-3 py-2 font-mono text-xs">
                        {{ $avisoRelatorio }}
                    </div>
                @endif

                @if ($relatorioGuardado)
                    <div class="mt-3 border border-good/40 bg-good/10 text-good rounded-sm px-3 py-2 font-mono text-xs break-all">
                        ✓ Saved in the vault: {{ $relatorioGuardado }}
                    </div>
                @endif
            </x-panel>
        </div>

        {{-- Generated report --}}
        <div class="lg:col-span-3 min-w-0">
            <x-panel>
                @if (!empty($relatoriosPassados))
                    <div class="flex flex-wrap items-center gap-3 mb-4 pb-4 border-b border-ink-soft/15">
                        <label for="relatorioSelecionado" class="eyebrow shrink-0">{{ $ehDicas ? 'Previous tip scripts' : 'Previous reports' }}</label>
                        <select id="relatorioSelecionado" wire:model.live="relatorioSelecionado"
                                class="flex-1 min-w-0 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5
                                       text-ink font-mono text-sm focus:border-teal focus:outline-none">
                            @foreach ($relatoriosPassados as $r)
                                <option value="{{ $r['path'] }}">{{ $r['rotulo'] }}</option>
                            @endforeach
                        </select>
                        <span wire:loading wire:target="relatorioSelecionado" class="text-ink-faint font-mono text-xs shrink-0">opening…</span>
                    </div>
                @endif

                @if ($relatorio)
                    <div class="flex items-start justify-between gap-4 mb-2">
                        <div>
                            <div class="eyebrow mb-1">{{ $ehDicas ? 'Tool tips' : 'Report' }} · {{ $relatorio['modo'] }} · {{ $relatorio['total'] }} item(s)</div>
                            <h2 class="font-display text-3xl text-ink leading-tight">{{ $relatorio['titulo'] }}</h2>
                        </div>
                        <x-selo label="Brand Machine" sub="{{ $ehDicas ? 'TIPS' : 'NEWS' }}" date="MMXXVI" color="#FFB347" />
                    </div>

                    @if (!empty($relatorio['redacao']))
                        <div class="mt-2 max-w-3xl text-ink leading-relaxed
                                    [&_p]:my-3 [&_strong]:text-teal [&_strong]:font-display [&_strong]:text-lg
                                    [&_hr]:my-4 [&_hr]:border-0 [&_hr]:border-t [&_hr]:border-ink-soft/15
                                    [&_h1]:font-display [&_h1]:text-2xl [&_h1]:text-ink [&_h2]:font-display [&_h2]:text-xl [&_h2]:text-ink
                                    [&_ul]:list-disc [&_ul]:pl-5 [&_li]:my-1 [&_a]:text-teal">
                            {!! \Illuminate\Support\Str::markdown($relatorio['redacao'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                        </div>
                        <div class="mt-2 font-mono text-[0.6rem] text-ink-faint">script · {{ $relatorio['redacao_metodo'] ?? 'heuristica' }}</div>
                    @else
                        <p class="text-lg text-ink-soft italic dropcap">{{ $relatorio['resumo'] }}</p>
                    @endif

                    @if (!empty($relatorio['por_plataforma']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($relatorio['por_plataforma'] as $plat => $n)
                                <x-badge tone="leather">{{ $plat }} · {{ $n }}</x-badge>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['destaques']))
                        <x-fleuron glyph="☙" />
                        <div class="eyebrow mb-2">Highlights</div>
                        <div class="space-y-1">
                            @foreach ($relatorio['destaques'] as $d)
                                <div class="flex items-start gap-3 py-2.5 border-b border-ink-soft/10 last:border-0">
                                    <x-badge tone="leather">{{ $d['plataforma'] }}</x-badge>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="text-ink hover:text-teal transition line-clamp-2">{{ $d['titulo'] }}</a>
                                        <div class="text-sm text-ink-soft italic">↳ {{ $d['angulo'] }}</div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="font-display text-2xl text-teal leading-none">{{ $d['relevancia'] }}</div>
                                        <div class="eyebrow !text-[0.55rem]">relevance</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['topicos']))
                        <x-fleuron glyph="❧" />
                        <div class="eyebrow mb-2">Topics</div>
                        <div class="space-y-3">
                            @foreach ($relatorio['topicos'] as $t)
                                <div>
                                    <div class="font-display text-lg text-ink">{{ $t['topico'] }}</div>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($t['itens'] as $it)
                                            <li class="text-sm text-ink-soft flex items-center gap-2">
                                                <x-badge tone="teal">{{ $it['plataforma'] }}</x-badge>
                                                <a href="{{ $it['url'] }}" target="_blank" rel="noopener" class="hover:text-teal truncate">{{ $it['titulo'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($relatorio['ideias_guiao']))
                        <x-fleuron glyph="✒" />
                        <div class="eyebrow mb-2">{{ $ehDicas ? 'Angles left to mine' : 'Script ideas' }}</div>
                        <ul class="space-y-2">
                            @foreach ($relatorio['ideias_guiao'] as $ideia)
                                <li class="flex gap-3 text-ink"><span class="text-gold shrink-0">❦</span><span>{{ $ideia }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <div class="py-16 text-center">
                        <div class="text-5xl text-gold/60 mb-3 select-none">{{ $ehDicas ? '✦' : '☙' }}</div>
                        <p class="text-ink-soft italic">Choose a period and click «{{ $ehDicas ? 'Create tip scripts' : 'Create news report' }}».</p>
                        <p class="text-ink-faint text-sm mt-2">
                            {{ $ehDicas ? 'The tips are mined from' : 'The report uses' }} the items already collected with «Aggregate now».
                        </p>
                    </div>
                @endif
            </x-panel>
        </div>
    </div>
</div>
