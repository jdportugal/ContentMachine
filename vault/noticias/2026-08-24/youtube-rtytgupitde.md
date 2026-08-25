---
titulo: 'CoT Fully Exposed?! The Worst AI Exploit That Almost Happened'
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-08-24'
url: 'https://www.youtube.com/watch?v=rtYTguPItDE'
thumbnail: 'https://i.ytimg.com/vi/rtYTguPItDE/maxresdefault.jpg'
descricao: 'Try CodeRabbit for free and simplify your PR reviews https://coderabbit.link/ad-bycloud-001 my latest project: Intuitive AI Academy We just wrote a new piece on Optimization!! https://intuitiveai.academy/ limited time code "LOCKIN" for 35% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud Sources: Let’s talk about encrypted reasoning [Blog] https://blog.cryptographyengineering.com/2026/05/29/fooling-around-with-encrypted-reasoning-blobs/ Stealing Reasoning Traces from Proprietary LLM APIs [Paper] https://alphaxiv.org/abs/2608.09867 Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI This video is supported by the kind Patrons & YouTube Members: 🙏Spam Maj, Alex, Chris LeDoux, DX Rese...'
resumo: 'The video covers a newly disclosed (and since-patched) jailbreak technique that could extract hidden chain-of-thought reasoning traces from major AI labs'' models, framed against the ongoing US narrative that Chinese labs are "stealing" via distillation. It explains what distillation actually is, how the exploit worked, and why leaked reasoning traces pose risks beyond model distillation.'
tags:
  - bycloud
  - bycloudai
  - 'encrypted reasoning'
  - 'Stealing Reasoning Traces from Proprietary LLM APIs'
  - 'stealing reasoning traces'
  - 'stealing ai reasoning'
  - 'ai reasoning'
  - chain-of-thought
  - 'ai data breach'
  - 'ai CoT'
  - 'stealing ai data'
fontes:
  - 'https://coderabbit.link/ad-bycloud-001'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://blog.cryptographyengineering.com/2026/05/29/fooling-around-with-encrypted-reasoning-blobs/'
  - 'https://alphaxiv.org/abs/2608.09867'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://x.com/miyashita_03'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

Try CodeRabbit for free and simplify your PR reviews https://coderabbit.link/ad-bycloud-001

my latest project: Intuitive AI Academy
We just wrote a new piece on Optimization!!
https://intuitiveai.academy/
limited time code "LOCKIN" for 35% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud


Sources:

Let’s talk about encrypted reasoning
[Blog] https://blog.cryptographyengineering.com/2026/05/29/fooling-around-with-encrypted-reasoning-blobs/ 

Stealing Reasoning Traces from Proprietary LLM APIs
[Paper] https://alphaxiv.org/abs/2608.09867





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
[Bitcoin (BTC)] 3JFMJQVGXNA2HJE5V9qCwLiqy6wHY9Vhdx
[Ethereum (ETH)] 0x3d784F55E0bE5f35c1566B2E014598C0f354f190
[Litecoin (LTC)] MGHnqALjyU2W6NuJSSW9fTWV4dcHfwHZd7
[Bitcoin Cash (BCH)] 1LkyGfzHxnSfqMF8tN7ZGDwUTyBB6vcii9
[Solana (SOL)] 6XyMCEdVhtxJQRjMKgUJaySL8cGoBPzzA2NPDMPfVkKN

## Transcrição

Chinese AI lab is stealing this,
distilling that. It's probably the most
used narrative that the US mainstream
media tries to paint about the current
AI landscape. Like Jensen Huang himself
literally created X for the first time
to suppose about the importance of open
weights LLM because that narrative is
just getting way too out of hand with
how the Congress is even considering
restricting Chinese open weights. He
even had to clarify what distillation is
because this common research technique
has been framed as some sort of dark
technology that is sneakily sucking out
all the AI secrets from Americans top AI
labs. But putting this technique aside,
isn't it even possible to extract
anything from these AI labs in the first
place? Because distillation doesn't work
properly if you don't have any good
data. Well, in the latest AI news, we
have some incredible Joe breaks that
have surfaced and oh lord, it can
extract some juicy reasoning traces that
AI labs have been trying their best to
hide. I mean, this exploit is now
patched of course, way before its paper
was published too, but it is still
fascinating to see how they have done it
and how it might pose a bigger problem
beyond distillation. And before we dive
into it, if you're building with AI
coding tools right now, you have
probably noticed the same problem.
Writing code is getting faster, but
reviewing that code is becoming the new
bottleneck. And honestly, reviewing a
giant AI generated PR can just feel
exhausting. So, this is where today's
sponsor, Code Rabbit, comes in. Code
Rabbit is an AI code review agent that
understands your code base, analyzes
your pull requests, and gives you
context-aware feedback before your
changes get merged. It doesn't just
leave vague comments either. It can
summarize the PR, catch issues, run
security scanners, suggest tests, and
for a lot of comments, it'll even give
you ready to commit fixes you can apply
in one click. And a really useful part
is that Code Rabbit keeps learning from
your team. It basically monitors your
behaviors based on the feedback you
give, the suggestions you accept, or the
rejections you make for certain
comments, which means your future
reviews become more aligned with your
team's actual standards. They They now
have Code Rabbit Change Stack, which
makes large PRs way easier to
understand. Like you no longer have to
read files alphabetically in GitHub's
default diff view, as it reorganizes the
change into logical cohorts and layers,
so foundational changes like data shapes
and contracts come before the code that
depends on them. And where diagrams are
useful, it can generate them in line,
too, so you can easily understand what
changed. On top of that, with Code
Rabbit Agent for Slack, your team can
bring the same engineering context
directly into Slack threads, making
reference a lot easier between comms and
developer environments. Code Rabbit is
currently trusted by at least 100,000
open source projects like Bun and
Nuxt.js, installed on over 3 million
repositories, and has already reviewed
more than 15 million PRs. So, if your
team is shipping more code with AI, but
reviewing is causing a major bottleneck,
check out Code Rabbit using the link
down in the description, and thank you,
Code Rabbit, for sponsoring this video.
Anyways, hiding chain of thought is
actually a much harder problem than
simply not displaying it. We got a lot
of methods that try to extract the data
indirectly, with the latest one being
this paper, How to Steal Reasoning
Without Reasoning Traces, which uses
something called a trace inversion,
where they basically take the result,
the summary of the reasoning, and the
input, and train a model to predict the
chain of thought. It sounds pretty
doable, as they used Deep CR1 to train
this inversion model, and were able to
recover about 89% of the original
reasoning. But at the end of the day,
you're still not actually opening
ChatGPT's brain and reading exactly what
it thought. And the extent you can reach
with just mere approximations still
differs from obtaining the exact
reasoning traces. but if we do go one
layer deeper into how reasoning models
are actually served through APIs, uh
things start to get a lot more
interesting. Because as it turns out,
for some of these APIs, the hidden
reasoning is technically sitting right
in front of us already. It's just
encrypted, which sounds pretty ironic,
right? Like if OpenAI or Anthropic is
trying so hard to hide the chain of
thought, why would they literally send
or even store the chain of thought on
the user's computer? Well, the answer is
actually because of a very lazy
infrastructure problem, which is the
stateless APIs. When you interact with
an API, the provider does not
necessarily want to maintain the entire
state of your conversation on their own
servers forever. Instead, your
application usually carries the
conversation history around and then
sends the relevant history back whenever
it makes the next request. And if
reasoning models introduced a new
problem, because what do you do about
the model's reasoning? The model is
generating thousands of tokens of
internal reasoning on the previous turn,
then it might need that reasoning again
to continue the conversation properly.
But obviously, you cannot just hand the
plain text chain of thought to the user.
So, the solution some providers landed
on is actually pretty clever, that is
encrypting the reasoning trace. So, you
basically return that encrypted blob to
the client, then on the next API call,
the client passes the same blob back.
The provider can authenticate and
decrypt it, while the user only ever
sees some giant unreadable string.
Sounds perfect, right? That was until it
was not clever enough. Even though the
encryption provides confidentiality and
prevents you from changing the content,
since the client holds it, you can still
actually do things to it. So, in May,
this researcher called Matthew Green
started poking around these encrypted
reasoning blocks, and he found that even
though you couldn't get into the blob,
you could still technically move the
blob around, which means you could take
an old encrypted reasoning block and
replay it in a different conversation or
even across completely different
accounts. But the most shocking finding
is that in the OpenAI experiments he
ran, some blocks could even be replayed
across different models. Before I
explain why replaying is a big deal
though, let me just explain why this
would even exist. So, ideally, you would
imagine an encrypted thought generator
for user A in conversation A with model
A would only be valid inside exactly
that context. But in reality, these
blocks were much more portable, and a
potential reason for why that might
happen is maybe you want to switch from
Opus to Sonnet halfway through a
conversation or a provider automatically
reroutes your request to another model
or you truncate some conversation
history. So, having a reasoning state
that survives these changes make all of
that easier. But, security-wise, it
creates a funny situation. Like, what
would happen if I take an encrypted
thought from one conversation and put it
into another one? Does the other model
actually read it? And he managed to show
that some of these replayed thoughts
were actually semantically active and
documented in this blog called "Let's
Talk About Encrypted Reasoning." In one
experiment, a reasoning block involving
a social security number was moved into
a completely different session on
another account and information from
that hidden reasoning appeared in the
new session. So, imagine if you pasted
your API keys or your credit card
number, that block basically has you on
a chokehold and you don't even know it
because the encryption is perfectly
fine. But, when it's transferred to
another session, oh, you know, its mouth
might slip. But, so far, this is only a
risk from third-party attacks, which
means only privacy-related issues and no
chain of thought is being stolen.
However, in this latest paper, stealing
reasoning traces from proprietary LLM
APIs, they kind of dropped a bomb
because as I was saying earlier, if
these encrypted reasoning blocks are
portable across models, then
technically, you can get it to other
models, right? So, for instance, an
encrypted reasoning block generated by
Opus 4.8 could be passed into Haiku 4.5.
For OpenAI, models in the GPT 5.6 family
could interact with reasoning traces
generated by earlier GPT models. Now,
obviously, this alone still does not
mean Haiku is going to politely decrypt
the Opus thoughts for you. But, here is
the funny part. The stronger frontier
models are usually the ones that have
the strongest safeguards against
revealing their chain of thought. But,
the weaker model might not have nearly
the same level of protection. On top of
that, they are dumber. And since the
weaker model can still process the
stronger model's encrypted reasoning,
you can basically make the weaker model
act as a decrypter. So, instead of
jailbreaking Opus, you let Opus reason
normally, take the encrypted reasoning
signature it gives you, pass that
signature into Haiku, and then jailbreak
Haiku into transcribing whatever
reasoning it just processed. And that is
basically the whole attack. The paper
shows one pretty clean example where
Opus 4.8 solves a factoring problem and
returns an encrypted thinking signature.
Then they send just that signature into
Haiku 4.5 and ask it to transcribe the
reasoning attached to the turn, with
Haiku just politely following through
with the request and transcribing
everything into plain text, which is
crazy. While the researchers obviously
did not have access to the real plain
text chain of thought from these closed
models, so they cannot really prove that
every single recovered token is
literally identical to the original
private reasoning, it is still a huge
exploit that seems pretty plausible to
completely bypass all the security
measures, and to also indirectly verify
their extracted chain of thought, they
compared the length of the extracted
trace against the number of thinking
tokens reported by the API. And across
120 Codeforces problems, those token
counts matched each other pretty closely
across the tested models, which implies
that they are recovering something very
close to the actual reasoning trace. And
once you get your hands on it, the data
is 100% going to distillation. Can you
imagine how much that will go for in the
Chinese black market? For a quick and
fun thought experiment, let's say a good
long-horizon task data probably goes up
to 20,000 each, and maybe you could sell
it at a tenth of the price on the black
market, which means if you can extract
10,000 examples, you can probably sell
that for $2 million. So, not only are
the AI labs literally getting robbed of
what they have paid for, but others
could also catch up to their progress
literally for cheap. And once they have
access to reasoning traces from models
across different companies, it also
means the researchers could start
comparing how these models actually
think. Not to mention, this is probably
a once-in-a-lifetime opportunity where
you would have reasoning traces from all
the frontier models to make this
comparison, which led to some rather
fascinating observations. First of all,
the reasoning styles across all models
are not nearly as isolated from each
other as you might expect. They took
decoded reasoning from models like
GPT-5.6 Soul and Claude Opus 4.8 and
used just the first four words of those
traces to start the reasoning of open
models from completely different
companies. And models like Kimi K3, Kimi
K2.6, and GLM 5.2 actually shifted their
subsequent reasoning style depending on
whether they were started with GPT or
Claude reasoning. Kimi K3 and GLM 5.2
even became measurably closer to Opus'
reasoning style after receiving an Opus
prefix. They also ran this interesting
perplexity test asking each model,
essentially, how natural does this other
model's reasoning look to you? And
surprisingly, most of the models they
tested actually assigned a lower
perplexity to some other model's
reasoning than to their own native
reasoning. So, I guess there are some
strange statistical similarities between
reasoning traces produced by completely
different model families. Now, this
absolutely does not prove that one
company distilled another company's
model. The paper itself explicitly warns
that perplexity is a rough measurement
and should not be treated as
confirmation of model similarity. But,
there was one model in particular where
these similarities became pretty
significant for the researchers to dig
much deeper. Because out of all the open
models they have tested, Kimi K3 reacted
unusually strongly to Claude Opus 4.8's
reasoning traces. If they gave Kimi K3
just a tiny prefix, sometimes only
around the first 1% of the trace, its
later reasoning and even its final
visible answer started drifting toward
Claude's style. In one of the examples,
the best pre-filled Kimi response had
roughly twice the n-gram overlap with
Opus answer compared with the
unpre-filled control. And they saw
something similar with GPT-5.6 Soul
reasoning too. So, the researchers asked
an even juicier question. Could this
mean Kimi had actually seen proprietary
reasoning traces during training? And
this [clears throat] is where they tried
a kind of memorization test. They
measured how many attempts each model
would need to exactly reproduce the next
16 tokens from a decoded Claude or GPT
reasoning trace. Kimi K3 was unusually
likely to continue those proprietary
reasoning traces. For Claude traces, it
was 10,000 to 1 million times more
likely to reproduce the next 16 tokens
than models like Deep Sea V4 Flash or
Inkling. But of course, there is a
gigantic asterisk here. Even for Kimi
K3, the absolute probability of
reproducing the trace was still
ridiculously low. On some human last
exam examples, matching just the next 16
tokens of Claude's reasoning would still
take around 10 billion queries on
average. So this is not evidence that
you can just prompt Kimi and suddenly
make it spit out Claude's hidden chain
of thought. It only shows that Kimi K3
was much more likely than the other
models tested to continue those
reasoning traces. Their results do not
establish verbatim memorization. And
they did not prove Moonshot secretly
distill Claude or ChatGPT's reasoning
either. There are a bunch of plausible
confounders here where they could have
shared data set, similar post-training
recipes, model architecture, serving
differences, or maybe reasoning models
just converge towards similar ways of
solving the same problem. Even the
perplexity analysis is still a very
rough measurement to be treated as
confirmation of model similarity. But of
course, this would not stop people from
raising eyebrows. But beside data
extraction and distillation, this paper
also discovered something really
concerning while they're were
experimenting with this encrypted blob.
So a reasoning model can internally
think through a harmful request, work
out specific details, realize what the
user is asking for, and only at the very
end decide not to answer. But once you
have a way to decode the blob, you can
extract that too. In their experiment
with Claude Opus 4.8, they give it a
harmful prompt asking for information
about stealing cars. While Claude Opus
reasons through the request internally,
its actual response still kind of
refused to answer. Then they take the
encrypted reasoning block and replay it
into Haiku 4.5 and now the harmful
details that never appeared in Opus's
visible response become recoverable from
the hidden reasoning. Not to mention the
ability to swap reasoning blobs also
means that the model is now prone to
thought injection attack where a
third-party API provider could inject
fake reasoning traces and extract
sensitive information, but that's just
low-key like a typical prompt injection
attack. So, I kind of just want to
mention it because the term does have a
core ring to it. So, how can you
potentially fix this? Well, most
frontier model providers have already
come up with a way to deal with all this
as they have stated in the paper that
after informing all of them this exploit
is no longer reproducible. And it sound
like they're going to share how they
have fixed it anyway, so the answer
might just be patch where the water is
leaking. And maybe what they have done
is an encryption binding approach, so
only the same model, the same user, and
the same conversation can use that exact
same reasoning trace. But this has
definitely been quite a saga. I would
have never expected be possible to find
exploits that no one have seen before. I
guess the more functionality and
convenience you build the more
vulnerabilities you potentially
introduce. And if you want to learn more
about how LLMs work much more in depth
without being overwhelmed with math, you
should definitely check out my website
intuitiveai.academy which intuitively
explains all of modern LLMs from the
ground up including a lot of advanced
topics. We cover everything from the
basics like the transformer architecture
all the way to more advanced topics like
LoRA, mixture of experts, and RLHF. And
we also just added a new advanced
chapter on optimizers which will bring
you all the way from the classics to the
current frontier techniques. So, whether
you're a student, software dev, founder,
or just someone trying to pivot into AI,
intuitiveai.academy gives you one clean
place to build real technical intuition.
And you can use the code lockin for 35%
off on a yearly membership. And thank
you guys for watching. A big shout out
to Spam Madge, Chris Leduc, Degen,
Robert Zaviasa, Marcelo Ferreria, Poof
and Inu, DX Research Group, Alex,
Midwest Maker, and many others that
support me through Patreon or YouTube.
Follow me on Twitter if you haven't, and
I'll see you in the next one.
