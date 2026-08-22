---
titulo: 'The Most Absurd Way To Train LLMs... With 3x Less Memory!?'
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-08-18'
url: 'https://www.youtube.com/watch?v=T_GhB7lK2YE'
thumbnail: 'https://i.ytimg.com/vi/T_GhB7lK2YE/maxresdefault.jpg'
descricao: 'Need high quality cloud GPUs? Check out Verda now, and use the code BYCLOUD-50 to get $50 of compute credits for just $5! https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=diffusion_blocks A great news for us gpu poors? This research DiffusionBlocks shows that you can train LLMs with 2-3x less memory with minimal performance loss. If crazier, you could even go up to 4x or 6x! my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud DiffusionBlocks [Paper] https://arxiv.org/abs/2506.14202 [Project Page] https://pub.sakana.ai/diffu...'
resumo: 'The video discusses a new AI research breakthrough that could significantly reduce memory usage in training large language models (LLMs) by two to six times, while also addressing communication bottlenecks in distributed training. It highlights the potential of this approach for various AI applications, although its effectiveness at scale remains to be confirmed.'
tags:
  - bycloud
  - bycloudai
  - diffusionblocks
  - 'sakana ai'
  - 'sakana ai research'
  - 'diffusion blocks'
  - 'diffusionblocks llm'
  - 'llm training'
  - 'machine learning'
  - 'llm pre-training'
fontes:
  - 'https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=diffusion_blocks'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://arxiv.org/abs/2506.14202'
  - 'https://pub.sakana.ai/diffusionblocks/'
  - 'https://github.com/SakanaAI/DiffusionBlocks'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://www.manimate.ai/'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

Need high quality cloud GPUs? Check out Verda now, and use the code BYCLOUD-50 to get $50 of compute credits for just $5! 
https://verda.com/?utm_source=bycloud&utm_medium=referral&utm_campaign=sponsorship&utm_content=diffusion_blocks

A great news for us gpu poors? This research DiffusionBlocks shows that you can train LLMs with 2-3x less memory with minimal performance loss. If crazier, you could even go up to 4x or 6x! 


my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud


DiffusionBlocks
[Paper] https://arxiv.org/abs/2506.14202
[Project Page] https://pub.sakana.ai/diffusionblocks/
[Code] https://github.com/SakanaAI/DiffusionBlocks



Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI

This video is supported by the kind Patrons & YouTube Members: 
🙏Spam Maj, Alex, Chris LeDoux, DX Research Group, Poof N' Inu, Deagan, Robert Zawiasa, Ryszard Warzocha, Tobe2d, Louis Muk, Akkusativ, Kevin Tai, Mark Buckler, NO U, Tony Jimenez, Ângelo Fonseca, jiye, Anushka, Asad Dhamani, Binnie Yiu, Calvin Yan, Clayton Ford, Diego Silva, Etrotta, Gonzalo Fidalgo, Handenon, Hector, Jake Disco very, Michael Brenner, Nilly K, OlegWock, Daddy Wen, Shuhong Chen, Sid_Cipher, Stefan Lorenz, Sup, tantan assawade, Thipok Tham, Thomas Di Martino, Thomas Lin, Richárd Nagyfi, Paperboy, mika, Leo, Berhane-Meskel, Kadhai Pesalam, mayssam, Bill Mangrum, nyaa, Toru Mon, Lame Plane, Matej Macak, Len Mo, saylikhapekar, ZyanSheep, THEVIERAOS, Ricardo Raphael Corona-Moreno, superchordate


[Discord] https://discord.gg/NhJZGtH
[Twitter] https://twitter.com/bycloudai
[Patreon] https://www.patreon.com/bycloud
[Business Inquiries] bycloud@smoothmedia.co
[Other Inquiries] bycloudai@gmail.com
[Profile & Banner Art] https://twitter.com/pygm7
[Video Editor] @aduckchicken2 
Manim Animations created with Manimate https://www.manimate.ai/
[Ko-fi] https://ko-fi.com/bycloudai

## Transcrição

Today is potentially a great day for the
GPU port because what if I tell you
there's this new AI research that could
potentially reduce memory usage by at
least two to three times and if you're
crazier, maybe up to six times. And this
includes text generation, image
generation, and even image
classification models. On top of that,
it also solves a major communication
bottleneck for distributed training. But
before I hype you up too much, this is
only if it successfully scales up to
billion-level parameters. Right now, it
has only been confirmed up to a few
hundred million parameters. It does seem
very promising though. So, in today's
video, let's break down how they
creatively figured out such an insane
cheat code that could actually drive the
entire GPU economy upside down. And
before I dive into it, as Frontier Open
Weights are getting progressively better
and more useful, it is getting more and
more appealing to host or even train
your models on these weights to
customize your experience, right? And
for that, you would need top-tier cloud
GPU server that is specialized at
running or training LLMs, like today's
sponsor, Verta, which is a full-stack AI
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
B300, along with NVLink, InfiniBand, and
fast NVMe storage for workloads where
throughput and interconnect performance
really matter. They're also an official
Nvidia preferred partner, which means
closer alignment with Nvidia's latest
hardware road map. Verta has already
opened early access for a video GB300
NVL72 Blackwell Ultra Clusters, which is
some of the most powerful AI systems
available today. For smaller
experiments, you can spin up on-demand
GPU instances or use spot instances at
up to three times lower pricing with the
trade-off that capacity can be
reclaimed. So, they're the best for
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
description, and use code BYOCLOUD50,
which provides $50 worth of compute
credit for just five bucks. And thanks
again to Verta for sponsoring this
video. Anyways, the VRAM memory
bottlenecks of LLMs can be mainly
separated into three big categories:
memory for context window, memory for
inferencing, and memory for training.
Context window is mainly about the
growing KV cache as more context is used
by the users. And the strain on the
memory is what a lot of names like
Google's Turbo Con and Deep CV4 are
trying to fix as long context reasoning
is becoming a common theme. For
inferencing, the entire weights have to
sit in GPU memory the whole time, since
it needs to run the model to produce
results. So, for a 10 billion parameters
model, that's about 20 GB at FP16.
However, you can technically cut corners
by running a quantized version, where
some parts of the weights are stored at
lower precision like int8 or int4
instead of full float. So, if you drop
it to int8, you probably only need
around 10 GB of VRAM. And if you drop it
to int4, then it's around 5 GB. But,
keep in mind, the more you quantize, the
more performance you lose. But, it saves
a lot of memory, and this is already
used by Deep CV4 in production to save
inference cost. However, for training so
far, we barely have any VRAM savings,
and that's because the corners you can
cut at inference mostly aren't usable
here. At inference, you can often get
away with an A or in for precision
because you're only doing a forward
pass. But training is much more
sensitive because you have to tune the
model. And if you overshoot it too
often, the model would be very unstable.
The weights, the gradients, activations,
and the optimizer updates all interact
every step, too. So, you usually cannot
quantize training as aggressively as
serving. Though precision training does
exist now, but you can't lean on it
nearly as hard as you do when serving.
And it's usually a way to reduce the
error of quantized inferencing. So, it's
not really a method you use from the
ground up, which means the barrier to
entry is much higher for training since
you still need a full precision copy of
the model weights sitting around. For
our 10 million parameter model example,
that's like 40 GB in FP32, which is two
times the FP16 footprint we would serve
it at. Well, the number gets worse
because the weights are the cheap part.
To do a single gradient update, the
network cannot just run a forward pass
and forget the values. It has to hold on
to the activation of every layer so it
can walk back through them afterwards,
which means memory grows with model
depth, too. On top of that, you're
storing the gradients and the optimizer
states. With Adam, the optimizer alone,
it keeps two extra copies of every
parameter, too, which is like 80 GB. So,
if we add it all up, we get roughly 160
GB, about four times the memory used
compared to inferencing in full
precision. So, this is what the new
paper by a Japanese AI lab called Sakana
AI is actually aiming to solve. In this
paper called blocks, it reframes the
problem of LM training into a diffusion
process. It's not saying LM generation
itself should have a diffusion
objective. It's saying that the training
path through the transformer layers can
be interpreted like a diffusion process.
And they were able to interpret it in
this way because every transformer layer
is connected by a residual connection,
which basically means each layer does
not completely rewrite the hidden state.
So, mathematically, we have a current
state, a small correction, then a next
state. And this is exactly the process
of a diffusion model. You start from
noise, then repeatedly apply small
denoising steps until the noisy thing
becomes clean, which gave rise to their
big idea, what if we reinterpret the
transformer depth as a denoising
trajectory. And the implication of this
interpretation is huge. If each chunk of
the network is really just one denoising
step, then by using the techniques from
diffusion modeling, we technically don't
have to train thing as one giant
connected blob. You can slice it into
blocks, hand each block its own slice of
noise to clean up, and train each block
completely on its own, just like how a
typical denoising process would do. And
this completely breaks the limit of a
fully end-to-end training process that
LLMs were completely stuck with. So,
let's take a 12-layer transformer as an
example. Typically, we would need to
forward through all 12 and backward
through all 12. But in diffusion blocks,
you can split this into three blocks.
Layer 1 to 4, layer 5 to 8, and layer 9
to 12. And each block gets assigned to a
different noise range. The first block
might be responsible for denoising very
noisy targets, the second block handles
medium noise targets, and the third
block handles cleaner targets. So, it is
more like training to solve a local
denoising problem. And because every
block has its own denoising objective,
you don't need to run backprop through
the entire model just to train that
block, which gives a training process of
sampling one block, sampling a noise
level inside that block's noise range,
corrupt the target, run only that block,
and update only that block. So, if we
have three blocks with four layers in
one block, then that's roughly three
times less memory that we need to use.
That is, if we train them one after the
other, of course. And if you have six
blocks, that's potentially six times
less memory. So, the model did not
shrink, but it's because the part of
model that needs gradients at any one
moment is now in a smaller chunk. And
you can train all of them independently,
which means you can do it in parallel,
and it will require barely any
synchronization, or you can train it
independently one after the other. So,
this is why the paper claims a roughly B
times memory reduction during training,
where B is the number of blocks. In a
way, you are also trading memory for
total training time, but that is a
luxurious option that we did not have
before. To put some numbers into
perspective, if a model was only
trainable on a H100 that costs like
30,000 US dollars, it is now trainable
on a RTX 4090 that's only like 2,000 US
dollars. Well, I mean, that is, of
course, theoretically. Let's take a look
at their results. And so far, they have
only tested on basic toy models ranging
from 50 million to around few hundred
million parameters. So, it's not a lot.
But, what's interesting is that they
tested it on more than LLMs. They
included vision transformers for image
classification, diffusion transformer
for image generation, masked diffusion
language models for language, and even
recurrent depth models. For image
classification, they took a 12-layer
ViT, tested it on CIFAR-100,
and split it into three blocks with the
normal normal ViT got around 60.5
accuracy, and diffusion blocks got 59.3,
which is pretty good. A three-times
memory saving while losing like 1%
accuracy. Then, for image generation,
they tested it on DiT, which is the
transformer architecture behind most
modern image generators. And here, the
idea fits even more naturally because
diffusion models objectives already have
noise levels. So, instead of running the
entire model for every denoising step,
diffusion blocks used different blocks
at different noise levels. On CIFAR-10,
the standard DiT got 39.83 test FID,
while diffusion blocks got 37.2. On
ImageNet, the standard DiT got 12.09
test FID, while diffusion blocks got
10.63. And the plot twist? If you don't
know what FID is, well, the lower the
score, the better. So, in these
experiments, it didn't just preserve
performance, it actually slightly
improved it, which is kind of crazy
because you are training with less
memory, but not paying any penalty.
Then, they tested on masked diffusion
language modeling, where instead of
denoising images, you denoise masked
text. And with a three-block setup, it
also improved from 1.56 bits per
character to 1.45 on text 8. But, the
most anticipated part is definitely the
autoregressive language model
experiment, because this is probably the
biggest deal for this paper, as it might
just revolutionize LLM training. So,
they use a 12-layer Llama 2-style
transformer split into four blocks, and
instead of needing to train the whole
model end-to-end with a next token loss,
each block learns to denoise the target
token embedding while conditioned on
previous tokens. So, it still has its
causal property. For benchmarks on LLM
1B, the normal autoregressive model got
a MAW score of 0.5, while diffusion
blocks got 0.71. MAW basically scores
how close generated text is to human
text, and the higher is better. On
OpenWebText, the normal autoregressive
model got 0.85, while diffusion blocks
got 0.82. So, basically pretty close.
So, on toy experiments, diffusion block
is already looking pretty promising.
It's just that the scale is way too
small to really imply or prove anything,
and the evaluation is not the same as a
full LLM pre-training benchmark, because
it's only testing on generative
perplexity plus similarity to human text
and not achieving actual useful tasks.
Another interesting aspect though is how
fine-grained the blocks can be, because
right now we only have seen them
splitting a 12-layer transformer into
three or four blocks. So, why not six or
12 blocks then? By the logic so far,
shouldn't splitting it into 12 make
sense, since it's just a denoising
process? And in the paper's ablation,
unfortunately, more blocks does not
equal better. While this setup does give
you more memory and speed, each block
actually becomes weaker. The main
problem is that the blocks need to have
enough depth in order to do good
denoising. So, if you split into 12
blocks, each block would only have one
layer, which as their experiment
suggests, cannot really do anything
significant. More specifically, for
ImageNet image generation, when B equals
to 1, which is just normal end-to-end
training, it gets an FID of 12.09.
Having two blocks improves it to 9.9,
but starting from three blocks, it
starts to degrade again, going up to
11.11. With four blocks, it is 11.9, and
with six blocks, it drops to 14.43,
which is worse than the baseline that is
normal training. So, from the look of
this, when it is scaled up, we can
probably expect two potential outcomes,
a ratio that needs to be balanced or a
minimum amount of layers needs to be
present in the block. And if it's the
latter, then we will most likely hit a
memory savings jackpot, because if we
can have 128 layer model that only needs
six layers per block, then we're able to
get 20 times the memory savings. And the
memory savings would scale linearly with
depth. Well, of course, that is if you
train them one after the other. But, the
upside is you can train them in parallel
with barely any communications, which
will reduce an insane amount of
communication requirement and pipeline
bubbles. So, there is a small potential
where this paper will shake up the
training economy by a big amount. And at
the end of the paper, they added a part
that is also pretty fascinating. Beyond
the fusion block for standard LMs, they
can also be applied on looped
transformers. I already have a video on
it, you can go check it out, but what it
basically does is that it reuses the
same transformer block again and again
to simulate depth. So, normally in a
typical transformer, you would run
through all layers, right? But, a loop
transformer repeats a block instead,
where the block is composed of only a
few layers. So, as an example, instead
of having 36 different layers, you might
only have one block that is applied 12
times, where the block has only three
layers, which means a loop transformer
save parameter memory. However, they
actually do not automatically save
activation memory. In some cases, they
can actually make training more
annoying, because now the same weights
are being updated through a long chain
of repeated computation. So, the fusion
block somehow became relevant here,
since a loop transformer looks even more
like a diffusion process. So, instead of
training the recurrent model by
unrolling all 12 steps, the fusion
blocks just train the loop itself as a
denoiser. You sample a noise level, give
the model a corrupted state, and train
one forward pass to map it closer to the
clean target, which means it only needs
to run a single forward pass per
training step. Then at inference, you
can still run the loop multiple times if
you want the full iterative computation.
A pretty mind-blowing improvement,
right? So not only has the fusion block
conquered some early toy experiments of
our beloved LLMs, and also image
generation and image classification, it
has also instantly upgraded the training
process of loop transformers, which is a
rather fresh architecture design. What a
crazy release. This reduction in
communication also means distributed
training will be so much more convenient
as you no longer need to sync data
across GPU clusters for extremely large
models. So the communication bottleneck
for training could literally disappear.
So with such a cool memory-saving method
for training, why does it not save
memory for inference then? Well, the
model still needs to be causal at
inference, so the entire model needs to
be hosted in the GPU memory. You could
offload it, but that doesn't really mean
anything because CPU offloading already
exists. And for inference, you want the
model to generate as fast as you can. So
trading VRAM for latency isn't really
worth it. And to be honest, it's more
like a method that'll benefit more on
the research side of things because this
enables more researchers to train or
even fine-tune larger models on much
cheaper hardware. So if this research
does scale, yeah, research will probably
accelerate. And for AI labs, training
extremely large model would be much
easier, too. Because you no longer need
to deal with communication between
during training. So to sum up the main
idea of this paper, the highlight is
definitely breaking down the end-to-end
training constraint that we thought LLMs
were permanently stuck with. So I will
provide you guys an update when this
research has been scaled up. So
subscribe to stay tuned. And if you want
to learn more about how LLMs work much
more in depth without being overwhelmed
with math, you should definitely check
out my website intuitiveai.academy,
which intuitively explains all of modern
LLMs from the ground up, including a lot
of advanced topics. We cover everything
from the basics like the transformer
architecture all the way to more
advanced topics like Laura, mixture of
experts and RLHF. And we also just added
a new advanced chapter on optimizers
which will bring you all the way from
the classics to the current frontier
techniques. So, whether you're a
student, software dev, founder, or just
someone trying to pivot into AI,
Intuitive AI dot academy gives you one
clean place to build real technical
intuition. And you can use the code lock
in for 35% off on a yearly membership.
Thank you guys for watching. A big shout
out to Spam Madge, Chris Ladue, Deegan,
Robert Zaviasa, Marcelo Ferraria, Pooven
Enew, DX Research Group, Alex, Midwest
Maker, and many others that support me
through Patreon or YouTube. Follow me on
Twitter if you haven't and I'll see you
in the next one.
