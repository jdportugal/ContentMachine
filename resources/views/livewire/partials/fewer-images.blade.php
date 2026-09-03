{{-- Halves the clip's image budget (see PlanAnimationsJob). Fewer generated
     pictures to approve and pay for; the freed scenes fall back to cards,
     bullet-lists and charts. --}}
<label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ $poucasImagens ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
    <input type="checkbox" wire:model.live="poucasImagens" class="accent-teal mt-1" />
    <span>
        <span class="block font-display text-lg text-ink leading-tight">Reduce the number of images</span>
        <span class="block text-ink-soft text-xs">Half as many generated pictures — the rest become cards, lists and charts.</span>
    </span>
</label>
