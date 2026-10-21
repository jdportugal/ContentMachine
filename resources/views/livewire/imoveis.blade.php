<div @if ($aDecorrer) wire:poll.5s @endif class="font-sans">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="eyebrow">Portal do consultor</span>
            <h1 class="mt-2 font-display text-5xl font-medium leading-none">Os meus vídeos</h1>
        </div>
    </div>

    {{-- Novo vídeo --}}
    <form wire:submit="criar" class="mt-8 rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-7 md:p-8">
        <div class="flex items-center gap-3.5">
            <span class="w-10 h-px bg-cyan"></span>
            <span class="text-xs tracking-[.32em] text-cyan">NOVO VÍDEO</span>
        </div>
        <label for="anuncio" class="mt-4 block font-display text-3xl leading-tight">Angariou um imóvel? Cole o link do anúncio.</label>
        <div class="mt-5 flex flex-wrap gap-3">
            <input id="anuncio" type="url" wire:model="anuncio" placeholder="https://www.remax.pt/pt/imoveis/…"
                   class="flex-[1_1_360px] min-w-0 min-h-[52px] px-4 rounded-[10px] bg-ivory text-navy text-[15px] border-0 focus:outline-none focus:ring-2 focus:ring-cyan">
            <button type="submit" wire:loading.attr="disabled" wire:target="criar, foto"
                    class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60">
                <span wire:loading.remove wire:target="criar">Criar vídeo →</span>
                <span wire:loading wire:target="criar">A enviar…</span>
            </button>
        </div>
        @error('anuncio') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror

        <details class="mt-5 group">
            <summary class="inline-flex items-center gap-2 min-h-11 text-sm text-[#C9CED6] cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <span aria-hidden="true" class="transition-transform group-open:rotate-90">›</span> Notas para o texto e a sua foto (opcional)
            </summary>
            <div class="mt-3 grid gap-4 md:grid-cols-[2fr_1fr]">
                <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Notas para o texto — mandam sobre o anúncio
                    <textarea wire:model="notas" rows="3" placeholder="Ex.: O preço baixou para 265.000 €. Destacar a vista mar. Não falar do sótão."
                              class="px-4 py-3 rounded-[10px] bg-navy-3 text-ivory text-[15px] border border-ivory/15 placeholder:text-[#8B95A7] focus:outline-none focus:border-cyan"></textarea>
                </label>
                <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">A sua foto (substitui a do anúncio)
                    <input type="file" accept="image/*" wire:model="foto" class="text-sm text-[#C9CED6] file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-ivory file:text-navy file:font-medium">
                    <span wire:loading wire:target="foto" class="text-xs text-cyan">A carregar…</span>
                </label>
            </div>
            @error('notas') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
            @error('foto') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        </details>
        <p class="mt-4 text-xs text-[#AEB5C1]">Lemos anúncios da RE/MAX, iad e idealista. O vídeo fica pronto aqui em poucos minutos.</p>
    </form>

    {{-- Lista --}}
    @if ($this->videos->isEmpty())
        <div class="mt-10 rounded-2xl border border-dashed border-ink-soft/30 px-8 py-14 text-center">
            <p class="font-display text-3xl">Ainda não há vídeos.</p>
            <p class="mt-2 text-sm text-ink-soft">Cole o link de um anúncio acima para criar o primeiro.</p>
        </div>
    @else
        <div class="mt-10 grid gap-6 grid-cols-[repeat(auto-fill,minmax(250px,1fr))]">
            @foreach ($this->videos as $v)
                @php
                    $l = $v->meta['listing'] ?? [];
                    $titulo = $l['title'] ?? $v->title;
                    $preco = ! empty($l['price']) ? number_format((float) $l['price'], 0, ',', '.').' €' : null;
                    [$etapa, $pct] = \App\Livewire\Imoveis::ESTADOS[$v->status] ?? ['A preparar', 10];
                @endphp
                <article wire:key="v-{{ $v->id }}" class="rounded-2xl overflow-hidden bg-navy-2 border border-ivory/10 flex flex-col">
                    <div class="relative aspect-[9/16] bg-navy-3">
                        @if ($v->status === 'done')
                            <video src="{{ route('clips-animados.media', $v->id) }}" controls playsinline preload="metadata" class="absolute inset-0 w-full h-full object-cover"></video>
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-[#E3F0E7] text-[#22603A] text-xs font-semibold">Pronto</span>
                        @elseif ($v->status === 'failed')
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-ivory">
                                <span class="px-2.5 py-1 rounded-full bg-coral text-navy text-xs font-semibold">Não foi possível</span>
                                <p class="text-sm text-[#C9CED6]">Não conseguimos fazer este vídeo. Verifique o link ou contacte-nos.</p>
                            </div>
                        @else
                            <div class="absolute inset-0 flex flex-col justify-end gap-2 p-5 text-ivory">
                                <span class="self-start px-2.5 py-1 rounded-full bg-[#FBEBD9] text-[#8A4B12] text-xs font-semibold">A preparar</span>
                                <div class="h-1 rounded-full bg-ivory/20 overflow-hidden"><div class="h-full bg-cyan animate-pulse" style="width: {{ $pct }}%"></div></div>
                                <p class="text-xs text-[#C9CED6]">{{ $etapa }}…</p>
                            </div>
                        @endif
                    </div>
                    <div class="p-4 flex flex-col gap-1 flex-1">
                        <h2 class="font-sans text-base font-semibold leading-snug tracking-normal">{{ $titulo }}</h2>
                        @if ($preco) <p class="text-sm text-ink-soft">{{ $preco }}</p> @endif
                        <p class="text-xs text-ink-faint">{{ \Illuminate\Support\Carbon::parse($v->created_at)->locale('pt_PT')->translatedFormat('j M Y, H:i') }}</p>
                        @if ($v->status === 'done')
                            <div class="mt-3 flex gap-2">
                                <a href="{{ route('clips-animados.media', ['id' => $v->id, 'download' => 1]) }}" class="flex-1 inline-flex items-center justify-center gap-2 min-h-11 rounded-full bg-coral text-navy text-sm font-semibold hover:brightness-105">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                                    Descarregar
                                </a>
                                @if (! empty($l['url']))
                                    <a href="{{ $l['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center min-h-11 px-4 rounded-full border border-shelf text-sm font-medium text-ink hover:bg-surface">Anúncio ↗</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
