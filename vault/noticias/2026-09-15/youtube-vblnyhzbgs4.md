---
titulo: "5 Voice Agent Failure Modes You'll Hit in Week One — Venky B, Plivo"
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-15'
url: 'https://www.youtube.com/watch?v=vblnYHzBgS4'
thumbnail: media/monitoring/youtube/vblnyhzbgs4.jpg
descricao: "Nearly every intelligence gain in language models over the past year has come from letting them think longer. Voice agents have to turn thinking off, because the budget between a user finishing a sentence and the agent starting to speak is measured in hundreds of milliseconds. Venky B is founder and CEO of Plivo, which carries over a billion voice calls a month and has been building telephony infrastructure since 2011, and this talk is a tour of what breaks when a voice agent leaves the demo and meets production. On latency his numbers are blunt. Teams aim for under 550 milliseconds and most land between 750 and 1,200, and past that users simply hang up. His team's answer is smaller open source models hosted themselves, targeting under 300 milliseconds, chosen partly on how many tokens a l..."
resumo: "A talk from Plivo's founder on the five common ways voice AI agents break once they move from a working dev prototype into real production deployments, drawing on patterns observed across their voice call traffic. It opens with background on the company's shift from voice/SMS APIs to a full-stack AI agent platform before walking through the failure modes."
tags: {}
fontes:
  - 'https://x.com/bevenky'
---

## Descrição

Nearly every intelligence gain in language models over the past year has come from letting them think longer. Voice agents have to turn thinking off, because the budget between a user finishing a sentence and the agent starting to speak is measured in hundreds of milliseconds. Venky B is founder and CEO of Plivo, which carries over a billion voice calls a month and has been building telephony infrastructure since 2011, and this talk is a tour of what breaks when a voice agent leaves the demo and meets production. On latency his numbers are blunt. Teams aim for under 550 milliseconds and most land between 750 and 1,200, and past that users simply hang up. His team's answer is smaller open source models hosted themselves, targeting under 300 milliseconds, chosen partly on how many tokens a language needs per word.

The failure he says wrecks half of all deployments is data collection, and his fix is to stop treating it as transcription at all. Decide the shape before you ask. A phone number is a typed field with a length and a validator, so a stray letter in the middle is either corrected with confidence or sent back to the caller, and evaluation happens per field as a unit test rather than end to end. That reframing took his accuracy from roughly 30 percent to the mid nineties with no fine tuning. He is equally specific about transcription being brittle by default, especially with proper nouns and code switched languages, and about never feeding model output straight into speech synthesis. His own benchmark for a vendor is whether it can pronounce his surname and his company's name.

Speaker info:
https://x.com/bevenky
  / bevenky  

Timestamps:
0:00 - Where voice agents break between demo and production
2:20 - A billion calls a month
4:28 - The pipeline everyone builds first
5:34 - Failure one: latency and time to first audio
6:42 - Balancing cost, intelligence, and latency
7:48 - Why thinking models do not fit
9:56 - Choosing and sizing open source models
13:06 - Failure two: assume transcription is brittle
15:14 - Code switched languages break everything downstream
16:17 - Dynamic keyword boosting and LLM post processing
18:24 - Failure three: collect data as typed fields
20:31 - Relative dates and other traps
21:37 - Field level evals instead of end to end
22:47 - Failure four: normalize before synthesis
25:00 - Failure five: turn detection and barge in

## Transcrição

[music]
Let's just do a couple of quick
questions and then we'll jump right in.
Uh, how many of us in the room here have
built voice AI agents?
Okay, that's a that's a pretty good
audience here. And how many of you guys
have built AI agents that have been
deployed in production?
Not bad. Okay, cool. So, uh we'll talk
about what typically happens, right?
Like everyone's talking about wise AI
agents. Uh the
you know, one pill solution to pretty
much everything in the world today uh is
is wise agents. So, everyone's building
one and trying to deploy that. They
sound great when you're sort of building
that in your dev sort of landscape and
then the moment you take this to from a
proof of concept to production things
start failing. Uh so we'll walk through
these five different angles of like how
uh or what we have seen uh at PO with
VIA agents but just before that a quick
uh intro from from my side uh I am Wenke
the founder and CEO uh she used the
title agent engineering manager u I'm
calling myself chief agent officer uh
from a from a title standpoint u okay so
what is what is uh you know why why are
even qualified for this this discussion
and and like uh what what are we seeing
that a lot of companies don't get to
see? I I'll talk a bit about our journey
in terms of like how we've uh come along
so far and then jump right in. Uh you
know we were we've been around for about
14 years. Our journey has been a
developer API platform and then now an
uh you know an AI agent business. We
started with voice and SMS APIs back in
the day uh 2011 and then uh you know now
we are primarily focused on our AI agent
offering uh the the full stack on our
platform. We we see over a billion voice
calls each month across the globe. Uh
and which is where we've seen a lot of
these uh you know patterns emerge in
terms of like how when we work with our
customers what happens on their voice
agents in in production. Uh we're a uh
90 member team and uh we've we have uh
50 million funding in the bank. Fun
fact, this is not from external VC
investors. This is all from being a
profitable company having put that cash
in the bank over over these years. Uh
some customers we we power across the
globe. Uh you know, we've just left some
some logos in there. But primarily from
an offering standpoint, u I would sort
of cohort this into three different
buckets. One is a programmable AI agent
offering. We call it uh I mean it's a
speech pipeline, not a true
speech-to-pech product yet, but that's a
that's a programmable offering. We also
have an AI agent studio. It's a no code
visual uh builder. And then we like I
said, we started with voice APIs. So we
obviously have built this out over the
last 14 years, the SIP trunking and the
audio streaming layers. So we don't rely
on other folks for the telefony or the
carrier layer. Like that's the
breadandbut business we've built over
all these years and and that's on top of
which our AI uh agent platform sits.
Okay, with that uh let's get into this,
right, which I'm I'm sure since you guys
have all built AI agents, you've all
seen this or you know built this in in
one manner or another and we'll spend
more time on this in terms of like how
uh the entire pipeline looks, right? Uh
what we see with customers is and and
I'm sure you guys can all relate to this
is you know anyone thinking about AI
agents what they do is they pick a bunch
of these orchestration frameworks and
they do a pretty good job live kit or a
pipecat you know build their AI oen on
top of that uh they think they can just
sort of orchestrate these different four
layers speechtoext lm uh and and TTS
with turn detection in between and we're
off to the races like my AI engine agent
works in a in a P and it's good to work
in production. Uh typically that's what
happens. They sort of measure their
latencies and you can see some
indicative latencies on on this slide at
at each layer and they're like yeah this
this uh seems good for me for what I
need. So let let's position production
and then the the production w uh sort of
start to kick in and and you see all
sort of failure modes which we are going
to spend you know most of the time on on
in this talk at least. Uh I've kept some
time at the end for Q&A if you guys want
to have uh you know questions but we'll
jump right in from from this to uh you
know different failure modes we see.
Let's start with
you know the first one which everyone
talks about like this is the most spoken
about failure mode which is latency. U I
think we have a few AI agent talks today
or AI agent talks today. Um I'm pretty
sure like everyone everyone's going to
touch upon this specific failure mode
which is why I'm bringing this right up
uh in in terms of uh you know some like
how this entire experience is for uh
users right uh typically most folks
measure this by time to first audio so
the time when you user stop speaking to
your agent starts speaking right and I
think you've you've probably seen this
if you guys have built voice agents on,
you know, what uh good or natural feels
like, what uh sort of annoying feels
like or noticeable feels like, and then
what annoying feels like, which is, you
know, different tiered steps. Uh we
notice, you know, most people want to be
under 550 cuz that's what's advertised
by, you know, platforms or uh you know,
solutions or or or or layers. But I
think most end up between 750 to 1.2. uh
that's where most of the folks end up
at. Uh the really bad performing ones
end up you know more than 1.2 and then
you start to see users uh hang up. Uh
now I I'll share with you like what
we've seen practically in in uh
production with uh customers using this
with at at different layers and then you
know solutions to uh some of these. The
way we want to think about this layer is
sort of a balance between these three
which is cost, intelligence and latency,
right? And and and why do I bring these
three up? Because they're sort of
interrelated. I think one of the things
I was just chatting with uh you know a
couple of folks outside one of the
things last one year we've seen lot of
innovations lot of intelligence spike on
the LLM side of uh things right and most
of the you know intelligence has come in
in terms of thinking or uh you know
reinforcement learning and and so on and
so forth the irony with voice agents is
like almost always your the the LLM or
the agent that's talking has to have
thinking turned
Right. So all the advancements we've had
in the LLM layer in the last one year
like none of that even apply here now.
Right? You obviously you have you know
better models that can do you know
better instruction following or tool
calling but pretty much all of your
intelligence that's been built in on the
thinking layer is all off by default if
you want it to be fast enough. So so
that's one of the ironies that we come
up with. So then how do you sort of
balance intelligent cost and latency?
Let's let's look at some of these uh you
know options uh that are out there in
the market right so and I'm specifically
picking LLM because if you looked at the
previous chart LLM is u you know sort of
your highest latency bucket that adds to
this right and uh if you look at you
know frontier models which I think most
folks start by default your your openi
your clouds your geminis u you know p50
ttfftd is roughly around 450 to 500 on
on a good day and it can get spiky,
right? It can it can uh you know P90 P95
can go easily upwards of 1.2 1.3 seconds
even uh and and that's not good for the
overall agent experience.
So so that so that's your frontier
model. Now there's another options which
is your your cerebrus or or the gro that
is famous and popular for spitting out a
lot of tokens or or tokens very fast,
right? uh these work but for you to get
dedicated latency or time to first token
on these you need dedicated capacity and
that is really expensive that's where I
spoke about the cost uh as as being one
of the things to balance right it's
really expensive and then like you talk
to anyone from the gro team or the
cerebrus team they'll tell you you need
to book 12 months in advance for
dedicated capacity they're booked out
for the next 12 months so so that's
that's a pretty expensive option and
then you really need to be sure that the
model you're deploying on some of these
infra layers uh will be here 12 months
from now and and it's a it's a big
investment and a big unknown. So, so
what's a realistic option for production
grade uh
agents that are that are good quality
and end up balancing uh three of these u
this is what has worked for us u which
is the open source models u there are
obviously a lot of them in terms of like
the variety and and variations you can
pick I'm specifically talking about the
two we work with u quen 3.5 and gemma
four. These are uh you know kind of
cutting edge open source models right uh
out in the market right now and we've
done a lot of benchmarking around this
in how they work. It it can be scary to
think like okay I have the models now I
have to host them you know run them on
my own GPUs and so on and so forth but
if you are consistently targeting under
300 ms u this we've seen this to be a a
great option to balance between latency
cost and intelligence now some more deep
dive here if you're doing only English
uh quen 3.5 or GMA both work fine but if
you're doing multilingual uh right
international audiences different
languages uh Gemma 4 is a much better
model for that uh we've seen uh token
fertility evals essentially what that
means is if if I were to dejargonize
that is like how many tokens does it
take to generate one word in that
language okay so Gemma is much much
better at least 2.5 to 3x better than
quen 3.5 from that perspective so your
time to words is much faster on Gemma or
everything else equal right on a on a
multilingual basis. Now what sizes do
you pick at the LLM layer? Uh the
mixture of expert usually works fine. Uh
the three or four billion mixture of
expert usually works fine. The the
problem with mixture of expert is like
if anyone goes down wants to go down the
direction of fine-tuning that can be a
challenge uh because fine-tuning mixture
of experts models are not easy. Uh you
can end up breaking the model uh a a lot
of times. So, so that's one challenge we
see with Make sure experts, but usually
out of the box, it gets you 90% closer
to where you want to be like even
without any fine-tuning or or or custom
work done on the model. Uh so that's the
advantage of mixer experts. Uh now, if
you want to fine-tune and and you you
want to go deeper and say like look, I'm
working for a specific domain,
healthcare, what have you, right? uh and
I want to make sure I I'm able to
fine-tune my model. You want to start at
least with uh the 8 billion 12 billion
at least uh from where we are today.
Maybe maybe six months from now a 4
billion 4 billion model beats the 8
billion model uh hands down. But for
today uh what we've seen is you minimum
need a 8 billion or 12 billion model. Uh
cuz you're looking for two things in
these models. One obviously fast tokens
but uh good instruction following. Okay.
And the second thing is like very high
uh success ratio in tool calling because
if you can do these two things well then
you are on to like 70 80% there from not
even having to fine-tune it fine-tune
any model like models will work out of
the box right u so so that's uh been our
recipe we've actually uh we run two
flavors one a fine tune model
for specific industries and then for uh
you know most generic use cases uh MOE
model just works out of the box. Uh
there are a few more tips and tricks
we'll talk about in the upcoming slides
where we see failure models, but but
that's where we stand from a from a
latency LLM standpoint. Um all right,
I'm running tight on time, so I'm going
to fast track this. U now there are a
couple of other flavors in this. Uh
people build agents with a a mixture of
models. What they do is you know for u
the the talking part of it they have a
conversational model which is a much
lower smaller model and then you know
maybe even a three billion model and
then for tool calling they have a much
larger model so that they have a
improved tool calling success ratio
there. Uh
sorry the second one is uh assume your
transcriptions are going to be brittle
like that's that's uh something you want
to sort of uh live by when you're
building AI agents even if you have the
best transcription engine out there and
I I I'll show you why right like the the
the state-of-the-art transcription
engines out out in the market u you know
sort of get you to four to 6% word error
rate right and this is on known eval
sets on real world noisy calls with you
know sort of uh accents like people
having different sort of accents uh
domain vocabulary and so on and so forth
like those usually end up in the double
digits from a word erate perspective
right uh now you obviously you can
fine-tune you know pick up an open
source model and fine-tune uh but we see
typically like what breaks here often
and there are patterns s here in terms
of what breaks. So, proper nouns,
jarens, uh phone numbers like random
missing digits with phone numbers, uh
wrong substitutions. I I'll walk through
some examples of like how you solve for
these addresses when you're trying to
collect a long address. Uh you know, the
the transcription engine could just end
up missing some parts of it.
Code switch languages. I I'll just take
a example of a language I speak because
that's was easy for me to put on the
slide. uh where you know like if you
were to sort of take English but written
in a different script uh that's what's
used for Hindi right like this is
English written in that script right
whereas like the actual English version
of this is hello how are you so if if
I'm addressing an audience in a
different country where I have code
switched languages and I start getting
my English in a different uh sort of
script everything starts breaking from
the transcription engine to the LLM
layer and then beyond because your LLM
starts then producing output in that
sort of script a lot of times and then
your TTS messes up. Okay. So, so this is
uh very important to be careful about
and if you want to build your agent
independent of the transcription engine,
you need to build a layer that
normalizes all of this, right? We'll
talk about solutions in a minute. And
there is the other case which is Hindi
in Latin or or or you know Roman, right?
Which is like this is Hindi but it reads
English which again messes up everything
uh you know downstream. Those are just
examples. This applies to, you know,
Arabic, Mandarin, uh, Japanese, what
have you. Uh, pretty much any language.
So, what actually moves the needle with
a at the transcription layer? Uh, for
prop proper nouns, we recommend uh you
using not just keyword boosting. I think
a lot of transcription engine engines
provide you keyword boosting where you
can put in specific words into their
engine, but doing dynamic keyword
boosting. What that means is don't keep
the keyword for the entire state of the
call. just add that dynamically when you
think you need that as an answer so that
you get the highest accuracy. Meaning at
different states of the call, the
transcription engine will have different
uh keywords boosted during different
phases, right? Uh and that's what we've
seen works best because if you just
pollute your context of the
transcription engine with tons of
keywords, it'll start hallucinating
again, right? So, so that's what we see
typically working best. Uh
yeah, post-process post-process your
transcripts with an LLM, right? Cuz your
LLM has domain context. Your
transcription engine does not. So a lot
of words that it would say uh I'll give
you some examples may not make sense.
This is transcription like a phone
number from a transcription engine.
Right? Like what do you think that E is?
Right? If you give it to an LM, it knows
that's a three. Similarly, like what
that one is, it's a digit one. So, so
your transcription engine a lot of times
could mess that up, but when you
postprocess it with the LLM layer, it'll
instantly correct that from a collection
standpoint. I mean, uh, and and the last
one, like I said, uh, transliteration is
your ST output that's sort of u, you
know, multilingual also gets normalized
using either an NLM you first
transliterated or, you know, use some
kind of a neural uh, transliteration
engine. There are a lot of them open
source. You can just pick one of them,
right? Uh that would do all of that work
for you. Send cleaned transcripts
consistently independent of the
transcription engine to your LLM.
All right. The third one we typically
see is collecting data. This is where I
think 50 to 60% of AI agents mess up
pretty badly. Uh and like we like to
think of it as
a UX problem. Uh but just for voice. So
think data models uh and not a
transcript coming into an LLM and and
trying to figure out what the transcript
said. So let's take some inspiration
from uh I'm assuming most of us are
developers here um you know take
inspiration from Python's data classes
pantic zod from Typescript or form
fields in the UI right like if you start
thinking of it from that problem
statement we have seen accuracy grow up
from grow from 30% to like 95% from a
data collection standpoint when you
start thinking in that manner. So like
decide your shape before you ask, right?
Like instead of keeping it open-ended,
can you keep it constrained? So can can
a phone number be a phone number type
field? The moment you do that, right,
you know like how many digits it needs
to have. You can do validation on on top
of that, right? And then what sort of
allowed values can even be there. So in
the previous example we saw if an E
comes in in middle of a phone number and
you know it's a phone number you
instantly know like either you smart
guess that to three and confirm that
with a user or you know that's an error
and then you validated that and asked
the user to repeat again right so so
that's I think one of the common
patterns we've seen here from from a a
collection pattern name I think is the
is the interesting one I've just picked
a you know a a hard to pronounce name
like There's no way a human is going to
get this right and and no way a
transcription engine will get this
right. How many ever times you do this
right? So the moment you start thinking
of this as fields and then have rules
and then confirmation mechanisms on on
spelling this uh you know sort of uh
letter by letter only then you kind of
get it right otherwise it's going to
mess up pretty badly in terms of how you
collect this on a voice call and and
that's just an example of you know what
u I'm talking about in terms of the the
data collection piece of it.
Another place where it goes badly
dramatically is relative uh values. Date
being one of the examples. If somebody
says next week uh Wednesday 8, 8 could
mean 8:00 a.m. 8:00 p.m. and then
figuring out what that date actually is.
Again, now becomes a very constrained
problem. If you knew this was a datetime
field and I I'm collecting a datetime
field and then you take the current date
and then figure out what this value
would be bases that, right? So, so
that's how you want to make sure like uh
you do this with a combination of the
LLM with the tool calling and the tool
calling is doing a lot of this heavy
lifting for you from a from a field
standpoint.
Yeah. And then you make you you run like
this from a unit test perspective. So
all of your u evals need to start
treating these fields as unit tests. And
as long as your unit tests uh sort of
validate and pass, you know, your agent
is going to be uh sort of reliable and
repeatable. You don't, you know, run uh
hundreds of end to end agent test cases
just to find out, you know, one field
collection is broken. You do your eval
at a field level and a unit test uh
level.
And then yeah, like I said, I think u
you this this mindset makes everything
more structured instead of hoping I'll
put a ton of prompt, keep changing, you
know, the prompt by a few uh characters
every time and somehow my prompt
engineering is going to make LLM much
more instruction tuned and sort of
magically start following some of these
things. So in fact u like I said right
like we have seen us get to 95 97%
accuracy without having to fine-tune a
model right and then and the trick is
basically like just breaking down your
context of what the agent is doing at
that point with specific u states of
what the agent is going through.
All right u I'm just going to quickly u
skip through this from a
time standpoint. I just see I got three
more minutes. Um hopefully that's a bug
but but we'll leave it at that. Okay. Um
so so this is the fourth area where we
see issues coming in. Most folks take
the LLM output and then we send it to a
TTS. Obviously I think there are a lot
of good TTS's in the market that take
care of a lot of heavy lifting but a lot
of times it it messes up. Uh what we
recommend and what we've seen is you
usually want to have a normalization
layer between your LLM and what is fed
to a TTS. You don't send your LLM output
directly to a TTS, right? And and we'll
just walk through some examples. The
basics which is strip emojis uh markdown
before before any synthesis into the
TTS. Most orchestration pipelines do
this like you know a live kit or a
pipecat would do that for you if you
just set a few flags. So I but but just
make sure if you're not using them or
buildings from scratch that you've set
this explicitly because you don't want
an emoji showing up on on on something
read out or you know markdown showing up
there.
Okay. I think I think some more common
ones uh custom uh dictionaries most TTS
engines provide this to you like how to
pronounce custom words whether it's you
know proper nouns brands uh acronyms and
so on and so forth. So set those in uh
when you go from your LLM to your TTS
output because if you don't, you're
going to mess that up. And I I'll I'll
show you an example of like how we test
that. Uh the the other one is like most
engines also give you speed. So if you
know you're pronouncing an entity, slow
down. Have your agent slow down. So at
point 8x or 7x so that it it's able to
like inunciate on that specific entity
and and doesn't mess up how it's
pronouncing an email or a phone number
or a name letter by letter
and yeah just normalize all the messy
stuff right like emails currency dates
don't leave it to the TTS to do it uh
most of them do it but don't leave it to
the TTS to do it like build your
normalization layer at your end so that
tomorrow you think you need to switch
TTS or you know for whatever reason the
first one's down and you want to use
another TTS you're able to sort of not
rely natively on the TTS's engine but
you are building this in-house uh for
for this to be managed
and then yeah u I think I don't have my
batch here but I I don't have my last
name on that so my first test is if it
cannot pronounce my last name or my
company's name it's already dropping the
ball so my last name is uh Balas
Subramanion and if you cannot pronounce
that using a voice AI agent uh like
that's a check for me. I I know like uh
you know the agent will mess up a lot of
words that uh you know need to be
spelled out day by day. The second one
is our company name Po. So a lot of
engines pronounce pronounce it pivo or
uh pleo and and so on and so forth. But
but I think specifically being able to
control this in your pipeline is super
critical. And then if you're building a
if you're building a customerf facing
product then then um you know sort of
give this option to your customers. All
right I'm just going to skim through the
the the last two slides. U I'm I'm
running badly over time. Uturn
detection. I think this is it own
separate topic but I'm just going to
quickly pull up all the points so you
guys can skim through that and if if you
need a chat u after this we can we can
talk about this. Right. Uh
I'm just going to leave that for like
five seconds and then and then we can
chat about this offline. I'm quite over
time. And then the the the last one is
uh bargin and and back channeling. I
think there's a lot of talk around
speech to speech models that do some of
this, but we've been able to see how we
could do all of this in speech to speech
pipelines. You really don't need a
speech to speech model to do all of this
up. Uh again, I'll just I just put put
this up on the slide and and sort of
close at that. Um
all right I don't think we have time for
questions we can take them offline if
you have any time but uh hopefully this
was helpful and gave you some insights
on uh what we are seeing in productions
uh with billions of calls at scale. All
right thanks
