{{-- Imoplanta's packs as credit packs, the same cards as the AI tools' (livewire/ferramentas/packs): each its video credits
     first, the price, and how many properties it makes (a property uses Creditos::IMOPLANTA). $p: bought from a property. --}}
@php
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $porImovel = \App\Services\Pagamentos\Creditos::IMOPLANTA;
@endphp
<div class="mt-4 grid gap-4 sm:grid-cols-3">
    @foreach (config('contentmachine.imoplanta.planos') as $chave => $pl)
        <a href="{{ \App\Services\Imoplanta\Imoplantas::link(auth()->user(), $chave, $p ?? null) }}"
           class="rounded-[18px] bg-navy text-ivory p-5 flex flex-col gap-1 hover:ring-2 hover:ring-coral">
            <span class="text-[11px] tracking-[.24em] text-cyan">{{ $pl['imoveis'] * $porImovel }} CRÉDITOS DE VÍDEO</span>
            <span class="font-display text-[34px] leading-none">{{ $eur($pl['preco']) }}</span>
            <span class="text-xs text-[#AEB5C1]">{{ $pl['imoveis'] === 1 ? '1 imóvel' : $pl['imoveis'].' imóveis · '.$eur(intdiv($pl['preco'], $pl['imoveis'])).' por imóvel' }}</span>
        </a>
    @endforeach
</div>
