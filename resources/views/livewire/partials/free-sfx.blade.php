{{-- Opt out of the stock animation vocabulary: GenerateFreeSfxJob has the model
     write a one-off Remotion component for each scene of THIS clip. --}}
<label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ $freeSfx ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
    <input type="checkbox" wire:model.live="freeSfx" class="accent-teal mt-1" />
    <span>
        <span class="block font-display text-lg text-ink leading-tight">Free SFX</span>
        <span class="block text-ink-soft text-xs">Sem as animações prontas — a IA escreve uma à medida para cada cena. Mais lento e com mais custo por vídeo.</span>
    </span>
</label>
