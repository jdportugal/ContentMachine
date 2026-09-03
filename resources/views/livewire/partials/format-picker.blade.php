{{-- Output frame for the clip. Same aspect presets the VFX Lab offers. --}}
<div>
    <label class="eyebrow block mb-2">Output format</label>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
        @foreach (array_merge(['auto' => ['label' => 'Match the video']], \App\Services\Clips\Store\VfxStore::ASPECTS) as $chave => $a)
            <label class="cursor-pointer flex flex-col items-center gap-1.5 rounded-sm border p-2 transition {{ $formato === $chave ? 'border-teal/50 bg-teal/5' : 'border-ink-soft/20 hover:border-ink-soft/40' }}">
                {{-- .live, not deferred: the only selected-state feedback is the highlight
                     below, which is server-rendered — a deferred model leaves every option
                     looking unselected until the next roundtrip. --}}
                <input type="radio" wire:model.live="formato" value="{{ $chave }}" class="sr-only" />
                @php
                    // A little proxy of the frame, so the shape is visible not just named.
                    $w = $chave === 'auto' ? 34 : (int) round(34 * min(1, ($a['width'] / $a['height'])));
                    $h = $chave === 'auto' ? 34 : (int) round(34 * min(1, ($a['height'] / $a['width'])));
                @endphp
                <span class="block border {{ $formato === $chave ? 'border-teal' : 'border-ink-soft/40' }} {{ $chave === 'auto' ? 'border-dashed' : '' }}"
                      style="width: {{ $w }}px; height: {{ $h }}px"></span>
                <span class="font-mono text-[0.55rem] text-ink text-center leading-tight">{{ $chave === 'auto' ? 'Auto' : $chave }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">«Auto» composes at the video’s own shape. Any other crops the video to fill the chosen frame.</p>
    @error('formato') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
</div>
