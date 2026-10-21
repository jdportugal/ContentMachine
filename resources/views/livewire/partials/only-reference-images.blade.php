{{-- Builds the clip only from the uploaded images (see PlanAnimationsJob):
     every one shown, placed by its description, nothing generated or fetched. --}}
<label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ $soReferencias ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
    <input type="checkbox" wire:model.live="soReferencias" class="accent-teal mt-1" />
    <span>
        <span class="block font-display text-lg text-ink leading-tight">Só imagens de referência</span>
        <span class="block text-ink-soft text-xs">Feito só com as suas imagens — todas aparecem, onde a descrição encaixa no guião. Nada gerado, procurado ou filmado.</span>
    </span>
</label>
