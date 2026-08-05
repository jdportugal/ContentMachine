---
titulo: 'Why Large? Tiny LMs & Agents on Edge/Robotics — Cormac Brick, Google'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-07-25'
url: 'https://www.youtube.com/watch?v=hacEQHHhu2Q'
thumbnail: 'https://i.ytimg.com/vi/hacEQHHhu2Q/maxresdefault.jpg'
descricao: "The constraint on edge AI is not compute, it is RAM, and it is getting worse: phone makers are shipping less of it this year, and a 6GB Raspberry Pi costs 2.5 times what it did at launch. So Cormac Brick's team at Google AI Edge spends its effort making models small enough to fit. A 2 billion parameter Gemma, quantized to 2.9 bits per weight, runs on a Raspberry Pi at about 8 tokens per second and on a Qualcomm NPU fast enough for a few frames of vision a second. Below that sit tiny models, from 500 million parameters down to 50, that reach the older laptops and cheap devices where even a small model will not fit. They usually need fine tuning rather than prompting, but the payoff is real: a fine tuned Gemma turns free text into the right function call across ten actions at over 86% reliab..."
resumo: 'O vídeo apresenta o estado da arte dos modelos de linguagem minúsculos para IA no dispositivo (edge), explicando porque são necessários face aos modelos maiores e o que é preciso para os pôr em produção. Inclui contexto sobre o trabalho da equipa de Edge AI da Google (LiteRT, MediaPipe, Gemma) e exemplos práticos do que já é possível construir hoje.'
tags:
  - ai
  - 'ai engineer'
  - 'ai engineering'
  - 'software development'
  - tech
  - startups
  - 'software architecture'
  - 'machine learning'
fontes:
  - 'https://x.com/cormacb'
  - 'https://www.linkedin.com/in/cbrick/'
  - 'https://github.com/google-ai-edge/gallery'
---

## Descrição

The constraint on edge AI is not compute, it is RAM, and it is getting worse: phone makers are shipping less of it this year, and a 6GB Raspberry Pi costs 2.5 times what it did at launch. So Cormac Brick's team at Google AI Edge spends its effort making models small enough to fit. A 2 billion parameter Gemma, quantized to 2.9 bits per weight, runs on a Raspberry Pi at about 8 tokens per second and on a Qualcomm NPU fast enough for a few frames of vision a second.

Below that sit tiny models, from 500 million parameters down to 50, that reach the older laptops and cheap devices where even a small model will not fit. They usually need fine tuning rather than prompting, but the payoff is real: a fine tuned Gemma turns free text into the right function call across ten actions at over 86% reliability, and putting a speech model in front gives you voice to function calling. One shipped example is an offline voice dictation app with no subscription, built on two sub billion Gemma models that also strip your ums and ahs.

Speaker info:
- https://x.com/cormacb
- https://www.linkedin.com/in/cbrick/
- https://github.com/google-ai-edge/gallery

Timestamps:
0:00 - Why intelligence at scale needs tiny models
1:17 - The Google AI Edge team and its open source stack
2:35 - Why run on the edge at all
3:25 - The real constraint: DRAM cost
4:40 - Small models: 1 to 4 billion parameters
6:08 - Shrinking Gemma to 2.9 bits per weight
7:36 - Decode speeds across Raspberry Pi, Jetson, and NPUs
9:30 - Try it yourself: AI Edge Gallery and a hobby robot
12:07 - When small is still too big: tiny models
13:24 - Off the shelf tiny models: ASR, vision, embeddings
14:28 - Fine tuning for voice to function calling
17:50 - In production: offline voice dictation
19:30 - Takeaways and Q&A

## Transcrição

[música]
Sim. Então, sim, uma mudança de
ritmo em relação às duas últimas palestras. Então, estamos
analisando robôs de gama mais alta. Se
quisermos que a inteligência artificial esteja presente em
muitos e muitos dispositivos, e
não apenas em robôs muito caros,
precisaremos de modelos minúsculos. E esta palestra
abordará o estado da arte dos
modelos em miniatura no momento. Quais são as
coisas em que eles são bons? E quais são
as coisas que você pode começar a construir
hoje?
OK. Então, sim, primeiro um pouco de
contexto, brevemente sobre mim e
a equipe em que trabalho. Em seguida, vamos dar
uma olhada em modelos menores com os quais você talvez já
esteja mais familiarizado. É só para
explorar o que eles conseguem fazer e o que
ainda não conseguem. E aí a gente pensa: "
Por que precisamos de modelos ainda menores
?" E então, analisando o
estado da arte dos micromodelos hoje em dia,
e o que é necessário para dar a eles
uma forma que possa ser implantada em
produção para realizar tarefas úteis. E
sim, por último, temos alguns
exemplos que podemos analisar, provenientes do
trabalho da nossa equipe.
OK. Então, eu trabalho... Eu trabalho na
Edi há algum tempo. Hum, atualmente
trabalho como líder técnico na equipe de IA de ponta
do Google. Bem, dentro da equipe, os
tipos de coisas que fazemos são... desenvolvemos
projetos de código aberto como
LighterTLM, LighterT e MediaPipe, que
facilitam a implantação de IA em dispositivos de borda.
Também trabalhamos bastante na entrega de
tecnologia central de IA de borda para os
próprios produtos do Google, alguns dos quais por
meio de TinyModels. Além disso,
trabalhamos com a equipe Gemma para garantir que
seus modelos funcionem bem
em diversos dispositivos. Temos
um foco significativo em modelos pequenos e
compactos, porque isso
é útil para muitos
aplicativos de celular ou para
distribuir um modelo em um
navegador, que também precisa ser muito
pequeno. Geralmente, nossa
estratégia é desenvolver soluções internas
primeiro e,
se conseguirmos encontrar uma maneira de compartilhá-
las por meio de um pacote de código aberto ou
disponibilizar essas ferramentas para o mundo todo,
fazemos isso. Isso ajuda muitas
outras pessoas a criarem soluções semelhantes
usando tecnologia de código aberto.
Certo, então por que editar isso provavelmente é algo
como... hum... rel, em vez de simplesmente fazer
tudo na nuvem. Sabe,
é meio óbvio, mas vou explicar mesmo assim
. Existe uma espécie de
latência, mas a velocidade é rápida e consistente, os
dados de privacidade permanecem no dispositivo e, mesmo offline, a
disponibilidade é confiável. Então,
esse tipo de recurso do qual
você depende no seu dispositivo móvel
continuará funcionando mesmo quando você não tiver
sinal. Isso pode ser muito útil. E
então, economias, especialmente hoje em dia, se
a alternativa for recorrer a um
modelo ainda mais rápido na nuvem, isso terá
um custo. Principalmente se você estiver
lançando um aplicativo, como um
app para celular ou algo parecido, que funcione em um navegador, onde
a interação do usuário ocorre em uma
escala muito, muito grande. Então, mesmo
que esses tokens sejam relativamente
baratos, você está multiplicando-os por um
número grande e o custo total aumenta rapidamente.
Então, o principal desafio da
implementação de IA na borda é o mais à esquerda, que é relativamente
novo: o custo do DAM (Distributed Asset Management). Essa
é uma restrição realmente significativa,
e você verá que
alguns fabricantes de celulares estão
colocando menos DAM em seus dispositivos este
ano do que anteriormente. Você também verá
que, desde o lançamento, o custo de um
Raspberry Pi de 36 gigabytes aumentou cerca
de 2,5 vezes. Então, o custo da barragem de represas é
realmente muito significativo. Isso acaba
lançando uma sombra sobre o resto
desta conversa, não é? Para
conseguirmos executar aplicações de IA
na borda, precisamos pensar
muito sobre quantização e
também sobre qual é
o menor modelo microscópico possível que podemos
usar para uma determinada tarefa. Outros
desafios são, sim, a existência de um leque maior
de dispositivos-alvo, e outro
desafio ainda é, sim, é justo
dizer que muitas das
horas de pesquisa dedicadas aos LLMs atualmente se concentram em
modelos e técnicas muito maiores,
e a extremidade inferior do
espectro LLM é muito menos estudada. Então,
sim, esses são desafios da implantação
na borda,
ok? Modelos pequenos e quando eu sou pequeno. Eu
diria algo como tipicamente entre
um e dois ou entre um e quatro bilhões de
parâmetros. Você pode descobrir que esses recursos já estão
integrados ao sistema operacional. Existe uma versão de
um modelo pequeno que vem integrada em
celulares Android de última geração com núcleo de IA.
Existe uma versão que vem com o software da Apple e
com a inteligência da Apple.
Alguns fornecedores de aplicativos disponibilizarão
modelos desse tamanho em seus aplicativos.
Certamente trabalhamos com alguns fornecedores de aplicativos
que fazem isso. Bem,
para aplicações como IoT e robótica,
normalmente seriam necessários de quatro
a oito gigabytes de DRAM para
poder comercializar esse tipo de modelo, o que implica em
um custo adicional para o dispositivo,
certo? Isso acaba restringindo
esses modelos a dispositivos como
laptops, celulares ou
eletrônicos de ponta, e os torna inacessíveis para
navegadores web de nível inferior ou para o mercado mais amplo
de IoT e robótica de consumo
.
Sim, e para modelos menores, sim, estamos
desenvolvendo modelos menores e veremos isso daqui a
pouco. Trabalhamos bastante para
minimizar o tamanho dos arquivos com algum tipo de
quantização. E a estratégia aqui
é basicamente dar dicas, certo? Se você quiser
implementar uma funcionalidade específica usando um
modelo menor, pode simplesmente usar
zero prompts suaves e obter um desempenho bastante satisfatório
. Também é possível usar adaptadores Laura, que
são bastante robustos para
realizar tarefas como
chamadas de função e habilidades de agente.
Ok, então, um exemplo bem rápido é o
nosso trabalho com a
equipe da Gemma; nossa referência favorita
para esse tipo de coisa é sempre a Gemma.
Então podemos ver que o modelo E2B
é bastante capaz em termos de
raciocínio. É certamente comparável a
um modelo Gemma 3, bem maior,
de cerca de 12 meses atrás. Sim,
então agora temos modelos muito menores
que são bastante capazes de
raciocinar e obtemos respostas muito boas
mesmo sem nenhum estímulo inicial
para uma determinada tarefa. Também
trabalhamos muito para otimizar ao máximo o
uso de memória desse modelo de dois bilhões de parâmetros
. Bem,
ele usa uma combinação de quantização de dois, quatro
e oito bits, reduzindo
para algo como 2,9 bits por peso,
se você observar os pesos reais que
precisamos armazenar na memória. Usamos outros
truques, como incorporações por camada.
Não vou entrar em todos os detalhes
aqui, mas o resultado final é que
você precisa de, digamos, 841
megabytes de memória para um modelo de texton,
só para os pesos. E aí, quando
você adiciona o
cache KV em tempo de execução, pode chegar
a precisar de uns 2 gigabytes de
RAM ativa para executar esse modelo. Isso sem
contar o sistema operacional e
outras coisas que estão acontecendo. É daí que vem a
regra prática de 44 gigabytes ou mais para implantar isso
em um dispositivo. Bem,
em termos de velocidade, isso está usando
nosso ambiente de execução. Esta é apenas uma lista dos
dispositivos em que executamos o programa. Bem, para os
propósitos desta apresentação, vamos
analisar mais detalhadamente as três últimas linhas
da tabela, que representam o que aconteceria se pegássemos esse
modelo de dois bilhões de parâmetros e o executássemos
em um Raspberry Pi. Isso dará
cerca de 7,6 tokens por segundo decodificados. Isso
sem considerar o MTP. Se você ativar o MTP,
a velocidade poderá ser até 2 vezes maior, dependendo
da tarefa. Bem, se você usar um
dispositivo mais potente, como um Jetson RN
Nano, podemos atingir até 24
tokens de decodificação por segundo, ou talvez
até mais rápido se usarmos o próprio conjunto de ferramentas da Nvidia
. Isso se enquadra na nossa cadeia de ferramentas. Bem,
também trabalhamos na adaptação disso para uma
placa IoT da Qualcomm, que é bastante
popular em
aplicações de robótica e IoT de ponta, e lá, como
você pode ver, é possível obter
cerca de 4.000 tokens por segundo, com
31 tokens por segundo decodificados, o
que é útil para muitas
aplicações quase em tempo real em uma
NPU, porque
com esses modelos Gemma 4,
uma imagem de resolução média
tem cerca de 500 tokens. Uma
imagem de alta resolução tem 1120 tokens. Assim, você
poderia obter, por exemplo, três quadros por
segundo de tokens de alta resolução
passando por esse modelo e
ter uma velocidade de decodificação bastante decente também.
Portanto, existem muitas
aplicações interessantes que você pode criar com esse
tipo de modelo pequeno, se estiver de acordo com o
mercado ou se estiver
disposto a investir em
hardware mais caro e em uma
linha de DRAM mais cara
na sua lista de materiais para
o dispositivo que está construindo.
Sim, assim como nossa cadeia de ferramentas,
também trabalhamos com outros modelos na
comunidade que têm tamanho semelhante e cada um deles
tem seus pontos fortes,
certo? Então, esses são alguns dos outros
modelos que oferecemos suporte aqui. Bem
resumidamente, não vou entrar em
muitos detalhes, mas também temos, se eu conseguir
reproduzir isso, um aplicativo
que pode ser usado tanto no iOS quanto no
Android. Então, se você quiser pegar um
desses modelos pequenos e ver a velocidade com que ele
funciona em um telefone, você pode simplesmente
fazer isso. Então, está
disponível na galeria AI Edge. Além disso, todos aqueles "
Ah, estou sentindo essa vibração". Ah, o
aplicativo todo também é totalmente de código aberto. Portanto, se você
quiser ver como construir algo
semelhante usando um desses modelos ou
ver como isso funciona usando o
ambiente de execução de código aberto que executa os modelos, você pode
ver tudo isso. Essa é uma ótima maneira
de começar e experimentar
modelos pequenos, se é isso que você quer fazer.
Ok, este é outro exemplo, e
eu não vou reproduzir este vídeo, mas você
definitivamente deveria conferir. Este é
um exemplo que mostra um
robô OpenDoc Mini v2 de código aberto. Este é um projeto que
Zavier, um dos engenheiros da
DeepMind, construiu como uma espécie de hobby
. Muito, muito divertido. Então, dê uma olhada
neste vídeo do YouTube. O que você
verá é que ele tem dois robôs.
Um usa os Jets e o Nano, o
outro usa o Raspberry Pi. E você
verá que o robô é capaz de...
ele é capaz de... ler placas
e reagir a coisas, como
acenar com a cabeça. Ele também é capaz de
receber entradas de voz e de imagem.
Sim, e o que você verá é que o Jets
Nano tem uma
interação em tempo real realmente boa. Aquele baseado em
Raspberry Pi funciona, mas é bem mais
lento, certo? Então, para alguns exemplos...
para certos tipos de interação... mesmo
os melhores modelos que temos hoje
talvez não atendam a todos os
requisitos de interação do usuário.
Mas sim, este é um vídeo muito divertido, então
definitivamente vale a pena conferir. Então, sim... os
modelos pequenos, embora sejam ótimos,
certo? Se o seu produto puder
usar um desses, eles são muito
fáceis de usar, porque você só precisa configurar o
comando `zero-shot` para que
funcione. A Genet fez um ótimo trabalho ao criar
modelos compactos e altamente capazes, prontos para
uso e otimizados para funcionar em todos os
dispositivos que você viu anteriormente. E
você sabe, se... sim, então, se
você conseguir viver dentro dessas
restrições, ótimo, certo? Sua
jornada terminaria aqui e você
criaria um recurso que você gostaria de ter, certo? Para
muitas outras coisas que
fazemos em nosso trabalho e para outras pessoas com quem
conversamos. Sabe, ainda estamos num
ponto em que os modelos pequenos são grandes demais
porque não conseguem alcançar
laptops mais antigos ou dispositivos de consumo mais voltados para a borda
. A interação do usuário precisa
ser mais responsiva. Bem, também podemos
ter a realidade, e isso acontece muitas
vezes, de que o modelo que você deseja
executar não é o recurso principal do
aplicativo. É como uma coisinha minúscula
num canto que precisa funcionar enquanto
todo o resto do sistema está
funcionando. Então, também precisamos de um
modelo menor para a saúde do sistema. É um
padrão muito comum.
Então entram em cena modelos bem pequenos,
certo? Normalmente, esses parâmetros são tão
pequenos quanto cerca de 50 bilhões.
Implementamos modelos que variam de
cerca de 500 milhões de parâmetros. Ah, eles são mais
fáceis de distribuir nativamente com os
aplicativos. Eles funcionariam com base nos
tipos de coisas que você vê no lado direito
. Hum, e exigiria,
sabe, talvez menos de 2 gigabytes de RAM, ou
até menos que isso. E também podem
ser programados para correr muito, muito rápido. Mas
o processo de implementação aqui é um
pouco mais complicado. Então, como você sabe,
às vezes existem modelos prontos
que fazem o que você precisa, e vamos analisá-
los no próximo slide. Ou então,
se isso não funcionar, você vai
acabar tendo que ajustar um
modelo para alcançar um determinado resultado, o
que funciona muito, muito bem. Então,
modelos de tarefas fixas... hum, há várias coisas
relacionadas a ASR, visão computacional e modelos de incorporação,
e se você tiver... se você tiver algo... sim,
então, como ASR, visão computacional e
incorporação, esses são todos
recursos padrão e funcionam muito, muito
bem. Este é um exemplo do VLM rápido da Apple,
que é um modelo de 0,5 bilhão de parâmetros
rodando em um
dispositivo Android usando
aceleração de hardware, e você pode ver que ele roda
muito, muito rápido. Então, se você precisasse
adicionar um pouco de
inteligência visual a um dispositivo de borda
ou a um dispositivo IoT, por exemplo, essa classe
de modelo é uma
excelente opção para obter esse
primeiro nível de percepção visual ou para reconhecimento automático de fala (
ASR). Sim, também existem alguns
modelos bastante robustos listados aqui.
E, por último, os modelos de incorporação
são ótimos para... bem, este é
apenas um modelo de incorporação de texto que é
realmente bom em processar
e encontrar correspondências em textos, o que pode ser
relevante em alguns casos.
OK. Mas o
próximo cenário é quando você quer ajustar
um modelo com mais precisão. Então, aqui você pode
começar com... os modelos que estou citando aqui
são modelos desenvolvidos pelo Google.
Então, existem alguns parâmetros iniciais, como 270
milhões, e Gemma 3 e a
função Gemma. Uh Gemma 3 é um
modelo de uso geral. A função Gemma é uma
que possui treinamento prévio adicional para
padrões de chamada de função. Então, aqui está o
desempenho. Se você se lembra, anteriormente
no Raspberry Pi, nosso desempenho era de, digamos, alguns
tokens decodificados por segundo
. Então, aqui, esse número sobe para
45 tokens por segundo porque precisamos
ler menos da memória a cada vez. E
podemos ajustar isso para fazer
coisas bastante interessantes. Então, do
lado direito, estamos executando o que
chamamos de modelo de ações móveis. Então, isso
faz entrada de texto e chamada de função.
Este modelo conhece cerca de 10
funções de saída diferentes e consegue invocá-las com
mais de 86% de confiabilidade a partir de qualquer
texto de entrada. E isso serve para
realizar tarefas comuns em um dispositivo móvel,
como agendar um compromisso no calendário ou
ativar e desativar o Wi-Fi, ou coisas do tipo.
E pode receber entradas de texto livre arbitrárias
e convertê-las em
chamadas de função apropriadas. E para esta demonstração,
pegamos outro modelo de reconhecimento automático de fala (ASR) e o colocamos
na frente desse. O que dá uma espécie de
voz à chamada de função como um recurso.
E a conversão de voz em função é
fundamental para muitos dispositivos de IoT e de borda,
porque, bem,
dispositivos menores tendem a
ter menus de configuração complexos, e
essa interface de usuário pode ser muito
desafiadora para muitas pessoas. Então,
sim, ser capaz de simplesmente falar com
algo para pedir um determinado resultado.
Essa é uma capacidade bastante importante e
podemos implementá-la de forma razoavelmente confiável usando
um modelo pequeno e bem ajustado. Então, o
procedimento padrão é o seguinte: você escolhe um
modelo base, verifica o desempenho
e se o consumo de memória está
dentro da faixa desejada e, então, a
parte mais difícil é...
O procedimento que descobrimos que funciona muito
bem é gerar
dados sinteticamente para ajustar esse modelo,
dependendo do modelo. Por exemplo, temos um
conjunto de dados de código aberto chamado
Mobile Actions, disponível no Hugging
Face, que corresponde a isso, caso você
queira recriar essa mesma demonstração
e ajustar a função Gemma
do zero. Mas, em geral,
descobrimos que entre 10.000 e 10
milhões de amostras de
dados gerados sinteticamente são suficientes para
ajustar um modelo menor a um
grau de confiabilidade realmente muito alto. E o mesmo se aplica a
outras tarefas que realizamos,
como resumos ou
revisão de textos. Portanto, algo que você poderia fazer
com um modelo de dois ou quatro bilhões de parâmetros de forma
razoavelmente confiável, se estiver
disposto a investir tempo e energia na
criação de um conjunto de dados sintéticos e no
ajuste fino de um modelo, você pode alcançar uma
qualidade semelhante, igual ou superior
com um modelo muito menor, que
funcionará em uma gama muito maior de dispositivos
e será muito mais responsivo.
Então, sim, e esse é o tipo de
resultado que estamos vendo agora, apenas
ajustando um modelo para uma única tarefa,
e descobrimos que
essa é uma ótima estratégia para
implantação em larga escala.
Aqui está outro exemplo, um
exemplo em produção, onde temos
um aplicativo que desenvolvemos para
ditado por voz sem assinatura. Toda
a digitação por voz acontece localmente
no dispositivo. Hum, e além de fazer
ditado, ele também...
uau, ele meio que corrige os "ums" e os "
a", né? Como você pode ver no
lado direito, ele consegue limpar o texto. Ele
também é capaz de direcionar a busca para
palavras e nomes que sejam
relevantes para você pessoalmente. É uma espécie de
personalização. O lado esquerdo
mostra, de certa forma, como construímos esse aplicativo.
Então, existe um mecanismo de reconhecimento automático de fala (ASR) e um
mecanismo de policiamento de mensagens de texto. E ambos são
versões aprimoradas de minúsculos
modelos Gemma. E isso nos permite pegar
algo que antes era uma
funcionalidade exclusiva do servidor, que
exigia uma assinatura para realizar
ditados de voz altamente precisos, e
ter um aplicativo capaz de fazer isso
completamente offline com altíssima
qualidade. Então, isso é algo que você pode
experimentar no iOS, se quiser testar
hoje. Mas, bem, sim, e
a espinha dorsal deste aplicativo são dois
pequenos modelos baseados em gemma, finamente ajustados, com
centenas de
milhões de parâmetros.
Vale ressaltar também que existem
recursos em versão prévia para desenvolvedores
no Chrome, como
APIs de resumo e revisão, que funcionam
como APIs integradas. A
disponibilização desses recursos por meio de
TinyModels permite que a equipe do Chrome os ofereça
a um conjunto muito maior de
usuários do que seria possível de outra forma.
Sim, então, provavelmente temos
um minuto para perguntas. Bem,
algumas das principais conclusões. Está no
último slide, se eu conseguir chegar lá. Sim
. Em resumo, a principal conclusão a tirar de dispositivos de consumo
e robótica de nível básico é que os pequenos LLMs
são fáceis de usar. E especialmente em
NPUs, elas são muito, muito rápidas. Os
modelos minúsculos permitirão alcançar um
número muito maior de dispositivos, e as
chamadas de voz para funções agora podem ser construídas para
serem robustas usando modelos minúsculos. Bem, isso
requer apenas investir em um
conjunto de dados sintéticos apropriado, com
amostras suficientes, e então você pode
ajustar um modelo para obter resultados realmente bons
.
Legal. Terei o maior prazer em responder a uma ou duas
perguntas, ou, se alguém tiver alguma. Sim
.
Desculpe. Vou desligar isso. Sim.
Desculpe.
Desculpe. Segundo.
como ambições mais amplas sobre aonde os
modelos minúsculos podem chegar. Uau. Bem, acho que
generalizar a voz para chamadas de função
é um objetivo fundamental, tornando
isso muito fácil para muitas pessoas,
porque acho que esse é um caso de uso essencial.
Se conseguirmos
descobrir como fazer
um agente gerar
dados sintéticos para você, certo? Bem, se quisermos,
certamente é possível tornar essa jornada
muito mais fácil do que é hoje e torná-la
acessível a muito mais pessoas. Sim, é
. e, certamente,
também o estímulo visual. Isso leva um
pouco de tempo no momento.
Certamente há espaço para modelos mais rápidos
que possam realizar uma gama maior de tarefas,
como segmentação e outras
funcionalidades que possibilitariam outros
casos de uso.
Incrível. Sim. Você consegue ver as horas?
Provavelmente não teremos sessão de perguntas e respostas
hoje. Sim. Mas Corman talvez fique depois
da sessão, e você pode fazer
mais perguntas sobre isso.
Ou então, pode
me encontrar lá embaixo, no estande da Jeep Mind,
às 16h. Estarei lá entre 4 e
5. Ok.
[música]
