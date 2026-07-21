@props(['glyph' => '❦'])
{{-- Filete ornamental: linha — losango — linha --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-ink-soft/50 my-6']) }}>
    <span class="h-px flex-1 bg-gradient-to-r from-transparent to-ink-soft/40"></span>
    <span class="text-gold text-sm select-none">{{ $glyph }}</span>
    <span class="h-px flex-1 bg-gradient-to-l from-transparent to-ink-soft/40"></span>
</div>
