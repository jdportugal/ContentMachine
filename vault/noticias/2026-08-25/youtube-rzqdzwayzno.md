---
titulo: "This Open Source Repo Solve Claude's #1 Problem"
tipo: item_agregado
plataforma: youtube
canal: 'Chase AI'
data: '2026-08-25'
url: 'https://www.youtube.com/watch?v=rZQDZWayzNo'
thumbnail: media/monitoring/youtube/rzqdzwayzno.jpg
descricao: "⚡Master Claude Code, Build Your Agency, Land Your First Client⚡ https://www.skool.com/chase-ai 🔥FREE community🔥 https://www.skool.com/chase-ai-community 💻 Need custom work? Book a consult 💻 https://chaseai.io Claude (or any AI model) can't be trusted to grade its own work. So instead of living with that deficiency, this skill brings in Codex to point out any and all flaws with Claude's plan and final build. ⏰TIMESTAMPS: 0:00 - Intro 0:47 - How It Works 3:50 - Demo 11:23 - Outro RESOURCES FROM THIS VIDEO: ➡️ Master Claude Code: https://www.skool.com/chase-ai ➡️ My Website: https://www.chaseai.io #claudecode"
resumo: 'The video introduces ClaudeX Loop, an open-source solution designed to address the issue of AI models, like Claude, being overly optimistic about their own work by incorporating a second model, Codex, to review and provide feedback on plans and executions. It outlines the four phases of this process, including reconnaissance, interrogation, review, and build, ensuring a collaborative approach to i...'
tags:
  - '#claudecode'
fontes:
  - 'https://www.skool.com/chase-ai'
  - 'https://www.skool.com/chase-ai-community'
  - 'https://chaseai.io'
  - 'https://www.chaseai.io'
---

## Descrição

⚡Master Claude Code, Build Your Agency, Land Your First Client⚡
https://www.skool.com/chase-ai

🔥FREE community🔥 
https://www.skool.com/chase-ai-community

💻 Need custom work? Book a consult 💻
https://chaseai.io

Claude (or any AI model) can't be trusted to grade its own work. So instead of living with that deficiency, this skill brings in Codex to point out any and all flaws with Claude's plan and final build.


⏰TIMESTAMPS:

0:00 - Intro
0:47 - How It Works
3:50 - Demo
11:23 - Outro

RESOURCES FROM THIS VIDEO:
➡️ Master Claude Code: https://www.skool.com/chase-ai
➡️ My Website: https://www.chaseai.io

#claudecode

## Transcrição

You cannot trust Claude to grade its own
work, which is a huge problem, but one
that this skill solves. It's called
ClaudeX Loop, and the premise is simple.
Instead of having Claude be responsible
for planning, executing, and grading its
own work, why don't we bring in Codex to
also take a look at the plan and the
execution of Claude and say, "Hey, this
looks good, this doesn't. Here's what I
think you should change." Because one of
the big problems with every single AI
model out there is that they look at
their own work very favorably. When I
ask Claude how good the plan is that it
created, it's going to say, "This thing
is awesome." So, it's important we have
systems in place where we can bring in a
second pair of eyes to look at what the
first model built and say, "Thumbs up,
thumbs down, and why." So, today I'm not
only going to give you this skill, I'm
going to break down how it works under
the hood, and we'll do a quick demo.
Now, the skill is broken out into four
phases, and the idea is you invoke it
before you add some sort of feature, or
if you're starting a brand new
greenfield project. And the general idea
is whatever the first model comes up
with as a plan or whatever it executes
on, we wait until the second model takes
a look at it and says, "Hey, this looks
great before we move forward on big
things." Now, for the first phase we're
doing some reconnaissance. We're
actually going to go out there on the
web, Claude is going to scout out to see
if the answers actually exist. We have
the option to invoke deep research,
which is the built-in dynamic workflow
if we want to get really deep with the
answers we're receiving. After that, we
go into the interrogation phase. Think
of that as an enhanced plan mode set of
questions where we try to get on the
same page with Claude before it creates
its first plan. Secondly, this is where
we bring in the second model. This is
the review stage. So, Claude Code, after
it comes up with a plan based on
everything we've talked about and done
our research on, it's going to create
your standard plan.md. From there, Codex
is going to review the plan in a
read-only sandbox, and it's going to
say, "Hey, either this is approved, or
we need to revise X, Y, and Z." It's
then going to send that answer with the
revisions to Claude Code. Claude Code's
going to take a look at it, say, "Mhm, I
agree, I don't agree," and then it's
going to send back its changes. This
loop will continue up to five times.
Now, I've never had it actually stall
out at five times where we didn't reach
an approved state. However, I have it
stopped at five. You can easily change
that with the skill so that it doesn't
get stuck in some weird endless loop and
you're just burning tokens forever and
ever. This gives you like a very clear
hard wall so you don't get stuck in that
situation. Lastly, once Claude and Codex
come to an agreement and a plan has been
created, we then move into the build
phase. Now, this skill gives you the
ability to not just have Claude build.
You can have Codex start building things
as well. But, whatever model builds it,
the second model is going to take a look
at what it actually created again before
it moves forward with anything. And
again, under the hood, there's a lot of
different variables you can tune. Like I
mentioned before, we have the review
section which is set to five. In the
build section, when we have Codex take a
look at what we built, I've knocked that
down to two loops. Again, just so we
don't get stuck in these sort of endless
loop scenarios. And I haven't really run
into issues where I felt like, "Oh, I
needed to bump this up." But, you can
play around with that. Further, you can
play around with this more and actually
like bring in a local model instead of
Codex if that's also how you want to
approach this. Now, if you use the
previous version of the skill, which was
called Grill Me Codex, the changes you
want to pay attention to with the
ClaudeX loop are a more enhanced
interrogation mode so it's going deeper
with its line of questioning. And in the
execution phase, we've integrated Codex
more so again we can get eyes on when it
comes to the actual code that's been
created. So, next step is the actual
demo, but before we jump into that, a
quick word from today's sponsor, me. So,
I just released a completely updated
version of my Claude Code Masterclass
inside of Chase AI Plus and it is the
number one way to go from zero to AI
dev, especially if you don't come from a
technical background. We focus on real
use cases. This is updated every single
week. So, if you're someone who wants to
get better at this insane tool and you
just don't come from a software
development background, this is for you.
So, if you want to check it out, there's
a link to it in the pin comment. Now,
for today's demo, we're going to use
ClaudeX loop to recreate Calendly. If
you don't know what Calendly is, it's a
scheduling web app. You give it a link
to people, they can see your calendar,
they can pick times, it automatically
creates either a Zoom link or a Google
Meet. So, it's something that a lot of
people use and actually pay for it, but
I figured, "Hey, why don't we just
create this ourselves and save us, you
know, 10 bucks a month or whatever it is
I'm paying." I should probably know
that. So, what I'm going to do is I just
invoke /claudesloop and I'm just going
to give it a stream of consciousness and
say something like,
"I want to use Claude's Loop to
essentially create our own version of
Calendly. As of right now, I would
probably just have it use Google Meet
instead of Zoom,
um but I'd want it tied with my calendar
and essentially just recreate Calendly,
all the major features." So, let's go
ahead and do that. So, at the beginning,
we're in phase zero, which is the
research phase. So, it's saying, "Hey,
how do you want to actually do this
research? Either we do the web search,
which is sort of your standard Claude's
going to send out a few sub agents, or
do we want to go full-blown deep
research?" Now, with this skill, I have
it pinned for Opus. So, as you know with
deep research, if you do it on Fable and
you just run /deepresearch, it's going
to call Fable sub agents, which can kind
of go nuts on your usage. So, as of now,
I have it just do Opus off the bat just
to sort of help you out. Again, you can
totally do that.
It's recommending web, but honestly, I'm
going to have it do deep just to see
what it comes back with. It's then going
to show me the proposed deep research
prompt and the questions it's actually
trying to get answers to. So, it needs
to know about Google Calendar, Meet,
scheduling domain pitfalls, and
generally the stack. So, if I approve
that, we'll just say, "Go ahead and
launch." And if I wanted to edit it,
it's very simple to do. So, Claude
finished doing deep research and then
created an assumptions ledger, which is
essentially a list of everything it
assumes you want to happen for this
project. Now, after this, it's going to
have more questions for us where we can
kind of branch down different paths, but
these are the things where it's like,
"Hey, I I I don't think there's a lot of
question here." Now, you can either just
give this a thumbs up and it's going to
do all this and this is kind of get
baked into the plan or you could say,
"Hey, I actually want to change the
stack or hey, I want to change how we're
doing things with reminders and things
of that nature." But for now, we're
going to say, "Hey, this is confirmed."
And then we're going to move into sort
of the what it calls the load-bearing
tier. You know, we all love the term
load-bearing that's now been appended to
everything Claude does. So now we're
moving into those load-bearing
questions. The first question is which
Google account does your real calendar
live on? For all of these questions, no
matter your project, it's going to give
you a series of questions alongside of
recommendations. So if you have no clue,
you can just go with recommended. But
frankly, as a best practice, if you have
no idea what Claude is telling you when
it comes to like potential answers or
like what is even going on, I highly
suggest you go into this other section
and basically just say something like
explain this further. This is how you're
actually going to get good when it comes
to like AI and building things and not
just being an accept monkey and hitting
recommended and recommended over and
over again. If you don't know, ask
Claude to continue explaining it to you
until you understand it. That's the only
way you're actually going to learn. But
kind of outside the scope of this video.
So we're going to go with personal Gmail
and I'm actually going to skip ahead to
after I've answered all these questions
because I think you kind of get how this
phase works. Now, after we answer the
load-bearing questions, then we go into
some cosmetic decisions that again
aren't really changing the base
functionality of the application. In
this scenario, it just lists them all
out similar to the assumption ledger. So
you can either say, "This is
acceptable." and it's going to just move
forward with it or you can say, "Hey,
change one, change two, change
whatever." I set it up this way so you
can just or speed this process along.
Now, after we answer those cosmetic
questions, we then move into phase two.
Claude is going to write that
plan.markdown file and then it's going
to get sent to Codex using GPT 5.6 soul.
From there, they will have their back
and forth for a maximum of five rounds
or until they reach an accepted verdict.
So Claude and Codex went back and forth
for five rounds. At the beginning, there
were 27 issues that were brought up in
round one and we've gotten to round five
and the issue is there's still a few
more problems. They haven't come to an
accepted verdict. I mentioned earlier
how I never had this happen before, so
I'm kind of glad it did here. So, we've
hit five rounds, they haven't figured it
out yet. There's still a few sort of
just minor issues. What do you want to
do? Well, you have options. You can
either stop here, keep it as is, you can
just accept the deadlock state, or you
can extend it two rounds. So, that's
what we're going to do and then we'll
see what happens when they finally get
on the same page. But, nice to know and
for you to see that even though we have
sort of these hard stops, for example,
five rounds, you aren't stuck with that.
If you get to that point, like we did
here, you can very easily extend it. So,
after seven rounds, they've come to an
agreement and now it's going to ask you,
"Who do you want to actually build this
thing?" So, we can either have Claude
build and Codex takes a look, or we can
flip it and Codex builds and Claude
takes a look. In certain situations,
depending on what we're doing, it will
also give you the option to do sort of a
tandem build. So, let's say we were
creating something that required like
asset generation or something like GPT
images too brought into the equation.
Well, it will also say like, "Hey, let's
bring in Codex for this specific portion
of the project." But, in this case,
we're going to do Claude builds and now
it's going to start creating what it is
called open book. So, Claude could
finish creating the calendar demo, which
is what we're looking at here. So, let's
see if it actually works and then we'll
go back and actually dive into what
Codex added to this entire process. So,
we have the intro call and then a
working session. So, if we go to the
intro call,
we can then see a bunch of different
times and schedules that are actually
synced up to my Gmail calendar. So,
let's say I'm picking something for the
31st and I pick 10:00 a.m.
You can just add my name.
We'll put my email.
And then we'll do confirm booking.
And we can see the email got sent to us
with a join link. And I can also see it
on my calendar. I also had it create
just a simple artifact kind of breaking
down visually what Codex added to this
entire process. We talked about this
earlier, that back and forth phase where
Claude had the plan and Codex made its,
you know, adjustments or proposed
adjustments. That went all the way to
seven rounds and it started with 27
issues and you can see each round it got
lower and lower until round seven we
finally had an approved verdict. And now
here are some of the things that Codex
actually brought to Claude's attention
during the planning phase. Stuff like
the double booking constraint could not
compile. Things like OAuth connect flow
having issues. There's also problems
with concurrency that two reschedules of
the same booking could both succeed and
on and on and on. A lot of edge case
related stuff. Now in the build phase
Claude implemented that improved plan we
had after the seven rounds and then we
had a new Codex session pop up with
completely fresh memory, new context
window. It hadn't read the plan and then
it read the code against the actual
spec, what was created versus what was
actually planned. So it came back with
23 findings, 19 were accepted and fixed,
four were rejected. So here are some of
the things that Codex found in this
build phase. The time grid drifted after
every meeting. The management token was
sitting in plain text. All day events
blocked the wrong hours. And again, more
and more things that again you could
argue are edge cases but it would have
taken Claude a long time to find them.
So if Codex had never been in the room
from the beginning, what would this have
looked like? Well, we would have had
broken features, we would have bookings
that exist in the database and nowhere
else and so on. Now, in reality, would
this have been the case if we just
relied on Claude? Probably at the
beginning, we just would have taken some
further iterations and we eventually
would get to something that probably
worked but with Codex, we found a lot of
these issues in the planning phase so we
didn't have to burn tokens and then burn
more tokens after the fact and we were
able to test these things with Codex,
take a look at these with Codex before
moving to production. So all in all this
saves you a lot of time and money. So
that's the Claude-X loop in action. If
you want to get your hands on this, I'll
put a link to it in the pinned comment
and besides that, I'll see you around.
