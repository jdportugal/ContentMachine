<div @if ($this->busy) wire:poll.5s @endif>
    <x-page-header
        eyebrow="Tomus · A"
        title="Avatares"
        cota="741.6 · ACM · '26"
        lead="Carregue um vídeo de alguém a falar e o áudio que deve dizer — o Sync Lipsync 2 Pro da fal.ai volta a animar a boca para corresponder." />

    @unless ($configurado)
        <div class="mb-6 px-4 py-3 rounded-sm border border-bad/40 bg-bad/5">
            <p class="font-mono text-[0.7rem] text-bad">Sem chave fal.ai definida. Adicione uma em
                <a href="{{ route('definicoes') }}" wire:navigate class="underline">Definições → Chaves de API</a> antes de gerar.</p>
        </div>
    @endunless

    <x-panel eyebrow="Novo" title="Sincronizar os lábios de um vídeo" glyph="☉">
        <form wire:submit="gerar" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="eyebrow block">Vídeo · o rosto</label>
                    <input type="file" wire:model="video" accept="video/mp4,video/quicktime,video/webm"
                           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink-soft text-sm file:mr-3 file:px-3 file:py-1 file:rounded-sm file:border-0 file:bg-teal/15 file:text-teal file:font-mono file:text-[0.65rem]" />
                    <p class="font-mono text-[0.55rem] text-ink-faint">.mp4 / .mov / .webm / .m4v · até {{ $maxSegundos }}s · menos de 200MB</p>
                    @error('video') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="video" class="font-mono text-[0.6rem] text-teal">a carregar…</div>
                </div>

                <div class="space-y-2">
                    <label class="eyebrow block">Áudio · a voz</label>
                    <input type="file" wire:model="audio" accept="audio/*"
                           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink-soft text-sm file:mr-3 file:px-3 file:py-1 file:rounded-sm file:border-0 file:bg-teal/15 file:text-teal file:font-mono file:text-[0.65rem]" />
                    <p class="font-mono text-[0.55rem] text-ink-faint">mp3 / wav / m4a / aac / ogg · o que passar da duração do vídeo é cortado</p>
                    @error('audio') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="audio" class="font-mono text-[0.6rem] text-teal">a carregar…</div>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <button type="submit" @disabled(! $configurado)
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled" wire:target="gerar,video,audio">
                    ☉ Sincronizar
                </button>
                <span class="font-mono text-[0.6rem] text-ink-faint">
                    ${{ number_format($precoPorMinuto, 2) }} por minuto de vídeo, segundos arredondados para cima — um vídeo de 10s custa ${{ number_format($precoPorMinuto / 6, 2) }}. Demora alguns minutos.
                </span>
            </div>
        </form>
    </x-panel>

    @if ($this->avatars->isNotEmpty())
        @php $total = array_sum($custos); @endphp
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">As suas sincronizações · {{ $this->avatars->count() }}</div>
                @if ($total > 0)
                    <span class="font-mono text-[0.55rem] text-ink-faint" title="Gasto total na fal.ai registado nesta página">
                        total ≈ ${{ number_format($total, 2) }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($this->avatars as $a)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-3 flex flex-col" wire:key="avatar-{{ $a->id() }}">
                        <div class="relative rounded-sm overflow-hidden bg-black/60 flex items-center justify-center aspect-video">
                            @if ($a->status === 'active')
                                {{-- wire:ignore so the poll never restarts playback mid-watch. --}}
                                <video wire:ignore class="w-full h-full object-contain" controls playsinline preload="metadata"
                                       src="{{ route('avatar-gen.media', $a->id()) }}"></video>
                            @elseif ($a->status === 'failed')
                                <div class="p-3 text-center">
                                    <div class="text-bad text-lg">✕</div>
                                    <p class="font-mono text-[0.55rem] text-bad/90 mt-1">{{ \Illuminate\Support\Str::limit($a->error, 200) }}</p>
                                    @if (is_array($a->get('fal_request')))
                                        <p class="font-mono text-[0.5rem] text-ink-faint mt-2">A fal já processou este — Retomar recolhe-o sem pagar de novo.</p>
                                    @endif
                                </div>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                                    <p class="font-mono text-[0.55rem] text-ink-faint mt-2">A sincronizar na fal.ai…</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 min-w-0 flex-1">
                            <p class="text-ink text-sm truncate" title="{{ $a->display_name }}">{{ $a->display_name ?: $a->id() }}</p>
                            <p class="font-mono text-[0.55rem] text-ink-faint mt-1 truncate">♪ {{ $a->get('audio_name', '—') }}</p>
                            <p class="font-mono text-[0.55rem] text-ink-faint mt-1">
                                @if ($a->get('duration')) {{ $a->get('duration') }}s @endif
                                @if (($custos[$a->id()] ?? 0) > 0)
                                    · <span class="text-teal"
                                            title="{{ $a->get('cost_estimated') ? 'Estimado — a fal não indicou as unidades faturáveis deste pedido' : 'O que a fal indica ter faturado por este pedido' }}">
                                        {{ $a->get('cost_estimated') ? '≈' : '' }}${{ number_format($custos[$a->id()], 2) }}</span>
                                @elseif ($a->status === 'active')
                                    · <span class="text-ink-faint" title="O ffprobe não conseguiu medir o resultado, por isso não foi possível calcular o custo por minuto — a fal faturou-o na mesma">custo não medido</span>
                                @endif
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($a->status === 'failed' && is_array($a->get('fal_request')))
                                <button type="button" wire:click="retomar('{{ $a->id() }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">↻ Retomar</button>
                            @endif
                            @if ($a->status === 'active')
                                <a href="{{ route('avatar-gen.media', $a->id()) }}?download=1"
                                   class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">⤓ Descarregar .mp4</a>
                            @endif
                            <button type="button" wire:click="apagar('{{ $a->id() }}')"
                                    wire:confirm="Apagar esta sincronização e o respetivo vídeo?"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕ Apagar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <x-empty-state class="mt-8" glyph="☉" title="Ainda sem sincronizações"
                       note="Carregue um vídeo e um áudio acima e o resultado aparece aqui, pronto a descarregar." />
    @endif
</div>
