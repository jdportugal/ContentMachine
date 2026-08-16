@php
    $im = $img[$i] ?? null;
    $g = $gerando[$i] ?? null;
    $temInstrucao = trim($editar[$i] ?? '') !== '';
@endphp
<div class="mt-2 border-t border-ink-soft/10 pt-3">
    <label class="eyebrow block mb-1.5">Image — changes</label>
    <textarea wire:model="editar.{{ $i }}" rows="3"
              placeholder="Describe what to change in the image: «darker background», «title in uppercase», «more sober tone», «more space at the top»…"
              class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none"></textarea>
    <div class="mt-2 flex items-center gap-3">
        @if ($g)
            <span class="font-mono text-xs text-gold animate-pulse">❖ drawing…</span>
        @else
            <button type="button" wire:click="regenerarCartao({{ $i }})"
                    class="border border-gold/50 text-gold hover:bg-gold/10 rounded-sm px-4 py-1.5 font-mono text-xs transition">
                ❖ {{ $im ? ($temInstrucao ? 'apply edit' : 'regenerate image') : 'generate image' }}
            </button>
            @if ($im && $temInstrucao)
                <span class="font-mono text-[0.6rem] text-ink-faint">uses the current image as a reference</span>
            @endif
        @endif
    </div>
</div>
