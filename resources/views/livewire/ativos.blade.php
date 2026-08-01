<div>
    <x-page-header
        eyebrow="Tomus · IV"
        title="Assets"
        cota="778.6 · ACM · '26"
        lead="Reusable media library in the app. Tracks uploaded here become available as background music in the Clip Generator." />

    {{-- Upload-in-progress indicator --}}
    <div wire:loading wire:target="novaMusica, adicionarMusica, novasImagens"
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

    {{-- Image library --}}
    <x-panel eyebrow="Images" title="Image library" glyph="▦" class="mb-6">
        <p class="font-mono text-xs text-ink-faint mb-3">
            Reusable images (logos, brand shots, screenshots; png, jpg, webp, gif; max. 20 MB each). When generating an animated clip, the planner searches here first — a good match is reused instead of asking you or generating one. Describe each image so it can be matched.
        </p>

        <div x-data="{ over: false }"
             @dragover.prevent="over = true"
             @dragleave.prevent="over = false"
             @drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('novasImagens', $event.dataTransfer.files, () => {}, () => {})"
             :class="over ? 'border-teal bg-teal/5' : 'border-ink-soft/30'"
             class="border-2 border-dashed rounded-sm p-6 text-center transition mb-4">
            <p class="font-mono text-xs text-ink-soft">
                Drag &amp; drop images here, or
                <label class="text-teal underline decoration-teal/40 underline-offset-2 hover:decoration-teal cursor-pointer">browse
                    <input type="file" class="hidden" multiple accept="image/*" wire:model="novasImagens" />
                </label>
            </p>
            <p wire:loading wire:target="novasImagens" class="font-mono text-[10px] text-ink-faint mt-2">uploading…</p>
        </div>
        @error('novasImagens.*') <p class="text-bad font-mono text-xs mb-3">{{ $message }}</p> @enderror

        @if (empty($imagens))
            <x-empty-state glyph="▦" title="Empty library"
                note="Add images above to have them reused automatically in animated clips." />
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach ($imagens as $img)
                    <div class="border border-ink-soft/15 rounded-sm bg-surface/30 overflow-hidden" wire:key="lib-{{ $img['id'] }}">
                        <div class="relative aspect-square bg-vellum/40">
                            <img src="{{ route('clips-animados.library-image', $img['id']) }}"
                                 onerror="this.style.visibility='hidden'"
                                 class="w-full h-full object-contain" alt="{{ $img['name'] }}" />
                            <button wire:click="removerImagem('{{ $img['id'] }}')" wire:confirm="Remove this image from the library?"
                                    class="absolute top-1 right-1 w-6 h-6 rounded-sm bg-nocturna/70 text-bad font-mono text-sm hover:bg-nocturna">✕</button>
                        </div>
                        <input type="text" value="{{ $img['description'] }}"
                               wire:change="atualizarDescricao('{{ $img['id'] }}', $event.target.value)"
                               placeholder="Describe this image (e.g. Gronk logo)"
                               class="w-full bg-transparent border-t border-ink-soft/15 px-2 py-1.5 font-mono text-[11px] text-ink placeholder:text-ink-faint focus:outline-none focus:bg-surface/40" />
                    </div>
                @endforeach
            </div>
        @endif
    </x-panel>
</div>
