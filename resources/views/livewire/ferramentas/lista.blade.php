@php
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $estados = [
        'rascunho' => ['Por começar', 'bg-[#EDEAE3] text-navy'], 'a_enquadrar' => ['A criar as imagens', 'bg-[#FBEBD9] text-[#8A4B12]'],
        'a_aprovar' => ['Imagens por aprovar', 'bg-coral text-navy'], 'a_animar' => ['A criar o vídeo', 'bg-[#FBEBD9] text-[#8A4B12]'],
        'pronto' => ['Pronto', 'bg-[#E3F0E7] text-[#22603A]'],
    ];
@endphp
<div @if ($videos->contains(fn ($v) => $v->aTrabalhar())) wire:poll.5s @endif class="font-sans">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="eyebrow">Ferramentas IA</span>
            <h1 class="mt-2 font-display text-5xl font-medium leading-none">Vídeos a partir de uma fotografia</h1>
            <p class="mt-3 max-w-2xl text-sm text-ink-soft">Escolha a ferramenta e envie uma fotografia. Vê e aprova a primeira e a última imagem antes de criarmos o vídeo.</p>
        </div>
        @if ($disponiveis !== null)
            <div class="rounded-[18px] bg-navy px-6 py-4 min-w-[200px] flex items-center justify-between gap-6">
                <span class="text-[10px] tracking-[.3em] text-cyan leading-relaxed">CRÉDITOS DE VÍDEO<br>DISPONÍVEIS</span>
                <span class="font-display text-[48px] leading-none text-ivory">{{ \App\Services\Pagamentos\Creditos::txt($disponiveis) }}</span>
            </div>
        @endif
    </div>

    @if ($pagamento)
        <p role="status" class="mt-6 rounded-xl bg-[#E3F0E7] text-[#22603A] px-5 py-3 text-sm font-medium">{{ $pagamento }}</p>
    @endif

    <div class="mt-8 grid gap-5 grid-cols-[repeat(auto-fill,minmax(240px,1fr))]">
        @foreach ($ferramentas as $chave => $f)
            @php $ex = \App\Services\Ferramentas\Ferramentas::exemplo($chave); @endphp
            <a href="{{ route('ferramentas.nova', $chave) }}" class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA] flex flex-col hover:border-coral hover:shadow-[0_12px_32px_rgba(14,22,38,0.08)]">
                <div class="aspect-video bg-navy">
                    @if ($ex['poster']) <img src="{{ $ex['poster'] }}" alt="" class="w-full h-full object-cover"> @endif
                </div>
                <div class="p-4 flex flex-col gap-1">
                    <span class="font-display text-2xl leading-tight text-ivory">{{ $f['nome'] }}</span>
                    <span class="text-sm text-[#AEB5C1]">{{ $f['linha'] }}</span>
                    <span class="mt-1 text-xs font-semibold text-cyan">{{ \App\Services\Pagamentos\Creditos::rotulo($f['creditos']) }}</span>
                </div>
            </a>
        @endforeach
    </div>

    @if ($videos->isNotEmpty())
        <h2 class="mt-12 font-display text-3xl">Os seus vídeos</h2>
        <div class="mt-4 grid gap-6 grid-cols-[repeat(auto-fill,minmax(250px,1fr))]">
            @foreach ($videos as $v)
                @php $capa = $v->fim ? 'fim' : 'original'; @endphp
                <a wire:key="v-{{ $v->id }}" href="{{ route('ferramentas.video', $v->id) }}" class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA] flex flex-col hover:border-cyan">
                    <div class="relative aspect-video bg-[#EDEAE3]">
                        <img src="{{ route('ferramentas.ficheiro', [$v->id, $capa, 'v' => substr(md5((string) $v->{$capa}), 0, 8)]) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-semibold {{ $estados[$v->estado][1] }}">{{ $estados[$v->estado][0] }}</span>
                    </div>
                    <div class="p-4">
                        <h3 class="font-sans text-base font-semibold leading-snug text-ivory">{{ $v->tipo()['nome'] }}</h3>
                        <p class="text-xs text-[#AEB5C1]">{{ $v->created_at->locale('pt_PT')->translatedFormat('j M Y, H:i') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($disponiveis !== null)
        <section id="packs" class="mt-12">
            <h2 class="font-display text-3xl">Créditos de vídeo</h2>
            <p class="mt-1 text-sm text-ink-soft">Os mesmos créditos dos vídeos Imocena e do Imoplanta: cada ferramenta usa os seus. IVA incluído.</p>
            @include('livewire.ferramentas.packs', ['video' => null])
        </section>
    @endif
</div>
