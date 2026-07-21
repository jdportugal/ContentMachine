@props(['glyph' => '❦', 'title' => 'Capítulo por escrever', 'note' => null])
{{-- Estado vazio — para secções ainda por implementar --}}
<div class="frame-engraved foxing bg-vellum/40 rounded-sm py-20 px-8 text-center">
    <div class="text-6xl text-gold/70 mb-4 select-none">{{ $glyph }}</div>
    <h3 class="font-display text-3xl text-ink">{{ $title }}</h3>
    @if ($note)
        <p class="mt-3 text-ink-soft max-w-md mx-auto">{{ $note }}</p>
    @endif
    <div class="mt-6 font-mono text-[0.62rem] text-ink-faint tracking-widest uppercase">Em preparação · Nota bene</div>
</div>
