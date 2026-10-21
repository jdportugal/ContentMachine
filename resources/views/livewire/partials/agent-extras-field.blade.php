{{-- This clip's after-intro video and the line said over it. Empty video → the project's
     (Design System → Project resources); the line starts as the project's. --}}
<div>
    <label class="eyebrow block mb-2">Vídeo após a intro (opcional)</label>
    <input type="file" wire:model="afterIntro" accept="video/*"
           class="block w-full text-sm text-ink-soft file:mr-3 file:py-2 file:px-4 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-xs file:cursor-pointer" />
    <div wire:loading wire:target="afterIntro" class="mt-1 font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
    @error('afterIntro') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
    <input type="text" wire:model="afterIntroLine" maxlength="200" aria-label="Frase após a intro"
           placeholder="Frase dita por cima, ex.: Se queres vender ou comprar casa, fala com…"
           class="mt-2 w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none" />
    @error('afterIntroLine') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">
        Passa em ecrã inteiro com o seu som logo a seguir à intro, a começar na frase.
        Sem vídeo aqui → o do projeto ({{ app(\App\Services\Clips\Store\EffectStore::class)->afterIntroPath() ? 'definido' : 'nenhum definido' }}).
        Uma narração escrita ou gerada diz a frase; uma gravação não pode, por isso faz uma pausa para o vídeo.
    </p>
</div>

{{-- The agent's licence for the outro card: starts as the project's; empty → none shown. --}}
<div>
    <label class="eyebrow block mb-2">Licença do consultor — AMI (opcional)</label>
    <input type="text" wire:model="licence" maxlength="60" placeholder="ex.: AMI 12345"
           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none" />
    @error('licence') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">Começa com a do projeto. Vazio → sem licença no cartão (um URL de anúncio pode trazer a sua).</p>
</div>
