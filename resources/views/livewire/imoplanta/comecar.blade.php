@php
    $eur = fn (int $c) => \Illuminate\Support\Number::currency($c / 100, 'EUR', 'pt_PT', precision: $c % 100 ? 2 : 0);
    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 mt-0.5"><path d="M5 12l5 5 9-10"/></svg>';
    $inclui = [
        'Modelo 3D da casa, com '.config('contentmachine.imoplanta.refazer_3d').' correções',
        'Até '.config('contentmachine.imoplanta.max_cameras').' fotografias realistas, câmaras já na sala, cozinha, quarto e casa de banho',
        config('contentmachine.imoplanta.refazer_imagem').' alterações por fotografia',
        'Vídeo de visita ao imóvel',
    ];
    // How it works, with icons (no step numbers): plans → 3D and cameras → photos and video.
    $como = [
        ['Carregue as plantas', 'As plantas de cada piso, com as cotas. Alçados e fotografias ajudam, mas são opcionais.', '<path d="M4 4h16v16H4z"/><path d="M4 12h8V4M12 12v8M16 12h4"/>'],
        ['Veja a casa em 3D', 'Rode o modelo, peça correções por escrito e escolha onde ficam as câmaras.', '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5"/>'],
        ['Receba fotografias e vídeo', 'Fotografias realistas de cada câmara e um vídeo de visita, prontos para o anúncio.', '<rect x="3" y="6" width="18" height="13" rx="2"/><circle cx="12" cy="12.5" r="3.5"/><path d="M8 6l1.5-2h5L16 6"/>'],
    ];
    $dicas = [
        'Uma imagem por piso, onde se leiam as cotas (as medidas).',
        'A planta de implantação mostra o terreno, a rua e os vizinhos.',
        'Alçados e cortes acertam o telhado, as alturas e as janelas.',
        'No link ou na descrição, diga os acabamentos que quer ver nas fotografias.',
    ];
    $seguir = [
        'Lemos as plantas e fazemos o modelo 3D em poucos minutos. Avisamos por email.',
        'Verifica o modelo, pede correções se for preciso e acerta as câmaras.',
        'Gera as fotografias e, quando quiser, o vídeo de visita.',
    ];
    $cartao = 'rounded-[20px] bg-navy text-ivory p-6 lg:p-8 shadow-[0_12px_32px_rgba(14,22,38,0.18)] flex flex-col gap-4';
    $rotulo = 'flex flex-col gap-1.5 text-[13px] text-[#C9CED6]';
    $campo = 'min-h-[48px] px-4 rounded-[10px] border-0 bg-ivory text-navy text-[15px] focus:outline-none focus:ring-2 focus:ring-cyan';
    $ficheiro = 'text-sm text-[#C9CED6] file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-ivory file:text-navy file:font-medium';
    $botao = 'min-h-[52px] rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60';
    $erro = '-mt-2 text-[13px] text-coral';
    $sobre = 'text-[11px] tracking-[.3em] text-[#0478A0]';
@endphp
<x-imoplanta.pagina>
    <x-slot:direita>
        @auth
            <span class="flex items-center gap-3">
                @if ($disponiveis)
                    <a href="{{ route('imoplanta') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-ivory/10 font-normal hover:bg-ivory/15"><strong class="font-semibold text-cyan">{{ $disponiveis }}</strong> {{ $disponiveis === 1 ? 'crédito de vídeo' : 'créditos de vídeo' }}</a>
                @endif
                <a href="{{ route('imoplanta') }}" class="hover:text-cyan">{{ auth()->user()->email }}</a>
            </span>
        @else
            <a href="{{ route('login') }}" class="hover:text-cyan">Entrar</a>
        @endauth
    </x-slot:direita>

    @if ($aviso)
        <p role="status" class="mb-6 rounded-xl bg-cyan/10 border border-cyan/40 px-4 py-3 text-sm">{{ $aviso }}</p>
    @endif

    @if ($passo === 'conta' || $passo === 'enviado')
        <x-imoplanta.colunas>
            <x-slot:intro>
                <div class="flex flex-col gap-3.5 lg:pt-6">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-0.5 bg-coral"></span>
                        <span class="{{ $sobre }}">IMOPLANTA</span>
                    </div>
                    <h1 class="font-display text-[40px] lg:text-[64px] font-medium leading-[1.02] text-navy">Das plantas ao vídeo do imóvel.</h1>
                    <p class="max-w-lg text-[15px] lg:text-[18px] leading-relaxed text-[#4A5263]">Carregue as plantas e receba o modelo 3D, fotografias realistas e um vídeo de visita, prontos para o anúncio.</p>
                </div>
            </x-slot:intro>
            <x-slot:extra>
                <ul class="flex flex-col gap-5 max-w-lg">
                    @foreach ($como as [$t, $d, $icone])
                        <li class="flex gap-4">
                            <span class="shrink-0 w-11 h-11 rounded-xl bg-navy flex items-center justify-center">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icone !!}</svg>
                            </span>
                            <span class="flex flex-col gap-0.5">
                                <strong class="text-[15px] font-semibold">{{ $t }}</strong>
                                <span class="text-sm leading-relaxed text-[#4A5263]">{{ $d }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-slot:extra>

            @if ($passo === 'conta')
                <form wire:submit="continuar" class="{{ $cartao }}" novalidate>
                    <h2 class="hidden lg:block font-display text-[30px] font-medium leading-tight text-ivory">Comece pelo seu email</h2>
                    <label class="{{ $rotulo }}">O seu email
                        <input type="email" wire:model="email" autocomplete="email" required class="{{ $campo }} min-h-[52px]">
                    </label>
                    @error('email') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    <div aria-hidden="true" class="absolute -left-[9999px] w-px h-px overflow-hidden">
                        <label>Site <input type="text" wire:model="site" tabindex="-1" autocomplete="off"></label>
                    </div>
                    <label class="flex gap-2.5 items-start text-[11px] leading-normal text-[#AEB5C1]">
                        <input type="checkbox" wire:model="consentimento" class="mt-px w-3.5 h-3.5 shrink-0 accent-cyan">
                        <span>Aceito que a Imocena use o meu email e as plantas que carregar para criar o modelo, as imagens e o vídeo do imóvel, de acordo com a <a href="#" class="text-ivory underline">Política de Privacidade</a> (RGPD).</span>
                    </label>
                    @error('consentimento') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="continuar" class="{{ $botao }}">Continuar →</button>
                    <p class="text-xs text-[#AEB5C1]">Sem palavra-passe: para voltar, basta o seu email.</p>
                </form>
            @else
                <section class="{{ $cartao }}">
                    <div class="w-14 h-14 rounded-full bg-cyan/15 flex items-center justify-center">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                    </div>
                    <h2 class="font-display text-[32px] font-medium leading-tight text-ivory">Veja o seu email</h2>
                    <p role="status" class="text-sm leading-relaxed text-[#C9CED6]">Esta conta tem acesso de administrador. Enviámos um link para <strong class="font-semibold text-ivory">{{ $enviadoPara }}</strong>: abra-o para entrar e continuar. O link vale 30 minutos e só funciona uma vez.</p>
                    <p class="text-[13px] text-[#AEB5C1]">Não chegou? Veja a pasta de spam ou promoções.</p>
                    <button type="button" wire:click="outroEmail" class="self-start text-[13px] text-[#C9CED6] underline">Usar outro email</button>
                </section>
            @endif
        </x-imoplanta.colunas>

    @elseif ($passo === 'plantas')
        <x-imoplanta.colunas>
            <x-slot:intro>
                <div class="flex flex-col gap-3 lg:pt-4">
                    <div class="flex items-start gap-3">
                        <span class="w-8 h-0.5 shrink-0 bg-coral mt-[1.2rem] lg:mt-[1.9rem]"></span>
                        <h1 class="font-display text-[32px] lg:text-[52px] font-medium leading-tight text-navy">Fale-nos do imóvel</h1>
                    </div>
                    <p class="hidden lg:block max-w-lg text-[17px] leading-relaxed text-[#4A5263]">Quanto mais claras as plantas, mais fiel fica o modelo 3D. Pode corrigi-lo depois, por escrito.</p>
                </div>
            </x-slot:intro>
            <x-slot:extra>
                <div class="hidden lg:flex flex-col gap-4 max-w-lg">
                    <span class="{{ $sobre }}">QUE PLANTAS CARREGAR</span>
                    <ul class="flex flex-col gap-3 text-[15px] text-[#3A4252]">
                        @foreach ($dicas as $d)
                            <li class="flex gap-3">{!! $check !!}<span>{{ $d }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </x-slot:extra>

            <form x-data wire:submit="enviar" class="{{ $cartao }}">
                <label class="{{ $rotulo }}">Nome do imóvel
                    <input type="text" wire:model="nome" placeholder="Ex.: Moradia T3, Rua das Flores 12" class="{{ $campo }}">
                </label>
                @error('nome') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="{{ $rotulo }}">Tipo de imóvel
                        <select wire:model="tipologia" x-on:change="$wire.tipologia = $event.target.value" class="{{ $campo }}">
                            @foreach (\App\Models\Imoplanta::TIPOLOGIAS as $valor => $nomeTipo)
                                <option value="{{ $valor }}">{{ $nomeTipo }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label x-show="$wire.tipologia === 'apartamento'" x-cloak class="{{ $rotulo }}">Andar (0 = rés-do-chão)
                        <input type="number" min="0" max="60" step="1" wire:model="piso" class="{{ $campo }}">
                    </label>
                </div>
                @error('tipologia') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                @error('piso') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                <label class="{{ $rotulo }}">Plantas e implantação (imagens)
                    <input type="file" multiple accept="image/png,image/jpeg,image/webp" wire:model="plantas" x-on:livewire-upload-start="$wire.set('plantas', [], false)" class="{{ $ficheiro }}">
                    <span wire:loading wire:target="plantas" class="text-xs text-cyan">A carregar…</span>
                </label>
                @error('plantas') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                @error('plantas.*') <p class="{{ $erro }}">{{ $message }}</p> @enderror

                <details class="group rounded-[14px] bg-[#1B2842] border border-cyan/35" @if ($errors->hasAny(['anuncio', 'notas', 'referencias', 'referencias.*']) || $anuncio || $notas) open @endif>
                    <summary class="min-h-11 px-3.5 flex items-center justify-between gap-2.5 cursor-pointer list-none text-[13px] font-medium [&::-webkit-details-marker]:hidden">
                        <span>Mais sobre o imóvel <span class="font-normal text-[#AEB5C1]">(opcional)</span></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#05B8E6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="transition-transform group-open:rotate-180"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="px-3.5 pb-3.5 flex flex-col gap-3">
                        <span class="text-xs text-[#AEB5C1]">Ajuda-nos a acertar os acabamentos, a fachada e a envolvente.</span>
                        <label class="{{ $rotulo }} text-xs">Link do anúncio ou do projeto
                            <input type="url" wire:model="anuncio" placeholder="https://…" class="{{ $campo }} min-h-11 text-sm">
                        </label>
                        @error('anuncio') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                        <label class="{{ $rotulo }} text-xs">Acabamentos, materiais, vista, o que quer destacar
                            <textarea wire:model="notas" rows="3" maxlength="2000" placeholder="Ex.: cozinha branca com bancada em pedra, soalho de carvalho, piscina virada a sul." class="px-4 py-2.5 rounded-[10px] border-0 bg-ivory text-navy text-sm resize-y focus:outline-none focus:ring-2 focus:ring-cyan"></textarea>
                        </label>
                        @error('notas') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                        <label class="{{ $rotulo }} text-xs">Alçados, cortes ou fotografias (imagens)
                            <input type="file" multiple accept="image/png,image/jpeg,image/webp" wire:model="referencias" x-on:livewire-upload-start="$wire.set('referencias', [], false)" class="{{ $ficheiro }}">
                            <span wire:loading wire:target="referencias" class="text-xs text-cyan">A carregar…</span>
                        </label>
                        @error('referencias') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                        @error('referencias.*') <p class="{{ $erro }}">{{ $message }}</p> @enderror
                    </div>
                </details>

                <button type="submit" wire:loading.attr="disabled" wire:target="enviar, plantas, referencias" class="{{ $botao }}">
                    <span wire:loading.remove wire:target="enviar">Continuar →</span>
                    <span wire:loading wire:target="enviar">A enviar…</span>
                </button>
            </form>
        </x-imoplanta.colunas>

    @else
        <x-imoplanta.colunas>
            <x-slot:intro>
                <div class="flex items-start gap-3 lg:pt-4">
                    <span class="w-8 h-0.5 shrink-0 bg-coral mt-[1.2rem] lg:mt-[1.9rem]"></span>
                    <h1 class="font-display text-[32px] lg:text-[52px] font-medium leading-tight text-navy">Tudo pronto para começar</h1>
                </div>
            </x-slot:intro>
            <x-slot:extra>
                <div class="hidden lg:flex flex-col gap-4 max-w-lg">
                    <span class="{{ $sobre }}">O QUE ACONTECE A SEGUIR</span>
                    <ul class="flex flex-col gap-3 text-[15px] text-[#3A4252]">
                        @foreach ($seguir as $d)
                            <li class="flex gap-3">{!! $check !!}<span>{{ $d }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </x-slot:extra>

            <section class="{{ $cartao }}">
                <div class="flex gap-3.5 items-center p-3 rounded-[14px] bg-[#1B2842]">
                    <img src="{{ route('imoplanta.ficheiro', [$pendente->id, 'planta', 0]) }}" alt="Planta" class="w-16 h-16 shrink-0 rounded-lg object-contain bg-ivory">
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <strong class="text-[15px] font-semibold truncate">{{ $pendente->nome }}</strong>
                        <span class="text-xs text-[#C9CED6]">{{ $pendente->tipologiaRotulo() }} · {{ count($pendente->plantas ?? []) }} {{ count($pendente->plantas ?? []) === 1 ? 'planta' : 'plantas' }}@if ($pendente->notas || $pendente->anuncio) · com descrição @endif</span>
                        <button type="button" wire:click="trocarPlantas" class="self-start text-xs underline hover:text-cyan">Alterar</button>
                    </div>
                </div>
                <ul class="flex flex-col gap-2.5 text-sm text-[#C9CED6]">
                    @foreach ($inclui as $i)
                        <li class="flex gap-2.5">{!! $check !!}<span>{{ $i }}</span></li>
                    @endforeach
                </ul>
                @php
                    $escolhido = $planos[$plano] ?? $planos[\App\Livewire\Imoplanta\Comecar::PLANO];
                    $comCreditos = ! $gratis && $disponiveis !== null && $disponiveis >= \App\Services\Pagamentos\Creditos::IMOPLANTA;   // no price: it uses credits
                    $total = $gratis || $comCreditos ? 0 : $escolhido['preco'];
                @endphp
                @if ($comCreditos)
                    <p class="pt-3.5 border-t border-ivory/10 text-sm text-[#C9CED6]">Usa <strong class="text-ivory">{{ \App\Services\Pagamentos\Creditos::IMOPLANTA }}</strong> dos seus {{ $disponiveis }} créditos de vídeo.</p>
                @elseif (! $gratis)
                    <fieldset class="flex flex-col gap-2 pt-3.5 border-t border-ivory/10">
                        <legend class="mb-2 text-[13px] text-[#C9CED6]">Quantos imóveis?</legend>
                        @foreach ($planos as $chave => $pl)
                            <label class="flex items-center gap-3 min-h-12 px-3.5 rounded-xl border cursor-pointer {{ $plano === $chave ? 'border-cyan bg-cyan/10' : 'border-ivory/15 hover:border-ivory/30' }}">
                                <input type="radio" wire:model.live="plano" value="{{ $chave }}" class="accent-cyan">
                                <span class="flex-1 text-sm font-medium">{{ $pl['imoveis'] }} {{ $pl['imoveis'] === 1 ? 'imóvel' : 'imóveis' }} <span class="text-[11px] font-normal text-[#AEB5C1]">· {{ $pl['imoveis'] * \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos</span></span>
                                <span class="text-right">
                                    <span class="block text-sm font-semibold">{{ $eur($pl['preco']) }}</span>
                                    @if ($pl['imoveis'] > 1)<span class="block text-[11px] text-[#AEB5C1]">{{ $eur(intdiv($pl['preco'], $pl['imoveis'])) }} por imóvel</span>@endif
                                </span>
                            </label>
                        @endforeach
                        @if ($escolhido['imoveis'] > 1)
                            <p class="mt-1 text-xs text-[#AEB5C1]">Este imóvel usa {{ \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos. Os outros {{ ($escolhido['imoveis'] - 1) * \App\Services\Pagamentos\Creditos::IMOPLANTA }} ficam na sua conta: servem para outros imóveis, vídeos Imocena e Ferramentas IA.</p>
                        @endif
                    </fieldset>
                @endif
                @unless ($comCreditos)
                <div class="flex items-baseline justify-between pt-3.5 border-t border-ivory/10">
                    <span class="text-[13px] text-[#C9CED6]">Total, IVA incluído</span>
                    <strong class="font-display text-[38px] font-medium">{{ $eur($total) }}</strong>
                </div>
                @endunless
                <button type="button" wire:click="pagar" wire:loading.attr="disabled" wire:target="pagar" class="{{ $botao }}">
                    <span wire:loading.remove wire:target="pagar">Criar o meu imóvel →</span>
                    <span wire:loading wire:target="pagar">Um momento…</span>
                </button>
                <p class="text-xs leading-normal text-[#AEB5C1]">{{ $total === 0 ? 'Sem custo: começamos já pelo modelo 3D e avisamos por email quando estiver pronto.' : 'Pagamento seguro pela Stripe. Depois de confirmado, começamos pelo modelo 3D e avisamos por email quando estiver pronto.' }}</p>
            </section>
        </x-imoplanta.colunas>
    @endif
</x-imoplanta.pagina>
