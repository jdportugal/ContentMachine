<x-layouts.site title="Imocena · Termos e condições">
    @php
        $h2 = 'mt-12 font-display text-[30px] leading-tight text-ivory';
        $p = 'mt-4 text-[16px] leading-relaxed text-[#C9CED6]';
        $li = 'mt-2 text-[16px] leading-relaxed text-[#C9CED6]';
        $a = 'text-ivory underline';
        $empresa = config('contentmachine.empresa');
        $email = config('mail.from.address');
    @endphp
    <main class="max-w-[760px] mx-auto px-5 sm:px-6 py-12 sm:py-16">
        <a href="{{ route('home') }}" class="flex items-baseline gap-2.5 text-ivory no-underline"><span class="font-display text-[30px] font-medium">Imocena</span><span class="w-1.5 h-1.5 rounded-full bg-cyan"></span></a>

        <h1 class="mt-10 font-display text-[clamp(36px,6vw,52px)] leading-[1.05] text-ivory">Termos e condições</h1>
        <p class="{{ $p }}">Última atualização: 10 de novembro de 2026.</p>

        <h2 class="{{ $h2 }}">1. Quem somos</h2>
        <p class="{{ $p }}">
            A Imocena (imocena.com) é um serviço de
            {{ $empresa['nome'] ?: 'Imocena' }}{{ $empresa['nif'] ? ', NIF '.$empresa['nif'] : '' }}{{ $empresa['morada'] ? ', com sede em '.$empresa['morada'] : '' }}.
            Contacto: <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>.
            Ao criar uma conta, pedir um vídeo ou comprar, aceita estes termos.
        </p>

        <h2 class="{{ $h2 }}">2. O serviço</h2>
        <p class="{{ $p }}">A partir do link de um anúncio de imóvel (RE/MAX ou iad Portugal), fazemos um vídeo vertical com as fotos do anúncio, efeitos, música e uma narração em português de Portugal gerada por inteligência artificial, terminando com o seu nome e contacto. Pode juntar fotos, escrever a sua narração e escolher a música.</p>
        <p class="{{ $p }}">O texto da narração e as legendas são escritos automaticamente a partir do anúncio e podem conter erros. Antes de publicar, confirme que o preço, as áreas e as restantes informações estão corretos. A responsabilidade pelo anúncio e pelo que publica é sua.</p>

        <h2 class="{{ $h2 }}">3. Conta</h2>
        <p class="{{ $p }}">Entra na sua conta através de um link enviado para o seu email, sem palavra-passe. Quem tiver acesso ao seu email tem acesso à sua conta, por isso mantenha-o protegido. Uma conta é pessoal.</p>

        <h2 class="{{ $h2 }}">4. Preços e pagamento</h2>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}"><strong class="text-ivory">1.º vídeo grátis</strong>, com marca d’água, para experimentar com um imóvel seu.</li>
            <li class="{{ $li }}"><strong class="text-ivory">1 vídeo</strong>: 20 €.</li>
            <li class="{{ $li }}"><strong class="text-ivory">Pack 10</strong>: 100 € (10 € por vídeo).</li>
            <li class="{{ $li }}"><strong class="text-ivory">Pack 20</strong>: 175 € (8,75 € por vídeo).</li>
        </ul>
        <p class="{{ $p }}">Os preços incluem IVA à taxa legal. O pagamento é feito no Stripe, que trata os dados do cartão; não os recebemos. A fatura é enviada para o seu email com o NIF que indicar no pagamento. Não há mensalidades nem renovações automáticas.</p>

        <h2 class="{{ $h2 }}">5. Vídeos comprados e edições</h2>
        <ul class="mt-4 list-disc pl-5">
            <li class="{{ $li }}">Cada vídeo comprado dá para fazer um vídeo de um imóvel, sem marca d’água.</li>
            <li class="{{ $li }}">Os vídeos de um pack podem ser usados durante 12 meses a contar da compra.</li>
            <li class="{{ $li }}">Cada vídeo inclui 2 edições grátis (narração, fotos e efeitos, contacto, logótipo e música). Depois disso, refazer o vídeo gasta um vídeo novo.</li>
            <li class="{{ $li }}">O vídeo fica normalmente pronto em poucos minutos. Se não o conseguirmos fazer, o vídeo usado volta à sua conta.</li>
        </ul>

        <h2 class="{{ $h2 }}">6. Direito de livre resolução (desistência)</h2>
        <p class="{{ $p }}">Se comprar como consumidor, tem 14 dias a contar da compra para desistir sem indicar motivo, nos termos do Decreto-Lei n.º 24/2014.</p>
        <p class="{{ $p }}">Cada vídeo é conteúdo digital feito logo que o pede. Ao pedir um vídeo, pede expressamente que seja feito de imediato e reconhece que perde o direito de livre resolução <strong class="text-ivory">quanto a esse vídeo</strong> (artigo 17.º, n.º 1, alínea l), do mesmo decreto-lei).</p>
        <p class="{{ $p }}">Os vídeos de um pack que ainda não usou continuam abrangidos: se desistir nos 14 dias, devolvemos o valor dos vídeos por usar, ao preço por vídeo desse pack. Para desistir, escreva para <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>. Devolvemos o dinheiro pelo mesmo meio de pagamento, no prazo de 14 dias.</p>

        <h2 class="{{ $h2 }}">7. O que nos envia</h2>
        <p class="{{ $p }}">Ao usar um anúncio, fotos, logótipos, texto ou música, garante que tem o direito de os usar para promover o imóvel (por exemplo, por ser o consultor responsável pela angariação). Autoriza-nos a usá-los só para fazer, editar e guardar os seus vídeos. Não os usamos em publicidade nossa sem lhe pedir.</p>

        <h2 class="{{ $h2 }}">8. O vídeo que recebe</h2>
        <p class="{{ $p }}">Pode usar os seus vídeos para promover o imóvel onde quiser: redes sociais, portais, o seu site ou o da sua agência. As músicas da biblioteca Imocena estão licenciadas para este uso. Se enviar a sua própria música, use só música que tem direito a usar: as redes sociais silenciam ou removem vídeos com música protegida.</p>
        <p class="{{ $p }}">O vídeo grátis leva a marca d’água Imocena, que não deve ser retirada nem tapada. Para ter o vídeo sem marca d’água, basta comprá-lo.</p>

        <h2 class="{{ $h2 }}">9. Utilização aceitável</h2>
        <p class="{{ $p }}">Não pode usar a Imocena para anúncios falsos ou enganosos, para imóveis que não está autorizado a promover, para conteúdos ilegais ou que violem direitos de terceiros, nem para tentar aceder a dados ou contas de outros. Podemos suspender contas que o façam.</p>

        <h2 class="{{ $h2 }}">10. Os seus vídeos ficam guardados</h2>
        <p class="{{ $p }}">Os seus vídeos ficam disponíveis na sua conta enquanto ela estiver ativa. Recomendamos que descarregue os ficheiros MP4 que quer guardar. Pode pedir a qualquer momento que apaguemos a sua conta e os seus vídeos.</p>

        <h2 class="{{ $h2 }}">11. Responsabilidade</h2>
        <p class="{{ $p }}">Fazemos o possível por manter o serviço disponível e os vídeos corretos, mas não garantimos que funcione sem interrupções. Exceto em caso de dolo ou culpa grave, ou quando a lei não o permita, a nossa responsabilidade limita-se ao valor que nos pagou nos 12 meses anteriores. Nada nestes termos limita os direitos que a lei lhe dá como consumidor.</p>

        <h2 class="{{ $h2 }}">12. Privacidade</h2>
        <p class="{{ $p }}">O tratamento dos seus dados pessoais está descrito na <a href="{{ route('privacidade') }}" class="{{ $a }}">política de privacidade</a>.</p>

        <h2 id="reclamacoes" class="{{ $h2 }}">13. Reclamações e litígios</h2>
        <p class="{{ $p }}">Para qualquer problema, escreva primeiro para <a href="mailto:{{ $email }}" class="{{ $a }}">{{ $email }}</a>; respondemos o mais depressa possível. Pode também usar o <a href="https://www.livroreclamacoes.pt" rel="noopener" class="{{ $a }}">Livro de Reclamações Eletrónico</a>.</p>
        <p class="{{ $p }}">Em caso de litígio de consumo, pode recorrer a uma entidade de resolução alternativa de litígios: <a href="{{ $empresa['ral_url'] }}" rel="noopener" class="{{ $a }}">{{ $empresa['ral_nome'] }}</a>. Mais informação no Portal do Consumidor (<a href="https://www.consumidor.gov.pt" rel="noopener" class="{{ $a }}">consumidor.gov.pt</a>).</p>
        <p class="{{ $p }}">Estes termos regem-se pela lei portuguesa.</p>

        <h2 class="{{ $h2 }}">14. Alterações</h2>
        <p class="{{ $p }}">Podemos atualizar estes termos. As alterações não se aplicam a compras já feitas. Se forem importantes, avisamos por email antes de entrarem em vigor.</p>
    </main>
</x-layouts.site>
