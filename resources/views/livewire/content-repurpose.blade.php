<div>
    <x-page-header
        eyebrow="Tomus · III"
        title="Content Repurpose"
        cota="778.7 · ACM · '26"
        lead="Take something already finished and produce it in another format — a video becomes a post or carousel, a post becomes a video." />

    @include('livewire.partials.transformer-tabs')

    {{-- Which direction to convert. --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach ([['video', '▶', 'Video → post'], ['post', '◇', 'Post → video']] as [$key, $glyph, $label])
            @php $on = $de === $key; @endphp
            <button type="button" wire:click="escolherOrigem('{{ $key }}')"
                    class="flex items-center gap-2 px-4 py-2 rounded-sm border transition font-mono text-xs
                           {{ $on ? 'bg-surface/70 border-teal/40 text-teal' : 'border-ink-soft/15 text-ink-soft hover:text-ink hover:bg-surface/40' }}">
                <span>{{ $glyph }}</span> {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($de === 'video')
        @if ($this->videos->isEmpty())
            <x-empty-state glyph="▶" title="No finished videos"
                           note="Promote a short or an animated clip to Finished and it will show up here, ready to become a post." />
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach ($this->videos as $v)
                    <x-panel wire:key="vid-{{ $v['source'] }}-{{ $v['ref'] }}">
                        <div class="eyebrow mb-1">{{ $v['kind'] }}</div>
                        <h2 class="font-display text-xl text-ink leading-tight">{{ $v['title'] }}</h2>
                        @if ($v['excerpt'])
                            <p class="text-ink-soft text-sm mt-2">{{ $v['excerpt'] }}</p>
                        @endif
                        <div class="flex flex-wrap gap-2 mt-4">
                            <button type="button" wire:click="paraPublicacao('{{ $v['source'] }}', '{{ $v['ref'] }}', 'post')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">→ Post</button>
                            <button type="button" wire:click="paraPublicacao('{{ $v['source'] }}', '{{ $v['ref'] }}', 'carrossel')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">→ Carousel</button>
                        </div>
                    </x-panel>
                @endforeach
            </div>
        @endif
    @else
        @if ($this->posts->isEmpty())
            <x-empty-state glyph="◇" title="No finished posts"
                           note="Mark a post or carousel as ready and it will show up here, ready to become a video." />
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach ($this->posts as $p)
                    <x-panel wire:key="post-{{ $p['ref'] }}">
                        <div class="eyebrow mb-1">{{ $p['kind'] }}</div>
                        <h2 class="font-display text-xl text-ink leading-tight">{{ $p['title'] }}</h2>
                        @if ($p['excerpt'])
                            <p class="text-ink-soft text-sm mt-2">{{ $p['excerpt'] }}</p>
                        @endif
                        <div class="flex flex-wrap gap-2 mt-4">
                            <button type="button" wire:click="paraVideo('{{ $p['ref'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">→ Animated video</button>
                        </div>
                    </x-panel>
                @endforeach
            </div>
        @endif
    @endif
</div>
