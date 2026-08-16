---
titulo: 'Kimi K3: How Did Open Weights LLM Catch Up To Closed Source?'
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-07-29'
url: 'https://www.youtube.com/watch?v=g683I1-4MKE'
thumbnail: 'https://i.ytimg.com/vi/g683I1-4MKE/maxresdefault.jpg'
descricao: 'Need high quality cloud GPUs? Check out Verda now, and use the code BYCLOUD-50 to get $50 of compute credits for just $5! https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=deepseekv4 The 6 months gap between open source and closed source might not exist now. Moonshot AI''s latest Kimi K3 just topped the LLM leaderboard, coming in as the 3rd best model in the world, while being open weights. In this video, I will be breaking down how they have achieve this insane feat. my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud Kimi K...'
resumo: 'O vídeo analisa o Kimi K3 da Moonshot AI, um modelo multimodal de pesos abertos com 2,8 biliões de parâmetros, e explica como fecha a distância face aos modelos fechados. Aborda as escolhas de arquitetura e as técnicas usadas para escalar o treino até ao nível da fronteira.'
tags:
  - bycloud
  - bycloudai
  - 'kimi k3'
  - 'kimi k3 explained'
  - kda
  - 'kimi delta attention'
  - 'kimi delta attention explained'
  - moonEP
  - 'kimi attention'
  - 'kimi k3 local'
  - 'kimi k3 vs claude'
  - 'kimi k3 vs chatgpt'
  - 'kimi k3 tutorial'
  - 'kimi k3 test'
  - 'kimi k3 breakdown'
  - 'what is kimi k3'
  - 'what is kimi'
  - 'kimi moonshot'
  - 'moonshot ai'
  - 'kimi ai'
  - 'kimi ai review'
  - 'kimi k3 review'
  - 'is kimi k3 good'
fontes:
  - 'https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=deepseekv4'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://www.alphaxiv.org/abs/2607.24653'
  - 'https://github.com/moonshotAI/moonep'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://x.com/miyashita_03'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

Need high quality cloud GPUs? Check out Verda now, and use the code BYCLOUD-50 to get $50 of compute credits for just $5! 
https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=deepseekv4


The 6 months gap between open source and closed source might not exist now. Moonshot AI's latest Kimi K3 just topped the LLM leaderboard, coming in as the 3rd best model in the world, while being open weights. In this video, I will be breaking down how they have achieve this insane feat. 


my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud


Kimi K3 Technical Report 
[Paper] https://www.alphaxiv.org/abs/2607.24653

MoonEP
[GitHub] https://github.com/moonshotAI/moonep



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
Manim Animations created by https://x.com/miyashita_03
[Ko-fi] https://ko-fi.com/bycloudai

## Transcrição

Just a few months ago, we thought
open-source models were three to six
months behind closed-source models, or
at least I was Deep Seek's reasonable
estimate in its V4 paper. But shortly
after, this new model called Kimi K3
just blew this assumption completely out
of the water. This release from Winch AI
closed the gap that we once thought
would be impossible for open labs to
overcome. And can you believe that they
just casually dropped a state-of-the-art
multimodal open weights model that is
near equivalent to Claude's Fable 5 and
even beating it on some benchmarks by
margin. Clearly moonshotting single
training runs and beating
trillion-dollar companies. While the US
is busy running the narrative about
Anthropic releasing the Fable AI super
weapon, Chinese labs kind of just
dropped this monster of a model into the
wild by open-sourcing it and we're like,
"Yep, you guys can deal with it." I
think the most crazy thing about this
model is not just its performance, but
also the immense size of this model. It
has a staggering 2.8 trillion
parameters, which is 75% more parameters
than the previous largest open-source
model that is Deep Seek V4. And can you
believe the fact that the third best
model in the world right now is open
weights? So in today's video, let us
find out how exactly they made this
insanely powerful model, what new AI
architecture designs they used, and how
they have successfully scaled up the
model to compete at the frontier level.
And before I dive into it, as frontier
open weights are getting progressively
better and more useful, it is getting
more and more appealing to host or even
train your models on these weights to
customize your experience, right? And
for that, you would need top-tier cloud
GPU server that is specialized at
running or training LLMs like today's
sponsor Verta, which is a full-stack AI
cloud built for the entire model life
cycle from GPU instances and instant
clusters to serverless containers.
Unlike general-purpose cloud providers
trying to support AI compute like an
afterthought, Verta is built
specifically for AI workloads from toy
experiments all the way to large-scale
training, high-throughput inference, and
agentic AI serving. Built by people who
also work with actual AI systems, their
infrastructure is designed around how AI
teams train, serve, and deploy models.
On the hardware side, Verta gives you
access to powerful Nvidia GPUs like
B300s along with NVLink, InfiniBand, and
fast NVMe storage for workloads where
throughput and interconnect performance
really matter. They're also an official
Nvidia preferred partner, which means
closer alignment with Nvidia's latest
hardware roadmap. Verta has already
opened early access for Nvidia GB300
NVL72 Blackwell Ultra Clusters, which is
some of the most powerful AI systems
available today. For smaller
experiments, you can spin up on-demand
GPU instances or use spot instances at
up to three times lower pricing with a
trade-off that capacity can be
reclaimed, so they're the best for
interruptible workloads. And if you're
doing serious training or fine-tuning,
Verta also offers instant clusters with
self-service access to 16 to 128 GPUs
connected through InfiniBand. That's
exactly the kind of setup you want when
multi-GPU performance actually matters.
Then, when your model is ready to
deploy, you can move into production
with auto-scaling serverless containers,
making the path from experiments to
inference a lot less painful. So,
whether you're experimenting, building
agentic AI systems, fine-tuning models,
or deploying inference at scale, Verta
gives you AI infrastructure built for
speed, power, and performance without
the usual scaling chaos. So, check out
Verta now using the link down in the
description and use code bycloud50,
which provides $50 worth of compute
credit for just five bucks. And thanks
again to Verta for sponsoring this
video. Anyways, by the time that this
video is out, I'm pretty sure most of
you guys are already kind of familiar
with Gemini K3's performance, so I'll
just talk about it briefly. Gemini K3 is
basically on the same level with GPT-3.5
X High for coding agents, number one on
front-end coding, number three on Deep
Seek, which is a private coding
benchmark, on par with Claude Fable on
Terminal Bench 2.1, not to mention it
achieved a huge jump in token efficiency
compared to its previous release,
beating the Claude models by at least
one fold, and sits just slightly behind
GPT-3.5.6. It also achieved second in
realistic business workflows. And all in
all, you can just tell that they are
built fully for coding or long-horizon
tasks. But I think the most impressive
thing about this release is how we are
past the stage of just showing AI
writing its own training code or its
kernel as a showcase. Because this time,
they have their model design its own
hardware chip for itself. So, in a
single 48-hour autonomous run, K3 built,
optimized, and verified the chip using
open-source tools on a 9-gate
45-nanometer library. And within 4 mm
squared, the chip Kimi K3 designed and
packed 1.46 million standard cells,
0.277
MB of SRAM, and an 8-bit 4-MAC array
with fused dequantization. And it was
able to achieve 8,700 tokens per second
decode throughput in simulation serving
a nano model. But, I think the clearest
sign of just how frontier this model is
comes from its long context reasoning
performance. It completely topped the
benchmark despite using a new attention
mechanism developed in-house while still
remaining relatively cheap, which is at
least three times cheaper than Fable 5.
So, yeah, I take back what I said about
DeepSeek being the only open lab capable
of designing entirely new attention
mechanisms for frontier models. Because
this attention mechanism, called KDA,
which is short for Kimi Delta Attention,
has actually matured into a reliable
attention variant, as you can see from
the model's performance. And to
understand why KDA is so impressive, we
first need to look at what it is doing
under the hood. With standard softmax
attention, every token can directly
compare itself against the previous
tokens in the context, right? This is
extremely expressive because the model
can retrieve a specific piece of
information directly from any word in
the sequence. But, the cost grows
quadratically with context length, and
this is already expensive for a normal
conversation. So, for coding agents that
might process a huge code repository,
repeated tool calls, or just a very long
reasoning trajectory, the attention cost
becomes one of the main bottlenecks. And
this is why most models now look towards
alternatives like sparse attention,
which DeepSeek is using for V4, or
linear attention, which is what KDA is
based on. So, instead of retaining every
previous key and value individually,
linear attention compresses the sequence
into a fixed size recurrent state, which
means when a new token arrives, the
model updates the state and then reads
from it. So, the memory needed for each
linear attention layer does not keep
increasing with the context length in
the same way as a regular KV cache.
However, the issue is that linear
attention has historically just been a
terrible mechanism. You can see my full
breakdown in this older video, but in
short, since the entire context is
compressed into a finite state,
different key-value associations can
interfere with each other. The model
also loses the ability to precisely
retrieve every individual token from the
original sequence. So, its prior forms
are not that appealing to use since it
often underperforms full attention even
on short context language modeling. I
would say its efficiency advantage is
the only thing that kept it relevant.
But, KDA is not the standard linear
attention we know of. It is built on top
of the delta rule. In basic linear
attention, every new key-value pair is
continuously added into the recurrent
state. But, the delta rule instead
checks what the value the current state
already predicts for that key. It then
compares that prediction against the new
value and updates the state using the
difference. So, the update is based on
the error or the delta between what the
memory currently returns and what it
should return. Hence, the name delta
attention. But, even with the delta
rule, there is still another problem.
Some information would still become
outdated. So, Gated Delta Net, which KDA
is based on, adds a forget gate that
controls how much of the previous state
should remain before the new update is
written. The only issue is that Gated
Delta Net uses one forgetting rate for
an entire attention head. So, every
feature channel inside that head is
decayed together even though they could
be store information with completely
different useful lifespans. And you can
probably guess by now the main change in
KDA is surprisingly straightforward.
Instead of giving the entire head one
scalar forget gate, KDA gives every
channel its own independent decay value,
which means one channel can rapidly
remove temporary information while
another channel can preserve its state
over a much longer sequence. The model
can therefore control the memory
lifetime at the feature level instead of
the head level, giving the fixed-size
recurrent state much more capacity to
separate short-term and long-term
information without making the state
itself larger. To sum that up simply,
KDA is just the same compression
mechanism, but it stores and organizes
the compression data a lot better than
the standard linear attention. However,
it's not like KDA has magically solved
every problem that linear attention has.
It cannot guarantee the same exact
retrieval ability as full attention
because the original keys and values are
no longer all available. And that is a
huge problem. So, just like every other
modern linear attention model, they had
to make a hybrid attention architecture
where they interleave three KDA layers
with one full MLA layer. According to
their ablations, this three-to-one ratio
gave them the best trade-off between
model quality and decoding throughput,
with them basically reducing KV cache
usage by up to 75% since only one out of
four attention layers need to store the
conventional full attention cache while
achieving up to six times decoding
throughput for a 1 million context
compared to the standard attention
baseline. These results actually came
from their first KDA paper published in
November last year called Chimera
Linear, where they were experimenting on
a 48 billion parameter MoE with 3
billion active parameters trained on 1.4
trillion tokens. What's even crazier is
that Chimera Linear did not just match
the full attention model in their paper,
it actually beat standard attention. And
not just on simple performance
benchmarks like MMLU Pro, where Chimera
Linear scored nearly four points higher
than MLA. On the 128,000 long context
benchmark called Ruler, Chimera Linear
also beat it by three points while
getting around four times the decoding
acceleration. Then, on 1 million token
context, its time per output token
dropped from 11.48 milliseconds to 1.84
milliseconds, giving a 6.3 times
decoding speed up. And the quality gain
also appeared during RL2, which made KDA
the perfect candidate for scaling up,
which brought us to this legendary
release called Chimera K3 that is based
on KDA. Another key design choice
they've made here is definitely
replacing the standard residual stream
with something called attention
residuals. I have a more in-depth video
about it, but in short, attention
residuals lets each layer use softmax
attention over earlier layer outputs.
So, instead of receiving the same fixed
sum of every previous computation, a
layer can selectively assign more weight
to whichever earlier representations are
actually useful for the current token.
You can think of it like applying
attention across the model staff rather
than only across a token sequence,
providing stronger learning dynamics for
the model. On the other hand, there is
also the ridiculous scale of the model
itself. This is the first ever 2.8
trillion open weights that has ever
existed. Before this, I'm pretty sure
there are only four trillion class
models, two of them are 1.6 trillion and
two that are around 1 trillion. So, this
model size is definitely a sight of its
own. It of course uses MoE with 896
experts that while activating only 16 of
them for each token. This is extremely
sparse, but not as sparse as GPT- 4.
But, to make an MoE this sparse
practically, especially at this model
size, they use something called a stable
latent MoE which is a pretty common
design nowadays. So, in a normal MoE,
each token has to send its full hidden
state to whichever GPU hosts the
selected expert. And when you have
hundreds of experts, moving all that
data becomes expensive, right? So, what
latent MoE does is it compresses the
hidden state before sending it to the
expert, then expands it back afterwards.
So, K3 moves much less data between
GPUs. And the stable part of this
basically just keeps the routing
balanced so a few experts do not become
overloaded while others sit unused. On
the other hand, we did get some pretty
interesting demos from them, especially
this one where they had Kimi K3 optimize
its own GPU kernel for attention
residuals. So, Moonshot AI's researchers
straight up gave it the production-sized
attention residuals kernel used by the
model, then allowing K3 to work on it
autonomously for up to 24 hours,
including repeatedly profiling the
kernel, changing the implementation,
benchmarking it, and continuing from the
results. After around 15 hours of
non-stop iterations, it designed a new
two-face algorithm, fused multiple
operations together without changing the
numerical results, and reduced the
operation time from 283.6
milliseconds down to 114.4
milliseconds, which is almost a 60%
speed up from their original kernel.
With its final performance being close
to Claude Fable 5 and achieving roughly
the same result trends across all other
kernel benchmarks, Kimi K3 is actually
just an incredible model. Another
notable aspect of Kimi K3 is its
top-tier visual-related generations.
From generating HTMLs like websites and
landing pages, coding 3D games and game
engines, to making a complete slide deck
for research and infographic-style
presentations, that
I'm just speechless. Like, these are
just way too good. Not only that, it can
also generate three blue one brown-style
visuals, too, using Manim. So, coding
visualizations is definitely its strong
suit. However, Kimi is also not perfect.
In fact, they released blog sets of
themselves, and I just love how
transparent and humble they are with it.
Number one is how Kimi K3 needs its
thinking history fully intact in order
to function well. This might be related
to how strong the RL the model and the
thinking process plays a key role in
helping the model improve its
performance. Number two is how it will
start assuming things if the user's
intention is unclear because the model
is trained specifically for long horizon
and autonomous tasks. This is a very
interesting behavior, and one that I did
not think was going to backfire when you
focus on training something to be
autonomous. And lastly, they mentioned
how despite being a highly competitive
model overall, K3 nonetheless exhibits a
noticeable gap in user experience
compared with Claude Fable 5 and GPT 5.6
Soul. And this is rather surprising
because it shows that they are admitting
there exists a gap in eval for vibes or
at least the user experience compared to
the proprietary Frontier, which is a
rather fascinating point for them to
bring up. And as of July 27th, they have
fully open sourced the weights a company
with 47 page technical report sharing
more in-depth details about the model.
First of all, this model has 2.5 times
more scaling efficiency than their
previous Gemini K2 series thanks to all
the new architectural advances, refined
data, and training recipes. And the
report shared that K3's post-training
was also utilizing multi-teacher on
positive distillation just like most of
the latest open weights. This means they
trained nine specialized models across
coding, general agents, and surprisingly
different reasoning efforts ranging from
low, high, and maximum. They then merged
all nine back into one model allowing K3
to dynamically scale how much reasoning
it uses. Another clever thing they did
is they trained it across many different
agent environments that could imitate
Gemini code, claw code, codex, open
claw, and Hermes. So, rather than
over-fitting to one tool format or agent
harness, K3 is made to be adaptable
across them. Its visual capabilities
were also trained natively from the
beginning. Instead of attaching an
existing SigLip vision encoder
afterward, Moonshot trained Moon ViT V2
completely from scratch alongside the
language model using next token
prediction. And surprisingly, this was
much more stable and still matched the
pre-trained vision baseline. The million
token context was not achieved by simply
changing a rope setting either. K3 uses
no explicit positional encoding and was
progressively trained from 8K to 64K,
then 256K, and finally 1 million tokens
including synthetic tasks where the
required information was intentionally
scattered across the entire context. To
make inference more efficient, they also
designed post-training around actual
deployment just like what Deep CV4 did.
K3 uses quantize aware training with
MXFP4 expert weights and MXFPA
activations while its pre-training
prediction layer was converted into an
Eagle 3 speculative decoding model that
was directly trained to maximize how
often its drafted tokens would be
accepted. And lastly, they also
open-sourced infrastructure codes just
like Deep Seek. Their Moon EP system
dynamically replicated overload experts,
so every accelerator received the same
amount of work while their agentic RL
system could suspend and resume
long-running micro VM environments
without losing their state. This is a
huge improvement over Deep Seek's Deep
EP, and that was already largely adapted
in the industry. So, K3 was not really
just one isolated breakthrough. It's a
bold, deliberate, and ambitious killing
run that not only tries to make the
model as performative as possible, but
also as cheap as you can run it. And
funnily enough, just like how badly
Chinese AI labs are export controlled,
which caused them to be kind of GPU
poor, they had to pause new subscription
sign-ups just to concentrate the compute
for existing users. Not only that, they
also had to make different usage type
subscriptions to better allocate GPUs
for dedicated power users versus people
that use Kimi more casually. But yeah,
this Kimi K3 release is just incredible.
It got the frontier lab shooting bullets
and brought the cost frontier for
intelligence down yet again. Do you like
using it so far? Let me know down in the
comments. And if you want to learn more
about how LLMs work much more in depth
without being overwhelmed with math, you
should definitely check out my latest
project intuitiveai.academy, where it
contains an intuitive explanation of all
modern LLMs from the ground up,
including a lot of technical topics like
distillation, which I just mentioned in
this video. We cover everything from the
basics like the transformer architecture
all the way to more advanced topics like
LoRA, mixture of experts, and RLHF. And
we also just added a new advanced
chapter on optimizers, which will bring
you all the way from the classics to the
current frontier techniques. So, whether
you're a student, software dev, founder,
or just someone trying to pivot into AI,
intuitiveai.academy gives you one clean
place to build real technical intuition.
And you use the code lockin for 35% off
on a yearly membership. And thank you
guys for watching. A big shoutout to
Spam Maj, Chris La Due, Deegan, Robert
Zaviassa, Marcelo Ferreria, Proof and
Enew, DX Research Group, Alex, Midwest
Maker, and many others that support me
through Patreon or YouTube. Follow me on
Twitter if you haven't, and I'll see you
in the next one.
