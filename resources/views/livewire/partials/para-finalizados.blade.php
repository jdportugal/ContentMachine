{{-- Sets the same `finished` flag the "→ send to Finished" button does, but at
     the end of the render (see RenderJob) — so a clip you already know you want
     lands in the Finished hub without the extra click. --}}
<label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ $paraFinalizados ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
    <input type="checkbox" wire:model.live="paraFinalizados" class="accent-teal mt-1" />
    <span>
        <span class="block font-display text-lg text-ink leading-tight">Send straight to Finished</span>
        <span class="block text-ink-soft text-xs">Lands in the Finished hub the moment it renders, ready to publish — no «send to Finished» click.</span>
    </span>
</label>
