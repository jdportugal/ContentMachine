@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral'   => 'text-ink-soft border-ink-soft/30',
        'teal'      => 'text-teal border-teal/40',
        'good'      => 'text-good border-good/40',
        'warn'      => 'text-warn border-warn/40',
        'bad'       => 'text-bad border-bad/40',
        'leather'   => 'text-leather border-leather/40',
        'gold'      => 'text-gold border-gold/40',
    ];
    $cls = $tones[$tone] ?? $tones['neutral'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 border rounded-full px-2.5 py-0.5 font-mono text-[0.62rem] uppercase tracking-wider $cls"]) }}>
    {{ $slot }}
</span>
