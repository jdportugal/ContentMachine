<div>
    <x-page-header
        eyebrow="Tomus · V"
        title="Posts"
        cota="686.2 · IAT · '26"
        lead="Your social media pieces. Compose new ones or review the ones already created." />

    @if (session('oficina_brief'))
        <div class="mb-6 flex items-start gap-2 border border-teal/40 bg-teal/10 text-teal rounded-sm px-4 py-3 font-mono text-sm">
            <span>✎</span>
            <span>Video text loaded — choose the format below and the brief comes pre-filled in the workshop.</span>
        </div>
    @endif

    {{-- Created posts --}}
    <x-panel eyebrow="Shelf" title="Created posts" glyph="❦" class="mb-8">
      <div @if ($this->algumAGerar) wire:poll.3s @endif>
        @forelse ($this->publicacoes as $nota)
            @php
                $tipo = $nota->get('tipo', 'post');
                $def = config('contentmachine.publicacoes.tipos.'.$tipo, []);
                $imagens = (array) $nota->get('imagens', []);
                $capa = $imagens[0] ?? null;
                $galeria = collect($imagens)->map(fn ($p) => \Illuminate\Support\Str::startsWith($p, 'http') ? $p : asset($p))->values();
                $meta = config('contentmachine.plataformas_meta.'.$nota->get('plataforma'));
                $agendado = $nota->get('estado') === 'agendado';
                $pronto = $nota->get('estado') === 'pronto';
                $editUrl = route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]);
                $gerando = $this->aGerar($nota->slug());
            @endphp
            <div class="flex items-center gap-4 py-3 border-b border-ink-soft/10 last:border-0" wire:key="pub-{{ $nota->slug() }}">
                @if ($gerando)
                    <div class="shrink-0 w-14 h-16 rounded-sm border border-gold/30 bg-vellum/40 flex items-center justify-center" title="Generating images…">
                        <span class="inline-block w-5 h-5 border-2 border-gold/30 border-t-gold rounded-full animate-spin"></span>
                    </div>
                @elseif ($capa)
                    <button type="button" @click="$dispatch('abrir-lightbox', {imgs: @js($galeria), i: 0})"
                            title="View fullscreen" class="shrink-0 rounded-sm overflow-hidden border border-ink-soft/20 hover:border-teal/60 transition">
                        <img src="{{ \Illuminate\Support\Str::startsWith($capa, 'http') ? $capa : asset($capa) }}" alt="cover" class="w-14 h-16 object-cover block">
                    </button>
                @else
                    <a href="{{ $editUrl }}" class="shrink-0 w-14 h-16 rounded-sm border border-ink-soft/20 bg-vellum/50 flex items-center justify-center text-2xl text-gold/60">{{ $def['glifo'] ?? '❦' }}</a>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <x-badge tone="teal">{{ $def['label'] ?? $tipo }}</x-badge>
                        @if (($n = (int) $nota->get('cartoes', 0)) > 1)<x-badge tone="leather">{{ $n }} cards</x-badge>@endif
                        @if ($meta)<x-badge tone="leather" style="color: {{ $meta['cor'] }}">{{ $meta['label'] }}</x-badge>@endif
                        @if ($gerando)
                            <span class="font-mono text-[0.6rem] px-1.5 py-0.5 rounded-sm border border-gold/40 text-gold animate-pulse">❖ generating…</span>
                        @elseif ($agendado)<x-badge tone="good">✓ scheduled</x-badge>@elseif ($pronto)<x-badge tone="good">✓ ready</x-badge>@else<x-badge tone="warn">draft</x-badge>@endif
                    </div>
                    <a href="{{ route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]) }}" class="font-display text-xl text-ink hover:text-teal transition leading-tight">{{ $nota->title() }}</a>
                </div>
                <a href="{{ route('publicacoes.oficina', ['tipo' => $tipo, 'nota' => $nota->slug()]) }}"
                   class="shrink-0 font-mono text-[0.62rem] text-teal hover:underline">edit →</a>
                @unless ($gerando || $agendado)
                    <button wire:click="alternarPronto('{{ $nota->path }}')"
                            class="shrink-0 font-mono text-[0.62rem] px-2 py-1 rounded-sm border transition
                                   {{ $pronto ? 'border-good/40 text-good hover:bg-good/10' : 'border-teal/40 text-teal hover:bg-teal/10' }}"
                            title="{{ $pronto ? 'Back to draft' : 'Send to Finished — ready to publish' }}">
                        {{ $pronto ? 'reopen' : 'send to Finished' }}
                    </button>
                @endunless
                <button wire:click="remover('{{ $nota->path }}')" wire:confirm="Remove this post?"
                        class="shrink-0 text-ink-faint hover:text-bad px-1 text-lg" title="Remove">🗑</button>
            </div>
        @empty
            <x-empty-state>No posts yet. Compose the first one below.</x-empty-state>
        @endforelse
      </div>
    </x-panel>

    {{-- New post --}}
    <div class="eyebrow mb-3">New post · choose the format</div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($this->tipos as $tipo => $def)
            <a href="{{ route('publicacoes.oficina', $tipo) }}" class="block group" wire:key="tipo-{{ $tipo }}">
                <x-panel class="h-full transition group-hover:border-teal/40">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="eyebrow mb-1">{{ $def['formato'] === 'carousel' ? 'Carousel' : 'Single piece' }} · {{ $def['proporcao'] }}</div>
                            <h2 class="font-display text-2xl text-ink">{{ $def['label'] }}</h2>
                            <p class="mt-1 text-ink-soft text-sm">{{ $def['descricao'] }}</p>
                        </div>
                        <span class="text-3xl text-gold/70 select-none">{{ $def['glifo'] }}</span>
                    </div>
                    <div class="mt-4 font-mono text-[0.62rem] text-teal">open workshop →</div>
                </x-panel>
            </a>
        @endforeach
    </div>

    @include('livewire.publicacoes._lightbox')
</div>
