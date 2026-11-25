@php
    $aTrabalhar = $p->aTrabalhar();
    $etapa = \App\Models\Imoplanta::ETAPAS[$p->estado] ?? null;
    // a version tag per file: a regenerated image gets a new link, so the browser can't show the cached old one
    $v = fn (?string $f) => substr(md5((string) $f), 0, 8);
    $nomePiso = fn (int $n) => $p->casa['niveis'][$n]['nome'] ?? 'Piso '.$n;
    $passos = [1 => 'Modelo e câmaras', 2 => 'Acabamentos e plantas 3D', 3 => 'Imagens', 4 => 'Vídeo'];
    $admin = auth()->user()->is_admin;
    $podeMexer = $p->user_id === auth()->id() || $admin;
    $download = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>';
@endphp
<div @if ($aTrabalhar) wire:poll.4s @endif class="font-sans">
    @vite('resources/js/imoplanta.js')
    <a href="{{ route('imoplanta') }}" class="text-sm text-ink-soft hover:text-ink">← Imoplanta</a>
    <div class="mt-2">
        <h1 class="font-display text-4xl md:text-5xl font-medium leading-none">{{ $p->nome }}</h1>
        <p class="mt-2 text-sm text-ink-soft">{{ $p->tipologiaRotulo() }}</p>
    </div>

    {{-- The steps: done ones can be looked at again; later ones open as the work gets there. --}}
    {{-- the finished page has no steps for customers; test accounts (admins, free accounts) keep them to go back --}}
    @if (! ($passo === 4 && $p->video) || $admin || auth()->user()->imoplantaGratis())
    <nav aria-label="Passos" class="mt-6">
        <ol class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach ($passos as $n => $nome)
                @php $feito = $n < $atual || ($n < $alcance); $aqui = $n === $passo; $aberto = $n <= $alcance; @endphp
                <li>
                    <button type="button" wire:click="$set('ver', {{ $n }})" @disabled(! $aberto) @if ($aqui) aria-current="step" @endif
                            class="w-full min-h-14 text-left px-4 py-2 rounded-xl border transition
                                   {{ $aqui ? 'border-coral bg-coral/10 text-ink' : ($aberto ? 'border-ink-soft/25 text-ink-soft hover:border-ink-soft/50 hover:text-ink' : 'border-ink-soft/10 text-ink-faint opacity-60 cursor-not-allowed') }}">
                        <span class="block text-[11px] tracking-[.18em] {{ $aqui ? 'text-coral' : ($feito ? 'text-cyan' : '') }}">{{ $feito && ! $aqui ? '✓ ' : '' }}PASSO {{ $n }}</span>
                        <span class="block text-sm font-semibold">{{ $nome }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>
    @endif

    @if ($etapa)
        <div role="status" class="mt-6 rounded-xl bg-[#FBEBD9] text-[#8A4B12] px-5 py-3 text-sm font-medium flex items-center gap-3">
            <span class="inline-block w-2 h-2 rounded-full bg-current animate-pulse"></span> {{ $etapa }}… pode sair desta página, o trabalho continua.
        </div>
    @endif
    @if ($p->estado === 'falhou')
        <div role="alert" class="mt-6 rounded-xl bg-coral/15 text-ink px-5 py-3 text-sm flex flex-wrap items-center gap-3">
            <span>Não conseguimos ler as plantas. {{ $p->erro }}</span>
            <button type="button" wire:click="tentarDeNovo" class="min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold">Tentar de novo (sem custo)</button>
        </div>
    @elseif ($p->erro)
        <p role="alert" class="mt-6 rounded-xl bg-coral/15 text-ink px-5 py-3 text-sm">{{ $p->erro }}</p>
    @endif

    {{-- The renders this browser still owes the server (top views, shots), made and sent in the background on any step. --}}
    @if ($envios && $envios['pendentes'])
        <div wire:ignore wire:key="envios-{{ $p->estado }}-{{ $p->imagens->count() }}-{{ count($envios['pendentes']) }}" x-data="imoplantaEnvios(@js($envios))">
            <p x-show="progresso" x-cloak x-text="progresso" role="status" class="mt-4 rounded-xl bg-[#FBEBD9] text-[#8A4B12] px-4 py-2.5 text-sm font-medium"></p>
        </div>
    @endif

    {{-- ═══════════ 1. Model and cameras ═══════════ --}}
    @if ($passo === 1)
        <div class="mt-6 flex gap-3 overflow-x-auto pb-2">
            @foreach ($p->plantas as $i => $f)
                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'planta', $i]) }}" target="_blank" class="shrink-0">
                    <img src="{{ route('imoplanta.ficheiro', [$p->id, 'planta', $i]) }}" alt="Planta {{ $i + 1 }}" class="h-24 w-auto rounded-lg border border-ink-soft/20 bg-white">
                </a>
            @endforeach
            @foreach ($p->referencias ?? [] as $i => $f)
                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'referencia', $i]) }}" target="_blank" class="shrink-0">
                    <img src="{{ route('imoplanta.ficheiro', [$p->id, 'referencia', $i]) }}" alt="Referência {{ $i + 1 }}" class="h-24 w-auto rounded-lg border border-ink-soft/20 bg-white">
                </a>
            @endforeach
        </div>

        @if ($tres)
            @php $ed = $tres['editavel']; @endphp
            <section class="mt-6">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 class="font-display text-3xl">Modelo 3D e câmaras</h2>
                    @if ($ed)
                        <p class="text-sm text-ink-soft">Alterações ao modelo 3D: <strong class="text-ink">{{ $p->refeitos3dRestantes() }}</strong> de {{ config('contentmachine.imoplanta.refazer_3d') }} disponíveis</p>
                    @endif
                </div>
                @if ($ed)
                    <p class="mt-1 text-sm text-ink-soft">À direita, cada cone numerado é uma câmara vista de cima. Arraste o ponto para a mudar de sítio e a pega na ponta para a rodar; o modelo 3D mostra a fotografia dessa câmara, e por baixo vê todas. Quando estiver como quer, continue: o modelo e as câmaras ficam fixos.</p>
                @else
                    <p class="mt-1 text-sm text-ink-soft">O modelo e as câmaras usados nas imagens (já não mudam). Clique numa câmara para ver a vista que foi enviada para a IA.</p>
                @endif
                {{-- wire:ignore'd three.js; re-made when the description changes (versao) or the step does (estado). --}}
                <div wire:ignore wire:key="tres-{{ $p->versao }}-{{ $p->estado }}" x-data="imoplanta(@js($tres))" class="mt-3 flex flex-col gap-4">
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_400px]">
                        <div class="relative h-[60vh] min-h-[360px] rounded-2xl overflow-hidden bg-[#dfe3e8]">
                            <div x-ref="tela" class="absolute inset-0"></div>
                            {{-- a previewed shot: its exact 16:9 frame, the rest darkened --}}
                            <div x-show="tiro !== null" x-cloak :style="quadroEstilo()" class="absolute pointer-events-none outline outline-1 outline-white/70 shadow-[0_0_0_100vmax_rgba(5,12,24,.6)]">
                                <span class="absolute left-3 bottom-3 rounded-md bg-navy/85 px-3 py-1.5 text-xs font-semibold text-ivory" x-text="rotulo()"></span>
                            </div>
                            <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                                <template x-if="tiro === null">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" @click="alternarTelhado()" class="min-h-11 px-4 rounded-full bg-white/85 text-navy text-sm" x-text="telhado ? 'Esconder cobertura' : 'Mostrar cobertura'"></button>
                                        <template x-if="niveis > 1">
                                            <select x-model.number="piso" @change="aplicar()" aria-label="Pisos visíveis" class="min-h-11 px-3 rounded-full bg-white/85 text-navy text-sm">
                                                <template x-for="i in niveis" :key="i"><option :value="i - 1" x-text="'Até ao piso ' + i"></option></template>
                                            </select>
                                        </template>
                                    </div>
                                </template>
                                <button type="button" x-show="tiro !== null" x-cloak @click="vistaLivre()" class="min-h-11 px-4 rounded-full bg-white/90 text-navy text-sm font-semibold">← Vista livre</button>
                            </div>
                            <p x-show="tiro === null" class="absolute left-3 bottom-3 rounded-md bg-white/85 px-3 py-1.5 text-xs text-navy">Arrastar: rodar · Roda: zoom · Botão direito: deslocar</p>
                            <p x-show="erro" x-cloak x-text="erro" class="absolute inset-x-3 top-16 rounded-md bg-white/90 px-3 py-2 text-sm text-coral"></p>
                        </div>

                        <div class="flex flex-col gap-3">
                            <div class="relative">
                                <canvas x-ref="topo" class="block w-full aspect-square rounded-2xl bg-[#dfe3e8]"></canvas>
                                @php $vista = $tres['vista']; @endphp
                                {{-- viewBox written by Blade: the HTML parser lower-cases a bound ":viewBox", which SVG then ignores. --}}
                                <svg x-ref="svg" viewBox="{{ $vista['u1'] }} {{ $vista['v1'] }} {{ $vista['u2'] - $vista['u1'] }} {{ $vista['u2'] - $vista['u1'] }}" class="absolute inset-0 w-full h-full rounded-2xl touch-none select-none"
                                     @pointerdown="comecar($event)" @pointermove="mover($event)" @pointerup="largar()" @pointercancel="largar()">
                                    {{-- Drawn as markup (x-html): Alpine's x-for <template> doesn't work inside SVG. Events by delegation (data-i / data-modo). --}}
                                    <g x-html="marcas()"></g>
                                </svg>
                            </div>
                            @if ($ed)
                                <div class="flex gap-2">
                                    <button type="button" @click="adicionar()" :disabled="cams.length >= max" class="flex-1 min-h-11 rounded-full border border-ink-soft/30 text-sm font-medium disabled:opacity-50">+ Câmara (<span x-text="cams.length"></span> de <span x-text="max"></span>)</button>
                                    <button type="button" @click="remover()" :disabled="cams.length <= 1" class="flex-1 min-h-11 rounded-full border border-ink-soft/30 text-sm font-medium disabled:opacity-50">Remover <span x-text="sel + 1"></span></button>
                                </div>
                                <template x-if="cams[sel]">
                                    <div class="rounded-2xl border border-ink-soft/20 p-4 flex flex-col gap-3 text-sm">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="font-semibold">Câmara <span x-text="sel + 1"></span></p>
                                            <button type="button" @click="verTiro(sel)" class="min-h-11 px-3 rounded-full border border-ink-soft/30 text-xs font-medium">Ver fotografia</button>
                                        </div>
                                        <label class="flex flex-col gap-1">Altura dos olhos (m){{ $p->tipologia === 'apartamento' ? ', acima do chão do apartamento' : '' }} — acima de 8 m é uma vista de drone
                                            <input type="number" step="0.1" min="0.5" max="120" x-model.number="cams[sel].altura" @input="mudou()" class="min-h-11 px-3 rounded-lg border border-ink-soft/30 bg-white text-navy">
                                        </label>
                                        <label class="flex flex-col gap-1">Ângulo de visão: <span x-text="Math.round(cams[sel].fov) + '°'"></span>
                                            <input type="range" min="20" max="110" step="1" x-model.number="cams[sel].fov" @input="mudou()">
                                        </label>
                                        <button type="button" @click="todasFov()" class="self-start text-xs underline text-ink-soft hover:text-ink">Usar <span x-text="Math.round(cams[sel].fov) + '°'"></span> em todas as câmaras</button>
                                        <label class="flex flex-col gap-1">Direção: <span x-text="Math.round(cams[sel].yaw) + '°'"></span>
                                            <input type="range" min="0" max="359" step="1" x-model.number="cams[sel].yaw" @input="mudou()">
                                        </label>
                                    </div>
                                </template>
                                <button type="button" @click="guardar()" :disabled="guardado" class="min-h-11 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold disabled:opacity-50"
                                        x-text="guardado ? 'Câmaras guardadas' : 'Guardar câmaras'"></button>
                                <button type="button" @click="avancar()"
                                        class="min-h-[52px] rounded-full bg-coral text-navy text-[15px] font-semibold">Continuar: acabamentos e plantas 3D →</button>
                                <p class="text-xs text-ink-soft">Continuar também guarda as câmaras.</p>
                            @endif
                        </div>
                    </div>

                    {{-- every planned shot, as the AI will get it --}}
                    <div>
                        <p class="text-sm font-semibold">As vistas das câmaras</p>
                        <div class="mt-2 grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                            <template x-for="(c, i) in cams" :key="i">
                                <button type="button" @click="escolher(i)" class="relative aspect-video rounded-xl overflow-hidden bg-[#dfe3e8] border-2 text-left"
                                        :class="i === sel ? 'border-coral' : 'border-transparent hover:border-ink-soft/40'">
                                    <img x-show="minis[i]" :src="minis[i]" alt="" class="absolute inset-0 w-full h-full object-cover">
                                    <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-full bg-navy/85 text-ivory text-xs font-semibold" x-text="i + 1"></span>
                                    <span class="absolute bottom-1.5 right-1.5 px-1.5 py-0.5 rounded bg-white/85 text-navy text-[10px]" x-text="Math.round(c.fov) + '°'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
                @error('cameras') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror

                @if ($ed)
                    <form wire:submit="alterarModelo" class="mt-4 rounded-2xl border border-ink-soft/20 p-5">
                        <label for="pedido" class="font-semibold">Algo não está como nas plantas? Peça à IA para mudar o modelo.</label>
                        <textarea id="pedido" wire:model="pedido" rows="2" maxlength="1000" placeholder="Ex.: A cobertura é de duas águas, não plana. · Acrescente uma piscina no jardim. · A garagem fica do lado esquerdo."
                                  class="mt-2 w-full px-4 py-3 rounded-[10px] border border-ink-soft/30 bg-white text-navy text-[15px] focus:outline-none focus:border-cyan"></textarea>
                        @error('pedido') <p class="mt-1 text-sm text-coral">{{ $message }}</p> @enderror
                        <button type="submit" @disabled($p->refeitos3dRestantes() === 0) wire:loading.attr="disabled" wire:target="alterarModelo"
                                class="mt-2 min-h-11 px-5 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold disabled:opacity-50">
                            Alterar o modelo ({{ $p->refeitos3dRestantes() }} {{ $p->refeitos3dRestantes() === 1 ? 'restante' : 'restantes' }})
                        </button>
                    </form>
                @endif
            </section>
        @endif

    {{-- ═══════════ 2. Materials and 3D floor plans ═══════════ --}}
    @elseif ($passo === 2)
        @php
            $emCurso = $p->estado === \App\Models\Imoplanta::REFERENCIAS;
            $ocupado = $p->quadro_estado === 'a_fazer' || collect($p->aereos ?? [])->contains(fn ($a) => ($a['estado'] ?? null) === 'a_fazer');
            $pronto = $p->referenciasProntas();
            $limite = config('contentmachine.imoplanta.refazer_referencias');
        @endphp
        <section class="mt-6">
            <h2 class="font-display text-3xl">Acabamentos e plantas 3D</h2>
            <p class="mt-1 text-sm text-ink-soft">O quadro de acabamentos e a planta 3D realista de cada piso vão com todas as imagens: o mesmo chão, móveis, azulejos, portas e caixilhos em todas. Veja se estão como quer{{ $emCurso ? ' — pode pedir até '.$limite.' alterações a cada um' : '' }}.</p>
            @if ($emCurso && $p->referenciasFalharam() && ! $ocupado)
                <div role="alert" class="mt-4 rounded-xl bg-coral/10 border border-coral/30 px-5 py-3 text-sm flex flex-wrap items-center gap-3">
                    <span>{{ $admin ? 'Falhou: '.($p->quadro_erro ?? 'uma planta 3D') : 'Uma das referências não ficou pronta.' }}</span>
                    <button type="button" wire:click="tentarReferencias" class="min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold">Tentar de novo (sem custo)</button>
                </div>
            @endif

            <div class="mt-5 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                {{-- the finishes board --}}
                <article class="rounded-2xl border border-ink-soft/20 overflow-hidden flex flex-col">
                    <div class="relative aspect-video bg-navy-3">
                        @if ($p->acabamentos_img)
                            <a href="{{ route('imoplanta.ficheiro', [$p->id, 'acabamentos', 'v' => $v($p->acabamentos_img)]) }}" target="_blank">
                                <img src="{{ route('imoplanta.ficheiro', [$p->id, 'acabamentos', 'v' => $v($p->acabamentos_img)]) }}" alt="Acabamentos" class="absolute inset-0 w-full h-full object-cover">
                            </a>
                        @endif
                        @if ($p->quadro_estado === 'a_fazer')
                            <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-[#FBEBD9] text-[#8A4B12] text-xs font-semibold animate-pulse">{{ $p->acabamentos_img ? 'A alterar' : 'A fazer' }}…</span>
                        @endif
                    </div>
                    <div class="p-4 flex flex-col gap-2">
                        <p class="font-semibold">Acabamentos{{ $p->acabamentos ? ' · '.(\App\Services\Imoplanta\Acabamentos::PALETAS[$p->acabamentos]['nome'] ?? '') : '' }}</p>
                        @if ($p->quadro_erro && $p->quadro_estado !== 'falhou') <p class="text-xs text-ink-soft">{{ $p->quadro_erro }}</p> @endif
                        @if ($emCurso && $p->acabamentos_img && $podeMexer)
                            <form wire:submit="alterarReferencia(null)" class="flex flex-col gap-2">
                                <label for="ref-q" class="text-sm">Mudar os acabamentos ({{ $admin ? 'sem limite' : $p->refeitosReferencia().' de '.$limite.' disponíveis' }})</label>
                                <textarea id="ref-q" wire:model="pedidosRef.q" rows="2" maxlength="1000" @disabled($ocupado || (! $admin && $p->refeitosReferencia() === 0))
                                          placeholder="Ex.: Soalho de carvalho mais claro e cozinha em verde-azeitona." class="px-3 py-2 rounded-lg border border-ink-soft/30 bg-white text-navy text-sm disabled:opacity-50"></textarea>
                                @error('pedidosRef.q') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                <button type="submit" @disabled($ocupado || (! $admin && $p->refeitosReferencia() === 0)) class="self-start min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold disabled:opacity-50">Alterar acabamentos</button>
                                <p class="text-xs text-ink-soft">As plantas 3D são refeitas com os novos acabamentos (incluído).</p>
                            </form>
                        @endif
                    </div>
                </article>

                {{-- each storey's realistic 3D floor plan --}}
                @foreach ($p->niveisComPlanta() as $n)
                    @php
                        $a = ($p->aereos ?? [])[$n] ?? [];
                        $f = $a['img'] ?? null;
                        $estadoPiso = $a['estado'] ?? null;
                    @endphp
                    <article wire:key="planta3d-{{ $n }}" class="rounded-2xl border border-ink-soft/20 overflow-hidden flex flex-col">
                        <div class="relative aspect-[3/4] bg-navy-3">
                            @if ($f)
                                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'aereo', $n, 'v' => $v($f)]) }}" target="_blank">
                                    <img src="{{ route('imoplanta.ficheiro', [$p->id, 'aereo', $n, 'v' => $v($f)]) }}" alt="Planta 3D · {{ $nomePiso($n) }}" class="absolute inset-0 w-full h-full object-contain bg-white">
                                </a>
                            @endif
                            @if ($estadoPiso === 'a_fazer' || (! $f && ! $estadoPiso && $emCurso))
                                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-[#FBEBD9] text-[#8A4B12] text-xs font-semibold animate-pulse">{{ $f ? 'A alterar' : 'A fazer' }}…</span>
                            @endif
                        </div>
                        <div class="p-4 flex flex-col gap-2">
                            <p class="font-semibold">Planta 3D · {{ $nomePiso($n) }}</p>
                            @if (! empty($a['erro'])) <p class="text-xs text-ink-soft">{{ $a['erro'] }}</p> @endif
                            @if ($f)
                                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'aereo', $n, 'download' => 1]) }}" class="self-start inline-flex items-center gap-1.5 min-h-10 px-3.5 rounded-full bg-coral text-navy text-xs font-semibold">{!! $download !!} Descarregar</a>
                            @endif
                            @if ($emCurso && $f && $podeMexer)
                                <form wire:submit="alterarReferencia({{ $n }})" class="flex flex-col gap-2">
                                    <label for="ref-{{ $n }}" class="text-sm">Mudar esta planta ({{ $admin ? 'sem limite' : $p->refeitosReferencia($n).' de '.$limite.' disponíveis' }})</label>
                                    <textarea id="ref-{{ $n }}" wire:model="pedidosRef.{{ $n }}" rows="2" maxlength="1000" @disabled($ocupado || (! $admin && $p->refeitosReferencia($n) === 0))
                                              placeholder="Ex.: O corredor fica vazio, sem sofá. · Cama de casal no quarto grande." class="px-3 py-2 rounded-lg border border-ink-soft/30 bg-white text-navy text-sm disabled:opacity-50"></textarea>
                                    @error('pedidosRef.'.$n) <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                    <button type="submit" @disabled($ocupado || (! $admin && $p->refeitosReferencia($n) === 0)) class="self-start min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold disabled:opacity-50">Alterar planta</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($emCurso)
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <button type="button" wire:click="gerarImagens" @disabled(! $pronto && ! ($p->referenciasFalharam() && ! $ocupado)) wire:loading.attr="disabled" wire:target="gerarImagens"
                            class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-50">Gerar as {{ count($p->cameras ?? []) }} imagens →</button>
                    @if (! $pronto) <p class="text-sm text-ink-soft">Disponível quando os acabamentos e as plantas 3D estiverem prontos.</p> @endif
                </div>
                @error('cameras') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
            @endif
        </section>

    {{-- ═══════════ 3. Images ═══════════ --}}
    @elseif ($passo === 3)
        <section class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-3xl">Imagens</h2>
                @can('admin')
                    <button type="button" wire:click="refazerFotografias" wire:confirm="Apagar estas imagens e voltar às câmaras para as tirar de novo? (só administradores; o que já foi gasto fica nos custos)"
                            class="min-h-11 px-4 rounded-full border border-ink-soft/30 text-sm font-medium">↺ Refazer fotografias (admin)</button>
                @endcan
            </div>
            @error('refazer') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
            @if ($p->imagens->contains('estado', 'falhou'))
                <button type="button" wire:click="tentarDeNovo" class="mt-2 min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold">Tentar de novo as que falharam (sem custo)</button>
            @endif
            <div class="mt-4 grid gap-6 md:grid-cols-2">
                @foreach ($p->imagens as $img)
                    <article wire:key="img-{{ $img->id }}" class="rounded-2xl overflow-hidden border border-ink-soft/20 flex flex-col">
                        <div class="relative aspect-video bg-navy-3">
                            @if ($img->foto)
                                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'foto', $img->id, 'v' => $v($img->foto)]) }}" target="_blank">
                                    <img src="{{ route('imoplanta.ficheiro', [$p->id, 'foto', $img->id, 'v' => $v($img->foto)]) }}" alt="Imagem {{ $img->numero }}" class="absolute inset-0 w-full h-full object-cover">
                                </a>
                            @elseif ($img->render)
                                <img src="{{ route('imoplanta.ficheiro', [$p->id, 'render', $img->id, 'v' => $v($img->render)]) }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60">
                            @endif
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-navy/80 text-ivory text-xs font-semibold">{{ $img->numero }}</span>
                            @if ($img->aTrabalhar())
                                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-[#FBEBD9] text-[#8A4B12] text-xs font-semibold animate-pulse">
                                    {{ ['a_renderizar' => 'A preparar a vista (mantenha esta página aberta)', 'a_fotografar' => 'A fotografar', 'a_alterar' => 'A alterar'][$img->estado] }}…
                                </span>
                            @elseif ($img->estado === 'falhou')
                                <span class="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-ivory text-navy text-xs font-semibold">Por gerar</span>
                            @endif
                        </div>
                        <div class="p-4 flex flex-col gap-2">
                            @if ($img->erro)
                                {{-- kie's own reason is for us; the customer gets a calm line and the free retry below --}}
                                <p class="text-sm {{ $admin ? 'text-coral' : 'text-ink-soft' }}">{{ $admin ? $img->erro : 'Esta imagem não ficou pronta. Pode gerá-la de novo, sem custo.' }}</p>
                            @endif
                            @if ($img->foto)
                                <a href="{{ route('imoplanta.ficheiro', [$p->id, 'foto', $img->id, 'download' => 1]) }}" class="self-start inline-flex items-center gap-2 min-h-11 px-4 rounded-full bg-coral text-navy text-sm font-semibold">{!! $download !!} Descarregar</a>
                            @endif
                            @if ($img->estado === 'falhou')
                                <button type="button" wire:click="gerarImagemDeNovo({{ $img->id }})" class="self-start min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold">↻ Gerar de novo (sem custo)</button>
                            @endif
                            @if ($img->estado === 'pronta')
                                <form wire:submit="alterarImagem({{ $img->id }})" class="flex flex-col gap-2">
                                    <label for="pi-{{ $img->id }}" class="text-sm">Pedir uma alteração ({{ $img->refeitosRestantes() }} de {{ config('contentmachine.imoplanta.refazer_imagem') }} disponíveis)</label>
                                    <textarea id="pi-{{ $img->id }}" wire:model="pedidosImagem.{{ $img->id }}" rows="2" maxlength="1000" @disabled($img->refeitosRestantes() === 0)
                                              placeholder="Ex.: Ponha o relvado mais verde e tire o carro." class="px-3 py-2 rounded-lg border border-ink-soft/30 bg-white text-navy text-sm disabled:opacity-50"></textarea>
                                    @error('pedidosImagem.'.$img->id) <p class="text-sm text-coral">{{ $message }}</p> @enderror
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" @disabled($img->refeitosRestantes() === 0) class="min-h-11 px-4 rounded-full bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50 text-sm font-semibold disabled:opacity-50">Alterar imagem</button>
                                        <button type="button" wire:click="gerarImagemDeNovo({{ $img->id }})" @disabled(! $admin && $img->refeitosRestantes() === 0)
                                                wire:confirm="Gerar esta imagem de novo a partir da mesma vista?{{ $admin ? '' : ' Usa 1 alteração.' }}"
                                                class="min-h-11 px-4 rounded-full border border-ivory/40 text-ivory hover:border-ivory/70 text-sm font-semibold disabled:opacity-50">↻ Gerar de novo</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($alcance >= 4)
                <button type="button" wire:click="$set('ver', 4)" class="mt-6 min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold">Seguir para o vídeo →</button>
            @else
                <p class="mt-6 text-sm text-ink-soft">O vídeo fica disponível quando todas as imagens estiverem prontas.</p>
            @endif
        </section>

    {{-- ═══════════ 4. Video ═══════════ --}}
    @else
        @php
            $todasProntas = $p->imagens->every(fn ($i) => $i->estado === 'pronta' && $i->foto);
            $nClips = $p->imagens->whereNotNull('foto')->count();
            $euros = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
            $campo = 'w-full min-h-11 px-3 rounded-lg border border-ink-soft/30 bg-white text-navy text-sm';
            $plantaEstado = $p->extra('planta');
            $reelEstado = $p->extra('video');
            $aFazer = fn (string $t) => '<p role="status" class="rounded-xl bg-[#FBEBD9] text-[#8A4B12] px-4 py-3 text-sm font-medium flex items-center gap-3"><span class="inline-block w-2 h-2 rounded-full bg-current animate-pulse"></span>'.e($t).'</p>';
            $livre = fn (?string $e) => in_array($e, [null, 'a_pagar'], true);
            $previaReelAFazer = ($id = $p->extra('video', 'previa_clip')) && $livre($reelEstado) && (app(\App\Services\Clips\Store\ClipStore::class)->find($id)?->isActive() ?? false);
            $comCredito = $creditos > 0 && $livre($reelEstado);
            $precoReel = $comCredito ? '1 vídeo' : $euros($precos['video']);
            $juntos = $podeMexer && $p->video && $livre($plantaEstado) && $livre($reelEstado) && ! $comCredito;
            // the pop-up in the video's last 4 seconds: only while an add-on can still be had
            $popup = $podeMexer && ($livre($plantaEstado) || ($p->video && $livre($reelEstado)));
        @endphp
        <section class="mt-6">
            <h2 class="font-display text-3xl">{{ $p->video && $p->video_estado !== 'a_fazer' ? 'O seu vídeo está pronto' : 'Vídeo walkthrough' }}</h2>
            @if ($p->video_estado === 'a_fazer')
                <div role="status" class="mt-4 rounded-xl bg-[#FBEBD9] text-[#8A4B12] px-5 py-3 text-sm font-medium flex items-center gap-3">
                    <span class="inline-block w-2 h-2 rounded-full bg-current animate-pulse"></span>
                    A criar o vídeo ({{ $nClips }} cenas, cerca de 5 a 10 minutos)… pode sair desta página, o trabalho continua.
                </div>
            @elseif ($p->video)
                <div x-data="{ fim: false, visto: false }" @keydown.escape.window="fim = false">
                    <video controls preload="metadata" class="mt-4 w-full max-w-4xl rounded-2xl bg-navy-3 aspect-video"
                           @if ($popup) @timeupdate="if (! visto && $el.duration && $el.duration - $el.currentTime <= 4) { visto = true; if (document.fullscreenElement) document.exitFullscreen(); fim = true }" @endif
                           src="{{ route('imoplanta.ficheiro', [$p->id, 'video']) }}?v={{ md5($p->video) }}"></video>
                    @if ($popup)
                        {{-- full-screen offer over the last scene; the video keeps playing behind it --}}
                        <div x-show="fim" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-labelledby="popup-extras"
                             class="fixed inset-0 z-50 bg-[#0E1626] text-ivory overflow-y-auto">
                            <div class="min-h-full max-w-4xl mx-auto px-4 py-10 flex flex-col justify-center gap-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <span class="text-[11px] tracking-[.3em] text-cyan">ANTES DE IR</span>
                                        <h2 id="popup-extras" class="mt-1 font-display text-4xl leading-tight">Mais dois vídeos deste imóvel</h2>
                                    </div>
                                    <button type="button" @click="fim = false" class="shrink-0 w-11 h-11 rounded-full border border-ivory/30 text-xl hover:border-ivory/60" aria-label="Fechar">×</button>
                                </div>
                                <div class="grid gap-6 md:grid-cols-[3fr_2fr] items-start">
                                    @if ($livre($plantaEstado))
                                        <div class="flex flex-col gap-3">
                                            <x-imoplanta.previa-planta :p="$p" />
                                            <p class="font-display text-2xl">Vídeo da planta 3D · {{ $euros($precos['planta']) }}</p>
                                            <p class="text-sm text-[#C9CED6]">Do terreno vazio às paredes e aos móveis, todos os pisos.</p>
                                            <button type="button" wire:click="comprarExtra('planta')" class="self-start min-h-12 px-6 rounded-full bg-coral text-navy text-sm font-semibold">Quero este vídeo →</button>
                                        </div>
                                    @endif
                                    @if ($livre($reelEstado))
                                        <div class="flex flex-col gap-3 max-w-[260px]">
                                            <x-imoplanta.previa-reel :p="$p" :preco="$reel['preco'] ?? ''" />
                                            <p class="font-display text-2xl">Vídeo Imocena · {{ $precoReel }}</p>
                                            <p class="text-sm text-[#C9CED6]">Reel vertical com narração, para as redes sociais.</p>
                                            <button type="button" wire:click="comprarExtra('video')" @click="fim = false" class="self-start min-h-12 px-6 rounded-full bg-coral text-navy text-sm font-semibold">Quero este vídeo →</button>
                                        </div>
                                    @endif
                                </div>
                                @if ($juntos)
                                    <button type="button" wire:click="comprarExtra('ambos')" class="self-start min-h-[52px] px-7 rounded-full border-2 border-coral text-ivory text-[15px] font-semibold hover:bg-coral/10">Os dois por {{ $euros($precos['ambos']) }} →</button>
                                @endif
                                <button type="button" @click="fim = false" class="self-start text-sm text-[#AEB5C1] underline">Agora não</button>
                            </div>
                        </div>
                    @endif
                </div>
                @php
                    $temCenas = $p->imagens->contains(fn ($i) => isset(((array) $p->video_clips)[$i->id]));
                    $botao = 'min-h-11 px-5 inline-flex items-center gap-1.5 rounded-full text-sm font-semibold';
                @endphp
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('imoplanta.ficheiro', [$p->id, 'video']) }}?download=1" class="{{ $botao }} bg-coral text-navy">{!! $download !!} Vídeo completo</a>
                    @if ($temCenas)
                        <a href="{{ route('imoplanta.zip', [$p->id, 'cenas']) }}" class="{{ $botao }} bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50">{!! $download !!} Todas as cenas do vídeo</a>
                    @endif
                    <a href="{{ route('imoplanta.zip', [$p->id, 'fotos']) }}" class="{{ $botao }} bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50">{!! $download !!} Todas as fotografias</a>
                    @if (collect($p->niveisComPlanta())->contains(fn ($n) => $p->aereo($n)))
                        <a href="{{ route('imoplanta.zip', [$p->id, 'plantas']) }}" class="{{ $botao }} bg-navy-3 border border-ivory/25 text-ivory hover:border-ivory/50">{!! $download !!} {{ count($p->niveisComPlanta()) > 1 ? 'Plantas 3D' : 'Planta 3D' }}</a>
                    @endif
                    @can('admin')
                        <button type="button" wire:click="criarVideo" wire:confirm="Fazer o vídeo de novo, com clips novos? (só administradores; ~0,40 $ por cena)"
                                class="min-h-11 px-4 rounded-full border border-ink-soft/30 text-sm font-medium">↺ Refazer vídeo (admin)</button>
                    @endcan
                </div>
            @else
                @if ($p->video_estado === 'falhou')
                    <p role="alert" class="mt-4 rounded-xl bg-coral/15 text-ink px-5 py-3 text-sm">{{ $admin ? 'O vídeo falhou: '.$p->video_erro : 'O vídeo não ficou pronto. Pode tentar de novo.' }}</p>
                @endif
                <p class="mt-2 text-sm text-ink-soft">Cada imagem ganha um movimento de câmara suave (5 s), pela ordem de uma visita: exterior, entrada, sala, cozinha, quartos, casas de banho. Fica pronto em cerca de 5 a 10 minutos.</p>
                <button type="button" wire:click="criarVideo" @disabled(! $todasProntas) wire:loading.attr="disabled" wire:target="criarVideo"
                        class="mt-3 min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-50">
                    {{ $p->video_estado === 'falhou' ? 'Tentar de novo' : 'Criar o vídeo com IA' }} ({{ $nClips }} cenas) →
                </button>
            @endif
            @error('video') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        </section>

        {{-- The add-ons: bought one by one (free accounts: 0 €), made in the background. --}}
        {{-- the add-ons appear once the AI video is done (not while it is first made or remade) --}}
        @if ($p->video && $p->video_estado !== 'a_fazer')
        <section id="extras" class="mt-12 scroll-mt-20" @if ($plantaEstado === 'a_fazer' || $reelEstado === 'a_fazer' || $previaReelAFazer) wire:poll.6s @endif>
            <h2 class="font-display text-3xl">Extras</h2>
            <p class="mt-1 text-sm text-ink-soft">Mais dois vídeos a partir do mesmo imóvel. Ficam prontos aqui; pode sair desta página entretanto.</p>
            @if ($aviso) <p role="status" class="mt-4 rounded-xl bg-[#E3F0E7] text-[#22603A] px-5 py-3 text-sm font-medium">{{ $aviso }}</p> @endif

            <div class="mt-5 grid gap-5 lg:grid-cols-2 items-start">
                {{-- the plan video --}}
                <article class="rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-6 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-2xl leading-tight">Vídeo da planta 3D</h3>
                        <span class="shrink-0 px-3 py-1 rounded-full bg-ivory/10 text-sm font-semibold">{{ $euros($precos['planta']) }}</span>
                    </div>
                    @if ($plantaEstado !== 'pronto') <x-imoplanta.previa-planta :p="$p" /> @endif
                    <p class="text-sm text-[#C9CED6]">A casa vista de cima: começa num terreno vazio, depois o chão e as paredes, e por fim os móveis a entrar no lugar. Todos os pisos, um a seguir ao outro.</p>
                    @if ($plantaEstado === 'pronto' && $p->extra('planta', 'video'))
                        <video controls playsinline preload="metadata" class="w-full rounded-xl bg-white"
                               src="{{ route('imoplanta.ficheiro', [$p->id, 'planta_video', 'v' => $v($p->extra('planta', 'video'))]) }}"></video>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('imoplanta.ficheiro', [$p->id, 'planta_video', 'download' => 1]) }}" class="inline-flex items-center gap-1.5 min-h-11 px-5 rounded-full bg-coral text-navy text-sm font-semibold">{!! $download !!} Descarregar</a>
                            @if ($admin)
                                <button type="button" wire:click="comprarExtra('planta')" class="min-h-11 px-4 rounded-full border border-ivory/40 text-sm">↻ Fazer de novo (admin)</button>
                            @endif
                        </div>
                    @elseif ($plantaEstado === 'a_fazer')
                        {!! $aFazer('A fazer o vídeo da planta (cerca de 10 minutos por piso)…') !!}
                    @elseif ($podeMexer)
                        @if ($plantaEstado === 'falhou')
                            <p role="alert" class="text-sm text-[#F5B8B1]">{{ $admin ? 'Falhou: '.$p->extra('planta', 'erro') : 'O vídeo não ficou pronto. Pode tentar de novo, sem custo.' }}</p>
                        @endif
                        <button type="button" wire:click="comprarExtra('planta')" wire:loading.attr="disabled" wire:target="comprarExtra('planta')"
                                class="self-start min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-60">
                            {{ $plantaEstado === 'falhou' ? 'Tentar de novo' : 'Quero este vídeo · '.$euros($precos['planta']) }} →
                        </button>
                    @endif
                    @error('extra.planta') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                </article>

                {{-- the walkthrough as an Imocena reel --}}
                <article class="rounded-2xl bg-navy-2 border border-ivory/10 text-ivory p-6 flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-2xl leading-tight">Vídeo Imocena</h3>
                        <span class="shrink-0 px-3 py-1 rounded-full bg-ivory/10 text-sm font-semibold">{{ $precoReel }}</span>
                    </div>
                    @if ($reelEstado !== 'pronto') <x-imoplanta.previa-reel :p="$p" :preco="$reel['preco'] ?? ''" class="w-full max-w-[220px]" /> @endif
                    <p class="text-sm text-[#C9CED6]">O seu vídeo feito reel vertical para redes sociais, como os da Imocena: narração em português, música e os dados do imóvel no ecrã.{{ $plantaEstado === 'pronto' ? ' Inclui o vídeo da planta 3D.' : '' }}</p>
                    @if ($reelEstado === 'pronto' && $reelClip)
                        <video controls playsinline preload="metadata" class="w-full max-w-xs aspect-[9/16] rounded-xl bg-navy-3"
                               src="{{ route('imoplanta.ficheiro', [$p->id, 'reel', 'v' => $v($reelClip->id)]) }}"></video>
                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('imoplanta.ficheiro', [$p->id, 'reel', 'download' => 1]) }}" class="inline-flex items-center gap-1.5 min-h-11 px-5 rounded-full bg-coral text-navy text-sm font-semibold">{!! $download !!} Descarregar</a>
                        </div>
                    @elseif ($reelEstado === 'a_fazer' && $p->extra('video', 'espera'))
                        {!! $aFazer('À espera do vídeo da planta, que também entra neste vídeo…') !!}
                    @elseif ($reelEstado === 'a_fazer')
                        {!! $aFazer((\App\Livewire\Imoveis::ESTADOS[$reelClip?->status][0] ?? 'A preparar').'… (cerca de 10 minutos)') !!}
                    @elseif (! $p->video)
                        <p class="text-sm text-[#AEB5C1]">Fica disponível quando o vídeo walkthrough estiver pronto.</p>
                    @elseif ($reelEstado === 'dados' && $podeMexer)
                        {{-- paid: now what the video needs, all optional --}}
                        @php $ficheiro = 'text-sm text-[#C9CED6] file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-ivory file:text-navy file:font-medium'; @endphp
                        <p role="status" class="rounded-xl bg-[#E3F0E7] text-[#22603A] px-4 py-3 text-sm font-medium">Pago. Agora os dados do vídeo: tudo opcional, pode deixar em branco.</p>
                        <form wire:submit="enviarDadosReel" class="flex flex-col gap-5">
                            <fieldset class="flex flex-col gap-3">
                                <legend class="mb-2 text-xs tracking-[.22em] text-cyan">O IMÓVEL</legend>
                                <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Link do anúncio (RE/MAX, iad ou idealista)
                                    <input type="url" wire:model="reel.anuncio" placeholder="https://www.idealista.pt/imovel/…" class="{{ $campo }}">
                                </label>
                                @error('reel.anuncio') <p class="-mt-1 text-sm text-coral">{{ $message }}</p> @enderror
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Preço <input wire:model="reel.preco" placeholder="265 000 €" class="{{ $campo }}"></label>
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Localização <input wire:model="reel.local" placeholder="Braga, São Victor" class="{{ $campo }}"></label>
                                </div>
                                <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">O que destacar
                                    <textarea wire:model="reel.notas" rows="3" maxlength="3000" placeholder="Ex.: Vista mar, a 5 minutos da praia. Pronta a habitar em 2027." class="px-3 py-2 rounded-lg border border-ink-soft/30 bg-white text-navy text-sm"></textarea>
                                </label>
                            </fieldset>
                            <fieldset class="flex flex-col gap-3">
                                <legend class="mb-2 text-xs tracking-[.22em] text-cyan">O SEU CONTACTO <span class="tracking-normal text-[#AEB5C1]">· no início e no fim do vídeo</span></legend>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6] sm:col-span-2">Nome <input wire:model="reel.nome" autocomplete="name" class="{{ $campo }}"></label>
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Telefone <input type="tel" wire:model="reel.telefone" autocomplete="tel" class="{{ $campo }}"></label>
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Email <input type="email" wire:model="reel.email" autocomplete="email" class="{{ $campo }}"></label>
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">A sua foto
                                        <input type="file" accept="image/*" wire:model="fotoContacto" class="{{ $ficheiro }}">
                                        <span wire:loading wire:target="fotoContacto" class="text-xs text-cyan">A carregar…</span>
                                    </label>
                                    <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Logótipo
                                        <input type="file" accept="image/*" wire:model="logoContacto" class="{{ $ficheiro }}">
                                        <span wire:loading wire:target="logoContacto" class="text-xs text-cyan">A carregar…</span>
                                    </label>
                                </div>
                            </fieldset>
                            @foreach (['reel.preco', 'reel.local', 'reel.notas', 'reel.nome', 'reel.telefone', 'reel.email', 'fotoContacto', 'logoContacto'] as $k)
                                @error($k) <p class="text-sm text-coral">{{ $message }}</p> @enderror
                            @endforeach
                            <button type="submit" wire:loading.attr="disabled" wire:target="enviarDadosReel, fotoContacto, logoContacto"
                                    class="self-start min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-60">Criar o vídeo →</button>
                        </form>
                    @elseif ($podeMexer)
                        @if ($reelEstado === 'falhou')
                            <p role="alert" class="text-sm text-[#F5B8B1]">{{ $admin ? 'Falhou: '.($p->extra('video', 'erro') ?? $reelClip?->error) : 'O vídeo não ficou pronto. Pode tentar de novo, sem custo.' }}</p>
                        @endif
                        <button type="button" wire:click="comprarExtra('video')" wire:loading.attr="disabled" wire:target="comprarExtra('video')"
                                class="self-start min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-60">
                            {{ $reelEstado === 'falhou' && ! $p->extra('video', 'compra') ? 'Tentar de novo' : ($comCredito || ($reelEstado === 'falhou' && $creditos > 0) ? 'Usar 1 dos seus vídeos ('.$creditos.' disponíve'.($creditos === 1 ? 'l' : 'is').')' : 'Quero este vídeo · '.$euros($precos['video'])) }} →
                        </button>
                        <p class="text-xs text-[#AEB5C1]">Depois pedimos os dados do anúncio e o seu contacto, tudo opcional.</p>
                    @endif
                    @error('extra.video') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                </article>
            </div>
            {{-- the two together, while neither has been asked for: the reel then opens with the plan video --}}
            @if ($juntos)
                <div class="mt-5 rounded-2xl border border-coral/50 bg-coral/10 p-5 flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm"><strong class="font-semibold">Os dois por {{ $euros($precos['ambos']) }}</strong> <span class="text-ink-soft">{{ $precos['ambos'] ? 'em vez de '.$euros($precos['planta'] + $precos['video']).'.' : '' }} O vídeo da planta entra também no Vídeo Imocena. Depois do pagamento pedimos os dados do vídeo.</span></p>
                    <button type="button" wire:click="comprarExtra('ambos')" wire:loading.attr="disabled" wire:target="comprarExtra('ambos')"
                            class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold disabled:opacity-60">Quero os dois · {{ $euros($precos['ambos']) }} →</button>
                </div>
                @error('extra.ambos') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
            @endif
        </section>
        @endif
    @endif
</div>
