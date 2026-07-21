@props([
    'label',
    'value',
    'delta' => null,      // ex.: +12.4  (percentagem)
    'unit' => null,
    'accent' => '#2dbab4',
])
@php
    $deltaNum = is_numeric($delta) ? (float) $delta : null;
    $deltaColor = $deltaNum === null ? 'text-ink-soft' : ($deltaNum >= 0 ? 'text-good' : 'text-bad');
    $deltaArrow = $deltaNum === null ? '' : ($deltaNum >= 0 ? '▲' : '▼');
@endphp
<div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm px-5 py-4 shadow-engraved">
    <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full" style="background: {{ $accent }}; box-shadow: 0 0 6px {{ $accent }}"></span>
        <span class="eyebrow">{{ $label }}</span>
    </div>
    <div class="mt-2 flex items-baseline gap-2">
        <span class="font-display text-4xl text-ink leading-none">{{ $value }}</span>
        @if ($unit)<span class="font-mono text-xs text-ink-faint">{{ $unit }}</span>@endif
    </div>
    @if ($delta !== null)
        <div class="mt-1.5 font-mono text-xs {{ $deltaColor }}">
            {{ $deltaArrow }} {{ $deltaNum !== null ? number_format(abs($deltaNum), 1).'%' : $delta }}
            <span class="text-ink-faint">vs. período anterior</span>
        </div>
    @endif
</div>
