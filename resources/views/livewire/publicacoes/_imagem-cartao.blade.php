@php
    $im = $img[$i] ?? null;
    $g = $gerando[$i] ?? null;
    $h = $hist[$i] ?? [];
    $src = fn ($p) => \Illuminate\Support\Str::startsWith($p, 'http') ? $p : asset($p);
    // Lista ordenada de todas as imagens da peça + posição deste cartão nela.
    $ordenadas = $img;
    ksort($ordenadas);
    $galeria = array_values(array_map($src, $ordenadas));
    $pos = count(array_filter(array_keys($ordenadas), fn ($k) => $k < $i));
@endphp
<div class="w-44 shrink-0">
    @if ($g)
        <div class="aspect-[4/5] rounded-sm border border-gold/30 bg-vellum/40 flex items-center justify-center text-center p-2">
            <span class="font-mono text-[0.6rem] text-gold animate-pulse">❖ a desenhar…</span>
        </div>
    @elseif ($im)
        <button type="button" @click="$dispatch('abrir-lightbox', {imgs: @js($galeria), i: {{ $pos }}})"
                title="Ver em ecrã inteiro"
                class="block w-full border border-ink-soft/20 rounded-sm overflow-hidden bg-vellum/40 shadow-engraved hover:border-teal/50 transition">
            <img src="{{ $src($im) }}" class="w-full block" alt="Cartão {{ $i + 1 }}">
        </button>
        @if (count($h))
            <div class="mt-2">
                <div class="font-mono text-[0.55rem] text-ink-faint mb-1">versões anteriores</div>
                <div class="flex gap-1 flex-wrap">
                    @foreach ($h as $k => $hp)
                        <button type="button" wire:click="restaurarVersao({{ $i }}, @js($hp))" title="Restaurar esta versão"
                                wire:key="h-{{ $i }}-{{ $k }}"
                                class="w-8 h-10 rounded-sm overflow-hidden border border-ink-soft/20 hover:border-teal transition">
                            <img src="{{ $src($hp) }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="aspect-[4/5] rounded-sm border border-dashed border-ink-soft/25 text-ink-faint flex flex-col items-center justify-center gap-1 text-center p-2">
            <span class="text-2xl text-gold/50">❖</span>
            <span class="font-mono text-[0.55rem]">sem imagem</span>
        </div>
    @endif
</div>
