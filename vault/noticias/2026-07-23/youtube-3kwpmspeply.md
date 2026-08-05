---
titulo: 'GLM-5.2: DeepSeek Was Wrong About RL?'
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-07-23'
url: 'https://www.youtube.com/watch?v=3KwpmSpEplY'
thumbnail: 'https://i.ytimg.com/vi/3KwpmSpEplY/maxresdefault.jpg'
descricao: 'Need high quality cloud GPUs? Check out Verda now, and use the code BYCLOUD-50 to get $50 of compute credits for just $5! https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=deepseekv4 Late to the GLM-5.2 party, but I think there is actually much more insights in their blogs I don''t see people talk about than the model performance itself. Especially the part where they decided to stop using GRPO and go back to PPO! my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud GLM-5.2 [Blog] https://z.ai/blog/glm-5.2 Try out my new fav p...'
resumo: 'A GLM-5.2, um modelo de pesos abertos sob licença MIT da ZAI, e as suas capacidades como agente de IA, comparando-o com modelos de fronteira em termos de desempenho, custo e contexto longo. Aborda ainda as escolhas de design técnico reveladas pela ZAI e o que estas mostram sobre o treino com aprendizagem por reforço.'
tags:
  - bycloud
  - bycloudai
  - GLM-5.2
  - 'glm 5.2'
  - 'glm 5.2 explained'
  - GRPO
  - 'glm GRPO'
  - 'GLM 5.2 ppo'
  - deepseek
  - 'deepseek GRPO'
  - 'glm-5.2 blog'
  - 'glm 5.2 blog'
  - 'glm 5.2 local'
fontes:
  - 'https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=deepseekv4'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://z.ai/blog/glm-5.2'
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


Late to the GLM-5.2 party, but I think there is actually much more insights in their blogs I don't see people talk about than the model performance itself. Especially the part where they decided to stop using GRPO and go back to PPO!


my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud

GLM-5.2
[Blog] https://z.ai/blog/glm-5.2


Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI

This video is supported by the kind Patrons & YouTube Members: 
🙏Spam Maj, Alex, Chris LeDoux, DX Research Group, Poof N' Inu, Deagan, Robert Zawiasa, Ryszard Warzocha, Tobe2d, Louis Muk, Akkusativ, Kevin Tai, Mark Buckler, NO U, Tony Jimenez, Ângelo Fonseca, jiye, Anushka, Asad Dhamani, Binnie Yiu, Calvin Yan, Clayton Ford, Diego Silva, Etrotta, Gonzalo Fidalgo, Handenon, Hector, Jake Disco very, Michael Brenner, Nilly K, OlegWock, Daddy Wen, Shuhong Chen, Sid_Cipher, Stefan Lorenz, Sup, tantan assawade, Thipok Tham, Thomas Di Martino, Thomas Lin, Richárd Nagyfi, Paperboy, mika, Leo, Berhane-Meskel, Kadhai Pesalam, mayssam, Bill Mangrum, nyaa, Toru Mon, Lame Plane, Matej Macak, Len Mo, saylikhapekar, ZyanSheep, THEVIERAOS, Ricardo Raphael Corona-Moreno, superchordate


[Discord] https://discord.gg/NhJZGtH
[Twitter] https://twitter.com/bycloudai
[Patreon] https://www.patreon.com/bycloud
[Business Inquiries] bycloud@smoothmedia.co
[Other Inquiries] bycloudai@gmail.com
[Profile & Banner Art] https://twitter.com/pygm7
Manim Animations created by https://x.com/miyashita_03
[Ko-fi] https://ko-fi.com/bycloudai

## Transcrição

GLM 5.2 just had their deepseeek moment
for AI agents. So this new ZAI's model
is so good that at the time of the
release it is the third best model in
the world excluding the exploit
controlled cloud fable of course. On top
of that it is an openw weightight model
fully under the MIT license which means
there now exists a model where everyone
has free access to and can build freely
on top of while being better than all of
Google's flagship models. But the most
crazy part is that this thing is sitting
at the price of $1.4 in and $4.4 out per
million tokens, which is at least five
times cheaper than claw 4.8 Opus and GBT
5.5. And when I said this is a deepseek
moment, I am not speaking of that
lightly. Not only has GLM 5.2 brought
the cost efficiency of Frontier LMS
down, but this is probably the first
time that an insanely strong agentic
model is available in the form of an
open weight. While there are indeed a
handful of 1 million context window open
LLMs, especially now with the latest Kim
K3 that completely blew GLM 5.2 out of
the water, how they actually trained
their model to utilize long context is
still really worth to look at. I mean,
Kim K3 didn't really share about how
they train their long context agents
either. And the initial reactions to GLM
5.2's agentic capabilities were pretty
good, too. In fact, some of the top
researchers in the field have said so
themselves. Like this is probably one of
the greatest egentic LMS that open-
source labs have ever achieved with
capabilities that are at times
indistinguishable from GPT 5.5 or claw
4.8 and their extremely strong
performance can also be observed on
multiple highly difficult private
benchmarks like Frontier Suite which
measures agents on open-ended technical
problems. A post-train bench which
measures how well agents can post-train
language models. Deep SUI which measures
agents on extremely long horizon
engineering tasks. NA briefcase which
measures agents on realistic business
workflows such as making spreadsheets,
presentations, and memos. To top it all
off, it once ranked second on the coding
arena, so it definitely passes the vibe
check. And what's also great about an
open weights release is that they
usually come with some incredible
technical insights that we usually would
not have the chance to learn, let alone
the knowledge that made a near
state-of-the-art model. Even though GLM
5.2 did not publish an in-depth
technical report, their blog still
shared some fascinating design choices
that surprised everyone. And before we
dive into it, as Frontier open weights
are getting progressively better and
more useful, it is getting more and more
appealing to host or even train your
models on these weights to customize
your experience, right? And for that,
you will need top tier cloud GPU server
that is specialized at running or
training LMS like today's sponsor Verta,
which is a full stack AI cloud built for
the entire model life cycle from GPU
instances and instant clusters to
serverless containers. Unlike general
purpose cloud providers trying to
support AI compute like an afterthought,
Verto is built specifically for AI
workloads from toy experiments all the
way to large scale training, high
throughput inference and agent tech AI
serving. Built by people who also work
with actual AI systems, their
infrastructure is designed around how AI
teams train, serve, and deploy models.
On the hardware side, Verdo gives you
access to powerful NVIDIA GPUs like
B300's along with MVLink infinite and
fast MVME storage for workloads where
throughput and interconnect performance
really matter. They're also an official
Nvidia preferred partner, which means
closer alignment with Nvidia's latest
hardware road map. Vera has already
opened early access for Nvidia GB300
MVL2 Blackwell Ultra clusters, which is
some of the most powerful AI systems
available today. For smaller
experiments, you can spin up on demand
GPU instances or use spot instances at
up to three times lower pricing with a
trade-off that capacity can be
reclaimed. So, they're the best for
interruptible workloads. And if you're
doing serious training or fine-tuning,
Vera also offers instant clusters with
self-service access to 16 to 128 GPUs
connected through Infinite Bend. That's
exactly the kind of setup you want when
multiGPU performance actually matters.
Then when your model is ready to deploy,
you can move into production with
autoscaling serverless containers,
making the path from experiments to
inference a lot less painful. So whether
you're experimenting, building agentic
AI systems, fine-tuning models, or
deploying inference at scale, Vera gives
you AI infrastructure built for speed,
power, and performance without the usual
scale and chaos. So check out Verta now
using the link down in the description
and use code by cloud 50, which provides
$50 worth of compute credit for just
five bucks. And thanks again to Vera for
sponsoring this video. Anyways, as great
as open weights model sound, a big issue
they often struggle with is that once
you want to do an autonomous or
complicated agent workflow, they often
fail easily. This is a huge reason why
many people are so willing to pay for
the latest clot attached to claw code,
even if it costs them like 10 grand a
month. As you can see in GDP eval, which
aims to reflect the tasks with real
world complexity across finance,
healthcare, legal, and other
professional domains, there was a huge
gap between the frontier open source
model before GLM5.2 2 and Mini Max M3
before they both came out. But now with
the appearance of GLM 5.2 that even
outperforms GPD 5.5 on this benchmark,
its strong agentic capabilities that
involves heavy tool coal and looping
makes it perfect for harnesses like open
claw or Hermes while being six times
cheaper. However, you could say that
cheaper per million tokens doesn't
really mean anything if it is using more
tokens as token efficiency right now is
arguably an even more important metric
as long horizon tasks become more
common. for the same GDP eval artificial
analysis provides a pretty good
breakdown showing how many tokens are
used when benchmarking the models. So
GLM 5.2 uses slightly less tokens than
4.8 Opus. But surprisingly, Fable uses
even less on this benchmark. On the
other hand, when you look over at the
cost per task, Fable is only 5.5 times
more expensive, while the per million
token cost is nearly 11 times compared
to GLM 5.2. However, 4.8 8 opus was only
three times more expensive per task
while the per million token cost is 5.6
times more. So the token cost may not
paint the whole picture even though the
claw models are still more expensive.
There is also the concern of benchmark
maxing which you know technically every
model might just have that problem. But
I think the main point here is that the
data mixture of private models from US
labs do have a wider range of private
data to utilize from. So on benchmarks,
some models may look similar in
performance, but it would feel
drastically different when you actually
use it or some would say the vibes are
off. So arena-like benchmarks where
models compete head-to-head and have
humans pick the better result would
sometimes serve as better vibe signals.
And arenas like the code arena and
design arena all reflect GLM 5.2's great
coding vibes really well. What's even
crazier is that a one bit quantized GLM
5.2 from Unsloth still maintains some
insane quality, which just shows how
good it is at coding and design. And one
of the key shocking details shared on
their technical blog is the method of
how they train these agentic
capabilities because for typical
reasoning models a lot of labs right now
use GRPO which stands for group relative
policy optimization. This is a technique
proposed by Deep Seek back in February
2024. And the idea is instead of
training a separate critic model to
estimate how good every token or action
was, you sample a group of answers from
the same prompt. Then what you do is you
compare each answer against the group
average. So if one answer is better than
a group average, you reinforce it. If
it's worse, you punish it. And this
works extremely well for math as it was
basically proposed in deepseek math,
which is their RL paper for training LMS
to do hard math. This method is not only
cheap as the standard method called PBO
usually requires a value model aka
critic to estimate how good of an action
is, which is pretty expensive. But if
the critic is bad, the whole RL process
might be doomed. So, GRPO was a very
elegant solution because for math you
would only get one answer at the end and
it can also be verified systematically.
You can sample eight solutions, check
which ones got the final answer correct
and train the model toward the better
ones. But the problem is that agentic
tasks are not clean like math. While the
end goal for an agent is also as
important, it will still need to achieve
intermediate steps and every step is
actually extremely important unlike some
math components that can be skipped. So
an entire action trajectory will be
produced and that 300 steps trajectory
all needs to be executed individually or
else a task would fail. Or on the other
side of spectrum there would also be
multiple ways to do the same thing which
creates a training signal that conflicts
with the data. One royale might finish
in 50 steps another might take 500. Some
agents might spend half the time reading
code and others might immediately edit
the files. So if the only thing you know
is whether the final task passed or
failed, it becomes really hard to know
which action actually deserve credit.
Which is where GRPO starts to show its
weakness as group comparison assumes
that every answer in the group is
somewhat comparable. But long horizon
agent rollouts are not naturally
comparable as the similarity is not
guaranteed. So for GLM5.2 Two, Zai
basically brings PO back and completely
sacked the GRPO in its RL process,
breaking the default assumption that
GRPO related methods is the go-to for RL
training. What PO does is that instead
of comparing a Royale against a group
average, PO uses a critic and that
critic would try to answer the question
given the current state of the
trajectory, how good does the future
look from there? So when the agent takes
an action, PBO can compare what actually
happened later against what the critic
expected to happen. If the outcome was
better than expected, then the model
increases the probability of the tokens
or actions that led there. If the
outcome was worse than expected, then it
decreases them. Which means the model
can learn from messier and variable
length trajectories. So PBO is not just
asking if the final task passes. It is
tracking the progress step by step in
the trajectory if the action makes the
future better or worse, which provides
much better signals than GRPO based
methods. On top of that, a super long
trajectory can get split into multiple
smaller subtraces and you can use VPO to
train on all of them. And to support
these kind of agentic tasks, they built
a system called slime which handles all
the messy infrastructure behind
organizing all these trajectories that
have wildly different use cases and
environments as training an agent is no
longer just some simple question answer
pairs. What slime does is that it
separates the roll out logic from the
learning logic. So instead of hard
coding one environment or one task
format, it treats every agent
interaction as a trajectory made of
states, actions, tool calls, and
feedback. Each environment would then
just plug into this interface. So you
could have a coding environment
returning compiler errors, a browser
agent returning HTML content, a research
agent returning retrieved documents. But
from the training systems perspective,
they all look like structured
trajectories. So it basically
standardizes how those trajectories are
stored, compacted, and fed into RL. They
also used slime to run parallel on
policy distillation training, merging
more than 10 expert models into the
final GLM 5.2 model in around 2 days.
But with the increased use of agentic
data, especially the cost of creating
Royals, it definitely motivated them to
try to somehow reduce the cost of
generating text even more. Especially
now GLM 5.2 has been upgraded from
256,000 up to 1 million context window.
Not only that, Long Horizon agents are
not just reading one long prompt and
answering ones. They are generating
calling tools, reading outputs, and
updating the context. So saving compute
while not deteriorating the attention's
performance is also extremely important.
And they actually figured something out.
They proposed a simple design on top of
their deep sea sparse attention that
they use for the GLM5 series called
index share. So in sparse attention, the
model does not attend to every token and
instead uses an indexer to pick the
important tokens to attend to. But at 1
million context, even that indexer
becomes expensive because before the
model can attend sparsely, it still has
to figure out what is worth attending
to. So ZAI's researchers made a really
interesting observation. Why should
every sparse attention layer redo the
same indexing work every time? There
might be some potential savings if you
reuse the indexing, right? So in their
experiments, they made every four sparse
attention layers share one lightweight
indexer. So one layer decides which
tokens matter and the next few layers
reuse the same selected indices reducing
75% of the indexing work instantly. This
not only reduces per token flops by
around 2.9 times at 1 million context
but it is still capable of outperforming
GLM 5.1 which is their previous model
series at long context. Alongside these
few key designs, they have also made
other much more technical optimizations
like multi-step MTP, long context kernel
overhead, cache transfer, CPU side
scheduling, and GPU execution bubbles.
And with these optimizations, they were
able to obtain increased throughput
performance where the longer the context
gets, the larger GLM 5.2's advantage
becomes. And this is exactly what you
would love to see from a real usable 1
million context model. Like if a model
only works well at short context but
collapses in throughput and performance
once you actually use the full context
window, then the 1 million might as well
be just marketing. But here, ZAI showed
that their serving stack actually scales
better as the context gets longer up to
nearly seven times normalized throughput
at 1 million context window compared to
GM 5.1 when it's only filled up to 32k
context. I will say this model release
points to an important direction for the
future of Agentic AI. While the
bottleneck for short prompts or tasks is
usually just raw compute or speed, once
you get into the ultra long context
territory though, the priority would
start to shift since you now need to
deal with and manage a large amount of
KV cash. And besides the future
direction of AI, what about the future
of ZAI if they do continue to release
front tier models for free? Well, their
stock has increased 1,600% year to date.
But of course, it still doesn't change
the fact that they are giving up on the
potential revenue they could have had if
all the requests go solely to them and
not thirdparty services that host their
model or have enterprises that can
deploy it locally for their internal
usage. While open sourcing is awesome, a
business is still a business and a
business is not sustainable if it is not
profitable and that is definitely a
short-term concern. But in the long
term, they are technically offloading a
huge amount of optimization work to the
ecosystem. Maybe it couldn't directly
generate a large amount of cash, but
this itself may still be an advantage
because the AAI now doesn't really need
to concern themselves about serving it,
which technically reduces the cost for
them. As you can see how Enthropping now
spends more GPUs on inferencing than
training thanks to agentic AI and having
inference providers like fireworks to
serve them would also save their limited
compute which can now all be directed to
training or research. On the other hand,
for closed models, the model and the
serving stack are bundled together very
closely. So if it's slow, you cannot
really do anything about it. This also
opens up a new idea where the model
layer and the hardware layer for AI can
be split apart. This could potentially
create a strategic advantage as ZAI can
offload that optimization like kernel
designs and focus more on making the
model better. So for the token pricing,
the speed and compute could vary for
external inference services and the
inference providers might be competing
against each other potentially making
latency and token generation speed
faster in the process. But yeah, this is
just my take as to what would happen to
the future of open source landscape. I
do hope there is no particular business
reason as to why labs will continuously
publish open weights other than for the
love of research. And I hope that ZAI
will keep up this amazing run they are
having. And what are your experiences of
using GLM 5.2 so far? Let me know down
in the comments. And if you want to
learn more about how LMS work much more
in depth without being overwhelmed with
math, you should definitely check out my
latest project, Intuitive AI. Academy,
where it contains an intuitive
explanation of all modern LMS from the
ground up, including a lot of technical
topics like distillation, which I just
mentioned in this video. We cover
everything from the basics like the
transformer architecture all the way to
more advanced topics like Laura, mixture
of experts, and RLHF. And we also just
added a new advanced chapter on
optimizers which will bring you all the
way from the classics to the current
frontier techniques. So whether you're a
student, software dev, founder, or just
someone trying to pivot into AI,
intuitive.academy
gives you one clean place to build real
technical intuition and you can use the
code lockin for 35% off on a yearly
membership. And thank you guys for
watching. A big shout out to Spam Match,
Chris Loo, Dan, Robert Zaviasa, Marcelo,
Ferraria, Proof and Enu, DX Research
Group, Alex Midwest Maker, and many
others that support me through Patreon
or YouTube. Follow me on Twitter if you
haven't and I'll see you in the next
one.
