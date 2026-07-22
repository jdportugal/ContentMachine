@php
    $im = $img[$i] ?? null;
    $g = $gerando[$i] ?? null;
    $temInstrucao = trim($editar[$i] ?? '') !== '';
@endphp
<div class="mt-2 border-t border-ink-soft/10 pt-3">
    <label class="eyebrow block mb-1.5">Imagem — alterações</label>
    <textarea wire:model="editar.{{ $i }}" rows="3"
              placeholder="Descreva o que mudar na imagem: «fundo mais escuro», «título em maiúsculas», «tom mais sóbrio», «mais espaço em cima»…"
              class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
    <div class="mt-2 flex items-center gap-3">
        @if ($g)
            <span class="font-mono text-xs text-gold animate-pulse">❖ a desenhar…</span>
        @else
            <button type="button" wire:click="regenerarCartao({{ $i }})"
                    class="border border-gold/50 text-gold hover:bg-gold/10 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                ❖ {{ $im ? ($temInstrucao ? 'aplicar edição' : 'regenerar imagem') : 'gerar imagem' }}
            </button>
            @if ($im && $temInstrucao)
                <span class="font-mono text-[0.6rem] text-ink-faint">usa a imagem actual como referência</span>
            @endif
        @endif
    </div>
</div>
