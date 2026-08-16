---
titulo: "DSpark: DeepSeek-V4's Insane Compute Optimization Explained"
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-07-20'
url: 'https://www.youtube.com/watch?v=Sl0XXm35JMo'
thumbnail: 'https://i.ytimg.com/vi/Sl0XXm35JMo/maxresdefault.jpg'
descricao: 'Check out Make and get a 1-month free Pro Plan with 10,000 operations & no credit card required! https://www.make.com/en?&promo=bycloud&utm_source=youtube&utm_medium=influencer&utm_campaign=bycloud-video-integration-jul26 They basically punched a hole in the ceiling of speculative decoding, and dropped DSpark. Never have I seen a lab sharing and even min-maxing inference this hard, and it is incredibly fascinating to see how they are approaching it not just from theory, but also from a practical serving perspective. my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud D...'
resumo: 'O vídeo analisa a nova implementação de inferência do DeepSeek-V4, explicada num relatório técnico de 33 páginas, e como esta consegue aumentar o débito (throughput) em produção em cerca de 50%, chegando a 660% em cenários mais exigentes. Aborda ainda o trabalho de código aberto da DeepSeek (bibliotecas de baixo nível como o DeepGEMM e o sistema de ficheiros 3FS), pensado sobretudo para grandes cl...'
tags:
  - bycloud
  - bycloudai
  - Dspark
  - 'deepseek spark'
  - 'deepseek research'
  - 'dspark deepseek'
  - 'deepseek dspark'
  - 'deepseek dspark explained'
  - 'what is deepseek dspark'
  - 'what is speculative decoding'
  - 'speculative decoding'
  - 'deepseek speculative decoding'
fontes:
  - 'https://www.make.com/en?&promo=bycloud&utm_source=youtube&utm_medium=influencer&utm_campaign=bycloud-video-integration-jul26'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://www.alphaxiv.org/abs/2607.05147'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://www.manimate.ai/'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

Check out Make and get a 1-month free Pro Plan with 10,000 operations & no credit card required! https://www.make.com/en?&promo=bycloud&utm_source=youtube&utm_medium=influencer&utm_campaign=bycloud-video-integration-jul26 


They basically punched a hole in the ceiling of speculative decoding, and dropped DSpark. Never have I seen a lab sharing and even min-maxing inference this hard, and it is incredibly fascinating to see how they are approaching it not just from theory, but also from a practical serving perspective. 


my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud

DSpark Paper
[Paper] https://www.alphaxiv.org/abs/2607.05147


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

## Transcrição

I still cannot believe the fact that
after spending a total of 45 minutes
going through a staggering 58 page
report explaining the latest DeepSpeed 4
release, which includes its new
attention mechanism all the way to its
new infrastructure implementations, they
still have something up their sleeves
ready to share that is related to their
development of V4. And when I say share,
it's not some simple features that can
be explained in a Twitter thread. I am
talking about an additional 33 page
technical report outlining their insane
inference implementation, which is
capable of giving V4 50% higher
throughput in production and even
hitting 660% higher in stricter
settings. I don't know how they have
this insane capacity to publish another
research paper right after an already
huge technical report a few months back.
But by now, you would have probably
realized that all the things DeepSpeed
open-sourced so far are actually not
meant for us GPU pores. From open-source
low-level libraries like Deep Gem to
their distributed file system 3FS,
notice how none of these are actually
that beneficial for us everyday
individuals. Like all these libraries
are meant for people that have a
million-dollar GPU cluster setup and
would need to transfer a few terabytes
of information in just a few seconds.
However, not being able to use it
doesn't mean we cannot appreciate the
work, right? It's like you don't have to
draw like Monet to appreciate their
paintings. And DeepSpeed sharing their
knowledge feels just like watching a
master at work. So in today's video, let
us dive into how DeepSpeed found ways to
take LLM inferencing to a speed that no
other models have ever come close out of
the box. But before we dive into it, if
you have actually tried to build AI
workflows for your work or projects, you
would know the pain is usually not
trying to prompt engineer the model, but
everything that you have to gather and
connect them with logic from all kinds
of places. So what if instead of you
manually moving outputs between apps,
there is an app that helps you bridge
all these things together. This is where
today's sponsor Make comes in. Make is a
visual-first platform for actually
building complex AI automations in real
time. They support up to 3,000 apps with
internal logic modules or tools for you
to orchestrate your workflow. So, here I
built a scenario of a feedback form for
my platform intuitive.ai.academy. I
connect a make to that feedback form
which will automatically scrape the form
every week and observe the responses I
get. For every feedback that a user
enters, it'll automatically be sent to
my Discord server's development channel
to keep me updated, which is the first
route. The second route will send the
user a referral coupon when they give
feedback on how they will recommend the
platform to their friends. And there's
this calendar that will increment for
every submission. It will follow that
counter and pull from the corresponding
row in the Google Sheets with a unique
coupons. They use ChatGPT to format an
email and send it to the user. The third
route pulls everyone that gives the app
rating below two have an AI agent look
at their feedback with reference to the
content library, create a Calendly link,
and send an email asking for potential
improvements and if they're willing to
do an user interview. So, if you also
want to build real AI systems easily
just like this, you can get a one-month
free pro plan with 10,000 operations, no
credit card required, through the link
down in the description. And thank you
Make for sponsoring this video. Anyways,
new release from DeepSeek is called Deep
Spec. It's a GitHub repository
containing a full-stack codebase for
training and evaluating speculative
decoding algorithms, including the paper
D Spark, which is the main character
today. And I wasn't really joking about
this release being an art form. The
readme literally says the default Qwen 3
4B target cache would take around 38
terabytes. 38 terabytes,
just for a cache, by the way. And the
default setup they assumed is an eight
GPU node. So, that is around $10,000
worth of SSD storage and a $100,000 GPU
setup if it's made out of A100s. But
this doesn't make D Spark any less
interesting just because it is not
usable by everyone. Like, what do you
mean this setup can get 50% higher
throughput, not from GPU, not from
hardware acceleration, but by generating
smarter. So, to understand why D Spark
is so powerful, we need to first
understand the very basic problem with
LLM
As all modern LMs reads the context and
generate text one token at a time, then
reads the context plus that new token to
generate the next one, it doesn't really
matter how powerful the model is, the
generation process is basically stuck on
this sequential autoregressive process.
But this gets worse as models become
more agentic and reasoning heavy.
Because reasoning models would straight
up need to generate a lot more tokens to
do tasks that are much more difficult.
So the better the model gets at
thinking, the more painful the decoding
bottleneck becomes. However, there is a
clever loophole researchers discovered
that can speed up this process. They
made an observation that generating
future tokens is sequential, but
checking a pre-existing sequence of
future tokens does not have to be
sequential in the same way. Because when
an LM generates text normally, it has to
sample token one before it can know what
token two should condition on. So
generation is sequential because the
input itself keeps changing one token at
a time. But if someone, like a
lightweight draft model that can
generate super fast, already gives the
model a potential continuation of the
future tokens, then the model does not
need to generate those tokens one by
one. It can take the entire proposed
continuation as input and compute the
probability of every next token
transition in the single forward pass.
So if a draft model says the next eight
tokens are a b c d e f g h, the target
model can check all of these positions
together. For instance, it'll be able to
compute these in parallel. Given the
original context, would it accept a?
Given the context plus a, would it
accept b? Given the context plus a and
b, would it accept c? And so on. All of
these token positions are still causally
masked, so each position only sees the
tokens before it. But inside a
transformer forward pass, all positions
in the sequence are processed at the
same time, which means the causal
dependency is handled by the attention
mask, not by running the model
separately and sample for every token.
This implies that verification can be
parallelized and it is much cheaper than
generating those tokens normally on the
big model. When the draft model
generates all the future tokens fast and
cheaply, so we went from if you wanted
to generate eight tokens
autoregressively, you would need eight
separate forward passes through the
target model to if a draft model
proposes those eight tokens first, the
target model can verify them with
roughly one forward pass over the
extended sequence. And if the draft
model is super accurate, then you
basically only need one forward pass
from the big model and eight forward
passes from the draft model to reproduce
the big model's output. This naive setup
is called speculative decoding. I have a
more in-depth video covering it you can
check it out. And the 38 terabytes of
target cash I mentioned earlier is
basically a huge precomputed cash of the
target models activations or output
distributions, which is used to train
the draft model without repeatedly
re-running the full target model from
scratch. So, the idea sounds fantastical
in a way that it feels like there are so
many things it needs to get right for it
to be able to generate fast, which means
a good approach basically needs to solve
three annoying parts of speculative
decoding at the same time. First, the
draft model has to be cheap and fast. A
second, that the draft tokens have to be
accurate enough to survive verification.
And third, the system cannot waste too
much big model compute verifying tokens
that are obviously going to get
rejected. If any of these three things
fail, the whole speedup would start to
collapse. And to understand these part
better, we need to first look at the
older approaches. The most basic draft
model is just a smaller model. So, if
your target model is huge, you use a
tiny model to guess its future tokens.
But the issue is that this tiny model is
trying to imitate the big model from the
outside. So, it does not directly see
what the big model is thinking
internally. So, there are methods like
multi-token prediction and Eagle that
make the drafter much more connected to
the target model. What they do is
instead of using a completely separate
small model, they attach a lightweight
draft module to the main model and let
it consume the target model's hidden
representations to make future
predictions. And this makes a lot of
sense because the target model's hidden
state already contains rich information
about what token could come next. So,
the The model does not need to do all
the reasoning from scratch, and in fact,
the DeepSeek is arguably the first lab
to actually use MTP at frontier level.
So, DeepSpark is not simply an
alternative to MTP, it is an improvement
built on top of MTP, which makes this
research even better. But, what is there
to improve about MTP? Well, it still has
one major limitation. The draft model
still generates sequentially. If you
want eight draft tokens, you usually
need to run eight drafting steps, and
the drafting cost grows with the number
of draft tokens. So, the more ambitious
your speculative block it becomes, the
more the drafter itself starts eating
into the speed up. So, the speed up
actually decreases the longer you
generate, and this is the first side of
the tradeoff. Then, we have methods like
DeepFlash, where it goes in the opposite
direction. Instead of drafting future
tokens one by one, it uses a parallel
block generation approach inspired by
diffusion language models. So, it can
propose all the draft tokens in one
forward pass, and this is much more
GPU-friendly, because you only pay for
the draft computation once, and you get
a whole block of future tokens. However,
we now have the opposite problem. If all
future positions are predicted in
parallel, then later tokens do not
actually know what earlier tokens were
sampled. If not enough iterations were
spent on generating, each token could be
predicted to independently, producing
continuations that don't make sense. On
top of that, the later positions become
less reliable because they are missing
the dependency on the actual prefix. So,
DeepSeek's DeepSpark came in as an
in-between of the two that takes the
best of both worlds, and they call this
approach a semi-autoregressive drafting.
First, DeepSpark uses a heavy parallel
backbone to produce a whole block of
draft representations quickly, similar
to the DeepFlash advantage where you get
the future block in one parallel pass.
Then, DeepSpark adds a tiny sequential
module on top. This tiny sequential
module walks through the draft and
biases each token towards what came
before, which means we have a heavy
parallel module proposing the
continuation, and a lightweight
sequential editor that fixes the local
coherence. And because the parallel
backbone already captures the broader
context, the sequential module does not
need to be a full transformer at every
position. It can be much cheaper. The
paper tests lightweight sequential
designs like an RNN head, with the main
version using a Markov head. What the
Markov head does here is it basically
makes the next token distribution biased
to based on the previous token. So, if
the previous token is off, it can push
the next token towards course. If the
previous token is no, it can push the
next token towards problem. And the
reason why he can use a Markov head here
is that this is a simpler task than
generating a full next token. So, less
complexity and compute is needed, which
means not only does D-Spark get the fast
block generation behavior of D-Flash, it
also recovers some of the consistency of
MTP. And in their results, the
improvements are really impressive.
Across 203B, 8B, and 14B, D-Spark
improves accepted length by around 30%
over Eagle 3 and around 16% to 18% over
D-Flash. Absolutely mugging any previous
speculative decoding methods. But keep
in mind, the acceptance rate is not
everything. In real production serving,
the problem is just not whether the
draft model can produce good tokens. The
problem is also whether those tokens are
worth verifying right now. And this is
where D-Spark shines much more than just
a better drafter. It actually comes with
something called the confidence
scheduled verification, which is
probably the most deep seek part of the
whole paper. So, imagine this. If you
put speculative decoding in the clean
offline benchmark and a draft model
generates eight tokens, then it makes
sense that all of those eight tokens
would be verified, right? But in a real
serving system, this is actually not
ideal because not every request has the
same predictability. A coding request
might have a very structured
continuation, so the draft model can
constantly predict a longer prefix. But
an open-ended chat response might branch
into many possible continuations,
meaning the later draft tokens are much
more likely to get rejected. So, using
the same fixed draft length for every
request it feels a bit wrong. But it
gets even more complicated when you
consider the hardware side of things.
So, when the GPUs are lightly loaded,
verifying extra draft tokens is not that
expensive. But, when the serving system
is already packed with requests, every
low confidence verification token is
stealing capacity from another user. And
if that token gets rejected, you
basically wasted precious target model
compute on a suffix that was going to
die anyway. So, the problem shifts to
how many tokens should the target model
verify under the current server load.
DeepSeek approaches this by giving every
drafted token a confidence score. This
confidence score estimates how likely
each token is going to survive the
verification process, assuming all
previous draft tokens were accepted. So,
if token one has high confidence, token
two has high confidence, token three
starts dropping, and token four looks
doomed, the system can just stop
verifying at token three, dropping
everything that comes after token four.
So, if there are in total 50 tokens, you
pretty much saved the compute needed for
verifying an additional 46 tokens. As
speculative decoding normally has this
hidden failure mode where you drafted
too many tokens, and the target model
wastes a lot of time verifying a bunch
of low quality suffix tokens. So, even
though verification can be done in
parallel, it still doesn't mean the
compute is free. And this saving really
shows when a lot of LLM use cases now
are long horizon tasks on top of
extremely large serving demand. But,
even this min-maxing is still not enough
because a token with 60% survival
probability might still be worth
verifying when the GPU is underloaded.
same token might be absolutely not worth
verifying when the system is already
maxed out. So, as greedy as DeepSeek is
at min-maxing compute, they did not stop
here. And this is where the
hardware-aware scheduler comes in.
DeepSeek incorporates the current
serving load to decide how aggressively
it should verify drafted tokens. So, if
the server is lightly loaded, it can
afford to verify a longer prefix because
the extra verification tokens are not
really blocking much. But, if the server
is heavily loaded, it becomes much
stricter and only verifies the high
confidence prefix. So, the same draft
model can behave differently depending
on the actual GPU workload. And figure
eight from the paper shows this exact
behavior in production. As concurrency
rises, DeepSpark automatically reduces
the verification budget per request,
preventing the system from wasting batch
capacity on low value draft tokens. This
also suggests that benchmarks are not
properly reflecting production
inference, too. Especially with how
messy and inconsistent user requests can
be from time to time. Which brings us to
the key idea of this paper, treating
speculative decoding as a dynamic
serving policy. Not only is DeepSpark a
better speculative decoding approach, it
is also workload aware. But, as you
would imagine, the implementation of
DeepSpark would definitely be a
nightmare fuel. With how LM serving
engines are heavily optimized around
batching, the extreme DeepSpark dynamics
makes efficiency optimization so much
harder and can also easily erase the
speed up that was present before this
method was in place. So, that's why they
wrote the library DeepSpec. And under no
particular reason that is directly
beneficial to them, open-sourcing it
along with the DeepSpark paper. And all
they want is to foster collective
advancement within the open-source
community. How can you not love this
company? So, when all of this comes
together, the gains are kind of absurd.
On DeepSee V4 Flash, DeepSpark gets
around 51% higher aggregate throughput,
which measures the total number of
tokens generated across all users per
second, maintaining at least 80 tokens
per second per user. And under a
stricter 120 tokens per second setting
where the system is required to keep
each user's generation speed at or above
120 tokens per second, the older MTP-1
baseline completely hits a wall as it
starts to spend too much target model
compute verifying low quality draft
tokens that will eventually get
rejected, which causes it unable to
maintain both speed guarantee and high
throughput at the same time. DeepSpark,
by contrast, avoids this collapse by
trimming verification to only the
high-confidence prefix and reaches up to
661%
higher throughput than the collapsed
baseline. On Deep Seek V4 Pro, it gets
around 52% higher throughput at 35
tokens per second and up to 406% higher
throughput under the stricter serving
conditions that we just talked about.
And at matched aggregate throughput, a
single user generation speed improves by
around 57% to 85%. So, if you are
serving a Frontier model at scale, 50%
higher throughput is a ridiculous amount
of saved hardware as you're literally
using fewer GPUs for the same traffic.
This completely changed the serving
parade of Frontier, which is the set of
best possible trade-offs between
competing goals like speed and
throughput where improving one would
necessarily make the other worse. While
this optimization doesn't really
automatically translate to other systems
right away as every model has different
specs and every server has a different
setup, the release of Deep Seek would
still be a huge parallel that improves
serving not just for Deep Seek models,
but also other open weights. And that is
the blessing of the whale. What do you
think about Deep Spark? Let me know down
in the comments.
