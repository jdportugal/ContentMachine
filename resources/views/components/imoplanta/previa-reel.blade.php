@props(['p', 'preco' => ''])
{{-- A preview of the Imocena reel add-on: this home's own walkthrough, vertical, with the reel's titles on top. --}}
@php
    $clipPrevia = ($id = $p->extra('video', 'previa_clip')) ? app(\App\Services\Clips\Store\ClipStore::class)->find($id) : null;
    $aFazerPrevia = $clipPrevia && $clipPrevia->isActive();
@endphp
@if ($clipPrevia && $clipPrevia->status === 'done' && $clipPrevia->output_path)
    {{-- the real reel, with its voice: muted until they press play --}}
    <video src="{{ route('imoplanta.ficheiro', [$p->id, 'reel_previa', 'v' => substr(md5($clipPrevia->output_path), 0, 8)]) }}" controls playsinline preload="metadata"
           {{ $attributes->merge(['class' => 'w-full aspect-[9/16] rounded-xl bg-navy-3 object-cover']) }} aria-label="Pré-visualização do Vídeo Imocena"></video>
@else
<div {{ $attributes->merge(['class' => 'relative aspect-[9/16] rounded-xl overflow-hidden bg-navy-3']) }} aria-label="Pré-visualização do Vídeo Imocena" role="img">
    @if ($p->video)
        <video src="{{ route('imoplanta.ficheiro', [$p->id, 'video', 'v' => substr(md5($p->video), 0, 8)]) }}#t=3" muted loop autoplay playsinline preload="metadata"
               class="absolute inset-0 w-full h-full object-cover motion-reduce:hidden"></video>
    @endif
    <div class="absolute inset-x-0 top-0 p-3 bg-gradient-to-b from-navy/80 to-transparent">
        <span class="block text-[9px] tracking-[.3em] text-cyan">À VENDA</span>
        <span class="block font-display text-lg leading-tight text-ivory">{{ \Illuminate\Support\Str::limit($p->nome, 40) }}</span>
    </div>
    <div class="absolute inset-x-0 bottom-0 p-3 bg-gradient-to-t from-navy/90 to-transparent text-ivory">
        <span class="block text-[11px]">{{ $p->tipologiaRotulo() }}</span>
        @if ($preco !== '') <span class="block font-display text-xl">{{ $preco }}</span> @endif
        <span class="mt-1 block w-full h-0.5 rounded-full bg-ivory/30"><span class="block h-full w-1/3 bg-coral"></span></span>
    </div>
    <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full bg-navy/80 text-ivory text-[10px] tracking-[.18em]">{{ $aFazerPrevia ? 'A PREPARAR…' : 'PRÉ-VISUALIZAÇÃO' }}</span>
</div>
@endif
