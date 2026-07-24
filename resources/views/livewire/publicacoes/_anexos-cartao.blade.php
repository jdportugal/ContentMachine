@php
    $an = array_values(array_filter($anexos[$i] ?? []));
    $src = fn ($p) => \Illuminate\Support\Str::startsWith($p, 'http') ? $p : asset($p);
    $descr = function ($path) use ($referencias) {
        foreach ($referencias as $r) {
            if (($r['path'] ?? '') === $path) {
                return trim((string) ($r['descricao'] ?? ''));
            }
        }
        return '';
    };
    $editado = $promptEditado[$i] ?? false;
@endphp
<div class="mt-2 border-t border-ink-soft/10 pt-3" x-data="{ anexosAbertos: false, promptAberto: false }">
    {{-- Images attached to this card --}}
    <div class="flex items-center justify-between gap-2">
        <label class="eyebrow">Card images</label>
        <button type="button" @click="anexosAbertos = !anexosAbertos"
                class="font-mono text-[0.6rem] text-teal hover:underline"
                x-text="anexosAbertos ? 'close' : '+ attach'"></button>
    </div>

    @if (count($an))
        <div class="mt-1.5 flex flex-wrap gap-1.5">
            @foreach ($an as $p)
                <div class="relative group" wire:key="anexo-{{ $i }}-{{ md5($p) }}"
                     title="{{ $descr($p) ?: 'no description' }}">
                    <img src="{{ $src($p) }}" class="w-10 h-10 object-cover rounded-sm border border-teal/40">
                    <button type="button" wire:click="desanexar({{ $i }}, @js($p))"
                            class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-papyrus border border-bad/50 text-bad text-[0.55rem] leading-none flex items-center justify-center opacity-0 group-hover:opacity-100 transition">×</button>
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-1 font-mono text-[0.55rem] text-ink-faint">no attached images — inherits the general references</p>
    @endif

    {{-- Attach panel: pool + direct upload --}}
    <div x-show="anexosAbertos" x-cloak class="mt-2 border border-ink-soft/15 rounded-sm p-2 bg-vellum/30 space-y-2">
        @if (count($referencias))
            <div>
                <p class="font-mono text-[0.55rem] text-ink-faint mb-1">from the reference pool (click to attach/detach):</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($referencias as $r => $ref)
                        @php $anexado = in_array($ref['path'] ?? '', $an, true); @endphp
                        <button type="button" wire:click="alternarAnexo({{ $i }}, {{ $r }})" wire:key="pool-{{ $i }}-{{ $r }}"
                                title="{{ trim($ref['descricao'] ?? '') ?: 'no description' }}"
                                class="relative w-11 h-11 rounded-sm overflow-hidden border transition {{ $anexado ? 'border-teal ring-1 ring-teal' : 'border-ink-soft/25 hover:border-teal/50' }}">
                            <img src="{{ $src($ref['path']) }}" class="w-full h-full object-cover {{ $anexado ? '' : 'opacity-60' }}">
                            @if ($anexado)
                                <span class="absolute inset-0 flex items-center justify-center bg-teal/25 text-teal text-sm">✓</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            <p class="font-mono text-[0.55rem] text-ink-faint">no references yet — upload in the panel above or here.</p>
        @endif

        <label class="block">
            <span class="font-mono text-[0.55rem] text-ink-faint">upload image for this card</span>
            <input type="file" wire:model="cartaoUploads.{{ $i }}" accept="image/*"
                   class="mt-1 block w-full text-[0.6rem] text-ink-soft file:mr-2 file:py-1 file:px-2 file:rounded-sm file:border file:border-teal/40 file:bg-teal/10 file:text-teal file:font-mono file:text-[0.55rem] hover:file:bg-teal/20 file:cursor-pointer">
        </label>
        <div wire:loading wire:target="cartaoUploads.{{ $i }}" class="font-mono text-[0.55rem] text-teal">uploading…</div>
    </div>

    {{-- Card kie prompt (collapsed, editable) --}}
    <div class="mt-2">
        <button type="button" @click="promptAberto = !promptAberto"
                class="flex items-center gap-1.5 font-mono text-[0.6rem] text-ink-soft hover:text-teal transition">
            <span x-text="promptAberto ? '▾' : '▸'"></span> prompt for the image (kie)
            @if ($editado)<span class="text-gold">· edited</span>@endif
        </button>
        <div x-show="promptAberto" x-cloak class="mt-1.5">
            <textarea wire:model.blur="prompts.{{ $i }}" rows="7"
                      placeholder="Prompt sent to kie for this card…"
                      class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-2 py-1.5 text-ink font-mono text-[0.62rem] leading-relaxed focus:border-teal focus:outline-none"></textarea>
            <div class="mt-1.5 flex items-center gap-2">
                <button type="button" wire:click="regenerarPrompt({{ $i }})"
                        @if ($editado) wire:confirm="This replaces the prompt you edited by hand. Continue?" @endif
                        class="border border-ink-soft/30 text-ink-soft hover:text-teal hover:border-teal/40 rounded-sm px-3 py-1 font-mono text-[0.6rem] transition">↻ regenerate prompt</button>
                <span class="font-mono text-[0.55rem] text-ink-faint">reflects text + attached images</span>
            </div>
        </div>
    </div>
</div>
