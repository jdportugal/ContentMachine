---
titulo: 'How I automate my own job at Hugging Face using agents — Niels Rogge, Hugging Face'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-20'
url: 'https://www.youtube.com/watch?v=FLUoowDJg4I'
thumbnail: 'https://i.ytimg.com/vi/FLUoowDJg4I/maxresdefault.jpg'
descricao: "Thousands of GitHub issues, opened automatically, have produced exactly two negative replies. Niels Rogge works on what he calls the Google Drive to the hub team at Hugging Face, whose job is noticing that a paper's weights are sitting on Dropbox or Zenodo where nobody will find them, then asking the authors to publish on the hub instead. Hundreds of papers land on arXiv every day, so he automated himself. The useful part is that he built it twice, in opposite shapes, and explains why each time. The outreach half is a deterministic workflow: a model call at each step of the path he used to walk by hand, no agent framework at all, running nightly as a cron job on free GitHub Actions minutes, with tracing so he can inspect prompts, cost, and latency. He chose that because the prevailing advi..."
resumo: 'The video discusses how Niels Rogge automates his work at Hugging Face by improving the visibility and accessibility of machine learning artifacts through the Citizen Science team, which encourages researchers to publish their models and datasets on the Hugging Face platform instead of using third-party services. It highlights the benefits of centralized storage and metadata tagging for enhancing...'
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
  - 'https://x.com/NielsRogge'
  - 'https://www.linkedin.com/in/niels-rogge-a3b7a3127/'
  - 'https://nielsrogge.github.io/'
---

## Descrição

Thousands of GitHub issues, opened automatically, have produced exactly two negative replies. Niels Rogge works on what he calls the Google Drive to the hub team at Hugging Face, whose job is noticing that a paper's weights are sitting on Dropbox or Zenodo where nobody will find them, then asking the authors to publish on the hub instead. Hundreds of papers land on arXiv every day, so he automated himself.

The useful part is that he built it twice, in opposite shapes, and explains why each time. The outreach half is a deterministic workflow: a model call at each step of the path he used to walk by hand, no agent framework at all, running nightly as a cron job on free GitHub Actions minutes, with tracing so he can inspect prompts, cost, and latency. He chose that because the prevailing advice when he built it was to avoid agents unless you genuinely need one. The follow up half, built recently, is the reverse. It is a fully autonomous loop whose main tool is bash, carrying one CLI, one skill, and a sandbox, fanned out so that every issue gets its own container. He is also candid that recipients are not told an agent wrote to them, on the grounds that it sends what he used to send himself and a disclosed bot tends to get closed unread.

Speaker info:
- https://x.com/NielsRogge
- https://www.linkedin.com/in/niels-rogge-a3b7a3127/
- https://nielsrogge.github.io/

Timestamps:
0:00 - The Google Drive to the hub problem
1:59 - Paper pages, metadata, and discoverability
3:41 - Why manual outreach does not scale
4:29 - The workflow he was running by hand
5:19 - Workflow or agent, and why it is not binary
7:03 - Nightly cron jobs, and tracing cost and latency
8:46 - The flood of replies, and automating follow up
9:36 - Switching to a fully autonomous loop
10:25 - Bash, one CLI, one skill, one sandbox
12:06 - A container per issue, fanned out
13:49 - What researchers actually reply
15:32 - Migrated models, and a 400 gigabyte dataset
18:06 - Open models, agents over workflows, and evaluation

## Transcrição

[música]
Ok.
Tudo bem.
Olá pessoal.
Obrigado por ter vindo.
Hoje vou falar sobre como automatizo meu
próprio trabalho na Hugging Face usando agentes.
Uma breve introdução. Eu sou apenas Niels,
da Bélgica, a terra da cerveja, das batatas fritas
e do chocolate. Estudei na KU Leuven
e trabalho como engenheiro de aprendizado de máquina na
Hugging Face há 5 anos.
Hoje vou falar sobre a
equipe de ciência cidadã da Hugging Face, da qual faço
parte.
Então, vou falar sobre como automatizo
grande parte do trabalho da equipe de ciência cidadã
. E, por fim, também falarei sobre
alguns outros esforços que realizamos na
Hugging Face.
Então, vamos começar com a
equipe de ciência cidadã da Hugging Face.
Basicamente, tudo começou quando me
enviaram uma pesquisa que estava em alta
no GitHub. E muitas
vezes, quando eu via trabalhos novos e interessantes,
os pesos não estavam disponíveis no
Hugging Face, infelizmente. Por exemplo, os pesquisadores
usam o Google Drive ou o
GitHub Releases.
Eles usam o Dropbox, o Zenodo ou
outros servidores para armazenar seus artefatos
. E isso prejudica a visibilidade do
trabalho deles. Não é algo facilmente
visível ou detectável.
E aí eu abro uma issue no GitHub para
dizer tipo, "Na verdade, você poderia disponibilizar seus
pesos no Hugging Face de graça." Na maioria
das vezes, as pessoas me respondiam algo como:
"Sim, migrar os pesos do
Google Drive para o Hugging Face
faz todo o sentido."
Então, sim, a equipe de ciência cidadã
também pode ser descrita como o Google Drive
para a equipe central.
Hum, por quê? Porque na Hugging Face, nós temos
essas páginas de papel,
e cada uma delas é de
arquivo. E então, no lado direito, você
pode basicamente listar os artefatos vinculados,
como os modelos ou conjuntos de dados vinculados.
Assim, as pessoas podem reproduzir facilmente seu
artigo ou encontrar os modelos ou conjuntos de dados. Sim
, você pode vê-los do
lado direito.
Isso melhora a visibilidade do
seu trabalho, pois temos essas
tags ou filtros de metadados no aplicativo, então você pode
encontrar facilmente, por exemplo, um
modelo de estimativa de profundidade ou
um LLM, se tiver interesse. Você pode
encontrá-los por idioma. Você pode marcá-los
com a biblioteca com a qual são compatíveis
e assim por diante. Assim, isso melhora a
visibilidade do seu trabalho. Então, essas
são, sim, as tags de metadados que você
pode adicionar a cada modelo no Hugging
Face ou a cada conjunto de dados.
Então, sim, esse é o principal problema
que observamos: muitas pessoas, muitos
pesquisadores, estão usando
serviços de terceiros para publicar seus
trabalhos. Temos a plataforma Hugging Face,
que é como um local centralizado onde as
pessoas podem encontrar artefatos de aprendizado de máquina
. A
documentação também melhora,
pois permite adicionar um cartão de modelo ou um
cartão de conjunto de dados. Temos ferramentas que permitem que você
carregue ou baixe arquivos do
Hugging Face com facilidade.
E também pode ajudar a alcançar pesquisadores
na promoção de seus trabalhos. Portanto, é
basicamente uma situação vantajosa para todos,
tanto para os pesquisadores quanto para outras
pessoas que utilizam a pesquisa.
Então, sim, esses são os
problemas típicos que eu estava abrindo no GitHub. Eu sempre tive
o mesmo modelo. Eu simplesmente perguntei:
"Vocês poderiam disponibilizar este
checkpoint no Hugging Face? Vocês poderiam
disponibilizar este conjunto de dados no Hugging
Face?"
E [bufa] então eu também abri PRs,
pull requests no Hugging Face para adicionar
cartões de conjuntos de dados ou cartões de modelos para melhorar
a documentação desses artefatos.
Mas há um problema.
Não é realmente viável para mim abrir
todos esses problemas ou solicitações de pull no GitHub,
porque todos os dias são
publicados centenas de artigos de pesquisa
no Archive, especialmente agora com o boom da IA. Sim
, e a NeurIPS, por exemplo, uma
importante conferência de IA, também está recebendo uma
quantidade enorme de artigos.
Então, podemos automatizar isso? Podemos ampliar
a equipe de ciência cidadã com agentes?
Então, essa é a segunda parte da minha apresentação.
Como podemos, sim, ampliar isso para uma
quantidade enorme de artigos de pesquisa?
Então, a ideia é bem simples. Bem,
deveríamos ter um agente de IA que pudesse
me ajudar a entrar em contato com todos esses
pesquisadores que publicam modelos ou
conjuntos de dados como parte de seus
trabalhos de pesquisa. E então, sim, faça a divulgação de
forma automatizada. Então, esse era o
fluxo de trabalho típico que eu seguia.
Basicamente, sempre que eu via um
artigo científico, a primeira coisa que eu tentava fazer era encontrar o
URL do GitHub desse artigo, se estivesse disponível.
Então, eu li o arquivo readme daquele
arquivo do GitHub. E então, basicamente, eu verifico se há
algo novo e interessante para
compartilhar no Hugging Face.
Hum, pode ser que já esteja no Hugging Face
. Nesse caso, verifico se
os cartões do modelo ou os cartões do conjunto de dados
já estão devidamente
presentes, se as tags de metadados,
por exemplo, estão lá.
Caso contrário, posso abrir uma
solicitação de pull request. Caso contrário, se os artefatos
ainda não estiverem no Hugging Face, eu abro uma
issue no GitHub. E, por fim, também entro em contato
com o autor para fazer o acompanhamento. Então, esse é o tipo de
fluxo de trabalho que eu tive que automatizar
com os agentes.
E existem várias maneiras de resolver
isso. Você poderia
optar por um fluxo de trabalho.
Aliás, essas imagens foram tiradas
do post do blog "Building Effective
Agents" da Anthropic, que é uma
leitura realmente excelente. Leia. Então, do
lado esquerdo, você vê, sim, um fluxo de trabalho que é
mais determinístico. Basicamente, você usa as
APIs do LLM dentro de etapas de um
caminho ou pipeline predefinido, o
que é mais previsível. É mais
determinístico. Você tem mais controle
sobre isso. Claro, é menos
flexível. Por outro lado,
você poderia ter um
agente totalmente autônomo, que é um LLM em um
loop que chama ferramentas até terminar,
o que é mais flexível, mas também menos
previsível.
Uh Uh, na época, sim, claro,
não precisa ser uma história binária. Você
pode ter um fluxo de trabalho, por um lado, e
um agente totalmente autônomo, por
outro, mas também pode, é claro,
combinar esses dois tipos de recursos, de acordo com o
seu caso de uso.
No meu caso, optei por um
fluxo de trabalho bastante determinístico. Hum, por quê? Porque
na época em que eu estava construindo isso,
em 2024,
foi quando a Anthropic escreveu
sua postagem no blog intitulada "Construindo
agentes eficazes". E eles disseram mesmo:
"Tente evitar criar agentes se
realmente não for necessário. Comece de forma simples,
comece com uma única API LLM.
Evite frameworks." Ah, e na verdade,
acho que essas foram ótimas dicas. Então, na
época, comecei a construir um fluxo de trabalho
que basicamente replicava o fluxo de trabalho
que eu estava usando quando fazia esse
trabalho de divulgação.
Então, sim, esse é todo o
processo. Isso foi criado usando o
servidor Excalidraw MCP no Cursor. É
muito legal poder criar uma visualização do
seu código.
Bem, não vou entrar em
detalhes, mas basicamente ele
replica o fluxo de trabalho que eu
usava quando fazia o trabalho de divulgação. E eu utilizo as
APIs do LLM em cada uma das etapas
, sem qualquer framework ou agente
. Então, isso tornou tudo bastante
determinístico e eu tive muito controle
sobre como as coisas aconteceram.
Bem, em termos de implementação desse
fluxo de trabalho, é uma tarefa cron simples. Então, o
cron é simplesmente algo que é executado
regularmente. No meu caso, eu executo o programa uma vez
por noite. Então, enquanto estou dormindo,
existe esse agente, mas tecnicamente
é apenas uma tarefa cron, um script Python
com uma API LLM, que vai ler
todas essas centenas de artigos arquivados
e, em seguida, pode abrir problemas no GitHub
ou solicitações de pull no
Hugging Face.
Estou usando o GitHub Actions para isso.
Vi um post muito bom em um blog sobre tarefas cron gratuitas
com o GitHub Actions, e na verdade,
provavelmente é o melhor ponto de partida se
você quiser configurar tarefas cron,
porque o GitHub tem um plano bem generoso para
quem quer começar a
criar tarefas cron simples
. Sim, isso facilita muito
para mim gerenciar todas essas
tarefas cron na interface do usuário. Sim
, todas as noites centenas de
problemas são criados no GitHub.
Para a parte de rastreamento,
estou usando o LangFuse. Ah, sim, a LangFuse
também tem um estande aqui.
Um LangFuse é muito bom.
Eu o utilizo principalmente para a parte de rastreamento,
a parte de observabilidade, apenas para ver o que
o LLM está fazendo, quais são as entradas,
quais são as saídas,
quais são os prompts, quanto
custa, a latência e assim por diante. Então, sim,
eu definitivamente recomendo
. Bem
, como meus agentes abrem
muitas solicitações no GitHub todas as noites,
acabo com uma quantidade enorme de notificações não lidas do
GitHub porque as pessoas
respondem a essas solicitações.
E isso dá muito trabalho, ter que responder
a todas essas questões. É mais ou menos
como vasculhar sua caixa de correio.
Então você pode se perguntar, será que também poderíamos
automatizar o acompanhamento dessas
ocorrências no GitHub? Inicialmente,
a criação da issue no GitHub era feita por
um agente, mas eu ainda era o
responsável pelo acompanhamento posterior. Bem, há
alguns meses também automatizei
o acompanhamento dessas
questões do GitHub.
Novamente, você pode pensar: como devo
resolver isso? Você deve optar por um
fluxo de trabalho mais determinístico ou pode escolher
agentes totalmente autônomos, tipo um LLM em
loop que funciona com algumas ferramentas e
habilidades?
Bem, aqui eu optei por
agentes totalmente autônomos, então é algo bastante
flexível. É um pouco menos previsível,
mas funciona muito bem. Eu escolhi
este curso porque, em novembro do ano passado,
na AI Engineer em Nova York, houve um
workshop muito bom da Anthropic sobre o
SDK do agente Claude.
E lá estavam eles, dizendo que os
agentes poderiam ser melhores do que os fluxos de trabalho.
Então eles estavam meio que se
contradizendo, mas ele
disse que os modelos se tornaram tão bons
que agora você pode começar a
trabalhar com agentes totalmente autônomos em vez de
um fluxo de trabalho.
Por isso, optei por essa abordagem
e, na verdade, estou usando o
SDK de agentes Claude para esse caso de uso.
Ah, teve outra palestra muito boa do
Cursor também na AI Engineer. Isso aconteceu
na versão europeia em Londres, alguns
meses atrás.
Lá, eles falaram sobre como
substituíram 12.000 linhas de código personalizado, um
fluxo de trabalho bastante sofisticado, por uma
habilidade muito simples de 200 linhas de código. Na
verdade, para mim é bem parecido.
Consigo
substituir muitos códigos personalizados, milhares
de linhas de código, por um
agente simples, talvez com uma interface de linha de comando como ferramenta
e uma habilidade, e pronto,
porque os modelos evoluíram muito.
Então, sim, em termos de
arquitetura, é mais ou menos assim que se parece
. Então, na
verdade, é apenas o
SDK de agentes Claude, que eu diria ser um
SDK Python muito bom para construir um
agente.
Inicialmente eu estava usando os modelos Claude,
mas desde esta semana estou
usando o modelo GLM 5.2 através dos
provedores de inferência do Hugging Face. A Hugging Face
oferece
um serviço que basicamente engloba vários
provedores de inferência, como Together AI,
Fireworks, Cerebras e outros. Assim, você
pode usar diversos modelos abertos de
forma unificada. É compatível com OpenAI
ou com Anthropic, e então eu
implanto isso no Modal. O modo modal também está
presente aqui hoje.
E utiliza principalmente o Bash como ferramenta, ou seja,
o terminal para
executar comandos da Hugging Face, já que
usa bastante a CLI da Hugging Face.
Então, eu o combino com a
skill CLI do Hugging Face, que é tudo o que ele
precisa. E então
pode ser que faça um comentário no GitHub como
acompanhamento. E também
publica no Slack, porque eventualmente
eu quero ver os resultados finais no
nosso canal do Slack
da Hugging Face. Então, sim, dado
que também há muita expectativa em torno do GLM
5.2 recentemente, por exemplo, a Cursor viu um
ótimo desempenho em seu teste de benchmark. O
Post-training Bench é outro exemplo em
que ele supera o Opus 4.8 e
é mais barato. Então,
sim, não há motivo para não usar o GLM
5.2, especialmente considerando que trabalho na
Hugging Face.
Bem, para a implantação, como eu disse antes,
eu uso Modal.
É ótimo se você quiser implantar
agentes. No meu caso, estou usando o
recurso de processamento em lote. Assim, elas permitem que
você inicie uma quantidade enorme de
contêineres, todos em paralelo.
Basicamente, cada contêiner é um loop de agente
que processa uma solicitação do GitHub.
É muito fácil de usar, devo
dizer.
E as startups também são bem rápidas.
Portanto, eu definitivamente recomendo se você estiver
criando agentes que, por
exemplo, funcionam em segundo plano, durante a
noite toda, por exemplo.
E quanto à forma como eu o invoco, sim,
tecnicamente eu também poderia simplesmente implantá-
lo como uma tarefa cron. Modal, por exemplo,
oferece suporte a isso. Mas, normalmente, o
acompanhamento das questões do GitHub, eu ainda
faço manualmente, invocando-o
como uma habilidade. Então, eu criei uma habilidade para
isso no Cursor.
Eu chamo isso de processo sob o nome Modal. E
então, o que ele vai fazer é
invocar um agente,
neste caso, o Composer 2.5, que é
o agente que eu mais uso no
Cursor, e que por sua vez, vai invocar
todos os outros agentes. Então, esse é o
tipo de ciclo de que as pessoas estão falando
. E, por fim,
todos os resultados serão publicados no nosso
canal do Slack. Então,
sim, e é exatamente isso que aparece na tela
. Então, basicamente, o que ele faz é
publicar uma enorme quantidade de
artigos da Hugging Face,
que são artigos de pesquisa que as
pessoas podem disponibilizar na Hugging
Face, porque sempre que alguém os menciona
em um cartão de modelo ou de conjunto de dados, nós os
indexamos no hub. E então, ele simplesmente
publica todos os artefatos que as pessoas têm
enviado com base na divulgação
que fazemos via GitHub. Então,
sim, eu ainda faço isso manualmente
. Então eu simplesmente invoco a skill e,
depois de alguns minutos, essas mensagens
aparecem no nosso canal do Slack. Sim
, eu incluí alguns
resultados divertidos porque, para ser sincero, é bem
legal ver as pessoas interagindo com os
agentes.
Para ser sincera, eu não revelo que se trata de
um agente. Por que? Porque acho que se as pessoas
souberem que é um bot, podem
fechar a questão rapidamente. E, para ser sincero,
eles postam exatamente as mesmas coisas que eu
fazia manualmente antes. Portanto, na
verdade, não vejo nenhum motivo para fazer isso.
Então, você vê respostas como
esta. Olá Niels, muito obrigado pela sua
sugestão e pela orientação clara. Na
verdade, também vejo com frequência pessoas
usando um agente para responder aos meus agentes. Então, é mais ou menos assim que a
internet funciona hoje em dia.
Mas, sim, as pessoas disponibilizam todos os seus
artefatos no Hugging Face. E,
dentre os milhares de problemas que estão sendo
criados no Hugging Face,
até agora eu só recebi dois
comentários negativos. Um cara dizendo: "É, por favor,
fechem essa bagunça." Então ele encerrou a questão.
E depois mais uma. Mas a maioria das
pessoas simplesmente diz: "Sim, na verdade
faz todo o sentido disponibilizar meus pesos
ou meus conjuntos de dados no Hugging
Face." Tipo, por que eu não pensei nisso? Então,
eu
diria que é uma situação em que todos saem ganhando.
Ah, eu
também costumo postar resultados interessantes no nosso
canal do Slack. Por exemplo, certa vez
um pesquisador da Apple
me mandou uma mensagem direta dizendo: "Vi que você entrou em contato
comigo." Sim, tecnicamente é meu agente
apenas relatando um problema no GitHub sobre a
publicação de um novo artigo da Apple sobre
os artefatos de um artigo da Apple no
Hugging Face. Ou, por exemplo, entra em
contato com o Google DeepMind
para publicar
conjuntos de dados matemáticos. Então,
muitas vezes recebo
e-mails, como aquele em particular, onde,
sim, querem publicar um
conjunto de dados de 400 GB no Hugging Face, mas
também era meu agente abrindo
problemas no GitHub. Sim
, este é mais um resultado interessante. Então, a
Paddle OCR é uma empresa chinesa.
Eles migraram todos os seus modelos de OCR para o
Hugging Face com base no contato dos
agentes que me causaram problemas. Sim
, é muito bom.
Outro resultado interessante é quando
ele completa o modelo padrão de
cartões de modelo no Hugging Face. Então, Mac
Mitchell, que também trabalha na Hugging
Face, tem um famoso documento chamado "
model cards" para relatórios de modelos,
garantindo que todos documentem seus modelos
de maneira adequada. Assim, nós fornecemos
esse modelo, que você pode ver no
lado esquerdo na comparação do Git. E então, o
agente simplesmente completa esse modelo
com base no conteúdo que encontra
no documento, como o arquivo readme do GitHub,
o próprio PDF e assim por diante.
Sim, também é bastante engraçado ver,
por exemplo, neste caso, que
me incluíram
no cartão do modelo. Dizia: "Autores dos cartões modelo
, Niels faz parte da
equipe de ciência cidadã da Hugging Face." Eu nunca
sugeri isso dessa forma, mas é bem
divertido de ver.
Ou as pessoas respondem: "Obrigado por
me ajudar a corrigir meus erros." Portanto, tudo isso
é feito pelos
agentes.
Acho que o problema mais popular
criado no GitHub foi este artigo, "Tiny
Recursive Models", que você talvez tenha
visto, e que foi bastante comentado
tanto no Hugging Face quanto no
Twitter.
Sim, mais de 60 pessoas
votaram a favor dessa questão, o que fez com que o modelo fosse
lançado no Hugging Face. Portanto,
acredito que esta seja novamente uma situação em que todos saem ganhando. Portanto, é
uma vitória tanto para o pesquisador, que torna
sua pesquisa mais visível no
Hugging Face, quanto
para as
pessoas que desejam se basear
nessa pesquisa e
utilizá-la.
Então, sim, eu tenho centenas de
problemas no GitHub onde acho que posso mostrar bons
resultados,
onde as pessoas interagem com os agentes.
Você também pode estar se perguntando, tipo, como
evitar a inconsistência, porque você pode pensar,
ok, você tem um agente... enviando spam para a
internet inteira com seus problemas do GitHub.
Tipo, será que você deveria fazer isso? Novamente, eu
já falei sobre a situação em que todos ganham. Bem,
um post de blog que eu recomendo muito,
se você quiser evitar que seu agente esteja
apenas publicando bobagens, é o FAQ "LLM Evils"
de Hamel Husain. Bem, eu diria que ele é
o principal especialista quando se trata de
avaliação de LLM.
Ele também oferece um curso pago, mas
publica muito conteúdo gratuito
online, incluindo este post do blog. Portanto,
recomendo fortemente que você leia o material se
quiser aprender mais sobre como avaliar
seus agentes.
Então, minha conclusão seria que os
modelos abertos estão ficando ótimos,
especialmente agora com o GLM 5.2. Você tem o
Deep Seek V4 e outros. Então, sim,
agora somos capazes de substituir modelos de código fechado
por modelos de código aberto.
Bem, para o meu caso de uso, eu diria que os agentes
são, na verdade, melhores do que os fluxos de trabalho.
Bem, eles só precisam de uma única CLI, que
é a CLI do Hugging Face. Eles precisam de uma
única habilidade, a habilidade CLI do Hugging Face
, e um ambiente de teste (sandbox), e isso é tudo o que
precisam para realizar seu trabalho.
E, por fim, sim, não se esqueça da
avaliação.
Bem,
finalmente, também posso falar sobre alguns
outros esforços que realizamos como parte da
equipe de ciência cidadã.
Hum, muito em breve. Bem, eu tenho uma
conta no Twitter que eu criei. Chama-se
Daily Papers.
E, na verdade, utiliza exatamente o mesmo
fluxo de trabalho que meus agentes nos bastidores
para publicar artigos de pesquisa interessantes sobre
X.
Recentemente, ultrapassou a marca de 90.000 seguidores
sem qualquer envolvimento meu. Acabei de
implementar isso e ele publica
artigos de pesquisa e
artefatos interessantes da Hugging Face a cada 4
horas ou sempre que alguém publica
algo legal na Hugging Face. Então, é isso
.
E eu tenho algo parecido com o que o Gemini está tentando me dizer: qual é o
melhor visual para twittar ou incluir no
tweet? Por exemplo, este tweet recente,
onde a Nvidia
anunciou o lançamento de uma versão otimizada do GLM 5.2,
recebeu mais de 2.000 curtidas. É
muito legal ver isso.
E um último projeto em que estou trabalhando
agora é o renascimento do Papers With
Code, um site que
existiu por um tempo, foi adquirido pela Meta
e, infelizmente, deixou de existir. Então, estou
tentando revitalizá-lo e tornar a
pesquisa e o que há de mais moderno mais
acessíveis. Por enquanto, está hospedado em
paperswithcode.co.
Ah, então, sim. Você pode encontrar referências
por lá. Por exemplo, para modelos de OCR,
todos os benchmarks de OCR são populares.
Mas também estou transformando-o em um
recurso educacional para que as pessoas possam aprender sobre
termos técnicos como treinamento misto,
destilação de políticas e assim por diante.
Então, sim. Essa foi toda a minha apresentação.
Espero que você tenha aprendido algo. Obrigado a todos
pela atenção.
[aplausos]
