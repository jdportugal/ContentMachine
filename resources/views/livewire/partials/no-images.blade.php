{{-- Plans the clip with no image layer at all (see PlanAnimationsJob): nothing
     generated, no page filmed, and no review step asking for pictures. Every
     scene's visual is built from what is said — cards, lists, charts, diagrams. --}}
<label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ $semImagens ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
    <input type="checkbox" wire:model.live="semImagens" class="accent-teal mt-1" />
    <span>
        <span class="block font-display text-lg text-ink leading-tight">No images</span>
        <span class="block text-ink-soft text-xs">No pictures at all — nothing generated, nothing filmed, nothing to approve. Scenes carry cards, lists, charts and diagrams instead.</span>
    </span>
</label>
