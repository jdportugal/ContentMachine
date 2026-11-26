@php
    $selo = ['pronto' => ['Pronto', 'bg-[#E3F0E7] text-[#22603A]'], 'a-fazer' => ['A fazer', 'bg-[#FBEBD9] text-[#8A4B12]'], 'falhou' => ['Falhou', 'bg-coral/20 text-coral']];
    $filtro = fn (string $v, string $t) => '<button type="button" wire:click="$set(\'estado\', \''.$v.'\')" class="min-h-9 px-4 rounded-full text-sm '.($estado === $v ? 'bg-cyan text-navy font-semibold' : 'border border-ink-soft/30 text-ink-soft hover:text-ink').'">'.$t.'</button>';
@endphp
<div class="font-sans">
    <span class="eyebrow">Contas</span>
    <h1 class="mt-2 font-display text-5xl font-medium leading-none">Todos os vídeos</h1>
    <p class="mt-3 max-w-2xl text-sm text-ink-soft">Os vídeos de todos os clientes, grátis e pagos, do mais recente para o mais antigo. Os links são os mesmos que o cliente recebeu por email e não expiram: copie-os para os enviar outra vez.</p>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="procura" placeholder="Procurar por email, nome ou link do anúncio"
               class="flex-[1_1_320px] min-h-11 px-4 rounded-[10px] bg-surface border border-ink-soft/20 text-ink text-sm focus:outline-none focus:border-cyan">
        <div class="flex flex-wrap gap-2">{!! $filtro('todos', 'Todos') !!}{!! $filtro('prontos', 'Prontos') !!}{!! $filtro('a-fazer', 'A fazer') !!}{!! $filtro('falhados', 'Falhados') !!}</div>
    </div>

    <p class="mt-4 text-xs text-ink-faint">{{ $leads->total() }} {{ $leads->total() === 1 ? 'vídeo' : 'vídeos' }}</p>

    <div class="mt-3 flex flex-col gap-2">
        @forelse ($linhas as $v)
            <article wire:key="v-{{ $v->id }}" x-data="{ copiado: '' }" class="rounded-xl border border-ink-soft/15 bg-vellum/60 px-4 py-3 flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $selo[$v->estado][1] }}">{{ $selo[$v->estado][0] }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] {{ $v->limpo ? 'bg-cyan/15 text-cyan' : 'bg-ink-soft/10 text-ink-soft' }}">{{ $v->limpo ? 'Sem marca d’água' : 'Grátis · com marca' }}</span>
                        <span class="text-xs text-ink-faint">#{{ $v->id }} · {{ \Illuminate\Support\Carbon::parse($v->quando)->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-ink truncate" title="{{ $v->titulo }}">{{ $v->titulo }}</p>
                    <p class="text-xs text-ink-soft truncate">
                        @if ($v->quem) <span class="font-medium">{{ $v->quem }}{{ $v->conta ? '' : ' (sem conta)' }}</span> @else <span class="text-ink-faint">sem email</span> @endif
                        @if ($v->nome) · {{ $v->nome }} @endif
                        · <a href="{{ $v->anuncio }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-cyan">anúncio</a>
                    </p>
                </div>
                @if ($v->ver)
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <a href="{{ $v->ver }}" target="_blank" rel="noopener" class="min-h-9 inline-flex items-center px-3.5 rounded-full border border-ink-soft/30 text-sm text-ink hover:border-cyan">Ver</a>
                        <button type="button" data-link="{{ $v->ver }}" x-on:click="navigator.clipboard.writeText($el.dataset.link).then(() => { copiado = 'ver'; setTimeout(() => copiado = '', 2000) })"
                                class="min-h-9 px-3.5 rounded-full bg-coral text-navy text-sm font-semibold"><span x-text="copiado === 'ver' ? 'Copiado ✓' : 'Copiar link'">Copiar link</span></button>
                        <button type="button" data-link="{{ $v->descarregar }}" x-on:click="navigator.clipboard.writeText($el.dataset.link).then(() => { copiado = 'mp4'; setTimeout(() => copiado = '', 2000) })"
                                class="min-h-9 px-3.5 rounded-full border border-ink-soft/30 text-sm text-ink hover:border-cyan"><span x-text="copiado === 'mp4' ? 'Copiado ✓' : 'Copiar link de download'">Copiar link de download</span></button>
                    </div>
                @endif
            </article>
        @empty
            <p class="mt-6 text-sm text-ink-soft">Nenhum vídeo{{ $procura !== '' ? ' para esta procura' : '' }}.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $leads->links() }}</div>
</div>
