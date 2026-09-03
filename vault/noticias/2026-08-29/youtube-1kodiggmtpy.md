---
titulo: 'The Signal Layer: What to Build When Anything Can Be Built — Lena Hall, Akamai'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-29'
url: 'https://www.youtube.com/watch?v=1KOdiGgMtpY'
thumbnail: media/monitoring/youtube/1kodiggmtpy.jpg
descricao: 'Lena Hall resolved a production incident from a hiking trail near a waterfall. A friend of hers ran 18 agents while riding his bike. That abundance is the setup for her actual argument, which is that the same leverage reached your competitors on the same morning, so the cost of average work fell to zero and took its value with it. Ask a model what users want or what to build next and it answers from common knowledge, competently and confidently and identically to whoever asked it before you. She calls it a convergence machine, and says the single decision it cannot make is where to point it. Her uncomfortable move is refusing the easy consolation that taste will save you. Taste is preference under feedback, and preference under feedback is precisely what these systems learn. What survives...'
resumo: 'The video discusses the challenges and opportunities in a landscape where AI and technology enable rapid production and innovation, emphasizing the need for businesses to identify and communicate their unique value propositions effectively. It introduces the concept of a "signal layer," which involves clearly defining what makes a product distinct and ensuring that this message resonates accuratel...'
tags: {}
fontes:
  - 'https://x.com/lenadroid'
---

## Descrição

Lena Hall resolved a production incident from a hiking trail near a waterfall. A friend of hers ran 18 agents while riding his bike. That abundance is the setup for her actual argument, which is that the same leverage reached your competitors on the same morning, so the cost of average work fell to zero and took its value with it. Ask a model what users want or what to build next and it answers from common knowledge, competently and confidently and identically to whoever asked it before you. She calls it a convergence machine, and says the single decision it cannot make is where to point it.

Her uncomfortable move is refusing the easy consolation that taste will save you. Taste is preference under feedback, and preference under feedback is precisely what these systems learn. What survives is narrower: judgment about things that have not happened yet, since no data exists for them, and judgment inside a relationship the model cannot observe, because it has read everything written about your customer and never met them. Hamming said a problem is important only when you have an attack on it, and her point is that agents just handed everyone an attack on everything, so the scarce skill became choosing which problem deserves one. The rest of the talk is about signal surviving the trip to a customer, through founders who compress past legibility and org charts that round toward the mean.

Speaker info:
https://x.com/lenadroid
  / lena-hall  

Timestamps:
0:00 - Drowning in abundance
1:37 - Why everyone gets the same answer
2:58 - An expo hall where everything sounds alike
4:21 - Benchmarks with graders, and shipping without one
5:47 - The most buildable thing is rarely the most valuable
7:13 - Why taste is trainable and judgment is not
8:34 - Hamming, and having an attack on a problem
9:56 - What the convergence machine did to content
11:16 - Two ways to use it that look identical
12:38 - Source distortion, and the deleted customer pain
14:00 - Organization distortion as an investment problem
15:21 - When a narrow eval becomes a promise
16:42 - Welding the limit to the claim
18:04 - Trust as the thing with no grader

## Transcrição

[music]
>> How is the conference for all of you so
far?
Great. Awesome.
Um well, I think this was the best most
productive year for so many of us.
I'm Lena. A few days ago, I solved a
production incident on a trail near a
waterfall.
My friend ran
18 agents while riding his bike.
We're literally drowning in abundance.
We have more output, more speed, more
leverage than any of us have ever had.
So, why do we have this feeling like the
ground underneath is moving too fast?
One of the engineers that I met at this
conference
said yesterday that it feels like
the opportunity cost for not working
9:00 a.m. to 9:00 p.m. 6 days a week is
too high right now.
So, we're all token maxing. We're all
working all the time.
But the same abundance that made you
fast, it also made everyone else fast.
So, now everyone can build everything.
Your competitor can build your feature
this afternoon, too.
So, the cost of the average just went to
zero and so did its value.
A year ago, the superpower, as we were
told, was to be good at using AI.
But models got so good
and they got so easy and everybody now
is a lot more skilled at using AI and
everybody's pointing AI at the same
goals.
Cuz AI gives everyone the same answer
because everybody is asking the same
question.
It It on data and data is a record of
what has already happened.
So, when you point AI at tasks like,
"Tell me what users want. Make more
money. What should we build? Make this
viral."
It answers from the common knowledge.
Very competent, very confident, but also
very identical to what it tells your
competitor.
To see something that data doesn't show
yet, we need to have a vision, a point
of view, a read on where it's going, and
then use all that automation to execute
it.
AI is a really smart convergence
machine.
So, if you leave it alone, it makes
everything the same.
There is one decision, though, that AI
can't and shouldn't make for you. It is
to decide what to point at.
So, the job, the new job for everyone of
us is deciding what it makes, being the
reason the right people choose your
version over the identical-looking rest.
But also, I'm sure many of you uh walked
around the Expo Hall at this conference,
and there are so many amazing products,
so many tools and vendors.
They're all solving important problems.
But what Why do they all sound the same?
So, when anyone can build anything, what
makes me different? What makes you
different? Why should anyone pick your
version, your product? Um I call this
work uh signal layer.
And there are two There's two halves to
solving it, to getting this right. So,
that's how we will walk through it.
The first half is knowing your signal,
being able to define it very clearly.
What you're building and why it's yours
and not the average. So, that's the
build side, the code, the product, the
road map.
And the second half is emitting that
signal without distortion. So, making
sure that your customers um making sure
what your customers come to believe
about you actually matches what you
believe and what you've built.
That's the ship side the content and go
to market engineering.
And I've had an unusual vantage point in
this. I've built products as an
engineer. I created my own as a founder.
I brought other people's products to
market. So three very different jobs
with one identical challenge. The signal
doesn't always survive. So let's start
with the build side.
So what do we even work on? Everything
is implementable.
Two years ago the best autonomous coding
agents you know solved on the fraction
of the tasks on the standard software
benchmark
and now the best agents are in the high
eighties. So we nearly tripled the
amount of
writing and shipping barely moved a
third.
The benchmark was measuring the part of
software engineering
that has a greater
and shipping is where all the ungraded
parts come back in. So
here is the rule underneath it. Anything
that you can measure you can train
against as Sarah Guo puts it.
A compiler is a free grader. A test
suite is a free grader.
And the instant a task can grade itself
you can grind a model against you know
that grade until it wins. Automation
of code was first because it's the most
checkable thing that we have.
So implementation is converging for free
for everyone at the same time
and the most buildable thing and the
most valuable thing are almost never the
same thing.
So the model will build whatever you
point it at but it will tell you nothing
about where to point. Anything visible
is replicatable.
So now, some people when they hear
everything is implementable, they panic.
Um but we can flip the question.
The pointing is actually the job. It has
always been the job. We just had so much
implementation work in the way
um that we never had to get good at it.
So, how do you decide where to point
that?
Paul Graham shared some wisdom on this.
Where you find something that people
genuinely want is by feeling the need
yourself.
Build something you and your friends
need because the market hasn't formed
yet, surveys can't see it, and your own
need is the only signal that isn't a
crap signal.
And the best ideas may sound genuinely
lame at first, like a guy uh strapped
with a with a camera on his head live
streaming his his life. That sounds
really ridiculous, but it became Twitch.
Um and the convergence machine doesn't
really, you know, propose proactively
these
uh weird, specific, genuinely
embarrassing ideas.
But even with the Twitch example,
it worked, but there were a thousand
other similar startups I start startup
ideas that didn't.
So, the weird specific signal is
necessary, but it is not sufficient.
It's also really tempting to say that
we just need to have good judgment and
good taste and call it safe. But taste
is really just preference under
feedback, and preference under feedback
is exactly what these systems can learn.
Anything you can demonstrate enough
times
uh with a better or worse signal
attached, the machine can eventually
imitate. So, broad good taste is not
really a differentiator.
What actually resists
training is more narrow and more
durable. So, two things.
Taste and judgement about what hasn't
happened yet.
Because there's no data for an event
that hasn't occurred. And then taste and
judgement embedded in a relationship
that the model can't observe.
What this customer in this situation
with this history that you share
actually needs.
The model has read everything ever
written about your customer, but it has
never actually met them.
So, if broad judgement is not safe, and
the AI just handed everyone the ability
to build anything, what's left to be
good at?
Richard Hamming uh spent his career
studying why some scientists did great
work and others who were just as smart
didn't.
He found that the great ones worked on
important problems.
And the problem isn't important because
it just sounds impressive.
It's important when you have a
reasonable attack on it. For example,
time travel is consequential, he would
say, but it's not important because
nobody has an attack on it.
So, Hamming would tell you to keep 10 or
20 ideas
um
on important problems in the back of
your mind so that when you finally have
an attack, a new tool, a new angle I
think that only you noticed, then you go
for it.
But in Hamming's world, the rarest thing
was having an attack. And AI just handed
everyone an attack on everything.
So, the rarest thing is knowing which
problem is actually worth attacking. And
that judgement comes from being a real
person, close to a real domain, with
your own battle scars, your weirdly
specific experience, the thing that you
care about more than is reasonable.
And you don't actually need to be first.
You just need to be genuinely close to a
problem you actually understand where
your insight is in the delta between
what AI has been trained on and what
should exist.
So, let's say you did it. You found that
sweet spot problem that
the one that you had an honest attack
on, that you built this thing. It's
genuinely good. It's genuinely yours,
not the average.
You can still lose uh because knowing
your signal is only half the job. The
other half is getting it from your head
into the head of a person that it was
meant for.
And it's about reaching the right
people.
And what do most of us
do for that?
We make content. So, let's talk about
what uh AI convergence machine does to
that.
What happened to the internet in the
last 2 years? Open any feed,
everything has started to sound the
same.
The same LinkedIn posts, the same, you
know, three bullet points and a bold
takeaway, and
the same blog post that uh says nothing
but actually looks very polished.
Um your readers can now pattern match AI
in just half a second. So, if a model
could have written your post from a
one-line prompt, your reader brain just
skips it for the same reason.
So, AI has really learned the algorithm.
It has learned the format that performs.
It has learned what gets clicks, and
everyone wants to hand the machine a
paragraph and say, you know, "Make it
viral. Make me rich." It fills every gap
that you leave with sameness.
So, what do you put in and what do you
let it fill in?
Cuz these there there are two different
ways to use this thing, and they look
very identical from the outside. One is
you give it an average prompt, and gives
you the average output.
And you ship one more indistinguishable
drop into an ocean of indistinguishable
drops.
So, you've automated your own
irrelevance very efficiently.
And two, you can bring in the part that
it can't have, your specific point of
view,
the real story that you were actually in
the room for, and then let the machine
do the converging work, the formatting,
the drafting, the algorithm
optimization, the cleanup around the
core that it
could have never generated.
The signal distorts on the way out. So,
you can have the signal perfectly clear
for you and still watch it fall apart
between your brain and your users'
understanding of it.
And in my experience, it breaks in three
places.
And there are fixes for each, but
they're different depending on product,
the type, and the size of the company.
One of them is source distortion, which
is very common in startups.
Founders, actually, they usually know
the signal so well
that they always have this accidental
gift of compressing it past legibility.
They often assume the context that the
audience doesn't have, and the room
hears something technically very cool,
but they
doesn't they don't really understand why
it matters.
I helped this one YC company with uh
this exact thing recently.
Absolutely brilliant founders, genuinely
new product, but every pitch that they
started um was you know starting with
architecture, with the clever parts,
with things that they were very proud
of. But it really landed as noise
because the customer pain has been
deleted from the whole story.
So, we rewrote the opening to include
the thing that the user hated, and this
product actually killed. So, the same
product, the same week, the next
conversations converted into pilots, and
then we turned that into repeatable GTM
system.
Organization distortion is another type
of distortion that almost every big
company has.
As signal travels through layers of
management, through legal, through
sales, through every department, at
every hand handoff, it gets rewound
towards the average.
And this really doesn't come from
incompetence, it comes from investment.
So, hand a founder and the person three
layers down the same task and the same
AI, and you get two different things.
Um the founder really sweats the
unaverageable details because the
outcome is really theirs and they're
personally invested and affected by it.
And others just ship it to spec, they
close Jira tickets, they were asked for,
you know, something like compliance, not
as much conviction.
So, a long delegation chain plus
convergence machine is really a factory
for automating the signal out of your
own company.
So, the first instinct usually is to add
more process, which adds layers,
bureaucracy, and slows everything down.
And we don't want that. Um
to fix this, we have to help take the
signal back
and reattach it to the outcome like a
founder and add the very thin signal
layer to your go-to-market engineering,
where its only job is to validate and
carry the original intent across the
handoffs intact.
Machine distortion is another way you
can lose signal. You write one careful
launch,
your claim, your evidence, and your
scope is very clear, but then of course
AI remixes it
um into a tweet, into a sales deck, into
a partner one-pager. For example, you
might have had this one very narrow eval
that scored 94%
but it was repeated enough times that
your customers actually heard it as a
promise.
So, we see the same through line. Your
signal has to survive the trip
undistorted.
And this is something you can engineer.
So, we need a thin signal layer, a small
deliberate function whose job is to make
sure that what your users take away is
still the specific thing you meant.
Say you're building a monitoring tool.
There are 12 other tools in this
category, but yours does something
different. It tells you what not to wake
up for, for example. It stays quiet on
the noise, so when you
get paged at night, you believe it. So,
that quiet, that trust earned by silence
is your signal.
So, first, say it in one sentence with
the limit built in. Definitely don't say
intelligent AI-native observability
platform.
Say something like uh stays quiet on
anything it can't tie to a real user
impact and shows you everything it
silenced so you can overrule it. The
promise and the scope are welded
together here.
Then make sure that the limit can't be
edited out. So, in the product, every
suppressed alert is visible. In the
launch, statements like 90% fewer pages
uh live next to statements like every
silence is visible and reversible. So,
when AI chops your launch into a tweet,
it can keep the impressive number, but
also remove the part that
keeps the part that uh keeps the product
honest.
And before you scale it, check what
people actually heard. So, give a readme
to an SRE who has never seen your
project and ask a person to describe the
product back to you. The gap between
what they say and what you meant is the
distortion that you were about to
broadcast.
And it's a very lightweight signal
layer, and a lot of it is buildable, So,
you can automate more of the checking
and the catching and the surveying than
most people realize.
So, step back and ask what all of this,
the building, the shipping, the
undistorted signal, is actually for.
It's for one thing of getting a human or
increasingly an agent to choose you and
rely on you when they have an infinite
identical-looking alternatives. So,
that's trust. Trust is the one thing
that's left with no greater. There's no
benchmark for it, no reward signal. It
can't be entirely automated because it's
granted slowly through relationship with
consent. For example, doctors who
open one particular tool every morning,
they didn't have that habit trained into
them.
And what happens if we get this wrong?
Getting your signal wrong is actually
not neutral. It's negative.
Producing averageness is not free. You
actually pay for it in tokens, in infra,
in the salaried hours of good people,
you know, with with customers that
take a look at your product once, decide
once, and never come back. So, every
generic post teaches them that your name
isn't worth the click. So, you spend
real money to make yourself harder to
choose.
So, back to the main question.
We got faster,
but the speed is not where the value
went.
Uh the value moved up to deciding what
is worth building, what is worth saying,
what deserves trust. And where does the
thing that you actually
um that that you meant survives the trip
to the people that it was for.
So, you don't need to be first. You need
a real problem and enough conviction to
carry the signal clearly through to, you
know, right people to find it.
So, when you can build anything, you
should build trust.
Have the strongest conviction, define
the signal yourself, protect it from
distortion, and use AI aggressively for
everything else.
Thank you. Let's connect and happy to
chat with you afterwards.
Thank you.
>> [applause]
[music]
