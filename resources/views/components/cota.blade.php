@props(['value' => '006.3 · ACM · \'26'])
{{-- Cota (call-number) — typewriter-style card --}}
<div class="shrink-0 border border-ink-soft/25 bg-surface/50 px-3 py-1.5 font-mono text-[0.62rem] text-ink-soft leading-relaxed text-right shadow-engraved">
    @foreach (explode(' · ', $value) as $line)
        <div>{{ $line }}</div>
    @endforeach
</div>
