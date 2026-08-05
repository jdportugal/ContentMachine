---
titulo: "Microsoft Just Dropped LLM's Frontier Data Engineering Secrets"
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-07-13'
url: 'https://www.youtube.com/watch?v=aD93kfArOik'
thumbnail: 'https://i.ytimg.com/vi/aD93kfArOik/maxresdefault.jpg'
descricao: 'I can''t believe Microsoft dropped a 109 page goldmine on Data Engineering, especially on a frontier level. No other frontier AI labs have ever shared this level of in-depth information on data engineering. This paper is the best I''ve seen so far. my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud MAI-Thinking-1: Building a Hill-Climbing Machine [Paper] https://www.alphaxiv.org/abs/mai-thinking-1 Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI This video is supported by the kind Patrons & YouTube Members: 🙏Spam Maj, Alex, Chris LeDoux,...'
resumo: 'O vídeo analisa o relatório técnico de 109 páginas da Microsoft sobre a série de modelos "MAI Thinking One", focando-se em como recolheram e prepararam os dados para treinar o modelo — descrevendo o processo de treino como uma "máquina de subir a montanha" (hill climbing). Aborda ainda descobertas sobre escalabilidade, nomeadamente como as misturas de dados não escalam de forma linear nem previsív...'
tags:
  - bycloud
  - bycloudai
  - MAI-thinking-1
  - 'MAI thinking 1'
  - 'microsoft llm'
  - 'microsoft AI'
  - 'microsoft new llm'
  - 'microsoft new model'
  - 'microsoft model'
  - 'Microsoft AI'
fontes:
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://www.alphaxiv.org/abs/mai-thinking-1'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://www.manimate.ai/'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

I can't believe Microsoft dropped a 109 page goldmine on Data Engineering, especially on a frontier level. No other frontier AI labs have ever shared this level of in-depth information on data engineering. This paper is the best I've seen so far.


my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud



MAI-Thinking-1: Building a Hill-Climbing Machine
[Paper] https://www.alphaxiv.org/abs/mai-thinking-1



Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI

This video is supported by the kind Patrons & YouTube Members: 
🙏Spam Maj, Alex, Chris LeDoux, DX Research Group, Poof N' Inu, Deagan, Robert Zawiasa, Ryszard Warzocha, Tobe2d, Louis Muk, Akkusativ, Kevin Tai, Mark Buckler, NO U, Tony Jimenez, Ângelo Fonseca, jiye, Anushka, Asad Dhamani, Binnie Yiu, Calvin Yan, Clayton Ford, Diego Silva, Etrotta, Gonzalo Fidalgo, Handenon, Hector, Jake Disco very, Michael Brenner, Nilly K, OlegWock, Daddy Wen, Shuhong Chen, Sid_Cipher, Stefan Lorenz, Sup, tantan assawade, Thipok Tham, Thomas Di Martino, Thomas Lin, Richárd Nagyfi, Paperboy, mika, Leo, Berhane-Meskel, Kadhai Pesalam, mayssam, Bill Mangrum, nyaa, Toru Mon, Lame Plane, Matej Macak, Len Mo, saylikhapekar, ZyanSheep, THEVIERAOS, Ricardo Raphael Corona-Moreno, superchordate


[Discord] https://discord.gg/NhJZGtH
[Twitter] https://twitter.com/bycloudai
[Patreon] https://www.patreon.com/bycloud
[Business Inquiries] bycloud@smoothmedia.co
[Other Inquiries] bycloudai@gmail.com
[Profile & Banner Art] https://twitter.com/pygm7
[Video Editor] @Booga04 
Manim Animations created with Manimate https://www.manimate.ai/
[Ko-fi] https://ko-fi.com/bycloudai
[Bitcoin (BTC)] 3JFMJQVGXNA2HJE5V9qCwLiqy6wHY9Vhdx
[Ethereum (ETH)] 0x3d784F55E0bE5f35c1566B2E014598C0f354f190
[Litecoin (LTC)] MGHnqALjyU2W6NuJSSW9fTWV4dcHfwHZd7
[Bitcoin Cash (BCH)] 1LkyGfzHxnSfqMF8tN7ZGDwUTyBB6vcii9
[Solana (SOL)] 6XyMCEdVhtxJQRjMKgUJaySL8cGoBPzzA2NPDMPfVkKN

## Transcrição

Once in a while, Microsoft would drop
something genuinely so interesting like
the quantum chip MyJoana and the LM 5
series, then returning to do some BS
like giving open claw full access to
your computer, making their own version
of Rabbit R1, or releasing a poop like
Windows 11. Which sometimes makes you
wonder, what would they look like if
they actually locked in? And this
relatively new model series called My
Thinking One that they released back in
early June is definitely one of those
sober moments where Microsoft suddenly
looks like a frontier lab again. While
the highlight is definitely not the
model's performance, the impressive part
I would say is actually the 109 page
technical report they have on My
Thinking One, which they refer to as
building a hill climbing machine. And
unlike a usual boring technical report
where people just flex about how big of
an MOE and how great their performance
is, Microsoft instead shares something
that has basically never been shared at
this scale, which is how they have
gathered the data to train My at a level
of detail you wouldn't expect data
engineering to involve. And they shared
this much information even though the
model is not open weights. And this does
not usually happen, especially for US
frontier AI labs. So, the first thing
that I would like to point out is how
they call the training process a hill
climbing machine. And the reasoning
behind this is simple. Frontier AI
making is not just one giant magic run,
but a loop that you repeat, improving
the data pipeline, training models at
different scales, measure what actually
improves, and use that winning recipe.
And this philosophy is the entire theme
of the paper. Which sounds obvious, of
course, but this approach is also not
for everyone. And what I mean by that is
it's an advantage to reserved for the
GPU rich. Like what do you mean they
just casually dropped a scaling section
that basically used three times the deep
CV3 compute budget just to prove a point
that caution is to how things really
scale is what makes this paper extremely
impressive. And it does actually pay off
because one of their most interesting
findings is that data mixtures do not
scale linearly or predictably. They
tested this with a code heavy mix and a
stem heavy mix. At a small scale, the
stem heavy mix looked better on stem
evals, which is exactly what you would
expect. But, when they scaled up to a
23B active model and trained for around
20 trillion tokens, the ranking flipped.
The code-heavy data mix ended up
performing better on the same STEM
E-val, which is honestly a very
horrifying result because in the past a
lot of intuitions for LM research have
been based on small-scale data ablations
that we assumed would be consistent when
you scale them up, especially if we're
things like data mixture that we thought
would be something much more
straightforward than finding a scaling
law for a, let's say, an architectural
design. Like, when we want to pre-train
a model on 30 trillion tokens and we
want it to be better at STEM, we would
naturally assume that if we give it a
larger portion of STEM data, it would
then be better at STEM, right? But, of
course, nothing will always follow what
you think when it comes to training a
model. As for the reason why this would
happen, the researchers' hypothesis is
that the STEM-heavy mix had some very
high-quality STEM sources, but those
sources were also more duplicated and
less diverse. So, at a smaller scale,
those tokens looked amazing as the
smaller model gets a very clean signal,
learns a bunch of STEM patterns quickly,
and the E-val goes down. But, at a
larger scale, the same advantage would
start to decay because the model has
already extracted most of the useful
information from that repeated data. In
other words, data set looked
high-quality, but it did not have enough
unique information to keep feeding a
much larger model over a much longer
run, leading to a weird scaling result.
Another issue is that even if you do all
these scaling experiments, you still
need to decide what hell you are
actually climbing because if you really
think about it, your training would
naturally be biased towards maxing the
benchmarks of your choice. So, tracking
the model's benchmark performance when
doing scaling experiments may not be the
best way. And this is where Microsoft
made another interesting choice. So,
instead of maxing public benchmarks like
MMLU, Human E-val, or whatever benchmark
is currently trending, they use
something called NLL E-vals for their
pre-training decisions. Short for
negative log likelihood, it measures how
surprised model is by the next token. If
the model assigns high probability to
the correct continuation, the NLL is
low, and if the model is confused and
assigns low probability to the correct
continuation, the NL is high, which
means low NL shows good understanding of
the underlying data distribution. So,
there's no need to have the model to
generate an answer and then checking if
it got it right, because not only you
would be measuring a sample from a
distribution, you would also be
introducing a bunch of extra noise that
has nothing to do with the actual
pre-training quality. Like, did the
model fail because it didn't know the
answer or did it fail because it sampled
a bad chain of thought? Or did it just
forget to generate the answer box? So,
they built around 40 internal NL
benchmarks across coding, STEM, math,
general knowledge, and multilingual data
to make sure the loss they are measuring
for the entire pre-training process is
accurate. As for the actual 30 trillion
pre-training data, they also clearly
outlined where and how they curated it.
While a good chunk of it came from web
HTML, PDFs, public GitHub code, books,
academic papers, news, and multilingual
text, the important part is that every
source is processed in-house from start
to finish. And the processing, of
course, sounds really boring. For web
data, they started with both a
proprietary crawler and common crawl,
then run HTML extraction, content
filters, exact, fuzzy, and cross-source
deduplication, and embedding generation.
They also stated that they did not use
open-source training data sets and
common machine learning repositories
like Hugging Face for the web data just
to avoid benchmark contamination. Not to
mention, web data has a lot of broken
HTML. On top of that, there's badly
formatted math, hidden tables, code
snippets, PDFs converted to garbage, and
the same article copied across the
internet. And they even built their own
AI content detection model to filter out
AI slop from the web corpus, too. For
STEM pages, they classify by topic,
educational value, and education level.
For PDFs, they use OCR and filtering,
then classify the documents again. For
GitHub, they don't scrape any
repositories, they have to process
94,000 public repositories, deduplicate
it, score their quality, filter
generated code, and just pretty much any
contamination for evaluation and
reasoning data. So, as boring and
repetitive as the deduplication process
sounds, the data mixture we just talked
about so shows just how important it is
as repeated data secretly changes your
training run. Without the strict
filtering, at small scale, this can make
the data set look high quality, but at
large scale, it becomes a trap where
it'll leave you unable to figure out why
the model doesn't scale properly. This
is why their data mixer result is so
important. So, their final 30 trillion
mix is mainly composed of coding data
sitting at 16.4 trillion training tokens
with high-quality math only around 300
billion unique tokens, but it is sampled
5.28 times more on average because it is
scarce and pretty valuable. Meanwhile,
web text and PDF data are sampled less
than once on average, meaning they don't
even use the full available pool of data
during that 30 trillion run. But, the
most surprising decision is that they
chose not to use synthetic data
generated by language models during
pre-training. This decision pretty much
shook the entire field. Most labs
nowadays use model-generated rephrasing,
reasoning traces, instruction data, code
explanation, or distilled outputs
somewhere in the pipeline. So, synthetic
data is a necessity. Especially right
now, there are already strong teacher
models, so why not use them to generate
more clean training data? Well, their
reasoning here is that capabilities
should be learned, not inherited. So,
everything was trained from scratch
through their RL climb with no exposure
to reasoning traces at all, which means
the model has to discover chain of
thought on its own. This decision has
been a hot topic in the research
community with many saying that by not
distilling to generate reasoning traces,
the model would not be able to close the
gaps for any long horizon tasks, which
is reflected in Mice's current benchmark
performance. On SweepBench Pro, it gets
52.8%, which is close to Claude Opus
4.6, but still behind models like GPT
5.4, Chinchilla K2.6, DeepMind Gopher 4,
and GLM 5.1. And on Terminal Bench 2.0,
it is much weaker than the top models.
In return, though, they do have stronger
control over what the model is and a
cleaner model lineage, which makes me
curious how far they will follow through
with this decision of no synthetic data.
On top of that, when the model improves
on Amy coding or suite tasks, that
improvement is not just the model
revealing a reasoning behavior that was
already baked in from distillation. It
is the model actually learning that
behavior during the climb. This is also
where Microsoft's interesting
post-training design choice comes in.
That is the use of self-distillation.
This is very different to learning from
another model as it is just picking
results from its own results. So, during
RL, the model generates rollouts, right?
Some rollouts are bad, but some are
good. So, instead of throwing them away,
Microsoft collects the successful traces
and uses them as supervised fine-tuning
data to stabilize or recover the model.
But, unlike modern methods like
on-policy distillation, which DeepSeek
and Mimo V2 use, they chose to
straight-up do supervised fine-tuning on
the data. So, no fancy online correction
and no complicated policy matching
objective. Just the model's own
successful rollouts as supervised data
and train the model to imitate the
behaviors it already discovered. On top
of that, instead of treating each RL run
as a fragile one-way climb, the
researchers turned the good rollouts
into reusable training data. So, every
good climbing attempt is saved and the
successful traces can be used to recover
performance and restart from a more
stable model. And their reasoning for
using supervised fine-tuning over
on-policy distillation is simply needing
a reliable way to checkpoint the
progress of an instable RL climb as they
are not trying to perfectly imitate the
distribution. And surprisingly, the
reasoning steps ramped up extremely fast
with most of the reasoning gain
happening around the first thousand
steps. On top of that, Agent Tech and
SWE climb was able to continue for much
longer. But, the reasoning did not
completely emerge out of nowhere,
either. So, before the first
self-distillation round, they set up a
prompt template that tells the assistant
to think first inside the thinking tags,
then answer inside the answer tag. So,
there is a format guardrail around it.
On top of that, the strong model does
not necessarily write longer chain of
thought. For STEM, weak models tend to
guess, pattern match, or brute force,
but strong models would verify candidate
solutions, find the invariance, and
become more skeptical of their own
reasoning. One example they show is an
IMI problem where the weak model
fabricates candidate values and commits
to a wrong answer, while the strong
model derives the candidate and then
checks the domain condition to remove
the invalid one. And my favorite part of
the paper actually just plays out like
Deep Six's aha moment where their strong
model literally got the wait, let's
re-examine, then test a small case, and
fixes his own logic, retracting its path
on its own. Aside from training, their
architecture design choice is rather
interesting, too. They have an MoE setup
for the model at 1 trillion parameters
with 35 billion parameter active,
sitting at eight out of 512 experts
active per token, but the surprising
part is they interleave MoE and dense
feedforward between layers. And this is
something we do not see very often.
Their thought process was that even
though MoE is efficient, it will also
create two problems. First is that every
MoE layer needs routing and all-to-all
communication when scaled across GPUs,
which becomes painful at scale. Keep in
mind they are not Deep Six, so they
don't have the option to optimize the
hell out of process because they feel
like it. Second, the model can start
relying too heavily on shared experts
where the shared expert becomes the safe
default path and overcrowd on that
expert, which causes the router experts
to specialize less cleanly. They argue
that the dense layers give every token a
stable or shared compute path, while the
MoE layers give the model sparse
specialist capacity. And according to
their ablation, this interleave design
had similar flops efficiency comparing
to having MoE in every layer, but has
better wall clock efficiency once real
training speed was included. The only
other model that also did something
interleaving MoE and dense feedforward
was Llama for Maverick, but with a major
difference that is it still has a large
shared expert. Microsoft's argument is
that once you interleave dense and MoE
layers, the dense layers already provide
that stable shared computation path, so
they remove the shared expert
completely. And to make the MoE part
cheaper, they also use latent MoE. In a
normal MoE layer, each token has a
hidden representation, and once the
router decides which experts should
process it, that activation has to be
sent across GPUs through all-to-all
communication, but all-to-all is
actually painfully slow. Because now,
the bottleneck is not just matrix
multiplication, but also moving a giant
hidden vector across the cluster to the
correct expert. So, latent MoE tries to
make the thing that's being moved much
smaller. Before dispatching the token to
experts, Microsoft applies a shared down
projection. So, instead of sending the
full hidden state through all-to-all,
they compress it into a smaller latent
representation first. Then, the experts
operate on that compressed
representation. After the experts
finish, the model projects the latent
representation back up to the original
hidden dimension. However, the router
still makes its routing decision based
on the original representation, not the
compressed one, which means the model is
not choosing experts from a degraded
hidden state. As for attention, they
interleave it at 5:1 ratio, which is a
bit more conservative than MeMo v2.5 Pro
that's also known to interleave sliding
window attention and global attention,
but at a 7:1 ratio. On top of that, my
sliding window attention is sitting at
512, which is four times larger than
MeMo. In the ablation study, MeMo did
show that 128 token sliding window
attention outperforms 512, but it seems
like Microsoft's reasoning is that
they had no reasoning at all. It was
probably chosen because it is a standard
sliding window attention number. On the
other hand, the global layers also use
NoP, meaning no positional embedding,
while the local layers still use RoPE.
This is a bit interesting because
usually you expect position encoding
everywhere, but the researchers found
that global NoP performs comparably
while being more efficient. And as you
can probably tell by now, efficiency is
the common theme for this paper. As most
of their ablation studies use a metric
called efficiency gain instead. So, if
EG is 1.3, that means the baseline would
need 30% more compute to match it. On
top of that, they don't just report EG
in flops, they also report EG in wall
clock time because a lot of ideas in AI
look efficient on paper, but once you
put them on real hardware, they are
slower because of communication, memory,
routing, or kernel overheads. So, this
is the main reason why they decided to
interleave dense feed forward and MoE.
So, they compared their interleave
design against two MoE every layer
variants, one with eight out of 384
experts active, and another with seven
routed experts plus one shared expert
out of 384. The version without a shared
expert was just worse. A weighted
average EG was 0.94 in flops and only
0.73 in wall clock time. The shared
expert version looked slightly better on
pure flops with 1.03 EG, meaning it was
around 3% better theoretically. However,
once they incorporated the actual
training time, it dropped to 0.82 EG,
which is really surprising, showing that
having no shared experts is actually
more efficient. On the other hand, for
expert sparsity, the story is really
interesting, too. Increasing from eight
out of 256 to eight out of 512 to eight
out of 1024 experts improved flops-based
EG, showing that the architecture
benefits from higher sparsity. But, they
still picked eight out of 512 for the
final model because the final choice had
to balance quality, training efficiency,
and inference efficiency, not just the
highest EG flops number. And besides
these architectural ablation studies,
they also talk about custom FPA gem and
quantization kernels, grouped gem,
Ulysses context parallelism, zero two
and zero three tradeoffs, activation
offloading, dropless MoE, deterministic
kernels, goodput, and MFU. I'll be
skipping those here because they're just
way too in-depth, but they did also try
to achieve deterministic kernels. It's
just that, unlike Deep Seek, they
weren't able to get the same amount of
efficiency as their implementation. But,
a surprising part that I want to
highlight is that Microsoft says they
added more than 20 infrastructure and
kernel optimizations just to keep every
generation above 20% MFU share for a
flops utilization, which measures how
efficient an AI model training run
utilizes a GPU's theoretical peak
computing power, showing just how
brutally inefficient Frontier MoE
training still is. They also reported
good put, which is also really rare.
Good put is basically how much of the
wall clock time is actually useful
training progress instead of being
wasted on crashes, restarts, checkpoint
stalls, recomputation, slow scheduling,
or MFU drops. And they reported that
their final pre-training run achieved a
90% good put on 8,000 GPUs, but even
there achieving 20% MFU is still one of
the largest hurdles for companies like
Microsoft. However, let's turn our eyes
to their loss graph. And as you can see,
it is super smooth with only a few
occasional spikes, showing that it is a
really well-executed training run.
Microsoft recently hired several leading
researchers from AI2, including key
members of the OLMo open model effort,
into its superintelligence team as AI2
has seen a wave of departures during a
shift in strategic direction. And one of
the few labs that consistently writes
good data engineering papers is AI2,
especially with the Tulu paper. So,
maybe the reason this paper reads so
cleanly and shares this much frontier
alpha is partly thanks to them. So,
overall, it is an extremely impressive
first-ever large-scale model released by
Microsoft. And even though it is closed
source, the amount of technical insights
they have shared regarding data
engineering and infrastructure
utilization is definitely a valuable
knowledge contribution to everyone. What
do you think? Let me know down in
comments. And if you want to learn more
about how LLMs work much more in depth
without being overwhelmed with math, you
should definitely check out my latest
project intuitive AI Academy, where it
contains an intuitive explanation of all
modern LLMs from the ground up,
including a lot of technical topics like
distillation, which I just mentioned in
this video. We cover everything from the
basics like the transformer
architecture, all the way to more
advanced topics like LoRA, mixture of
experts, and RLHF. And we also just
added a new advanced chapter on
optimizers, which will bring you all the
way from the classics to the current
frontier techniques. So, whether you're
a student, software dev, founder, or
just someone trying to pivot into AI,
intuitiveai.academy gives you one clean
place to build real technical intuition.
And you can use the code lock in for 35%
off on a yearly membership. And thank
you guys for watching. A big shout out
to Spam Maj, Chris Ladue, Deegan, Robert
Zaviasa, Marcelo Ferreira, Proof and
Inu, DX Research Group, Alex, Mit Was
Maker, and many others that support me
through Patreon or YouTube. Follow me on
Twitter if you haven't, and I'll see you
in the next one.
