@props(['title' => null, 'eyebrow' => null, 'glyph' => null])
{{-- Ficha / cartão de biblioteca --}}
<section {{ $attributes->merge(['class' => 'foxing bg-vellum/60 border border-ink-soft/15 rounded-sm p-6 shadow-engraved']) }}>
    @if ($title || $eyebrow)
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                @if ($eyebrow)<div class="eyebrow mb-1">{{ $eyebrow }}</div>@endif
                @if ($title)<h2 class="font-display text-2xl text-ink leading-tight">{{ $title }}</h2>@endif
            </div>
            @if ($glyph)<span class="text-gold text-xl select-none">{{ $glyph }}</span>@endif
        </div>
    @endif
    {{ $slot }}
</section>
