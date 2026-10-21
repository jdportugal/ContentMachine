<div>
    {{-- Played full-screen, as uploaded (its own sound, nothing on top), right after the intro of every clip. --}}
    <div class="mb-5 border border-ink-soft/15 rounded-sm px-4 py-3 bg-surface/30">
        <div class="flex flex-wrap items-center gap-3">
            <span class="eyebrow">★ Depois da intro</span>
            @if ($this->afterIntro)
                <span class="font-mono text-[0.7rem] text-teal">✓ vídeo definido · {{ $this->afterIntro }} s</span>
                <button type="button" wire:click="apagarAfterIntro" wire:confirm="Remover o vídeo depois da intro?" class="font-mono text-[0.6rem] text-ink-soft hover:text-bad">✕ remover</button>
            @endif
            <label class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition cursor-pointer">
                ⊕ {{ $this->afterIntro ? 'Substituir vídeo' : 'Carregar vídeo' }}
                <input type="file" wire:model="afterIntroUpload" accept="video/*" class="hidden">
            </label>
            <span wire:loading wire:target="afterIntroUpload" class="font-mono text-[0.6rem] text-ink-faint">a carregar…</span>
        </div>
        @error('afterIntroUpload') <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
        <label class="mt-2 flex items-center gap-2">
            <span class="font-mono text-[0.62rem] text-ink-soft shrink-0">Frase dita por cima</span>
            <input type="text" wire:model.blur="afterIntroLine" placeholder="Se queres vender casa, fala com o Nuno Narciso"
                   class="flex-1 min-w-0 bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-1.5 text-sm text-ink focus:border-teal/50 focus:outline-none">
        </label>
        @error('afterIntroLine') <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
        <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Os guiões dos imóveis dizem a frase logo a seguir à intro, e o vídeo passa uma vez, em ecrã inteiro e tal como foi carregado (com o seu próprio som), a começar nessa frase; se o vídeo for mais longo, a locução espera por ele. Sem frase, a locução faz pausa durante todo o vídeo.</p>
    </div>
</div>
