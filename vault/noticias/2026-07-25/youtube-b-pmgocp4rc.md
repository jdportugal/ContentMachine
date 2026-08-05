---
titulo: 'Evaling Video Slop — Maor Bril, Character.ai'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-07-25'
url: 'https://www.youtube.com/watch?v=b_PmGocP4rc'
thumbnail: 'https://i.ytimg.com/vi/b_PmGocP4rc/maxresdefault.jpg'
descricao: "A generated clip where the character stands frozen for four seconds can still score well, because the judge rewarded the gloss and the vibe instead of what actually happened. That failure is the whole problem with evaling video: CLIP score misses temporal incoherence, a team watching clips on Friday does not scale, and any AI judge you wire up drifts from human preference unless you measure the drift. Video breaks the text playbook because it has to hold temporal consistency, shot continuity, and a coherent story across frames, not just look good in a single still. The fix that stuck was to stop scoring and start comparing. Absolute scores collapsed to one dimension, but pairwise preference, is B a better story than A, held up, so Maor Bril's team trained a Qwen3-VL judge with Bradley-Terr..."
resumo: 'O vídeo aborda o problema da avaliação de vídeos gerados por IA: apesar de modelos como Kling, Veo ou Sora produzirem vídeo quase gratuitamente, as métricas herdadas da era do texto e da imagem (CLIP score, LPIPS) só analisam fotogramas isolados e não detetam alucinações nem falhas de física. Defende-se que o vídeo é um meio narrativo, pelo que a avaliação tem de verificar se a história é efetivam...'
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
  - 'https://x.com/maorbril'
  - 'https://www.linkedin.com/in/maorbril'
  - 'https://github.com/character-ai/judgejudy'
---

## Descrição

A generated clip where the character stands frozen for four seconds can still score well, because the judge rewarded the gloss and the vibe instead of what actually happened. That failure is the whole problem with evaling video: CLIP score misses temporal incoherence, a team watching clips on Friday does not scale, and any AI judge you wire up drifts from human preference unless you measure the drift. Video breaks the text playbook because it has to hold temporal consistency, shot continuity, and a coherent story across frames, not just look good in a single still.

The fix that stuck was to stop scoring and start comparing. Absolute scores collapsed to one dimension, but pairwise preference, is B a better story than A, held up, so Maor Bril's team trained a Qwen3-VL judge with Bradley-Terry loss on pairs of real and deliberately broken footage to catch slop before it ships. Drift is cheapest to catch early, especially on longer form video, so the judge runs as a regression gate in CI: every AgentX release at Character.ai clears an eval wall, calibrated against human scores, before users ever see it.

Speaker info:
- https://x.com/maorbril
- https://www.linkedin.com/in/maorbril
- https://github.com/character-ai/judgejudy

Timestamps:
0:00 - Introduction: evaluating AI generated video
1:19 - Why video generation drifts between frames
3:14 - Story and sound: what a clip has to get right
4:43 - LLM as a judge, and catching drift early
7:01 - Story and sound failure modes
8:28 - Small model vs bigger model as judge
9:20 - Don't score, compare: pairwise preference
10:47 - When the judge scores vibe over substance
11:53 - Pairing real footage to train a quality detector
13:27 - Self verification in the generation loop
15:05 - Q&A

## Transcrição

[music]
>> So, hi. I'm Mayur. I've been with
Character for a bit over 2 years and
we'll talk about
AI slop, right?
I think that, you know,
when we look at video generations as a
whole, right? We have like two kind of
uh parallel tracks. One is the the video
generation, which
became insanely good from from models
like Kling and SeaDance and VEO and
Sora. We still
remember Sora.
But but but the part that got left
behind is how we evaluate the quality of
the video that was
generated, right? So, on on the one
hand, um we we we still kind of squint
at it and decide whether or not it's
it's good, but on the other hand, we
know that the generation has gotten a
lot better. And when when we when we we
look at at X or
whatever
social you're you're you're consuming
your content on, there are a lot of
guides on how to create amazing videos
with um this model or or that. So, the
hard part was never how to make video.
The hard part was how do we generate
um
good enough video and how do we judge if
the video is good enough? So, so now
we've gotten to a world where
the the generation of video is basically
free, right? Free as especially when you
compare it to how much studios would
charge. Um and but but the problem is
the most the grand majority of videos
that is generated is not that good,
right? We have like
a lot of hallucinations like a third
limb, uh opening and closing the the
door at the same time, hovering,
physics, um etc. So, unfortunately, in
order to get high high high-quality
content, we need a human to judge. And I
I know when was the last time you've
seen how someone is creating these
long-form
um
generated video. It's usually a lot of
shorter generations and a lot of
editing.
The problem is because we're using a lot
of the tools that we built for the ticks
for the text era, for the image era, for
videos, right? We're using things like
clip score, which is is great to to to
judge a single frame.
Things like
LP
IPS will help us kind of detect the
drift between frames, but we don't have
I mean but the problem is when you kind
of combine all these together, all these
tools are good at watching the
individual frames. They're good at
checking this this this this does this
specific frame does it match the the
prompt that generated it, right? It will
check consistency between frames and it
will check whether or not it match the
prompt that the drove it. But but what
it won't do it doesn't tell you if
if it's if you told if you told the
story that you you meant to tell, right?
If you think about what is video, video
is a storytelling medium.
Video is just another form on how we
tell a story, right? From for
any type of story. So so one of one of
the things we have to look at, does it
tell the actual story? Does the physics
make sense? Like for example, if you
want a video of a character walking
downstairs, does it actually walk or or
hover?
Does the character stay the same
character across multiple shots?
Does the pacing make sense? Like you
know, for example, people take time
going from one place to another. We need
to make sure that the pacing makes sense
as well. And especially when we add
audio, we want to make sure that the
audio is kind of synced with the
imagery. Like for example, if someone is
slamming a door, we want that that that
that that that sound of the door being
slammed to be exactly when the door is
actually being slammed.
Now, the the next iteration we all went
to a while ago. We started using LLM as
a judge for everything and we have
amazing foundational models that we just
throw
videos at them. The problem with them is
that A, they're slow. B, they're only as
good as your prompt and multiple people
will prompt multiple ways and the same
model may respond in a very very
different way. And sometimes the prompt
we use like is it consistent?
Does this match the the prompt? But but
then the question we really care about
is it good? And the answer varies.
So,
oops, sorry about that. So, our first
iteration is like let's take all these
things and build a repeatable
benchmark on how we test video that we
can rerun over and over and over again.
So, so that combines both metrics as I
said earlier that that knows how to view
individual frames, but also
consistent LLM as a judge, right? Where
we also use human annotation to to
calibrate the the LLM as a judge. So,
for every report that we generate with
that harness,
we're able to have humans annotate it
and basically feed that feedback back in
into the the
the LLM as a judge prompt to make sure
that it's it's aligned with what I think
or what the annotator thought is good.
And and we use it to to score the
videos. The problem with
this approach, it's very slow, it's very
expensive and especially when we we want
to bring it in for our users to be able
to
generate a lot of video because creation
is a very hard process. And
so the problem as I said, the problem is
when when So, this is a slow process and
we need to bring it as close to the
users as possible and also earlier into
the process. The reason for that is
if we take a look at all the metrics and
there's there mistakes that we can find
earlier than
than later than it's a lot cheaper to
correct that that particular mistake.
So, for
example, right? On
on the left we have
two starting frames of different
different shots, right? But it's easy to
to correct to to view it at this point
and see did the character drift between
frame one and and frame two because
those frames will be used as starting
frames to generate videos. So, if you
can correct catch the drift at this
point and correct it, then
then it's much cheaper to generate the
video as a whole because we we can
correct it at a much
cheaper cost. And the same thing applies
when we look at longer form video,
right? When we see all these three,
four, five-minute long videos, they're
usually a collection of a lot of shorter
videos. And being able to catch a
six-second generation that drifted and
and regenerated that regenerate that
before we combine the whole video will
end up being a better result as a whole.
Um
>> [clears throat]
>> And
now the the other problem what we're
trying to solve is some of these axes,
right? Only exist across time, right?
So, for example, when we look at the
right? We we mentioned the story, right?
So, does does the story that we're
trying to tell with that video, does it
hold in that video? Does the video tell
the exact story? Does the pacing make
sense, right? And we mentioned the the
the the sound. So,
as I said, right? The the you know, the
underlying goal is to bring that evals
closer to to the to the the online
generation because the sooner we're able
to catch those mistakes, we were to
the sooner we're able to catch that
drift, right? Then it's um
it's much easier, much much cheaper to
fix.
Now,
so so that now now the problem is that
as I said, this is a very slow process.
So
the the solution is actually actually to
take all these committee of experts and
distill it into one small model that is
also very very fast, but
it is able to give us
a response that is not whether or not
this video is slap or not, but why is it
slap? Right? Why Why is that video
scored low versus the other? Because for
example, it added an extra limb, because
it didn't obey physics, because
the
audio was was out of sync. So so the the
goal was a build it on top of a small
VLM and why is it a VLM? VLM because we
needed the video
the model to be able to see the image,
but also we needed to work fast, right?
Because we bought it closer to the
generation, where in fact it takes about
the model we have trained, it takes
about 3 seconds to score a three
15 second video.
Now, the
we we also
tested a bigger model and the results
were better, but it was significantly
slower. And and the decision was to go
with the the
the smaller model because the the added
value from the bigger model didn't
justify the the the
the slowness.
The other very
interesting realization we came to is
don't score compare. What does that
mean? For example, if I'll ask any
person in this room to look at a
particular video and and rank it from 1
to 10 on storytelling, right? I'm pretty
sure that you know, what will be a six
for you will be a five for you, will be
a four for you and and and an and an
eight for you. Right? But if I show you
two videos and I'll ask you which one of
them is telling a better story, the
grand majority will probably agree that
B is telling a better story than A,
right? And if you do it enough times,
then it it uh it's easy to um uh
generalize the model at um towards
detecting what's
better uh uh versus
uh not.
So,
>> [clears throat]
>> we trained on pairs, right? Um uh A
versus B as opposed to 1 through 10.
Now, we manufactured badness. So,
luckily, the internet is full of very
high-quality videos, and it's very very
easy to get good videos, and it was very
fun to create bad videos A by either
corrupting
good videos or by, you know, just
generating random slop.
>> [clears throat]
>> Um
Now,
we shipped V1, and it was so wrong. It
It was wrong, but it was wrong in a very
confident way. So, uh uh for for for
example, the
the frame you see here is from from a
video that the model scored 9.2 on the
camera work, and the camera didn't move.
For for 4 seconds, it was like a still
image of the same character, but but but
the model was was very very happy with
with, you know, the
cinematography.
Um
so, the physics in in in in some
other videos, which I'm not showing the
cuz of uh time limitations, it says that
the physics look great, but it said it
on on ghosts hovering and people flying,
etc.
So,
the I mean, so the so then the question
is like, why was it wrong? The The
reason it was wrong is because how we
generated that data, right? It It um it
scored the vibe as opposed to the the
the axes. So, it it learned how to how
to detect um cohe- coherent videos and
it learned how to detect the the the the
the the artificial artifacts. Basically,
the gloss of the video as opposed to uh
whether or not the video actually um
told the the the the the Sorry.
The videos actually told the the the
story. And
um
And so so so the solution was to fix the
data set. And so the way we we fixed the
the the the the the data set, we
actually I
uh um I started pairing real footage
versus
um AI footage. Now, the risk with that
and that that's the reason why I voted I
voted I avoided doing it at first is
because I didn't didn't want to create
an AI detector, right? Because if you
start creating pairs of good is is is
human-generated video and bad is AI uh
uh video, then then there's a very big
chance of of the model overfitting and
becoming an AI detector as opposed to a
um uh uh
video quality detector. So, the
There are two things I did in order to
avoid that. A, I made sure that the
encoding is is consistent across both
sides of of the equation. So, uh there
there there there's no
um artificial artifacts for for video A
versus video B.
Uh and I I used the exact same method of
annotating both videos. So, so both the
axes So, all the axes in those videos
were annotated in the same way.
Uh and surprise, it it turned out pretty
awesome. Um and so so so now what what
what we're able to do, especially when
we you looking at at at videos,
A, we we changed from a very complex
pipeline, right? To an
agentic workflow. The reason behind this
is, a, the pipelines work great if you
have a very very unique use case. But
one once you put put
put it in front of users, they'll have a
very very distinct story that they want
to tell with their own characters, with
with their own images, and their own
voice. So that's where it starts to
drift.
But by providing the agents with tools
to validate the quality of of the
outputs it's creating, it's able to
adapt to changes better. It's also able
to verify its own work and and and fix
things as they go along. So if you're
going to steal from from these
from the stock of a few things, one, go
relative, not absolute, right? As I
explained earlier,
the the value of
comparing video A versus
video B will always give you a better
result going forward.
Be score the real axis that you care
about. So if you care about
storytelling, if you care about pacing,
if you care about
physics,
score those axes.
Don't expect them to
miraculously appear. And put eval inside
the
generation loop, right? Especially if
your goal is is is is to have a higher
quality of generation, get the the eval
as close to to the generation loop loop
as possible.
Eventually evaluate it as a story.
Videos are stories. Videos are just
another way for us to tell stories to
others. And
Thank thank you very much.
>> [applause]
>> All right. Any questions? Okay, down
here. Awesome.
All right, I got two down here. Here you
go.
>> Hi. How do you eval sound?
Sound and video matching.
>> I'm I'm I'm Can you you repeat?
>> How do you evaluate sound? Sound effects
and matching with the video.
>> oh yeah.
That's a fantastic question. So,
um
so so sound is actually a a combination
of few things. One, I'm using
Atmos to
to to make sure that that that the the
the sound quality
is high enough and is
understandable. B, the model will will
will will learn to learn to identify key
frames.
Right? And and and especially because
when I feed something into the model,
it's it can be just a video or it can be
the video plus the prompt that generated
that video. So, for example, if the
prompt will say
the door slammed, right? It will look
for a door being slammed and
and and will will will match the sound
at that same frame.
I
Did I answer your question?
>> How does the model recognize sound?
>> Uh so, it's both by using um um
uh
Atmos, but
and also to to correlate the
So, for example, when it's looking at
the frames, right? It's making sure that
that
for example, the door being slammed at
frame six, frame six has a a specific
timestamp. So, it's looking for that
that
spike in the sound at that timestamp. It
doesn't know that it is that sound, but
it's looking for a specific spike of
sound at at that timestamp.
>> What about lip syncing?
>> Lip syncing is an unsolved problem yet.
>> [laughter]
>> One question.
>> We're trying though.
Yeah.
Yeah, I said the
the question was what about lip syncing?
>> Oh, Well, wasn't me, but
I guess the lip syncing answer would be
interesting before I ask my question.
>> Uh yeah, um as I said, it is an unsolved
problem still. We're still
working through it.
Especially for us,
some some of the characters that we're
trying to do a talking head are humans,
right? Which, you know,
we can look at the different techniques
to trying to identify the the lips, but
some of them are just talking, you know,
talking animations
that have no real correlation between,
you know, the the movement of of of the
mouth and and and speech. So,
unfortunately, I don't have a solution
for that yet.
>> So, I'm
So, I'm curious about, for example, if
you wanted to further enrich the data
set with human evaluation.
>> Yes.
>> Um
the question of of taste in what is
good, because I think there is a big
question mark about is that is that
going to remain the domain of humans?
But, I've also seen people say that,
well, most humans
they have terrible taste anyway in in
videos and games and books.
>> Fair.
>> Um so, how would you construct and align
sort of like any human
judges?
>> Yeah. So,
So, So, So, this is actually solved at
first at the the the Judge Judy part,
where
every report it will generate a human
can go and annotate it. And we actually
we
we do that. We we will periodically have
sessions where everyone spends 10 to 15
minutes just
annotating videos. And and that usually
happens on
multiple axes. I won't ask everyone to
annotate the same video on on 10
different things. It'll It'll It'll be
random. And I use
the data to to calibrate the the AI
judges. And and the results from that is
actually being served as as a data set
for for training for the next version of
of that model. So, it's a process that
does take a little bit of time and and
hopefully and and it does evolve over
time. Uh but it's not immediate you
know, because also
taste is very subjective and and things
that are great for me, you know, some
that I think are fantastics some people
that come and say
are you sure they're great because you
know
So, yeah, it's it's it's a process and
and and and I use the human
feedback to calibrate the models all the
time.
>> Um how did you land on the Quan small
VLM? Did you try any others?
>> Uh I did. So, so the intent I had was to
A you know, find a small enough model.
The reason I went with Quan is because
we also had a very good experience
with post training Quan
on other use cases. So, it
I mean yes, I could have I did try a few
others, but it just you know, everything
was just there and it was good enough.
>> So, my question is about scale. So,
obviously Character AI produces
thousands, millions, bajillion videos.
What scale does this become reasonable
for my domain that is not Character AI?
So, my domain has hundreds, maybe a
thousand videos.
>> Mhm.
Sure.
So,
if you're happy with with the cohort of
experts and and and and it and you don't
need right so
I'll
rephrase that.
The scale is both for speed, right? As
well as capacity because I can serve
this model as one instance on one GPU or
I can serve it as you know,
100 instances, right? So, so that's that
determines my scale. The reason I chose
to go towards the model is because I
wanted to to speed up the creation
process, right?
It would work would have worked just as
well if I didn't have this particular
model, I would have used like the cohort
of experts, right? From
metrics
that are available both on CPU and GPU
as well as the
frontier models. Right? So it's it was a
balance
as
you know, A, how long did it take me to
to to train this model and to curate the
the data set and and get it to a working
set, right?
And and how much does it cost to serve
it
versus how much it would have cost me to
do this A slower. Now potentially it is
better, right? I mean like I assume that
if you're going to use
Fable which came back today, right? It
will probably give you a a better
result, but at what cost, right?
If you do it for one or two, that's
probably fine. If if you do it for
thousands or tens of thousands per day,
it adds up.
So it's it's it's a matter of your
your your unit economics.
>> Cool. Over here.
On your right, there you go.
Last question.
>> It's very bright. I'm sorry.
>> No worries. No worries. Um
My question is I looked a bit at the
repo. You guys don't export all tail
traces of the LMS judges yet.
>> Correct.
>> Is that something are you open to that
so you can connect to other
platforms?
>> Sure. So so the
the the repo itself
it's a harness and you can connect any
any
agents or any LLMs
you want. We actually have an internal
version of this which is running it as a
service, right? We we have an agentic
harness on top of it that has all the
the the
the metrics
we we care about, but I I I I do accept
your feature request and I'll be adding
hotel
telemetry to the the harness.
>> Awesome. Thank you very much. A warm
welcome or round of applause for Mayor.
Thank you.
>> Thank you all.
>> Thanks.
>> Oh.
