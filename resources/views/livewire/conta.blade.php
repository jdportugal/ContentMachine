<div class="max-w-[1100px]" @if ($aDecorrer) wire:poll.5s @endif>

    <div class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <div class="flex items-center gap-3.5"><span class="w-10 h-px bg-cyan"></span><span class="text-xs tracking-[.32em] text-cyan">A MINHA CONTA</span></div>
            <h1 class="mt-4 font-display text-[clamp(36px,5vw,52px)] leading-[1.02] text-ivory">Os seus vídeos</h1>
        </div>
        {{-- the one dark block: how many videos are left --}}
        <div class="rounded-[18px] bg-navy px-6 py-4 min-w-[200px] flex items-center justify-between gap-6">
            <span class="text-[10px] tracking-[.3em] text-cyan leading-relaxed">VÍDEOS<br>DISPONÍVEIS</span>
            <span class="font-display text-[48px] leading-none text-ivory">{{ $creditos }}</span>
        </div>
    </div>

    {{-- Make a video with a credit --}}
    @if ($creditos > 0)
        <section class="mt-8 rounded-[18px] bg-white border border-[#E4E1DA] p-5 sm:p-6">
            <form wire:submit="usarCredito" class="flex flex-col gap-3" novalidate>
                <label for="anuncio" class="font-display text-[24px] text-ivory">Fazer um vídeo (usa 1 dos seus {{ $creditos }})</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input id="anuncio" type="url" wire:model="anuncio" placeholder="Cole aqui o link do anúncio" class="flex-1 min-h-[52px] rounded-full bg-white border border-[#D9D5CC] px-5 text-navy placeholder:text-[#7C8696] focus:border-cyan focus:outline-none">
                    <button type="submit" class="min-h-[52px] px-6 rounded-full bg-coral text-navy font-semibold hover:brightness-105" wire:loading.attr="disabled">Criar vídeo →</button>
                </div>
                @error('anuncio') <p class="text-sm text-coral">{{ $message }}</p> @enderror
                <p class="text-xs text-[#AEB5C1]">Sem marca d’água. Fica pronto em poucos minutos, aqui nesta página.</p>
                <a href="{{ route('conta.novo') }}" class="inline-flex items-center gap-2 min-h-11 self-start text-sm font-medium text-cyan underline underline-offset-2">Juntar fotos, escrever a narração ou escolher a música →</a>
                <a href="{{ route('conta.novo', ['modo' => 'ficheiros']) }}" class="inline-flex items-center gap-2 min-h-11 self-start text-sm font-medium text-cyan underline underline-offset-2">Não tem link? Carregue as fotos e os vídeos →</a>
            </form>
        </section>
    @endif

    {{-- Buy more: always visible --}}
    <section id="comprar" class="mt-6 rounded-[18px] bg-white border border-[#E4E1DA] p-5 sm:p-6">
        <p class="font-display text-[22px] text-ivory">{{ $creditos > 0 ? 'Precisa de mais vídeos?' : 'Comprar vídeos' }}</p>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="{{ $link('avulso') }}" class="flex flex-col gap-1 rounded-[14px] border border-ivory/15 px-5 py-4 hover:border-cyan">
                <span class="text-[11px] tracking-[.24em] text-cyan">1 VÍDEO</span><span class="font-display text-[30px] leading-none text-ivory">20 €</span><span class="text-xs text-[#AEB5C1]">Para um imóvel, agora.</span>
            </a>
            <a href="{{ $link('pack10') }}" class="flex flex-col gap-1 rounded-[14px] border border-coral/60 bg-coral/5 px-5 py-4 hover:border-coral">
                <span class="text-[11px] tracking-[.24em] text-coral">PACK 10 · MAIS ESCOLHIDO</span><span class="font-display text-[30px] leading-none text-ivory">100 €</span><span class="text-xs text-[#AEB5C1]">10 € por vídeo · 12 meses.</span>
            </a>
            <a href="{{ $link('pack20') }}" class="flex flex-col gap-1 rounded-[14px] border border-ivory/15 px-5 py-4 hover:border-cyan">
                <span class="text-[11px] tracking-[.24em] text-cyan">PACK 20</span><span class="font-display text-[30px] leading-none text-ivory">175 €</span><span class="text-xs text-[#AEB5C1]">8,75 € por vídeo · 12 meses.</span>
            </a>
        </div>
        <p class="mt-3 text-xs text-[#AEB5C1]">IVA incluído. Ao comprar, aceita os <a href="{{ route('termos') }}" class="text-cyan underline underline-offset-2">termos e condições</a>.</p>
    </section>

    @if (session('editado'))
        <p class="mt-8 rounded-[14px] border border-cyan/40 bg-cyan/5 p-4 text-sm text-ivory">{{ session('editado') }}</p>
    @endif

    <section class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($videos as $v)
            <article class="rounded-[18px] bg-white border border-[#E4E1DA] p-4 flex flex-col gap-3" wire:key="video-{{ $v->id }}">
                @if ($v->estado === 'pronto')
                    <video src="{{ $v->video }}" controls playsinline preload="metadata" class="w-full aspect-[9/16] rounded-[12px] bg-navy object-contain"></video>
                @else
                    <div class="w-full aspect-[9/16] rounded-[12px] bg-navy flex flex-col items-center justify-center gap-4 px-6 text-center">
                        @if ($v->estado === 'falhou')
                            <span class="text-sm text-coral">Não conseguimos fazer este vídeo.</span>
                            <span class="text-xs text-[#C9CED6]">{{ $v->devolvido ? 'O vídeo que usou já voltou para a sua conta.' : 'Já fomos avisados.' }} Às vezes o portal não deixa ler o anúncio à primeira.</span>
                            <button type="button" wire:click="tentarDeNovo({{ $v->id }})" wire:loading.attr="disabled" wire:target="tentarDeNovo({{ $v->id }})"
                                    class="min-h-11 px-5 rounded-full bg-coral text-navy text-sm font-semibold disabled:opacity-60">Tentar outra vez</button>
                        @else
                            <span class="text-sm text-ivory">{{ $v->etapa }}…</span>
                            <div class="w-full h-1.5 rounded-full bg-ivory/10 overflow-hidden"><div class="h-full bg-cyan transition-all duration-700" style="width: {{ $v->pct }}%"></div></div>
                        @endif
                    </div>
                @endif
                <p class="text-sm text-ivory line-clamp-2">{{ $v->titulo }}</p>
                @if ($v->estado === 'pronto')
                    <span class="text-[11px] tracking-[.2em] {{ $v->limpo ? 'text-cyan' : 'text-[#AEB5C1]' }}">{{ $v->limpo ? 'SEM MARCA D’ÁGUA' : 'VERSÃO GRÁTIS (COM MARCA D’ÁGUA)' }}</span>
                    <div class="flex flex-wrap gap-2">
                        <x-descarregar :href="$v->descarregar" class="inline-flex items-center min-h-11 px-4 rounded-full bg-coral text-navy text-sm font-semibold">Descarregar MP4</x-descarregar>
                        <a href="{{ route('conta.editar', ['lead' => $v->id]) }}" class="inline-flex items-center min-h-11 px-4 rounded-full border border-ivory/30 text-ivory text-sm">Editar{{ $v->edicoesRestantes ? ' · '.$v->edicoesRestantes.' grátis' : '' }}</a>
                    </div>
                    @unless ($v->limpo)
                        <a href="{{ $link('avulso', ['client_reference_id' => 'lead-'.$v->id]) }}" class="text-sm font-medium text-cyan underline underline-offset-2">Tirar a marca d’água · 20 €</a>
                    @endunless
                @endif
            </article>
        @empty
            <p class="text-[#C9CED6]">Ainda não tem vídeos.</p>
        @endforelse
    </section>
</div>
