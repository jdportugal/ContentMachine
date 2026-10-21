---
titulo: 'I Monitored Crime Audio. Voice Agents Scare Me More. — Sumanyu Sharma, Hamming AI'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-15'
url: 'https://www.youtube.com/watch?v=qStB9GbppMU'
thumbnail: media/monitoring/youtube/qstb9gbppmu.jpg
descricao: "Sumanyu Sharma showed up for a doctor's appointment a voice agent had told him was booked. He was not on the schedule, the front desk turned him away, and he lost two hours. His point is not the inconvenience but the substitution: make that his grandparent, and make it a procedure rather than a checkup, and the same failure costs something else entirely. Sharma founded Hamming after years at a public safety app, where he and his team listened to thousands of hours of police radio and pushed millions of alerts across several US cities. He puts the two experiences side by side deliberately. Crime is decreasing and it is local, touching whoever happens to be involved. Voice agents are scaling fast and they are centralized, so one prompt change propagates to everyone at once. Around a trillion..."
resumo: "A talk on why voice AI agents are risky as they move from demos into production, covering how the underlying speech and orchestration stacks have improved while reliability remains the top barrier to deployment at scale. It draws on the speaker's earlier work monitoring police radio audio at Citizen to frame the stakes, and shows real examples of agents giving customers wrong information and erodi..."
tags: {}
fontes:
  - 'https://x.com/sumanyu'
  - 'https://hamming.ai'
---

## Descrição

Sumanyu Sharma showed up for a doctor's appointment a voice agent had told him was booked. He was not on the schedule, the front desk turned him away, and he lost two hours. His point is not the inconvenience but the substitution: make that his grandparent, and make it a procedure rather than a checkup, and the same failure costs something else entirely. Sharma founded Hamming after years at a public safety app, where he and his team listened to thousands of hours of police radio and pushed millions of alerts across several US cities. He puts the two experiences side by side deliberately. Crime is decreasing and it is local, touching whoever happens to be involved. Voice agents are scaling fast and they are centralized, so one prompt change propagates to everyone at once. Around a trillion phone calls happen every year. Even at a one percent error rate that is ten billion bad interactions, and across the ten thousand agents his company monitors the real rate is nearer ten percent.

The failures are rarely dramatic. An agent reports it found the right policy while quietly skipping the eligibility check. It applies a discount nobody authorized. It says it booked something it did not. His remedy is a loop rather than a fix: find the problems, size them by frequency and severity, change something, verify the change did not break something else, and keep watching. He is emphatic that listening to individual calls by hand is where to start and not what to scale, and that the real insight lives in patterns across conversations. Then the harder warning. His team's adversarial testing breaks roughly one agent in five.

Speaker info:
https://x.com/sumanyu
  / sumanyusharma  
https://hamming.ai

Timestamps:
0:00 - Listening to crime audio at scale
2:50 - Why reliability still blocks deployment
4:32 - Crime is local, voice agents are centralized
5:23 - Sizing severity, from annoying to unsafe
7:04 - A loop for finding and fixing failures
7:57 - Coverage: from listening by hand to cross call analysis
10:32 - Testing whether a fix really worked
12:13 - When bad actors learn to dial
13:07 - Breaking one agent in five

## Transcrição

[music]
My name is Suman Yu and I'm the founder
and CEO of Hamming. And before working
on voice agent reliability and safety, I
worked at a company called Citizen
out of New York. Anybody here use
Citizen app? Awesome. Thank you. Uh, and
at Citizen,
we listened to crime, thousands of hours
of police radio station data and sent
millions of alerts to users in San
Francisco,
New York, LA, uh, Chicago, Baltimore,
and so on.
Some obviously gory and pretty sad. Uh,
but others more funny like a person
stealing bags of ice cream from Safeway
or report of a man hanging off the side
of the house after a woman stole his
ladder.
If I actually take a look at the citizen
app right now for those who are
customers or users, I can see that there
is a man yelling at person. There's
indecent exposure. This is real. This is
real time. This is, you know,
couple hours ago. These are real-time
alerts that we're sending.
Now, voice agents scare me more because
they're finally graduating from demos
and PC's to production. We should be
super excited, but I'm nervous. I'm
personally nervous. Uh, they're talking
to users at a scale that would make Gary
Tan and Polygram proud.
When I got started in voice agent
reliability in early 2024, voice was
just starting to work. It was not quite
good yet, but it was just starting to
work. You would have to pay me a lot of
money for me to stop using, you know,
Aqua voice, Super Whisper, uh, Whisper
Flow, and so on. These products are just
getting super, super good. And a big
reason is because the underlying
infrastructure is getting better, and
the orchestration layer is getting
meaningfully better. It's getting much
faster to build products and voice
experiences that maybe are 60% good in a
pretty short period of time, but the
long tail is still Hey, Gorov. the long
tail is still uh wise away.
I think speech speech models are getting
better. Um teams are experiment
experimenting with hybrid architectures
of combining more voicetovoice
modalities and also cascading stacks to
make the experience reliable but still
pretty low latency.
Things are obviously getting better.
Agents are being connected to calendars,
CRM, HRs, reservation systems, and so
on. Voice agents can now take actions.
However, reliability is still the number
one problem holding back most voice
agent deployments at scale. This is
still the number one problem. This is an
example I found on Twitter pretty
randomly, you know, two weeks ago and a
person is trying to get information for
a tradein and gets absolutely confused
with the information that they're
receiving. Alex now has to correct for
this loss of trust but trying to, you
know, call the person and see what see
what happened and fix the situation. Let
me see if audio works here.
>> Screwed up with another customer. We're
getting it fixed, but I got to call him
and see if I can work it out.
>> I'm like, dude, half the time I'm like,
I don't know if I'm talking to AI. I
don't know if I'm talking to a person.
It was just confusing, but we got there.
>> It probably is AI and human. And
>> so I think voices sound very confident.
They sound very natural, but the
information provided is often, you know,
not correct. That's the biggest problem
here.
This example is more personal. I had
booked an appointment with a physician a
couple weeks ago or I thought I did. I
showed up to the appointment and turns
out I was not actually on the schedule.
So the front desk, you me turned me
away. I wasted 2 hours. For me, this was
a waste of time. But what if this was
actually your parent?
What if this was your grandparent?
What if this appointment was for a
procedure instead of a regular checkup?
The costs for these different
permutations of the same failure mode
can actually be super super high.
Now, let's compare crime to voice
agents. Um, I think observation number
one is crime is actually decreasing over
time. This is a good thing and I hope it
crosses the x- axis at some point you
know in the future.
Voice on the other hand is generally
taking off right we're seeing a pretty
fast takeoff of voice agents being
deployed in production. There's at least
a trillion calls that are done every
single year and majority of these will
be done by conversational voice agents
over the next you know five years. If
you assume a 1% error rate that is still
10 billion incidents per year. That's a
lot.
In practice, we currently monitor 10,000
agents and the error rate is closer to
10% in practice. These range from agents
saying they found the right policy when
they actually skipped the eligibility or
verification steps or applying discounts
when they were not really supposed to,
misharing what the person said,
providing incorrect information, or
claiming they booked an appointment when
they actually did not, just like it
happened for me.
Now, not every single call has an
equally, you know, bad cost. Uh, some
range, you know, in the crime land, some
range from trash fires, which are kind
of funny, annoying, not really hurting
somebody. For a voice equivalent, that
would be annoyances like repetition, um,
or just sort of not quite understanding
what the user is saying. all the way to
safety risks like mass shootings or in
the voice agent equivalent, it would be
um a drive-thru that's deploying um
voice agents at scale like a Taco Bell
or McDonald's and a person orders a
vegan burger with peanut allergies.
If one of those two situations are not
handled correctly, that is definitely a
safety concern at scale.
The other big difference between crime
and and voice agent deployments is is
crime generally tends to be pretty hyper
local,
tends to be very decentralized, right?
Things like robbery or motor vehicle
theft or lararseny. They're impacting a
finite set of individuals that are
involved in that um situation.
On the other hand, voice agents are much
more centralized. a single prompt change
or an architecture change can have
pretty massive implications downstream
for all of the millions of you know
users that are um in the crossfire. So
the blast radius is is quite quite
massive. So the natural question is how
do you make these incidents much more
visible and obvious? That's the kind of
obvious question here.
I'll borrow a framework from a couple of
my friends who were OG growth folks at
Facebook. So step one is to identify
okay what are all the challenges and
problems that um exist in your
conversation experience. Step two is to
prioritize an impact size. There's a
frequency and severity analysis that's
pretty important. Step three is to
understand okay how do we actually fix
this? Step four execute. Step five okay
did my change actually work and did it
cause any regressions somewhere else.
And lastly we continue to monitor in
production.
On the y- axis, I think it's important
to highlight there are known problems
that already exist. Things like turnover
latency, interruptions, um maybe some
ASR problems you're, you know, aware of.
And these are known problems that exist
that the team should track over time. On
the other axis is actually emerging
behavior or patterns that are only
obvious across lots of conversations. Um
on the x- axis, you have coverage just
like insurance. Are you analyzing few
conversations? Are you analyzing many,
many conversations? Most teams will
typically start by listening to calls
manually.
And I think that's the best place to
start. I don't think you should skip
that step. There's a lot of depth and
insights to get by actually listening to
specific conversations and building that
texture that that comes from that
intuition. However, it's obviously not
scalable. So most teams end up having a
spreadsheet of I don't know five or 10
different rubrics around greetings,
closing, validation,
um, core logic and so on. To scale that
up even further, you then end up
investing in some eval product, right?
You might run some element as a judge
and compute classic metrics and also
more more deterministic and stoastic
scoring logic. Um, but there you're
still stuck with checking for
consistency of known problems, but
you're not really discovering novel
insights that are actually happening
across conversations. We're spending a
ton of time on performing cross
conversation analysis, not a pattern on
a single call, but across conversations.
And some of the best teams that we work
with are are doing the same.
Now, to prioritize an impact size, I
think there's problems that are one-off
that are low impact. I mean, who cares?
uh even low impact and systematic
problems in the crime world that would
be a trash fire in a voice aation world
it could be some repetitions the team is
experiencing they're still annoying at
scale and if you are doing a bake off
it's still worth solving for them I
would not ignore these class of problems
oneoff and high impact well hope it
doesn't chronic and I think systematic
and high impact are obviously the P 0
you know target areas um for the team to
solve an example of that
would be in a fins serve capacity
There's a voice agent that um helps
users freeze their credit cards. And if
it doesn't do that, well, that's a
massive fail.
All right. So, understand and execute.
I'm pretty sure everyone's doing this.
Please fix my agent. Uh I think fixing
or rather attempting to make a fix is
the simplest and the lowest effort
component of this debugging pipeline and
loop. Um the next step is all right, I
made a change to my system. How do I
actually know this thing works um for
real? A great way that's naive is to
take a real call, for example, in my
case, I booked an appointment and it
didn't get scheduled and replay that
exact conversation and run that maybe 5,
10, 20, 50 times and see, okay, what is
my probability of passing this type of
issue? A better way is to keep the same
intent but change the wordings, change
the patterns, change the accents, change
the style, add one more intent to the
mix. And that gives teams much more, you
know, better coverage to feel confident
that yes, I actually made a change and
my changes are net positive instead of
net negative.
There are certain fixes and I guess
hypothesis that are very difficult to
test in a pre-eployment synthetic
setting. And so AB testing ends up
being, you know, pretty pretty critical
for those circumstances. For example, if
you have an outbound agent, the first 5
seconds of a conversation tends to be
the most important. And so the vocal
quality um and the specific words you
end up using, they matter the most. And
so AB testing that is the only way in in
kind of real life setting to to get
results. You can't really do it through
simulations alone.
And so there we have the loop. Identify,
prioritize, impact size, understand the
fix, execute, check, make sure it didn't
break anything, and then continue
monitoring.
So I think making voice agents useful is
already hard as it is. even when dealing
with earnest users on the other line,
right? These are people who who just
want their problem solved. They're not
trying to mess with you. These are like
legit normal people.
Now, what happens when mythos learns how
to dial?
So, if it can extract trade secrets and
uh you know, from the NSA, it can
certainly, you know, seduce you into
revealing PHI and PII data as well.
And I think both voice agents and humans
will [clears throat] be targeted here.
Voice agents because there's a pressure
to make these more capable. Give them
access to more data. Give them access to
more tools.
Deploy them quickly.
The more the capability, the bigger the
surface area. This is this is pretty
pretty common sense. And the more the
voice agents become natural and human
sounding, the more humans will be
tricked along the way as well for those
who are weaponizing.
Uh we ship a we shipped a red tipping
product um back in April just to test
out this hypothesis for how many agents
can we actually break from a adversarial
capacity and we can probably break one
in five agents at this point. We've
tested this across financial services,
healthcare, um consumer and so on. We've
bypassed verification. Uh we've
definitely had agents, you know, we've
been able to promject uh several agents
and and gotten data we should not have.
So this is not theoretical. This is
actually a real a real concern.
I think the only real defense against
the dark arts is
step one to invest deeply in
pre-eployment testing. This could be
textto text. This could be voice to
voice. There's pros and cons to both.
Happy to chat offline if folks are
interested. And this is just making sure
you're not self-owning, you know, when
you're talking to real people who just
want to get their problem solved. Step
two is to have a great monitoring system
of all kinds. And I've highlighted, you
know, different flavors of monitoring
per call scoring, manual kind of evals,
you know, listening to conversations and
cross call analysis. And this is helpful
both for monitoring what the agent is
saying and behaving and how it's
actually doing, but also the users. Are
the users being adversarial? Are they
being annoying? Are are they trying to
trick the agent into doing things it's
not supposed to be doing?
And I think our new recommendation now
is to run 24/7 red teaming um for your
agents, especially if you believe the
cost of bad interactions can be can be
rather large.
So, I think voice agents um have this
awesome potential of of making the world
feel much more human compared to
interacting with clunky IVR trees or
chat bots or worse um being stuck on a
on a hold.
And when we think about crime, we often
think of crime happening to somebody
else. You know, crime does not happen to
you typically with voice agents,
especially bad actors. As these agents
are deployed and as bad actors start to
exploit a lot of the vulnerabilities,
the number of incidents is about to kind
of go way way up. And so the reason I
fear voice agents more than crime is
that one of these incidents is going to
impact you. It already did for me.
Awesome. So it's time for me to shill.
Well, we burn a lot of tokens. If you
are interested in working in this space,
please come and talk to us. And if you
are deploying voice agents and want to
validate whether your architectured or
your eval are set up correctly, please
come and talk to us. We'll be outside.
And here's here's my number. Here's my
WhatsApp. Thanks everyone.
>> [music]
