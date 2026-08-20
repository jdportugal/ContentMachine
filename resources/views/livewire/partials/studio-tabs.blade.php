{{-- Subtabs for the Effects Studio — SFX (clip vocabulary), VFX (standalone
     long-form assets) and Backgrounds (the backdrop a clip renders behind every
     scene) share one nav entry. Links, not wire:click: they are separate Livewire
     pages, so wire:navigate swaps them without a full reload.
     Styled like the platform tabs in monitorizacao.blade.php. --}}
@php
    $studioTabs = [
        ['route' => 'clips-animados.sfx', 'label' => 'SFX Studio', 'glyph' => '✶', 'color' => '#6FE0D0', 'match' => 'clips-animados.sfx*'],
        ['route' => 'clips-animados.vfx', 'label' => 'VFX Lab', 'glyph' => '✧', 'color' => '#8FE0B0', 'match' => 'clips-animados.vfx*'],
        ['route' => 'clips-animados.backgrounds', 'label' => 'Backgrounds', 'glyph' => '◆', 'color' => '#C9A227', 'match' => 'clips-animados.backgrounds'],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach ($studioTabs as $tab)
        @php $ativo = request()->routeIs($tab['match']); @endphp
        <a href="{{ route($tab['route']) }}" wire:navigate
           @if ($ativo) aria-current="page" @endif
           class="flex items-center gap-2 px-4 py-2 rounded-sm border transition font-display text-lg
                  {{ $ativo ? 'bg-surface/70 border-ink-soft/30 text-ink' : 'border-ink-soft/15 text-ink-soft hover:text-ink hover:bg-surface/40' }}">
            <span style="color: {{ $tab['color'] }}">{{ $tab['glyph'] }}</span>
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
