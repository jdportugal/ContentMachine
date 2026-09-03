{{-- DM for link: show/copy the armed link, or arm one after the fact. Shared by
     the scheduled and posted cards on Rascunhos. --}}
@if ($item['dm_link'] ?? false)
    <div class="mt-2 flex items-center gap-2 flex-wrap">
        <span class="eyebrow text-[0.55rem]">DM «{{ $item['dm_keyword'] ?: 'LINK' }}»</span>
        <span class="font-mono text-xs text-ink-soft break-all">{{ $item['dm_link'] }}</span>
        <button type="button" x-data
                x-on:click="navigator.clipboard.writeText(@js($item['dm_link'])); $dispatch('toast', { message: 'DM link copied' })"
                class="font-mono text-[0.6rem] px-2 py-0.5 border border-ink-soft/25 rounded-sm hover:border-teal hover:text-teal">copy</button>
    </div>
@elseif ($zernioReady && in_array('instagram', $item['plataformas'] ?? [], true))
    <div class="mt-3 flex flex-wrap items-end gap-3">
        <div>
            <label class="eyebrow block mb-1.5 text-[0.55rem]">Keyword</label>
            <input type="text" wire:model="dm.{{ $item['id'] }}.keyword" placeholder="GUIDE"
                   class="bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
        </div>
        <div class="grow max-w-sm">
            <label class="eyebrow block mb-1.5 text-[0.55rem]">Link they get</label>
            <input type="url" wire:model="dm.{{ $item['id'] }}.link" placeholder="https://…"
                   class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
        </div>
        <button type="button" wire:click="ativarDm('{{ $item['source'] }}', @js($item['ref']))"
                class="font-mono text-[0.65rem] px-3 py-2 border border-ink-soft/25 rounded-sm hover:border-teal hover:text-teal">Activate DM</button>
    </div>
@endif
