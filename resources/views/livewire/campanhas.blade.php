@php
    $eur = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', ' ').' €';
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn ($v) => $v === null ? '—' : (($v * 100 < 10 ? number_format($v * 100, 1, ',', ' ') : number_format($v * 100, 0)).' %');
    $th = 'px-3 py-2 text-right font-mono text-[0.68rem] uppercase tracking-wider text-ink-faint font-normal';
    $td = 'px-3 py-3 text-right tabular-nums text-ink';
@endphp
<div class="font-sans">
    <span class="eyebrow">Marketing</span>
    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-5xl font-medium leading-none">Campanhas</h1>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($periodos as $chave => $nome)
                <button type="button" wire:click="$set('periodo', '{{ $chave }}')"
                        class="min-h-11 px-4 rounded-full text-sm border {{ $periodo === $chave ? 'bg-ink text-paper border-ink' : 'border-ink-soft/30 text-ink hover:bg-surface/60' }}">{{ $nome }}</button>
            @endforeach
            <button type="button" wire:click="atualizar" wire:loading.attr="disabled"
                    class="min-h-11 px-4 rounded-full text-sm border border-ink-soft/30 text-ink-soft hover:bg-surface/60 disabled:opacity-60">↻ Atualizar</button>
        </div>
    </div>

    {{-- Meta not connected / failing: say what to do, and still show the site's side. --}}
    @if (! $f['meta']['ok'])
        <div class="mt-6 rounded-xl border border-coral/40 bg-coral/10 p-4 text-sm text-ink">
            @if ($f['meta']['erro'] === 'sem-token')
                <p><strong>Falta ligar a conta de anúncios da Meta.</strong> Sem isso, o funil começa nas visitas ao site.</p>
            @else
                <p><strong>A Meta não devolveu os resultados dos anúncios.</strong> Resposta: {{ $f['meta']['erro'] }}</p>
            @endif
            <p class="mt-2 text-ink-soft">Crie um token com a permissão <span class="font-mono">ads_read</span> para a conta de anúncios (Definições do negócio → Utilizadores do sistema → Gerar token) e guarde-o no servidor como <span class="font-mono">META_ADS_TOKEN</span> no ficheiro <span class="font-mono">.env</span>.</p>
        </div>
    @endif
    @if ($f['semEtiquetas'])
        <div class="mt-6 rounded-xl border border-coral/40 bg-coral/10 p-4 text-sm text-ink">
            <p><strong>Os anúncios têm cliques, mas nenhuma visita chegou com as etiquetas da campanha.</strong> Em cada anúncio, no campo «Parâmetros de URL», ponha:</p>
            <p class="mt-2 break-all font-mono text-xs">{{ $parametros }}</p>
        </div>
    @endif

    {{-- The numbers that matter --}}
    <div class="mt-8 grid gap-3 grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Gasto em anúncios', $f['meta']['ok'] ? $eur($f['gasto']) : '—'],
            ['Custo por clique', $eur($f['custos']['clique'])],
            ['Custo por vídeo pedido', $eur($f['custos']['pedido'])],
            ['Custo por compra', $eur($f['custos']['compra'])],
            ['Receita dos anúncios', $eur($f['receita'] / 100).($f['retorno'] !== null ? ' · '.number_format($f['retorno'], 1, ',', ' ').'×' : '')],
        ] as [$rotulo, $valor])
            <div class="rounded-xl border border-ink-soft/15 bg-surface/40 p-4">
                <p class="font-mono text-[0.68rem] uppercase tracking-wider text-ink-faint">{{ $rotulo }}</p>
                <p class="mt-2 font-display text-3xl leading-none text-ink">{{ $valor }}</p>
            </div>
        @endforeach
    </div>

    {{-- The funnel --}}
    <section class="mt-8 rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-6 md:p-8">
        <div class="flex items-center gap-3.5">
            <span class="w-10 h-px bg-cyan"></span>
            <span class="text-xs tracking-[.32em] text-cyan">FUNIL DOS ANÚNCIOS META</span>
        </div>
        <div class="mt-6 flex flex-col gap-3">
            @foreach ($f['passos'] as $p)
                <div class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[190px_minmax(0,1fr)_90px]" wire:key="passo-{{ $loop->index }}">
                    <div>
                        <p class="text-[15px] text-ivory">{{ $p['nome'] }}</p>
                        <p class="text-xs text-[#AEB5C1]">{{ $p['nota'] }}</p>
                    </div>
                    <div class="h-11 rounded-lg bg-navy-3 overflow-hidden">
                        <div class="h-full flex items-center rounded-lg {{ $loop->last ? 'bg-coral text-navy' : 'bg-cyan text-navy' }}" style="width: {{ $p['largura'] }}%">
                            @if ($p['largura'] >= 14) <span class="px-3 font-semibold tabular-nums">{{ $n($p['n']) }}</span> @endif
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2 sm:justify-end">
                        @if ($p['largura'] < 14) <span class="font-semibold tabular-nums text-ivory">{{ $n($p['n']) }}</span> @endif
                        <span class="text-sm tabular-nums text-[#AEB5C1]" title="Em relação ao passo anterior">{{ $loop->first ? '' : $pct($p['taxa']) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-5 text-xs text-[#AEB5C1]">A percentagem compara cada passo com o anterior. As barras usam uma escala comprimida, para os passos pequenos se verem. Do site, contam só as visitas que chegaram com as etiquetas dos anúncios.</p>
    </section>

    {{-- By ad --}}
    <section class="mt-10">
        <h2 class="font-display text-3xl">Por anúncio</h2>
        @if ($f['anuncios'] === [])
            <p class="mt-3 text-ink-soft">{{ $f['meta']['ok'] ? 'Nenhum anúncio teve entregas neste período.' : 'Os anúncios aparecem aqui quando a conta da Meta estiver ligada.' }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-ink-soft/15">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-surface/60">
                        <tr>
                            <th class="{{ $th }} text-left">Anúncio</th>
                            <th class="{{ $th }}">Gasto</th><th class="{{ $th }}">Impressões</th><th class="{{ $th }}">Cliques</th><th class="{{ $th }}">CTR</th>
                            <th class="{{ $th }}">Visitas</th><th class="{{ $th }}">Pedidos</th><th class="{{ $th }}">Compras</th><th class="{{ $th }}">Receita</th><th class="{{ $th }}">Custo / pedido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($f['anuncios'] as $a)
                            <tr class="border-t border-ink-soft/10" wire:key="anuncio-{{ $a['id'] }}">
                                <td class="px-3 py-3 text-left"><span class="text-ink">{{ $a['nome'] }}</span><br><span class="font-mono text-[0.68rem] text-ink-faint">{{ $a['campanha'] }}</span></td>
                                <td class="{{ $td }}">{{ $eur($a['gasto']) }}</td>
                                <td class="{{ $td }}">{{ $n($a['impressoes']) }}</td>
                                <td class="{{ $td }}">{{ $n($a['cliques']) }}</td>
                                <td class="{{ $td }}">{{ $pct($a['impressoes'] > 0 ? $a['cliques'] / $a['impressoes'] : null) }}</td>
                                <td class="{{ $td }}">{{ $n($a['visitas']) }}</td>
                                <td class="{{ $td }}">{{ $n($a['pedidos']) }}</td>
                                <td class="{{ $td }}">{{ $n($a['compras']) }}</td>
                                <td class="{{ $td }}">{{ $eur($a['receita'] / 100) }}</td>
                                <td class="{{ $td }}">{{ $eur($a['gasto'] > 0 && $a['pedidos'] > 0 ? $a['gasto'] / $a['pedidos'] : null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- By channel: everything that reaches the site, paid or not --}}
    <section class="mt-10">
        <h2 class="font-display text-3xl">Todos os canais</h2>
        <p class="mt-1 text-sm text-ink-soft">De onde vêm as visitas ao site, e o que fazem. Conta a primeira origem de cada visitante.</p>
        @if ($f['canais'] === [])
            <p class="mt-3 text-ink-soft">Ainda não há visitas registadas neste período.</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-xl border border-ink-soft/15">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-surface/60">
                        <tr>
                            <th class="{{ $th }} text-left">Canal</th>
                            <th class="{{ $th }}">Visitas</th><th class="{{ $th }}">Vídeos pedidos</th><th class="{{ $th }}">Visita → pedido</th>
                            <th class="{{ $th }}">Prontos</th><th class="{{ $th }}">Compras</th><th class="{{ $th }}">Receita</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($f['canais'] as $chave => $c)
                            <tr class="border-t border-ink-soft/10" wire:key="canal-{{ $chave }}">
                                <td class="px-3 py-3 text-left text-ink">{{ $canaisNomes[$chave] }}</td>
                                <td class="{{ $td }}">{{ $n($c['visitas']) }}</td>
                                <td class="{{ $td }}">{{ $n($c['pedidos']) }}</td>
                                <td class="{{ $td }}">{{ $pct($c['visitas'] > 0 ? $c['pedidos'] / $c['visitas'] : null) }}</td>
                                <td class="{{ $td }}">{{ $n($c['prontos']) }}</td>
                                <td class="{{ $td }}">{{ $n($c['compras']) }}</td>
                                <td class="{{ $td }}">{{ $eur($c['receita'] / 100) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-ink-soft/30 font-semibold">
                            <td class="px-3 py-3 text-left text-ink">Total</td>
                            <td class="{{ $td }}">{{ $n($f['total']['visitas']) }}</td>
                            <td class="{{ $td }}">{{ $n($f['total']['pedidos']) }}</td>
                            <td class="{{ $td }}">{{ $pct($f['total']['visitas'] > 0 ? $f['total']['pedidos'] / $f['total']['visitas'] : null) }}</td>
                            <td class="{{ $td }}">{{ $n($f['total']['prontos']) }}</td>
                            <td class="{{ $td }}">{{ $n($f['total']['compras']) }}</td>
                            <td class="{{ $td }}">{{ $eur($f['total']['receita'] / 100) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
        <p class="mt-3 text-xs text-ink-faint">As visitas contam-se desde 11/11/2026. Os pedidos e as compras anteriores aparecem, mas sem visitas correspondentes.</p>
    </section>
</div>
