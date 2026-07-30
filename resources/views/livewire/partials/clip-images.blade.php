{{-- Image manager — operates on $images / $imageReplace. Replace keeps an image's
     id (the plan keeps using it); add appends a new one; remove drops it. --}}
@if (!empty($images))
    <div class="space-y-2 mb-4">
        @foreach ($images as $i => $img)
            <div class="flex items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="clip-img-{{ $i }}">
                <img src="{{ route('clips-animados.upload', basename($img['path'])) }}"
                     onerror="this.style.visibility='hidden'"
                     class="w-12 h-12 object-cover rounded-sm border border-ink-soft/20 bg-vellum/40" alt="" />
                <span class="flex-1 min-w-0 text-sm text-ink truncate">{{ $img['description'] ?? '' }}</span>
                <label class="font-mono text-[0.6rem] text-teal hover:opacity-70 cursor-pointer whitespace-nowrap" title="Replace this image (keeps its place in the video)">
                    <span wire:loading.remove wire:target="imageReplace.{{ $i }}">↻ replace</span>
                    <span wire:loading wire:target="imageReplace.{{ $i }}">uploading…</span>
                    <input type="file" class="hidden" accept="image/*" wire:model="imageReplace.{{ $i }}" />
                </label>
                <button type="button" wire:click="removerImagem({{ $i }})" class="text-bad font-mono text-sm hover:opacity-70" title="remove">✕</button>
            </div>
            @error('imageReplace.'.$i) <p class="text-bad text-xs mt-0.5">{{ $message }}</p> @enderror
        @endforeach
    </div>
@endif

<div class="grid grid-cols-12 gap-2 items-end">
    <div class="col-span-5">
        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">file</label>
        <input type="file" wire:model="newImage" accept="image/*"
               class="block w-full text-xs text-ink-soft file:mr-2 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-teal/40 file:bg-transparent file:text-teal file:font-mono file:text-[0.6rem] file:cursor-pointer" />
        <div wire:loading wire:target="newImage" class="mt-1 font-mono text-[0.55rem] text-ink-faint">uploading…</div>
    </div>
    <div class="col-span-5">
        <label class="block font-mono text-[0.55rem] text-ink-faint mb-1">description</label>
        <input type="text" wire:model="newImageDesc" placeholder="e.g.: company logo"
               class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1.5 text-ink text-sm focus:border-teal/50 focus:outline-none" />
    </div>
    <div class="col-span-2">
        <button type="button" wire:click="adicionarImagem"
                class="w-full font-mono text-[0.62rem] px-3 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">+ add</button>
    </div>
</div>
@error('newImage') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
@error('newImageDesc') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
