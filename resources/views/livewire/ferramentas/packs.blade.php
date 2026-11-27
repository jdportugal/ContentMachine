@php $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0); @endphp
<div class="mt-4 grid gap-4 sm:grid-cols-3">
    @foreach (config('contentmachine.ferramentas.packs') as $chave => $pack)
        <a href="{{ \App\Services\Ferramentas\Ferramentas::link(auth()->user(), $chave, $video) }}"
           class="rounded-[18px] bg-navy text-ivory p-5 flex flex-col gap-1 hover:ring-2 hover:ring-coral">
            <span class="text-[11px] tracking-[.24em] text-cyan">{{ $pack['creditos'] === 1 ? '1 CRÉDITO DE VÍDEO' : $pack['creditos'].' CRÉDITOS DE VÍDEO' }}</span>
            <span class="font-display text-[34px] leading-none">{{ $eur($pack['preco']) }}</span>
            <span class="text-xs text-[#AEB5C1]">{{ $pack['creditos'] > 1 ? $eur((int) round($pack['preco'] / $pack['creditos'])).' por crédito' : 'Um crédito, agora.' }}</span>
        </a>
    @endforeach
</div>
