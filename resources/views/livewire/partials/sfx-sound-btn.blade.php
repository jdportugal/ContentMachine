@php($hasSound = in_array($slug, $sfxAudio ?? [], true))
<button type="button" wire:click="abrirAudio('{{ $slug }}')"
        title="{{ $hasSound ? 'Som do efeito anexado — clique para alterar' : 'Anexar um som a este efeito' }}"
        class="text-sm leading-none px-1.5 py-1 rounded-sm border {{ $hasSound ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ $hasSound ? '🔊' : '♪' }}</button>
