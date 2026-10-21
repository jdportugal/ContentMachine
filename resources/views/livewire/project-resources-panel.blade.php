<div>
    <x-panel eyebrow="Used in every listing video" title="Project resources" glyph="◈">
        <p class="text-ink-soft -mt-2 mb-5">
            The agent this project is for, and what their videos use: photo, logo, after-intro, music and extra images.
            Anything here replaces what the listing brings; anything uploaded on a single video replaces what is here.
        </p>

        <form wire:submit="guardar" class="space-y-6">
            {{-- Agent --}}
            <div>
                <div class="eyebrow mb-2">Agent</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (['name' => 'Name', 'phone' => 'Phone', 'email' => 'Email', 'licence' => 'Licence number'] as $field => $label)
                        <label class="block">
                            <span class="font-mono text-[0.62rem] text-ink-soft">{{ $label }}</span>
                            <input type="text" wire:model="agent.{{ $field }}"
                                   class="mt-1 w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-sm text-ink focus:border-teal focus:outline-none">
                            @error("agent.$field") <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 font-mono text-[0.58rem] text-ink-faint">Empty fields keep what the listing says.</p>
            </div>

            {{-- Photo + logo --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @foreach (['agent-photo' => ['Agent photo', $hasPhoto, 'agentPhoto'], 'logo' => ['Agency logo', $hasLogo, 'logo']] as $name => [$label, $has, $model])
                    <div>
                        <div class="eyebrow mb-2">{{ $label }}</div>
                        <div class="flex items-center gap-3">
                            @if ($has)
                                <img src="{{ route('project-resources.image', $name) }}?v={{ $version }}" alt="{{ $label }}"
                                     class="w-16 h-16 object-contain rounded-sm border border-ink-soft/25 bg-surface/40">
                            @endif
                            <label class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition cursor-pointer">
                                ⊕ {{ $has ? 'Replace' : 'Upload' }}
                                <input type="file" wire:model="{{ $model }}" accept="image/*" class="hidden">
                            </label>
                            @if ($has)
                                <button type="button" wire:click="apagar('{{ $name }}')" wire:confirm="Remove the {{ strtolower($label) }}?"
                                        class="font-mono text-[0.6rem] text-ink-soft hover:text-bad">✕ remove</button>
                            @endif
                            <span wire:loading wire:target="{{ $model }}" class="font-mono text-[0.6rem] text-ink-faint">uploading…</span>
                        </div>
                        @error($model) <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            {{-- Music --}}
            <label class="block">
                <span class="eyebrow">Music</span>
                <select wire:model="music"
                        class="mt-2 w-full sm:w-auto bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-sm text-ink focus:border-teal focus:outline-none">
                    <option value="">Default ({{ $defaultTrack }})</option>
                    <option value="nenhuma">No music</option>
                    @foreach ($tracks as $track)
                        <option value="{{ $track }}">{{ $track }}</option>
                    @endforeach
                </select>
                <span class="block mt-1 font-mono text-[0.58rem] text-ink-faint">Tracks come from Assets. A video's own music choice still wins.</span>
            </label>

            {{-- Extra images --}}
            <div>
                <div class="eyebrow mb-2">Extra images</div>
                <p class="text-ink-soft text-sm mb-3">Given to the planner with every listing video, to use where they fit — say what each one shows.</p>
                <div class="space-y-2">
                    @foreach ($extras as $i => $extra)
                        <div class="flex items-start gap-3" wire:key="extra-{{ $extra['file'] }}">
                            <img src="{{ route('project-resources.image', $extra['file']) }}" alt=""
                                 class="w-16 h-16 object-cover rounded-sm border border-ink-soft/25 shrink-0">
                            <div class="flex-1 min-w-0">
                                <input type="text" wire:model="extras.{{ $i }}.description"
                                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-sm text-ink focus:border-teal focus:outline-none">
                                @error("extras.$i.description") <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <button type="button" wire:click="removerImagem('{{ $extra['file'] }}')" wire:confirm="Remove this image?"
                                    class="font-mono text-[0.6rem] text-ink-soft hover:text-bad mt-2">✕ remove</button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <input type="file" wire:model="newImage" accept="image/*"
                           class="text-sm text-ink-soft font-mono file:mr-3 file:rounded-sm file:border file:border-ink-soft/25 file:bg-papyrus/60 file:px-3 file:py-1.5 file:text-ink file:cursor-pointer hover:file:border-teal">
                    <input type="text" wire:model="newImageDesc" placeholder="e.g. Nuno handing over the keys in front of a house"
                           class="flex-1 min-w-[12rem] bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-sm text-ink focus:border-teal focus:outline-none">
                    <button type="button" wire:click="adicionarImagem" wire:loading.attr="disabled" wire:target="newImage,adicionarImagem"
                            class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition disabled:opacity-40">
                        ⊕ Add image
                    </button>
                </div>
                @error('newImage') <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
                @error('newImageDesc') <p class="text-bad text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
                    class="rounded-sm border border-teal/50 bg-teal/10 px-5 py-2 text-ink font-display hover:bg-teal/20 hover:border-teal transition disabled:opacity-50">
                Save resources
            </button>
        </form>

        {{-- After-intro: saves on its own, shared with the SFX page. --}}
        <div class="mt-6">
            <livewire:after-intro-setting />
        </div>
    </x-panel>
</div>
