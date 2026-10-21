{{-- Optional news LEAD: one line pinned on screen for the whole clip.
     Pre-filled automatically when the script came from a news bit (`> …`). --}}
<div>
    <label class="eyebrow block mb-2">Destaque — frase no ecrã (opcional)</label>
    <input type="text" wire:model="lead" maxlength="120"
           placeholder="ex.: O GPT-5 ficou 60% mais barato — para todos"
           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none" />
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">Mostrada como faixa durante todo o vídeo. Deixe vazio para não ter.</p>
</div>
