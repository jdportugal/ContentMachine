{{-- Which presentation styles the AI may use on an overlay (video) clip. --}}
<div>
    <label class="eyebrow block mb-2">Estilos permitidos</label>
    @php
        $styles = [
            'video'     => ['t' => 'Só vídeo', 'd' => 'vídeo + legendas'],
            'over'      => ['t' => 'Animação por cima', 'd' => 'grafismo sobre o vídeo'],
            'split'     => ['t' => 'Ecrã dividido', 'd' => 'animação em cima, vídeo em baixo'],
            'animation' => ['t' => 'Ecrã inteiro', 'd' => 'a animação cobre o vídeo'],
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($styles as $key => $s)
            <label class="cursor-pointer flex items-start gap-2 rounded-sm border p-3 transition {{ in_array($key, $allowedPresents) ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
                <input type="checkbox" wire:model="allowedPresents" value="{{ $key }}" class="accent-teal mt-1" />
                <span>
                    <span class="block font-display text-lg text-ink leading-tight">{{ $s['t'] }}</span>
                    <span class="block text-ink-soft text-xs">{{ $s['d'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('allowedPresents') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
</div>
