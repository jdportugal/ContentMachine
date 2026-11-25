@php $eur = fn ($v, $d = 2) => number_format((float) $v, $d, ',', ' ').' €'; @endphp
<div class="font-sans">
    <span class="eyebrow">Imoplanta · admin</span>
    <h1 class="mt-2 font-display text-5xl font-medium leading-none">Custo vs. receita</h1>
    <p class="mt-3 max-w-3xl text-sm text-ink-soft">Custo real de cada imóvel (LLM por tokens, imagens kie.ai por modelo, taxa Stripe por crédito; o modelo 3D e as vistas são feitos no navegador do cliente, sem custo de servidor), contra a receita do seu crédito (preço do pack ÷ imóveis do pack). Preços em <code>config/contentmachine.php</code> → <code>imoplanta.custos</code>; USD → EUR a {{ config('contentmachine.imoplanta.custos.eur_por_usd') }}.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-ink-soft/20 p-5 overflow-x-auto">
            <h2 class="font-display text-2xl">Por escalão</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-ink-soft"><tr><th class="py-1">Plano</th><th>Imóveis</th><th>Receita méd.</th><th>Custo méd.</th><th>Margem méd.</th></tr></thead>
                <tbody>
                    @foreach ($resumo['tiers'] as $plano => $t)
                        <tr class="border-t border-ink-soft/10"><td class="py-1.5">{{ $plano }}</td><td>{{ $t['imoveis'] }}</td><td>{{ $eur($t['receita']) }}</td><td>{{ $eur($t['custo'], 3) }}</td><td>{{ $eur($t['margem']) }}</td></tr>
                    @endforeach
                    <tr class="border-t border-ink-soft/30 font-semibold"><td class="py-1.5">Todos</td><td>{{ $resumo['media']['imoveis'] }}</td><td>{{ $eur($resumo['media']['receita']) }}</td><td>{{ $eur($resumo['media']['custo'], 3) }}</td><td>{{ $eur($resumo['media']['margem']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="rounded-2xl border border-ink-soft/20 p-5">
            <h2 class="font-display text-2xl">Custo médio por passo (por imóvel, com todas as alterações)</h2>
            <table class="mt-3 w-full text-sm">
                @foreach ($resumo['passos'] as $passo => $v)
                    <tr class="border-t border-ink-soft/10"><td class="py-1.5 font-mono">{{ $passo }}</td><td class="text-right">{{ $eur($v, 4) }}</td></tr>
                @endforeach
                <tr class="border-t border-ink-soft/30 font-semibold"><td class="py-1.5">Total</td><td class="text-right">{{ $eur($resumo['media']['custo'], 4) }}</td></tr>
            </table>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-ink-soft/20 p-5 overflow-x-auto">
        <h2 class="font-display text-2xl">Por imóvel</h2>
        <table class="mt-3 w-full text-sm whitespace-nowrap">
            <thead class="text-left text-ink-soft">
                <tr><th class="py-1 pr-3">#</th><th class="pr-3">Imóvel</th><th class="pr-3">Cliente</th><th class="pr-3">Plano</th><th class="pr-3">Refeitos 3D / imagens</th>
                    @foreach ($passos as $passo) <th class="pr-3 font-mono font-normal">{{ $passo }}</th> @endforeach
                    <th class="pr-3">Custo</th><th class="pr-3">Receita</th><th>Margem</th></tr>
            </thead>
            <tbody>
                @forelse ($linhas as $l)
                    <tr class="border-t border-ink-soft/10">
                        <td class="py-1.5 pr-3">{{ $l['id'] }}</td><td class="pr-3">{{ $l['nome'] }}</td><td class="pr-3">{{ $l['cliente'] }}</td><td class="pr-3">{{ $l['plano'] }}</td>
                        <td class="pr-3">{{ $l['refeitos_3d'] }} / {{ $l['refeitos_imagens'] }} ({{ $l['imagens'] }} img.)</td>
                        @foreach ($passos as $passo) <td class="pr-3">{{ $eur($l['passos'][$passo] ?? 0, 3) }}</td> @endforeach
                        <td class="pr-3 font-semibold">{{ $eur($l['custo'], 3) }}</td><td class="pr-3">{{ $eur($l['receita']) }}</td>
                        <td>{{ $eur($l['margem']) }} @if ($l['margem_pct'] !== null) ({{ $l['margem_pct'] }}%) @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="99" class="py-6 text-center text-ink-soft">Ainda não há imóveis Imoplanta.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
