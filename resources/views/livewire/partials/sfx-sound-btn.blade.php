@php($hasSound = in_array($slug, $sfxAudio ?? [], true))
<button type="button" wire:click="abrirAudio('{{ $slug }}')"
        title="{{ $hasSound ? 'Effect sound attached — click to change' : 'Attach a sound to this effect' }}"
        class="text-sm leading-none px-1.5 py-1 rounded-sm border {{ $hasSound ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ $hasSound ? '🔊' : '♪' }}</button>
