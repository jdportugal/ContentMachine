<x-layouts.site title="Imocena · Termos e condições">
    @php
        $h2 = 'mt-12 font-display text-[30px] leading-tight text-ivory';
        $p = 'mt-4 text-[16px] leading-relaxed text-[#C9CED6]';
        $li = 'mt-2 text-[16px] leading-relaxed text-[#C9CED6]';
        $a = 'text-ivory underline';
        $empresa = config('contentmachine.empresa');
        $email = config('mail.from.address');
        $c = \App\Services\Pagamentos\Creditos::class;
        $ferramentas = \App\Services\Ferramentas\Ferramentas::lista();
        $planos = config('contentmachine.imoplanta.planos');
        $eur = fn (int $v) => \Illuminate\Support\Number::currency($v / 100, 'EUR', 'pt_PT', precision: $v % 100 ? 2 : 0);
    @endphp
    <main class="max-w-[760px] mx-auto px-5 sm:px-6 py-12 sm:py-16">
        <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>

        <h1 class="mt-10 font-display text-[clamp(36px,6vw,52px)] leading-[1.05] text-ivory">Termos e condições</h1>
        <p class="{{ $p }}">Última atualização: 27 de novembro de 2026.</p>

        <h2 class="{{ $h2 }}">1. Quem somos</h2>
        <p class="{{ $p }}">
            A Imocena (imocena.com) é um serviço de
            {{ $empresa['nome'] ?: 'Imocena' }}{{ $empresa['nif'] ? ', NIF '.$empresa['nif'] : '' }}{{ $empresa['morada'] ? ', com sede em '.$empresa['morada'] : '' }}.
            Contacto: <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>.
            Ao criar uma conta, pedir um vídeo ou comprar, aceita estes termos.
        </p>

        <h2 class="{{ $h2 }}">2. O serviço</h2>
        <p class="{{ $p }}">A partir do link de um anúncio de imóvel (RE/MAX, iad Portugal, eXp Portugal, KW Portugal, Century 21, Predimed, Bússola Haus ou My Best Houses), fazemos um vídeo vertical com as fotos do anúncio, efeitos, música e uma narração em português de Portugal gerada por inteligência artificial, terminando com o seu nome e contacto. Pode juntar fotos, escrever a sua narração e escolher a música.</p>
        <p class="{{ $p }}">Pode também usar o <strong class="text-ivory">Imoplanta</strong>, que a partir das plantas de uma casa faz um modelo 3D, fotografias realistas e um vídeo de visita, e as <strong class="text-ivory">Ferramentas IA</strong>, que a partir de uma fotografia (ou de uma planta) fazem um vídeo curto ou uma fotografia tratada por inteligência artificial: vista de drone, movimento lateral, limpeza, remodelação, construção e planta animada. Nas Ferramentas IA vê e aprova as imagens antes de o vídeo ser feito.</p>
        <p class="{{ $p }}">O texto da narração e as legendas são escritos automaticamente a partir do anúncio e podem conter erros. As imagens e os vídeos feitos por inteligência artificial (Imoplanta e Ferramentas IA) são ilustrações: mostram uma possibilidade, não o imóvel tal como é, e podem ter diferenças em relação à realidade. Antes de publicar, confirme que o preço, as áreas e as restantes informações estão corretos e, se usar uma imagem ou um vídeo feito por IA, indique que é uma simulação. A responsabilidade pelo anúncio e pelo que publica é sua.</p>

        <h2 class="{{ $h2 }}">3. Conta</h2>
        <p class="{{ $p }}">Entra na sua conta através de um link enviado para o seu email, sem palavra-passe. Quem tiver acesso ao seu email tem acesso à sua conta, por isso mantenha-o protegido. Uma conta é pessoal.</p>

        <h2 class="{{ $h2 }}">4. Créditos de vídeo, preços e pagamento</h2>
        <p class="{{ $p }}">Paga com <strong class="text-ivory">créditos de vídeo</strong>, que compra em packs. Os mesmos créditos servem para tudo na Imocena:</p>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}"><strong class="text-ivory">Vídeo Imocena</strong> (a partir de um anúncio): 1 crédito.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Imoplanta</strong>: {{ $c::IMOPLANTA }} créditos por imóvel.</li>
            @foreach ($ferramentas as $f)
                @php $precos = array_unique(array_merge([$f['creditos']], array_column($f['obras'] ?? [], 'creditos'))); sort($precos); @endphp
                <li class="{{ $li }}"><strong class="text-ivory">{{ $f['nome'] }}</strong> (Ferramentas IA): {{ count($precos) > 1 ? $c::txt(reset($precos)).' a '.$c::rotulo(end($precos), false).', conforme a obra' : $c::rotulo($f['creditos'], false) }}.</li>
            @endforeach
        </ul>
        <p class="{{ $p }}">Os créditos podem ser usados em frações de 0,1. Os packs:</p>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}"><strong class="text-ivory">1.º vídeo grátis</strong>, com marca d’água, para experimentar com um imóvel seu.</li>
            <li class="{{ $li }}"><strong class="text-ivory">1 crédito</strong>: 20 €.</li>
            <li class="{{ $li }}"><strong class="text-ivory">10 créditos</strong>: 100 € (10 € por crédito).</li>
            <li class="{{ $li }}"><strong class="text-ivory">20 créditos</strong>: 175 € (8,75 € por crédito).</li>
            @foreach ($planos as $pl)
                <li class="{{ $li }}"><strong class="text-ivory">{{ $pl['imoveis'] * $c::IMOPLANTA }} créditos</strong>, para {{ $pl['imoveis'] === 1 ? '1 imóvel' : $pl['imoveis'].' imóveis' }} Imoplanta: {{ $eur($pl['preco']) }} ({{ $eur((int) round($pl['preco'] / ($pl['imoveis'] * $c::IMOPLANTA))) }} por crédito).</li>
            @endforeach
        </ul>
        <p class="{{ $p }}">Alguns extras do Imoplanta (o vídeo da planta 3D) são vendidos à parte, pelo preço indicado na página. Os preços incluem IVA à taxa legal. O pagamento é feito no Stripe, que trata os dados do cartão; não os recebemos. A fatura é enviada para o seu email com o NIF que indicar no pagamento. Não há mensalidades nem renovações automáticas.</p>

        <h2 id="creditos" class="{{ $h2 }}">5. Usar os créditos</h2>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}">Os créditos ficam na sua conta e podem ser usados durante 12 meses a contar da compra. Usamos primeiro os créditos mais antigos.</li>
            <li class="{{ $li }}">Os créditos são gastos quando começamos a fazer o que pediu: o vídeo Imocena, o imóvel Imoplanta (antes de lermos as plantas) ou, nas Ferramentas IA, as imagens para aprovar.</li>
            <li class="{{ $li }}">Cada vídeo Imocena inclui 2 edições grátis (narração, fotos e efeitos, contacto, logótipo e música). Depois disso, refazer o vídeo gasta um crédito.</li>
            <li class="{{ $li }}">Cada imóvel Imoplanta inclui {{ config('contentmachine.imoplanta.refazer_3d') }} correções do modelo 3D e {{ config('contentmachine.imoplanta.refazer_imagem') }} alterações por fotografia. Cada uso das Ferramentas IA inclui {{ config('contentmachine.ferramentas.editar_imagem') }} alterações às imagens e {{ config('contentmachine.ferramentas.refazer_video') }} novas versões do vídeo.</li>
            <li class="{{ $li }}">Se não conseguirmos fazer o que pediu (por exemplo, uma falha nossa ou do serviço de inteligência artificial), os créditos usados voltam à sua conta.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Resultados feitos por inteligência artificial.</strong> As imagens e os vídeos do Imoplanta e das Ferramentas IA são gerados por IA: o resultado varia de pedido para pedido, pode não corresponder ao que espera e pode ter erros ou diferenças em relação à fotografia, à planta ou ao pedido. Não garantimos um resultado específico. Por isso inclui alterações e novas versões, e nas Ferramentas IA aprova as imagens antes do vídeo. Os créditos usados não são devolvidos por o resultado não ser o que queria; só voltam se não conseguirmos fazer o pedido. Isto não afasta os direitos que a lei lhe dá como consumidor.</li>
            <li class="{{ $li }}">Os créditos não são convertidos em dinheiro nem transferidos para outra conta, exceto no caso do ponto 7.</li>
        </ul>

        <h2 class="{{ $h2 }}">6. Passagem para créditos de vídeo</h2>
        <p class="{{ $p }}">Até 27 de novembro de 2026, a Imocena vendia vídeos, imóveis Imoplanta e vídeos IA em saldos separados. O que tinha por usar passou a créditos de vídeo, sem custo e sem perder nada:</p>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}">cada vídeo por usar passou a 1 crédito (continua a dar para o mesmo vídeo);</li>
            <li class="{{ $li }}">cada imóvel Imoplanta por usar passou a {{ $c::IMOPLANTA }} créditos (continua a dar para o mesmo imóvel);</li>
            <li class="{{ $li }}">cada vídeo IA por usar passou a {{ $c::VIDEO_IA_ANTIGO }} créditos, o que dá para qualquer ferramenta.</li>
        </ul>
        <p class="{{ $p }}">Os créditos convertidos mantêm a data da compra original para o prazo do ponto 5, e os imóveis e vídeos IA que eram válidos sem prazo continuam sem prazo. Se preferir, pode pedir-nos o reembolso do que tinha por usar, nos termos em que o comprou.</p>

        <h2 class="{{ $h2 }}">7. Direito de livre resolução (desistência)</h2>
        <p class="{{ $p }}">Se comprar como consumidor, tem 14 dias a contar da compra para desistir sem indicar motivo, nos termos do Decreto-Lei n.º 24/2014.</p>
        <p class="{{ $p }}">Cada vídeo, imóvel ou imagem é conteúdo digital feito logo que o pede. Ao usar créditos, pede expressamente que o conteúdo seja feito de imediato e reconhece que perde o direito de livre resolução <strong class="text-ivory">quanto aos créditos usados</strong> (artigo 17.º, n.º 1, alínea l), do mesmo decreto-lei).</p>
        <p class="{{ $p }}">Os créditos de um pack que ainda não usou continuam abrangidos: se desistir nos 14 dias, devolvemos o valor dos créditos por usar, ao preço por crédito desse pack. Para desistir, escreva para <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>. Devolvemos o dinheiro pelo mesmo meio de pagamento, no prazo de 14 dias.</p>

        <h2 class="{{ $h2 }}">8. O que nos envia</h2>
        <p class="{{ $p }}">Ao usar um anúncio, fotos, logótipos, texto ou música, garante que tem o direito de os usar para promover o imóvel (por exemplo, por ser o consultor responsável pela angariação). Autoriza-nos a usá-los só para fazer, editar e guardar os seus vídeos. Não os usamos em publicidade nossa sem lhe pedir.</p>

        <h2 class="{{ $h2 }}">9. O vídeo que recebe</h2>
        <p class="{{ $p }}">Pode usar os seus vídeos para promover o imóvel onde quiser: redes sociais, portais, o seu site ou o da sua agência. As músicas da biblioteca Imocena estão licenciadas para este uso. Se enviar a sua própria música, use só música que tem direito a usar: as redes sociais silenciam ou removem vídeos com música protegida.</p>
        <p class="{{ $p }}">As imagens e os vídeos do Imoplanta e das Ferramentas IA são seus para o mesmo uso.</p>
        <p class="{{ $p }}">O vídeo grátis leva a marca d’água Imocena, que não deve ser retirada nem tapada. Para ter o vídeo sem marca d’água, basta comprá-lo.</p>

        <h2 class="{{ $h2 }}">10. Utilização aceitável</h2>
        <p class="{{ $p }}">Não pode usar a Imocena para anúncios falsos ou enganosos, para imóveis que não está autorizado a promover, para conteúdos ilegais ou que violem direitos de terceiros, nem para tentar aceder a dados ou contas de outros. Podemos suspender contas que o façam.</p>

        <h2 class="{{ $h2 }}">11. Os seus vídeos ficam guardados</h2>
        <p class="{{ $p }}">Os seus vídeos ficam disponíveis na sua conta enquanto ela estiver ativa. Recomendamos que descarregue os ficheiros MP4 que quer guardar. Pode pedir a qualquer momento que apaguemos a sua conta e os seus vídeos.</p>

        <h2 class="{{ $h2 }}">12. Responsabilidade</h2>
        <p class="{{ $p }}">Fazemos o possível por manter o serviço disponível e os vídeos corretos, mas não garantimos que funcione sem interrupções. Exceto em caso de dolo ou culpa grave, ou quando a lei não o permita, a nossa responsabilidade limita-se ao valor que nos pagou nos 12 meses anteriores. Nada nestes termos limita os direitos que a lei lhe dá como consumidor.</p>

        <h2 class="{{ $h2 }}">13. Privacidade</h2>
        <p class="{{ $p }}">O tratamento dos seus dados pessoais está descrito na <a href="{{ route('privacidade') }}" class="{{ $a }}">política de privacidade</a>.</p>

        <h2 id="reclamacoes" class="{{ $h2 }}">14. Reclamações e litígios</h2>
        <p class="{{ $p }}">Para qualquer problema, escreva primeiro para <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>; respondemos o mais depressa possível. Pode também usar o <a href="https://www.livroreclamacoes.pt" rel="noopener" class="{{ $a }}">Livro de Reclamações Eletrónico</a>.</p>
        <p class="{{ $p }}">Em caso de litígio de consumo, pode recorrer a uma entidade de resolução alternativa de litígios: <a href="{{ $empresa['ral_url'] }}" rel="noopener" class="{{ $a }}">{{ $empresa['ral_nome'] }}</a>. Mais informação no Portal do Consumidor (<a href="https://www.consumidor.gov.pt" rel="noopener" class="{{ $a }}">consumidor.gov.pt</a>).</p>
        <p class="{{ $p }}">Estes termos regem-se pela lei portuguesa.</p>

        <h2 class="{{ $h2 }}">15. Alterações</h2>
        <p class="{{ $p }}">Podemos atualizar estes termos. As alterações não se aplicam a compras já feitas. Se forem importantes, avisamos por email antes de entrarem em vigor.</p>
    </main>
</x-layouts.site>
