{{-- "Novo vídeo" without a link: the files, the description, the agent. Uses $passo from the page. --}}
@php
    $campo = 'w-full min-h-[52px] rounded-[14px] bg-navy border border-ivory/20 px-4 text-[16px] text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none';
    $rotulo = 'text-[13px] text-[#C9CED6]';
    $mb = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, $b >= 104857600 ? 0 : 1, ',', '').' MB' : max(1, round($b / 1024)).' KB';
@endphp

{{-- 1 · Photos and videos --}}
<section class="flex flex-col gap-4">
    {!! $passo(1, 'Fotos e vídeos', 'Até '.\App\Livewire\NovoVideoPagina::MAX_FOTOS_SEM_LINK.' fotos e '.\App\Livewire\NovoVideoPagina::MAX_VIDEOS.' vídeos. A primeira foto abre o vídeo.') !!}
    <label x-data="{ sobre: false }"
           x-on:dragover.prevent="sobre = true" x-on:dragleave.prevent="sobre = false"
           x-on:drop.prevent="sobre = false; $wire.uploadMultiple('novas', $event.dataTransfer.files)"
           :class="sobre ? 'border-cyan bg-cyan/10' : 'border-ivory/30'"
           class="flex flex-col items-center justify-center gap-1 min-h-[160px] rounded-[16px] border-2 border-dashed px-4 py-6 text-center cursor-pointer transition-colors">
        <input type="file" accept="image/png,image/jpeg,image/webp,video/mp4,video/quicktime" multiple wire:model="novas" class="sr-only">
        <span class="text-[15px] text-ivory">Arraste as fotos e os vídeos para aqui</span>
        <span class="text-sm text-[#AEB5C1]">ou toque para escolher · JPG, PNG, MP4 ou MOV</span>
        <span wire:loading wire:target="novas" class="mt-2 text-xs text-cyan">A enviar… os vídeos podem demorar um pouco</span>
    </label>
    @error('novas') <p class="text-sm text-coral">{{ $message }}</p> @enderror
    @error('novas.*') <p class="text-sm text-coral">{{ $message }}</p> @enderror
    @error('ficheiros') <p class="text-sm text-coral">{{ $message }}</p> @enderror
    @error('fotos') <p class="text-sm text-coral">{{ $message }}</p> @enderror
    @error('videos') <p class="text-sm text-coral">{{ $message }}</p> @enderror

    @if ($videos)
        <ul class="flex flex-col gap-2">
            @foreach ($videos as $i => $video)
                <li class="flex items-center gap-3 min-h-[56px] rounded-[12px] border border-ivory/15 bg-navy px-3" wire:key="video-{{ $i }}-{{ $video->getFilename() }}">
                    <span class="shrink-0 w-10 h-10 rounded-[8px] bg-cyan/15 text-cyan flex items-center justify-center text-sm" aria-hidden="true">▶</span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate text-[14px] text-ivory">{{ $video->getClientOriginalName() }}</span>
                        <span class="block text-xs text-[#AEB5C1]">Vídeo · {{ $mb($video->getSize()) }}</span>
                    </span>
                    <button type="button" wire:click="tirarVideo({{ $i }})" aria-label="Tirar este vídeo" class="shrink-0 w-11 h-11 rounded-full text-ivory text-xl">×</button>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($fotos)
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
            @foreach ($fotos as $i => $foto)
                <div class="relative aspect-square rounded-[10px] overflow-hidden bg-navy border border-ivory/15" wire:key="foto-{{ $i }}-{{ $foto->getFilename() }}">
                    <img src="{{ $foto->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    @if ($i === 0)
                        <span class="absolute left-1 bottom-1 rounded-full bg-navy/85 px-2 py-0.5 text-[10px] tracking-wide text-cyan">CAPA</span>
                    @endif
                    <button type="button" wire:click="tirarFoto({{ $i }})" aria-label="Tirar esta foto"
                            class="absolute right-1 top-1 w-8 h-8 rounded-full bg-navy/80 text-ivory text-lg leading-none flex items-center justify-center">×</button>
                </div>
            @endforeach
        </div>
    @endif
    @if ($fotos || $videos)
        <p class="text-xs text-[#AEB5C1]">{{ count($fotos) }} {{ count($fotos) === 1 ? 'foto' : 'fotos' }} · {{ count($videos) }} {{ count($videos) === 1 ? 'vídeo' : 'vídeos' }}</p>
    @endif
</section>

{{-- 2 · Description --}}
<section class="flex flex-col gap-4">
    {!! $passo(2, 'Descrição do imóvel', 'Como no anúncio: tipo, zona, áreas, divisões e o que tem de especial. Escrevemos a narração a partir daqui.') !!}
    <textarea wire:model="descricao" rows="12" maxlength="6000"
              placeholder="Ex.: Moradia T3 em Lajeosa do Dão, com 180 m² e jardim de 600 m². Sala com lareira, cozinha equipada, três quartos (um em suite), garagem para dois carros. A cinco minutos de Tondela…"
              class="w-full rounded-[14px] bg-navy border border-ivory/20 p-4 text-[16px] leading-relaxed text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none"></textarea>
    <div class="flex justify-between text-xs text-[#AEB5C1]" x-data><span>A primeira linha é o título.</span><span x-text="$wire.descricao.length + ' / 6000'"></span></div>
    @error('descricao') <p class="text-sm text-coral">{{ $message }}</p> @enderror
    <label class="flex flex-col gap-1.5 sm:max-w-[280px]">
        <span class="{{ $rotulo }}">Preço (opcional)</span>
        <span class="relative">
            <input type="text" wire:model="preco" inputmode="numeric" placeholder="350000" class="{{ $campo }} pr-10">
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[#AEB5C1]">€</span>
        </span>
        <span class="text-xs text-[#AEB5C1]">Sem preço, o vídeo termina com o seu contacto.</span>
    </label>
    @error('preco') <p class="text-sm text-coral">{{ $message }}</p> @enderror
</section>

{{-- 3 · The agent --}}
<section class="flex flex-col gap-4">
    {!! $passo(3, 'O consultor', 'Aparece no início e no fim do vídeo.') !!}
    @if ($fotoAtual || $nome !== '')
        <p class="text-xs text-cyan">Preenchido com os seus dados do último vídeo. Confirme ou corrija.</p>
    @endif
    <div class="flex items-center gap-4">
        <div class="shrink-0 w-24 h-24 rounded-full overflow-hidden border border-ivory/20 bg-navy flex items-center justify-center">
            @if ($fotoConsultor)
                <img src="{{ $fotoConsultor->temporaryUrl() }}" alt="" class="w-full h-full object-cover">
            @elseif ($fotoAtual)
                <img src="{{ $fotoAtual }}" alt="" class="w-full h-full object-cover">
            @else
                <span class="text-3xl text-[#7C8696]" aria-hidden="true">👤</span>
            @endif
        </div>
        <label class="inline-flex items-center justify-center min-h-[48px] rounded-full border border-ivory/30 px-5 text-sm text-ivory cursor-pointer">
            <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="fotoConsultor" class="sr-only">
            {{ $fotoConsultor || $fotoAtual ? 'Trocar foto' : 'Escolher foto' }}
        </label>
        <span wire:loading wire:target="fotoConsultor" class="text-xs text-cyan">A enviar…</span>
    </div>
    @error('fotoConsultor') <p class="text-sm text-coral">{{ $message }}</p> @enderror

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label class="flex flex-col gap-1.5 sm:col-span-2">
            <span class="{{ $rotulo }}">Nome</span>
            <input type="text" wire:model="nome" autocomplete="name" placeholder="Nome e apelido" class="{{ $campo }}">
            @error('nome') <span class="text-sm text-coral">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="{{ $rotulo }}">Telefone</span>
            <input type="tel" wire:model="telefone" autocomplete="tel" inputmode="tel" placeholder="+351 912 345 678" class="{{ $campo }}">
            @error('telefone') <span class="text-sm text-coral">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="{{ $rotulo }}">Email (opcional)</span>
            <input type="email" wire:model="email" autocomplete="email" inputmode="email" placeholder="nome@agencia.pt" class="{{ $campo }}">
            @error('email') <span class="text-sm text-coral">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="{{ $rotulo }}">Agência e loja</span>
            <input type="text" wire:model="agencia" autocomplete="organization" placeholder="Ex.: RE/MAX Gold" class="{{ $campo }}">
            @error('agencia') <span class="text-sm text-coral">{{ $message }}</span> @enderror
        </label>
        <label class="flex flex-col gap-1.5">
            <span class="{{ $rotulo }}">Licença AMI (opcional)</span>
            <input type="text" wire:model="ami" inputmode="numeric" placeholder="Ex.: 12345" class="{{ $campo }}">
            @error('ami') <span class="text-sm text-coral">{{ $message }}</span> @enderror
        </label>
    </div>
    <p class="text-xs text-[#AEB5C1]">Guardamos estes dados na sua conta para o próximo vídeo.</p>
</section>
