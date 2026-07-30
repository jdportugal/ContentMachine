{{-- Background music — same library as the shorts (storage/app/shorts/musicas). --}}
<div>
    <label class="eyebrow block mb-2">Background music</label>
    <div class="grid sm:grid-cols-4 gap-3">
        <select wire:model="musica"
                class="sm:col-span-3 bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink text-sm focus:border-teal/50 focus:outline-none">
            <option value="">Random (from library)</option>
            <option value="nenhuma">None (voiceover only)</option>
            @foreach ($musicas as $m)
                <option value="{{ $m['name'] }}">♪ {{ $m['name'] }}</option>
            @endforeach
        </select>
        <input type="number" step="any" min="0" max="1" wire:model="musicaVolume" placeholder="Volume 0-1"
               class="bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink text-sm focus:border-teal/50 focus:outline-none" />
    </div>
    @if (empty($musicas))
        <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">Library empty — upload tracks in <a href="{{ route('ativos') }}" class="text-teal underline">Assets</a>.</p>
    @endif
</div>
