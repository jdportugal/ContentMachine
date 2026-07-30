@props(['eyebrow' => null, 'title', 'cota' => null, 'lead' => null])
<header class="mb-2">
    <div class="flex items-start justify-between gap-6">
        <div class="min-w-0">
            @if ($eyebrow)
                <div class="eyebrow mb-2">{{ $eyebrow }}</div>
            @endif
            <h1 class="font-display text-5xl text-ink leading-none">{{ $title }}</h1>
            @if ($lead)
                <p class="mt-3 text-lg text-ink-soft max-w-2xl">{{ $lead }}</p>
            @endif
        </div>
        @if ($cota)
            <x-cota :value="$cota" />
        @endif
    </div>
    <x-fleuron />
</header>
