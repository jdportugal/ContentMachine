@props(['label' => 'IATECA', 'sub' => 'EX · LIBRIS', 'date' => 'MMXXVI', 'color' => '#5A7BFF'])
{{-- Circular seal styled as a library stamp --}}
<div class="selo inline-flex flex-col items-center justify-center rounded-full w-28 h-28 text-center"
     style="color: {{ $color }}">
    <span class="text-[0.55rem] tracking-[0.16em]">{{ $sub }}</span>
    <span class="font-display text-lg leading-tight my-0.5">{{ $label }}</span>
    <span class="text-[0.55rem] tracking-[0.16em]">{{ $date }}</span>
</div>
