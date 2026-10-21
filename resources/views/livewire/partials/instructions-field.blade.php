{{-- Extra direction for THIS clip only, added to the end of the planner's
     prompt where it overrides the project's style manual. Project-wide style
     lives in Design System → Animation instructions. --}}
<div>
    <label class="eyebrow block mb-2">Instruções para este vídeo (opcional)</label>
    <textarea wire:model="instructions" rows="6" maxlength="10000"
              placeholder="ex.: escuro e cinematográfico, sem gráficos — este é uma história, não dados"
              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none"></textarea>
    @error('instructions') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">
        Até 10 000 caracteres — cole aqui o anúncio/briefing completo. Aplica-se só a este vídeo e prevalece sobre o manual de estilo do projeto.
        Para todos os vídeos do projeto, edite
        <a href="{{ route('design-system') }}" class="text-teal hover:opacity-70 underline">Sistema de design → Instruções de animação</a>.
    </p>
</div>
