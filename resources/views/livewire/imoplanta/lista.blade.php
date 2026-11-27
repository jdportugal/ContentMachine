{{-- not bought yet (and nothing on its way from a payment): what Imoplanta is, and the packs --}}
@if (! $gratis && $disponiveis !== null && $disponiveis < \App\Services\Pagamentos\Creditos::IMOPLANTA && $imoveis->isEmpty() && ! $pagamento)
<div>
    @include('livewire.imoplanta.boas-vindas')
</div>
@else
<div @if ($imoveis->contains(fn ($p) => $p->aTrabalhar())) wire:poll.5s @endif class="font-sans">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="eyebrow">Imoplanta</span>
            <h1 class="mt-2 font-display text-5xl font-medium leading-none">Das plantas às imagens</h1>
            <p class="mt-3 max-w-2xl text-sm text-ink-soft">Carregue as plantas da casa. Fazemos o modelo 3D, que pode rodar e corrigir, escolhe onde ficam as câmaras e recebe fotografias realistas do imóvel.</p>
        </div>
        @if ($disponiveis !== null && ! $gratis)
            {{-- the one dark block: the video credits left (a property uses 5) --}}
            <div class="rounded-[18px] bg-navy px-6 py-4 min-w-[200px] flex items-center justify-between gap-6">
                <span class="text-[10px] tracking-[.3em] text-cyan leading-relaxed">CRÉDITOS DE VÍDEO<br>DISPONÍVEIS</span>
                <span class="font-display text-[48px] leading-none text-ivory">{{ \App\Services\Pagamentos\Creditos::txt($disponiveis) }}</span>
            </div>
        @endif
    </div>

    @if ($pagamento)
        <p role="status" class="mt-6 rounded-xl bg-[#E3F0E7] text-[#22603A] px-5 py-3 text-sm font-medium">{{ $pagamento }}</p>
    @endif

    @if ($gratis)
        <div class="mt-6 rounded-2xl border border-ink-soft/20 p-6">
            <p class="font-display text-2xl">Cada imóvel custa <strong>0 €</strong></p>
            <p class="mt-1 text-sm text-ink-soft">Esta conta não paga: crie os imóveis que quiser. Inclui o modelo 3D com {{ config('contentmachine.imoplanta.refazer_3d') }} alterações por IA e as imagens com {{ config('contentmachine.imoplanta.refazer_imagem') }} alterações cada.</p>
        </div>
    @elseif ($disponiveis !== null && $disponiveis < \App\Services\Pagamentos\Creditos::IMOPLANTA)
        {{-- Nothing left to spend: the packs, each its Stripe Payment Link (as the video packs on /conta). --}}
        <div class="mt-6 rounded-2xl border border-ink-soft/20 p-6">
            <p class="font-display text-2xl">Cada imóvel usa {{ \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos de vídeo</p>
            <p class="mt-1 text-sm text-ink-soft">Tem {{ \App\Services\Pagamentos\Creditos::txt($disponiveis) }}. Cada imóvel inclui o modelo 3D com {{ config('contentmachine.imoplanta.refazer_3d') }} alterações por IA e as imagens com {{ config('contentmachine.imoplanta.refazer_imagem') }} alterações cada. IVA incluído.</p>
            @include('livewire.imoplanta.packs')
        </div>
    @endif

    {{-- the form only when there is a property to spend (free accounts and admins always) --}}
    @if ($gratis || $disponiveis === null || $disponiveis >= \App\Services\Pagamentos\Creditos::IMOPLANTA)
    <form x-data wire:submit="criar" class="mt-8 rounded-2xl bg-white border border-[#E4E1DA] text-ivory p-7 md:p-8">
        <div class="flex items-center gap-3.5">
            <span class="w-10 h-px bg-cyan"></span>
            <span class="text-xs tracking-[.32em] text-cyan">NOVO IMÓVEL</span>
        </div>
        <p class="mt-4 font-display text-3xl leading-tight">Carregue as plantas</p>
        <div class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr]">
            <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Nome do imóvel
                <input type="text" wire:model="nome" placeholder="Ex.: Moradia T4, Rua das Flores 12"
                       class="min-h-[52px] px-4 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan">
            </label>
            <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Plantas e implantação (imagens)
                <input type="file" multiple accept="image/png,image/jpeg,image/webp" wire:model="plantas" x-on:livewire-upload-start="$wire.set('plantas', [], false)"
                       class="text-sm text-[#C9CED6] file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-[#EDEAE3] file:text-navy file:font-medium">
                <span wire:loading wire:target="plantas" class="text-xs text-cyan">A carregar…</span>
            </label>
        </div>
        <label class="mt-4 flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Alçados, cortes, fotografias da casa ou do terreno, materiais (opcional, imagens)
            <span class="text-xs text-[#AEB5C1]">Ajudam a acertar alturas, telhado, janelas, fachada e envolvente.</span>
            <input type="file" multiple accept="image/png,image/jpeg,image/webp" wire:model="referencias" x-on:livewire-upload-start="$wire.set('referencias', [], false)"
                   class="text-sm text-[#C9CED6] file:mr-3 file:min-h-11 file:px-4 file:rounded-full file:border-0 file:bg-[#EDEAE3] file:text-navy file:font-medium">
            <span wire:loading wire:target="referencias" class="text-xs text-cyan">A carregar…</span>
        </label>
        <div x-data class="mt-4 grid gap-4 md:grid-cols-[1fr_1fr]">
            <label class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Tipo de imóvel
                <select wire:model="tipologia" x-on:change="$wire.tipologia = $event.target.value"
                        class="min-h-[52px] px-4 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan">
                    @foreach (\App\Models\Imoplanta::TIPOLOGIAS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
            <label x-show="$wire.tipologia === 'apartamento'" x-cloak class="flex flex-col gap-1.5 text-[13px] text-[#C9CED6]">Andar do apartamento (0 = rés-do-chão)
                <input type="number" min="0" max="60" step="1" wire:model="piso" placeholder="Ex.: 3"
                       class="min-h-[52px] px-4 rounded-[10px] bg-white text-navy text-[15px] border border-[#D9D5CC] focus:outline-none focus:ring-2 focus:ring-cyan">
            </label>
        </div>
        @error('tipologia') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('piso') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('nome') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('plantas') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('plantas.*') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('referencias') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        @error('referencias.*') <p class="mt-2 text-sm text-coral">{{ $message }}</p> @enderror
        <div class="mt-5 flex flex-wrap items-center gap-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="criar, plantas, referencias"
                    class="min-h-[52px] px-7 rounded-full bg-coral text-navy text-[15px] font-semibold hover:brightness-105 disabled:opacity-60">
                <span wire:loading.remove wire:target="criar">Criar modelo 3D →</span>
                <span wire:loading wire:target="criar">A enviar…</span>
            </button>
            <p class="text-xs text-[#AEB5C1]">{{ $gratis || $disponiveis === null ? 'Ao criar: 0 €.' : 'Ao criar, usa '.\App\Services\Pagamentos\Creditos::IMOPLANTA.' dos seus '.\App\Services\Pagamentos\Creditos::txt($disponiveis).' créditos de vídeo.' }}</p>
        </div>
    </form>
    @endif

    @if ($imoveis->isEmpty())
        <div class="mt-10 rounded-2xl border border-dashed border-ink-soft/30 px-8 py-14 text-center">
            <p class="font-display text-3xl">Ainda não há imóveis.</p>
            <p class="mt-2 text-sm text-ink-soft">Carregue as plantas acima para começar.</p>
        </div>
    @else
        <div class="mt-10 grid gap-6 grid-cols-[repeat(auto-fill,minmax(250px,1fr))]">
            @foreach ($imoveis as $p)
                @php $capa = $p->imagens->firstWhere('foto'); @endphp
                <a wire:key="p-{{ $p->id }}" href="{{ $p->estado === \App\Models\Imoplanta::A_PAGAR ? route('plantas') : route('imoplanta.imovel', $p->id) }}" class="rounded-2xl overflow-hidden bg-white border border-[#E4E1DA] flex flex-col hover:border-cyan hover:shadow-[0_12px_32px_rgba(14,22,38,0.08)]">
                    <div class="relative aspect-video bg-[#EDEAE3]">
                        @if ($capa)
                            <img src="{{ route('imoplanta.ficheiro', [$p->id, 'foto', $capa->id, 'v' => substr(md5((string) $capa->foto), 0, 8)]) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                        @elseif ($p->plantas)
                            <img src="{{ route('imoplanta.ficheiro', [$p->id, 'planta', 0]) }}" alt="" class="absolute inset-0 w-full h-full object-contain bg-white">
                        @endif
                        <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-semibold {{ $p->estado === 'falhou' || $p->estado === 'a_pagar' ? 'bg-coral text-navy' : ($p->aTrabalhar() ? 'bg-[#FBEBD9] text-[#8A4B12]' : 'bg-[#E3F0E7] text-[#22603A]') }}">
                            {{ \App\Models\Imoplanta::ETAPAS[$p->estado] ?? ($p->estado === 'a_pagar' ? 'Por pagar' : ($p->estado === 'falhou' ? 'Não foi possível' : ($p->estado === 'modelo' ? 'Modelo 3D pronto' : ($p->aTrabalhar() ? 'A fazer as imagens' : 'Imagens prontas')))) }}
                        </span>
                    </div>
                    <div class="p-4">
                        <h2 class="font-sans text-base font-semibold leading-snug text-ivory">{{ $p->nome }}</h2>
                        <p class="text-xs text-[#AEB5C1]">{{ $p->created_at->locale('pt_PT')->translatedFormat('j M Y, H:i') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- the credit packs, always at the foot (as on the AI tools' page and /conta); the block above has them when short --}}
    @if (! $gratis && $disponiveis !== null && $disponiveis >= \App\Services\Pagamentos\Creditos::IMOPLANTA)
        <section id="packs" class="mt-12">
            <h2 class="font-display text-3xl">Créditos de vídeo</h2>
            <p class="mt-1 text-sm text-ink-soft">Cada imóvel usa {{ \App\Services\Pagamentos\Creditos::IMOPLANTA }} créditos. Os mesmos créditos dos vídeos Imocena e das Ferramentas IA. IVA incluído.</p>
            @include('livewire.imoplanta.packs')
        </section>
    @endif
</div>
@endif
