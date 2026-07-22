@php
    $im = $img[$i] ?? null;
    $g = $gerando[$i] ?? null;
    $h = $hist[$i] ?? [];
    $src = fn ($p) => \Illuminate\Support\Str::startsWith($p, 'http') ? $p : asset($p);
@endphp
<div class="w-44 shrink-0">
    @if ($g)
        <div class="aspect-[4/5] rounded-sm border border-gold/30 bg-vellum/40 flex items-center justify-center text-center p-2">
            <span class="font-mono text-[0.6rem] text-gold animate-pulse">❖ a desenhar…</span>
        </div>
    @elseif ($im)
        <div class="border border-ink-soft/20 rounded-sm overflow-hidden bg-vellum/40 shadow-engraved">
            <img src="{{ $src($im) }}" class="w-full block" alt="Cartão {{ $i + 1 }}">
        </div>
        <input type="text" wire:model="editar.{{ $i }}" placeholder="alterar: «fundo mais escuro»…"
               class="mt-1.5 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1 text-ink font-body text-xs focus:border-teal focus:outline-none">
        <button type="button" wire:click="regenerarCartao({{ $i }})"
                class="mt-1.5 w-full border border-gold/40 text-gold hover:bg-gold/10 rounded-sm px-2 py-1 font-mono text-[0.6rem] transition">
            ↻ {{ trim($editar[$i] ?? '') !== '' ? 'aplicar edição' : 'regenerar' }}
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
        <button type="button" wire:click="regenerarCartao({{ $i }})"
                class="w-full aspect-[4/5] rounded-sm border border-dashed border-gold/40 text-gold/70 hover:bg-gold/5 flex flex-col items-center justify-center gap-1 transition">
            <span class="text-2xl">❖</span>
            <span class="font-mono text-[0.6rem]">gerar imagem</span>
        </button>
    @endif
</div>
