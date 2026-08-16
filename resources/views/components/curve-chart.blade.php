@props([
    'points' => [],      // array<int|float> values, oldest→newest
    'color' => '#4DE0E0',
    'height' => 56,      // rendered px height
])
@php
    $paths = \App\Services\Monitoring\MonitoringAnalytics::curvePath($points);
    // Stable-ish gradient id per colour+shape so two charts don't clash.
    $gid = 'cg'.substr(md5($color.implode(',', array_map('strval', $points))), 0, 10);
@endphp
<svg viewBox="0 0 100 40" preserveAspectRatio="none" class="w-full block" style="height: {{ $height }}px" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="{{ $color }}" stop-opacity="0.35" />
            <stop offset="100%" stop-color="{{ $color }}" stop-opacity="0" />
        </linearGradient>
    </defs>
    @if ($paths['area'] !== '')
        <path d="{{ $paths['area'] }}" fill="url(#{{ $gid }})" stroke="none" />
    @endif
    @if ($paths['line'] !== '')
        <path d="{{ $paths['line'] }}" fill="none" stroke="{{ $color }}" stroke-width="1.75"
              vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
    @endif
</svg>
