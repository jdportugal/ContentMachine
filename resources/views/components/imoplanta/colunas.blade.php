@props(['intro', 'extra' => null])
{{-- Phone: intro, the card, extra, one under the other. Computer: intro and extra on the left, the card on the right. --}}
<div class="grid gap-6 lg:gap-x-20 lg:gap-y-10 lg:grid-cols-[minmax(0,1fr)_460px] lg:grid-rows-[auto_1fr] items-start">
    <div class="lg:col-start-1 lg:row-start-1">{{ $intro }}</div>
    <div class="lg:col-start-2 lg:row-start-1 lg:row-span-2">{{ $slot }}</div>
    @if ($extra)
        <div class="lg:col-start-1 lg:row-start-2">{{ $extra }}</div>
    @endif
</div>
