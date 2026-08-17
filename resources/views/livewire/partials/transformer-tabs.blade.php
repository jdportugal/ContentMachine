{{-- Subtabs for the Content Transformer. Three ways a long piece of content
     becomes something else: cut into shorts, written up as posts, or converted
     between finished formats. Same tab styling as the Effects Studio.

     Each tab matches its own page route exactly — 'clips.*' would swallow the
     siblings, and the other 'clips.…' names are asset endpoints, never a page. --}}
@php
    $transformerTabs = [
        ['route' => 'clips',           'label' => 'Shorts Generator',  'glyph' => '✂', 'color' => '#5A7BFF'],
        ['route' => 'clips.posts',     'label' => 'Posts Generator',   'glyph' => '◇', 'color' => '#9C7DFF'],
        ['route' => 'clips.repurpose', 'label' => 'Content Repurpose', 'glyph' => '⇄', 'color' => '#4DE08A'],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach ($transformerTabs as $tab)
        @php $ativo = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}" wire:navigate
           @if ($ativo) aria-current="page" @endif
           class="flex items-center gap-2 px-4 py-2 rounded-sm border transition font-display text-lg
                  {{ $ativo ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink hover:bg-surface/40' }}">
            <span style="color: {{ $tab['color'] }}">{{ $tab['glyph'] }}</span>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
