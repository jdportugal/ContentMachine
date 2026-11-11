<x-layouts.site title="Imocena · Política de privacidade">
    @php
        $h2 = 'mt-12 font-display text-[30px] leading-tight text-ivory';
        $p = 'mt-4 text-[16px] leading-relaxed text-[#C9CED6]';
        $li = 'mt-2 text-[16px] leading-relaxed text-[#C9CED6]';
        $empresa = config('contentmachine.empresa');
    @endphp
    <main class="max-w-[760px] mx-auto px-5 sm:px-6 py-12 sm:py-16">
        <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>

        <h1 class="mt-10 font-display text-[clamp(36px,6vw,52px)] leading-[1.05] text-ivory">Política de privacidade</h1>
        <p class="{{ $p }}">Última atualização: 10 de novembro de 2026.</p>

        <h2 class="{{ $h2 }}">Quem somos</h2>
        <p class="{{ $p }}">A Imocena (imocena.com) faz vídeos de imóveis para consultores imobiliários a partir do link de um anúncio. O responsável pelo tratamento dos dados descritos aqui é {{ $empresa['nome'] ?: 'a Imocena' }}{{ $empresa['nif'] ? ', NIF '.$empresa['nif'] : '' }}{{ $empresa['morada'] ? ', com sede em '.$empresa['morada'] : '' }}. Contacto para qualquer assunto de privacidade: <a href="mailto:info@imocena.com" class="text-ivory underline">info@imocena.com</a>.</p>

        <h2 class="{{ $h2 }}">Que dados tratamos e porquê</h2>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}"><strong class="text-ivory">Dados do anúncio</strong> que nos indica (fotos, descrição, preço, e o nome e contacto do consultor que constam do anúncio), para fazer o vídeo e o contactar sobre ele. Base: o pedido que nos faz (execução do serviço).</li>
            <li class="{{ $li }}"><strong class="text-ivory">Conta</strong>: email e nome, para entrar sem palavra-passe (link por email) e guardar os seus vídeos. Base: execução do serviço.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Compras</strong>: nome, email, NIF e valor, para processar o pagamento e emitir a fatura. Os dados do cartão são tratados pelo Stripe e não chegam até nós. Base: contrato e obrigações legais.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Conteúdos que envia</strong> (fotos, texto da narração, logótipo, música), só para fazer e editar os seus vídeos.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Avisos de imóveis novos e novidades</strong>, só se marcar a opção no pedido do vídeo: guardamos o seu email e consultamos regularmente (de hora a hora) os seus anúncios públicos no portal, para o avisar quando publicar um imóvel novo. Base: o seu consentimento, que pode retirar com um clique em qualquer email.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Origem da visita</strong> (por exemplo, a campanha ou o site de onde veio) e endereço IP, para segurança e para saber que canais trazem clientes. Base: interesse legítimo.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Medição de anúncios</strong> (Meta e Google), só com a sua autorização no aviso de cookies. Ver abaixo.</li>
        </ul>

        <h2 class="{{ $h2 }}">Com quem partilhamos</h2>
        <p class="{{ $p }}">Usamos prestadores que tratam dados por nossa conta, só para estas finalidades:</p>
        <ul class="mt-2 list-disc pl-5">
            <li class="{{ $li }}">DigitalOcean (alojamento do site e dos vídeos);</li>
            <li class="{{ $li }}">Stripe (pagamentos e faturas);</li>
            <li class="{{ $li }}">Postmark (envio dos emails de acesso e de serviço);</li>
            <li class="{{ $li }}">fornecedores de inteligência artificial que escrevem o texto e geram a voz da narração a partir dos dados do anúncio (Anthropic, ElevenLabs);</li>
            <li class="{{ $li }}">Meta e Google, só para medição de anúncios e só com o seu consentimento.</li>
        </ul>
        <p class="{{ $p }}">Alguns destes prestadores podem tratar dados fora da União Europeia, com as garantias previstas no RGPD (por exemplo, cláusulas contratuais-tipo). Não vendemos dados pessoais.</p>

        <h2 class="{{ $h2 }}">Durante quanto tempo</h2>
        <p class="{{ $p }}">Enquanto a sua conta estiver ativa ou for necessário para o serviço que pediu. Os documentos de faturação são guardados pelo prazo exigido pela lei fiscal. Pode pedir a qualquer momento que apaguemos os seus dados e vídeos, salvo o que a lei nos obriga a guardar.</p>

        <h2 class="{{ $h2 }}">Os seus direitos</h2>
        <p class="{{ $p }}">Pode pedir acesso, retificação, apagamento, limitação, portabilidade ou opor-se ao tratamento, e retirar um consentimento a qualquer momento, escrevendo para <a href="mailto:info@imocena.com" class="text-ivory underline">info@imocena.com</a>. Pode também apresentar reclamação à Comissão Nacional de Proteção de Dados (<a href="https://www.cnpd.pt" rel="noopener" class="text-ivory underline">cnpd.pt</a>).</p>

        <h2 id="cookies" class="{{ $h2 }}">Cookies</h2>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}"><strong class="text-ivory">Necessários</strong> (sempre ativos): a sessão e a proteção dos formulários (laravel_session, XSRF-TOKEN) e a sua escolha sobre cookies (imocena_cookies, 1 ano).</li>
            <li class="{{ $li }}"><strong class="text-ivory">Medição</strong> (só se aceitar): Meta Pixel (_fbp, _fbc) e Google Analytics (_ga, _ga_*), para saber que anúncios trazem visitas, pedidos e compras. Quando aceita, também enviamos esses mesmos eventos à Meta a partir do nosso servidor.</li>
        </ul>
        <p class="{{ $p }}">Pode mudar a sua escolha em <a href="#" data-cookies class="text-ivory underline">Preferências de cookies</a>.</p>
        <p class="{{ $p }}">Ver também os <a href="{{ route('termos') }}" class="text-ivory underline">termos e condições</a>.</p>
    </main>
</x-layouts.site>
