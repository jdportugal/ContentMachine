<div>
    <x-page-header
        eyebrow="Tomus · IV"
        title="Assets"
        cota="778.6 · IAT · '26"
        lead="Reusable media library in the app. Tracks uploaded here become available as background music in the Clip Generator." />

    {{-- Upload-in-progress indicator --}}
    <div wire:loading wire:target="novaMusica, adicionarMusica"
         style="position:fixed; bottom:1rem; right:1rem; z-index:9998; width:20rem; max-width:90vw; border:1px solid rgba(90,123,255,.5); color:#5A7BFF;"
         class="rounded-sm px-4 py-2.5 font-mono text-sm shadow-engraved bg-surface flex items-start gap-2">
        <span class="animate-pulse">⏳</span>
        <span class="flex-1">Loading…</span>
    </div>

    {{-- Music library --}}
    <x-panel eyebrow="Audio" title="Music library" glyph="♪" class="mb-6">
        <p class="font-mono text-xs text-ink-faint mb-3">
            Upload background tracks (mp3, wav, m4a, aac, ogg, flac; max. 30 MB). In clips, choose a specific track or leave it on <span class="text-teal">Random</span> — one is drawn from the library when generating the short.
        </p>

        <form wire:submit="adicionarMusica" class="flex flex-wrap items-center gap-3 mb-4">
            <input type="file" wire:model="novaMusica" accept="audio/*"
                   class="font-mono text-xs text-ink file:mr-3 file:border file:border-teal/40 file:text-teal file:bg-transparent file:rounded-sm file:px-3 file:py-1.5 file:font-mono file:text-xs file:uppercase file:tracking-wider file:cursor-pointer">
            <button type="submit" wire:loading.attr="disabled"
                    class="bg-teal/90 text-papyrus font-display text-base px-5 py-1.5 rounded-sm hover:bg-teal-deep transition disabled:opacity-40">
                Upload
            </button>
        </form>
        @error('novaMusica') <p class="text-bad font-mono text-xs mb-3">{{ $message }}</p> @enderror

        @if (empty($musicas))
            <x-empty-state glyph="♪" title="Empty library"
                note="Upload tracks above to use as background music in shorts." />
        @else
            <div class="space-y-2">
                @foreach ($musicas as $m)
                    <div class="flex items-center gap-3 border border-ink-soft/15 rounded-sm px-3 py-2 bg-surface/30">
                        <span class="font-mono text-xs text-ink truncate max-w-xs">♪ {{ $m['name'] }}</span>
                        <span class="font-mono text-[10px] text-ink-faint">{{ number_format($m['size'] / 1048576, 1) }} MB</span>
                        <audio controls preload="none" src="{{ route('clips.musica', $m['name']) }}" class="h-8 ml-auto max-w-[280px]"></audio>
                        <button wire:click="removerMusica('{{ $m['name'] }}')" wire:confirm="Remove this track?"
                                class="text-bad font-mono text-xs hover:text-bad/70">✕</button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>
</div>
