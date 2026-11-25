@props(['p'])
{{-- The plan video add-on's preview: one demo GIF for every property (made once from a real plan video), so it costs nothing. --}}
<div {{ $attributes->merge(['class' => 'relative aspect-video rounded-xl overflow-hidden bg-white']) }}>
    <img src="{{ asset('imoplanta-demo/demo-planta.gif') }}" alt="Exemplo do vídeo da planta 3D: um terreno vazio, depois o chão e as paredes, e por fim a mobília." loading="lazy"
         class="absolute inset-0 w-full h-full object-contain">
    <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-full bg-navy/80 text-ivory text-[10px] tracking-[.18em]">EXEMPLO</span>
</div>
