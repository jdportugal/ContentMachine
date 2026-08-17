<div>
    <x-page-header
        eyebrow="Tomus · III"
        title="Posts Generator"
        cota="778.6 · ACM · '26"
        lead="Turn a long video into posts and carousels. The AI reads the transcript and proposes angles; each one opens pre-filled in the post workshop." />

    @include('livewire.partials.transformer-tabs')

    @if ($this->fontes->isEmpty())
        <x-empty-state glyph="◇" title="No long videos yet"
                       note="Add a long video in the Shorts Generator first — posts are written from its transcript." />
    @else
        <div class="space-y-4">
            @foreach ($this->fontes as $fonte)
                @php
                    $sugestoes = $this->sugestoes($fonte);
                    $transcrita = $this->transcrita($fonte);
                    $aberto = $aberta === $fonte->path;
                @endphp
                <x-panel wire:key="fonte-{{ $fonte->path }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="eyebrow mb-1">Long video</div>
                            <h2 class="font-display text-2xl text-ink leading-tight">{{ $fonte->title() }}</h2>
                            <p class="font-mono text-[0.6rem] text-ink-faint mt-1">
                                {{ $transcrita ? 'transcribed' : 'not transcribed yet' }}
                                @if ($sugestoes) · {{ count($sugestoes) }} post idea{{ count($sugestoes) === 1 ? '' : 's' }} @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 shrink-0">
                            @if ($transcrita)
                                <button type="button" wire:click="sugerir('{{ $fonte->path }}')"
                                        wire:loading.attr="disabled" wire:target="sugerir('{{ $fonte->path }}')"
                                        class="font-display text-lg px-5 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="sugerir('{{ $fonte->path }}')">✦ {{ $sugestoes ? 'Suggest again' : 'Suggest posts' }}</span>
                                    <span wire:loading wire:target="sugerir('{{ $fonte->path }}')">thinking…</span>
                                </button>
                            @else
                                <a href="{{ route('clips') }}" wire:navigate
                                   class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition self-center">
                                    Transcribe it first →
                                </a>
                            @endif
                            @if ($sugestoes)
                                <button type="button" wire:click="alternar('{{ $fonte->path }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-ink transition">
                                    {{ $aberto ? '▲ hide' : '▼ show ideas' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    @if ($aberto && $sugestoes)
                        <div class="mt-5 space-y-3 border-t border-ink-soft/10 pt-4">
                            @foreach ($sugestoes as $i => $s)
                                <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-3 flex flex-wrap items-start justify-between gap-3"
                                     wire:key="ideia-{{ $fonte->path }}-{{ $i }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-display text-lg text-ink leading-tight">{{ $s['titulo'] ?? 'Untitled idea' }}</p>
                                        @if (!empty($s['angulo']))
                                            <p class="text-ink-soft text-sm mt-1">{{ $s['angulo'] }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-2 shrink-0">
                                        <button type="button" wire:click="abrir('{{ $fonte->path }}', {{ $i }}, 'post')"
                                                class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">→ Post</button>
                                        <button type="button" wire:click="abrir('{{ $fonte->path }}', {{ $i }}, 'carrossel')"
                                                class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">→ Carousel</button>
                                        <button type="button" wire:click="abrir('{{ $fonte->path }}', {{ $i }}, null)"
                                                class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">Choose format</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-panel>
            @endforeach
        </div>
    @endif
</div>
