{{-- Image manager — operates on $images / $imageReplace. Replace keeps an image's
     id (the plan keeps using it); add appends a new one; remove drops it. --}}
@if (!empty($images))
    <div class="space-y-2 mb-4">
        @foreach ($images as $i => $img)
            <div class="flex items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="clip-img-{{ $i }}">
                @if (!empty($img['video']))
                    <video src="{{ route('clips-animados.upload', basename($img['path'])) }}" muted playsinline
                           class="w-12 h-12 object-cover rounded-sm border border-ink-soft/20 bg-vellum/40"></video>
                @else
                    <img src="{{ route('clips-animados.upload', basename($img['path'])) }}"
                         onerror="this.style.visibility='hidden'"
                         class="w-12 h-12 object-cover rounded-sm border border-ink-soft/20 bg-vellum/40" alt="" />
                @endif
                {{-- Editable: left empty, the AI describes it from the pixels at planning. --}}
                <input type="text" wire:model.blur="images.{{ $i }}.description" placeholder="auto — descrita pela IA"
                       class="flex-1 min-w-0 bg-transparent border-b border-transparent hover:border-ink-soft/20 focus:border-teal/50 text-sm text-ink placeholder:text-ink-faint focus:outline-none" />
                <label class="font-mono text-[0.6rem] text-teal hover:opacity-70 cursor-pointer whitespace-nowrap" title="Substituir esta imagem (mantém o lugar no vídeo)">
                    <span wire:loading.remove wire:target="imageReplace.{{ $i }}">↻ substituir</span>
                    <span wire:loading wire:target="imageReplace.{{ $i }}">a carregar…</span>
                    <input type="file" class="hidden" accept="image/*,video/mp4,video/quicktime,video/webm" wire:model="imageReplace.{{ $i }}" />
                </label>
                <button type="button" wire:click="removerImagem({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70" title="remover">✕</button>
            </div>
            @error('imageReplace.'.$i) <p class="text-bad text-xs mt-0.5">{{ $message }}</p> @enderror
        @endforeach
    </div>
@endif

{{-- Bulk: pick or drop many at once; descriptions are written by the AI at planning.
     A drop goes through the same upload as the picker (updatedNewImages). --}}
<label x-data="{ over: false, busy: false }"
       @dragover.prevent="over = true" @dragleave.prevent="over = false"
       @drop.prevent="over = false; if ($event.dataTransfer.files.length) { busy = true; $wire.uploadMultiple('newImages', $event.dataTransfer.files, () => busy = false, () => busy = false) }"
       :class="over ? 'bg-teal/10 border-teal' : ''"
       class="flex items-center justify-center gap-2 mb-3 rounded-sm border border-dashed border-teal/40 p-5 cursor-pointer hover:bg-teal/5 transition">
    <span class="font-mono text-[0.65rem] text-teal" x-show="!busy" wire:loading.remove wire:target="newImages">⊕ Largue imagens aqui ou clique para adicionar várias de uma vez</span>
    <span class="font-mono text-[0.65rem] text-ink-faint" x-show="busy" x-cloak>a carregar…</span>
    <span class="font-mono text-[0.65rem] text-ink-faint" wire:loading wire:target="newImages">a carregar…</span>
    <span class="font-mono text-[0.55rem] text-ink-faint">— a IA descreve-as</span>
    <input type="file" multiple wire:model="newImages" accept="image/*,video/mp4,video/quicktime,video/webm" class="hidden" />
</label>
@error('newImages.*') <p class="-mt-2 mb-2 text-bad text-sm">{{ $message }}</p> @enderror

<div class="grid grid-cols-12 gap-2 items-end">
    <div class="col-span-5">
        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">ficheiro</label>
        <input type="file" wire:model="newImage" accept="image/*,video/mp4,video/quicktime,video/webm"
               class="block w-full text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
        <div wire:loading wire:target="newImage" class="mt-1 font-mono text-[0.55rem] text-ink-faint">a carregar…</div>
    </div>
    <div class="col-span-5">
        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">descrição (opcional)</label>
        <input type="text" wire:model="newImageDesc" placeholder="ex.: o consultor — ou deixe para a IA"
               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
    </div>
    <div class="col-span-2">
        <button type="button" wire:click="adicionarImagem"
                class="w-full font-mono text-[0.62rem] px-3 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">+ adicionar</button>
    </div>
</div>
@error('newImage') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
@error('newImageDesc') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
