@props(['label' => 'IATECA', 'sub' => 'EX · LIBRIS', 'date' => 'MMXXVI', 'color' => '#2dbab4'])
{{-- Selo circular estilo carimbo de biblioteca --}}
<div class="selo inline-flex flex-col items-center justify-center rounded-full w-28 h-28 text-center"
     style="color: {{ $color }}">
    <span class="text-[0.55rem] tracking-[0.16em]">{{ $sub }}</span>
    <span class="font-display text-lg leading-tight my-0.5">{{ $label }}</span>
    <span class="text-[0.55rem] tracking-[0.16em]">{{ $date }}</span>
</div>
