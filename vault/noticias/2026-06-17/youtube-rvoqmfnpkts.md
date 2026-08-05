---
titulo: "The Karpathy Method That 10x'd My Claude Code (Steal This)"
tipo: item_agregado
plataforma: youtube
canal: 'Charlie Automates'
data: '2026-06-17'
url: 'https://www.youtube.com/watch?v=RvoqMfNpkTs'
thumbnail: 'https://i.ytimg.com/vi/RvoqMfNpkTs/maxresdefault.jpg'
descricao: 'Work With Me Directly To Scale Your Business With AI: https://charlieautomates.com/charlie-os-vs/ ———————————— Join my community with 3,300+ business owners (choose premium for weekly calls): https://www.skool.com/cc-strategic-ai/about ———————————— 🔑 Resources: SEED & PAUL Podcast Segment w/ Creator: https://youtu.be/NB9Pf4cdFeM SEED REPO: https://charlieautomates.com/free-resources/#seed PAUL REPO: https://charlieautomates.com/free-resources/#paul-plugin ———————————— Andrej Karpathy''s Four Principles (give these to Claude for your CLAUDE.md file) ⬇️ "## Coding Behavior ★ Behavioral guidelines to reduce common LLM coding mistakes (distilled from the verified Karpathy / agentic-engineering research; pairs with the `dev` CARL domain). Bias toward caution over speed; for trivial tasks, use j...'
resumo: 'O vídeo explica o "método Karpathy" para usar o Claude Code, esclarecendo que o ficheiro Claude.md que circula no GitHub não foi escrito por Andre Karpathy nem reflete a sua abordagem, cuja ideia central é que a IA automatiza aquilo que se consegue verificar. Apresenta os quatro princípios a incluir no ficheiro Claude.md — pensar antes de codificar, simplicidade primeiro, alterações cirúrgicas e e...'
tags:
  - entrepreneur
  - money
  - business
  - education
  - development
  - entrepreneurial
  - 'Digital Business'
  - ai
  - replit
  - claude
  - 'chat gpt'
  - 'go high level'
  - gohighlevel
  - businessgrowth
  - artificialintelligence
  - startup
  - aitools
  - automation
  - salesfunnel
  - funnelbuildr
  - digitalconsulting
  - marketingautomation
  - businessconsulting
  - aiforbusiness
  - aiconsulting
  - techentrepreneur
  - futureofbusiness
  - aihustle
  - 'ai funnel builder'
  - 'replit tutorial'
  - 'gohighlevel alternatives'
  - 'ai automation tools'
  - 'build sales funnel with AI'
  - 'Ai for entrepreneurs'
fontes:
  - 'https://charlieautomates.com/charlie-os-vs/'
  - 'https://www.skool.com/cc-strategic-ai/about'
  - 'https://youtu.be/NB9Pf4cdFeM'
  - 'https://charlieautomates.com/free-resources/#seed'
  - 'https://charlieautomates.com/free-resources/#paul-plugin'
---

## Descrição

Work With Me Directly To Scale Your Business With AI: https://charlieautomates.com/charlie-os-vs/
————————————
Join my community with 3,300+ business owners (choose premium for weekly calls): https://www.skool.com/cc-strategic-ai/about
————————————

🔑 Resources:

SEED & PAUL Podcast Segment w/ Creator: https://youtu.be/NB9Pf4cdFeM

SEED REPO: https://charlieautomates.com/free-resources/#seed

PAUL REPO: https://charlieautomates.com/free-resources/#paul-plugin

————————————

Andrej Karpathy's Four Principles (give these to Claude for your CLAUDE.md file) ⬇️

"## Coding Behavior

★ Behavioral guidelines to reduce common LLM coding mistakes (distilled from the verified Karpathy / agentic-engineering research; pairs with the `dev` CARL domain). Bias toward caution over speed; for trivial tasks, use judgment.

★ **Core discipline:** LLMs automate what you can *verify*. Define success criteria first, then loop until verified. You can outsource execution, never understanding.

1. ★ **Think before coding** — State assumptions explicitly; if uncertain, ask. If multiple interpretations exist, present them — don't pick silently. If a simpler approach exists, say so and push back. If something's unclear, stop and name it.
2. ★ **Simplicity first** — Minimum code that solves the problem, nothing speculative. No unrequested features, abstractions for single-use code, or error handling for impossible scenarios. If 200 lines could be 50, rewrite it.
3. ★ **Surgical changes** — Touch only what the request requires. Don't refactor or "improve" adjacent code; match existing style. Note unrelated dead code, don't delete it. Every changed line should trace to the request.
4. ★ **Goal-driven execution** — Convert vague asks into verifiable goals ("fix the bug" → "write a failing test that reproduces it, then make it pass"). For multi-step work, plan as `1. [step] → verify: [check]`.

★ Working if: fewer unnecessary diffs, fewer rewrites from overcomplication, and clarifying questions land *before* implementation. Reframed for delivery: spec = the brief, verifier = acceptance criteria, environment = the repeatable stack."

————————————

Timeline:
00:00 Intro
00:20 Automate What You Verify
01:01 Four Principles Breakdown
02:24 Raw Prompt vs Frameworks 
02:53 1st Framework (SEED)
04:37 2nd Framework (PAUL)
06:47 Why Raw Prompts Fail
08:27 PAUL Memory System
09:56 Speed vs Quality Tradeoff
11:06 PAUL Pause Feature
11:45 Resources and Final Thoughts

#ClaudeCode #AndrejKarpathy #agenticai

## Transcrição

Right now, everybody is rushing to
GitHub to download Andre Karpathy's
Claude MD file. Only problem is,
Karpathy never wrote it. For those of
you who don't know who Andre Karpathy
is, he's one of the founding members of
OpenAI. And to be clear, the file that
most people are downloading isn't even
his method. So, if you copied it and
your Claude is still fighting you,
that's why. But, here's what he actually
said. "Traditional computers automate
what you can specify, but these models
automate what you can verify. That's the
whole game in one line." Everybody
copied a 65-line file and skipped the
one sentence that actually matters. So,
I rebuilt how I run Claude code around
that idea. Same model, but now I spec
the job. I define how it gets checked,
then I let it loop. Just watch the
difference. Cold prompting Claude will
burn more tokens and it's more
susceptible to drift. Spec it and it
ships in one clean pass. In this video,
I'm going to give you the four key
principles that Karpathy actually called
out, then show you how I turn this into
a system that brings in actual ROI for
your business. Not just cleaner code.
So, this is the Karpathy-inspired
section of my Claude.md file, and no, I
didn't download this from GitHub. The
four key principles that we see here are
all based around a bias towards caution
over speed. And this core discipline
lays it out even more clearly. Your AI
automates what it can verify. So, we
have to define what success actually
looks like first, and in a way that your
AI can understand and read. Then, we
loop that data until it's verified, and
we can outsource the execution to the AI
models, which we all know, but we should
never outsource the understanding.
Systems thinking is always more valuable
than the system itself. So, the first
principle is to think before coding.
Basically, giving your model the ability
to have pushback with you. Whatever it's
unclear on, tell me. And if there's a
simpler method, let me know. And if
something isn't clear, stop it and name
it right now. Second, simplicity first.
It's basically a whole less is more
idea. Third is surgical changes. We only
want you to touch what's required to
make this thing work. Follow the
existing style if it's already working.
I want you to tell me if there's any
unnecessary code, but don't just delete
it. Tell me. And you have to be able to
tell me what you changed based off of my
request. And last but not least,
goal-driven execution. Turn my basic
asks into verifiable goals. And these
are the four principles that Karpathy
has laid out for us. Now that we
understand the four key principles that
should be instilled in our Claude MD
file, I'm going to show you how these
principles should work in real time. In
the left terminal here, we're going to
go with a raw prompt to build something
out. Versus on the right, we're going to
use frameworks to actually ideate and
build our project. Albeit, we do have
our Claude MD updated, so the raw output
won't be terrible, but the results on
the right will be 20 times better,
considering the fact that we're going to
be using solid frameworks installed
inside of my OS. So, on the left, I have
a raw prompt. I'm going to send it. I
want it to build a dashboard for my
social media analytics. And on the right
side, I'm going to start with a
framework for ideation. So, we have some
interview questions here. I could choose
the brand. I'll go with my Charlie
Automates kit. We'll let it rock. But
notice here on the right, it's giving us
options. And it's asking us if we want
to ideate. It's going to essentially let
us package our idea into a spec file
that Claude can read more effectively.
But on the left, it's already building.
So, on the right side, I'm just going to
tell it to ideate, and I'm going to give
it the same prompt that I gave it here
on the left. And we can immediately see
that this is just creating the folder
now, and it's going to start working on
the project versus us getting a deeper
idea with Claude about the project name,
the stack options, the scope of what
we're trying to build. So, this is going
to prepare us to build much more
effectively than just one-shotting a
prompt. And this framework that I'm
using for ideation falls exactly in line
with Karpathy had in mind with his four
principles. So, I'm answering the
questions here before we move from
ideation to actually building with
another framework that I have in mind.
So, we're leaning on the side of caution
over speed, as per what Karpathy had in
mind. But before I drop any more sauce,
if you guys are getting value out of
this and enjoying it, please like the
video and subscribe if you want to
continue seeing more content like this
because it's the best way to support me.
And 90% of my viewers aren't even
subscribed. So hit that like button and
subscribe for more because I do one to
two videos like this every single week.
Now we could see some work that's being
done. We won't get too caught up in the
technicals, but every framework that I'm
mentioning and the first one I used was
something called seed is going to be
attached below with a resource video
that I did on it previously. And there's
one more framework that I'm going to
show you, but you guys are going to have
to wait until we finish up the ideation
before I show you. The left side has
already created the app folder with the
project here versus the framework side
is asking us what we want to do next
with this idea. So what we want to do is
actually launch this and initialize
poll. This is the looping system that
Karpathy has mentioned. So I'll tell it
to launch it and put it in the apps
folder. And the reason why most vibe
coders projects are failing because I
just put in a wish list here versus
having a clear spec sheet on my idea. We
didn't see any quality gate here, but we
do see a proper verification here using
seed when ideating for this project. The
social dashboard folder here is from the
raw prompt and social pro is from the
right terminal here where we use the
seed framework. Notice how the raw
prompt folder only has one file for the
project to build. Versus the framework
side, we have a poll folder here. This
is the other framework that we're going
to get into. It gave us a project file
which lays out exactly what this app is
about, the road map of where we're
looking to go with the application, and
the state of the file, meaning how far
we've built it. So Claude has a full
memory system of where you're at on this
built. In the description below, I'm
going to attach an entire playbook
resource for you guys to gain access to
seed as well as poll right here. Seed
was for our ideation to package the idea
nicely so Claude can understand and
build a spec file. And poll is how we're
actually going to build it. And it
stands for plan, apply, unify, and loop.
And now as you can see, we launched the
Paul file, so we're planning with Paul
based off of the seed idea. We're going
to apply the idea and start building out
the different phases, which I'm going to
show you in a sec. And I showed you the
roadmap, state, and project file.
Whenever we get to a certain point of
building, we would run a unify command
and update those files so Claude
understands what the heck is going on.
And as a golden nugget for watching this
far, in the resource document that I'm
attaching below, I'll also include
instructions for your Claude MD that are
inspired from Karpathy. We don't see any
file management system on the raw prompt
side of things, but running with seed
and Paul, we do right here. And we're
going to move over to the framework
side. Notice how it's asking us to plan
phase one. And this is the prompt to
plan it. And you see there's an empty
phases folder here. This is how we build
slowly and effectively chunk by chunk
with Claude versus just one shotting a
development. So, we let it run. It's
going through the templates that Paul
has installed, as well as the project
files here, so it has perfect context on
everything about the project, including
the initial spec file. Now, while we're
actually moving forward on the framework
side, I want to boot open the raw
version that was built out. And it's
clean. It's not connected to any of my
data yet, so it's not terrible. But, the
problem is there's no tracking system.
This is that folder right here. And
every time I want to start working on
the project, I have to remind Claude
exactly what it did last. So, there's no
memory. Versus on the right side, we
might not even have the project built
yet, but now we have a plan that we can
build on and look back on for context.
And Claude can read it super effectively
with the unification system I'm talking
about. So, we'll only loop it once for
this video. I'll show you what it built
out, and I can show you how it's built
out by actually applying now. So, we
planned, which is the P and Paul. We're
applying, which is the build part of
Paul. And then, after it's done
building, I can boot it open, and we can
unify. So, these files here for the
project are updated. This is how you
make sure that every project you build
with Claude actually gets shipped. And
you don't have to be super technical to
understand what I'm talking about here,
but it's not using the same tech stack.
Because with a raw prompt, there wasn't
much discussion of how it was going to
be built. Even if you don't know what
Next.js is, it recommended these based
off of proper ideation that Seed brought
to the table. Because realistically,
what are you going to do every time
you're building on a raw prompt and you
don't have any file management system?
What are you going to just like write
notes and kind of hope for the best that
Claude remembers what was done last?
That's not smart. And you're only going
to waste money and time. They're using
about the same context. On the right, we
have about 16% of our context window
used of a million tokens, and on the
left we've used about 11%. But that
extra 5% went towards proper planning.
So if you're somebody who's looking to
build with Claude code and you don't
have proper frameworks and you don't
understand exactly where to go from
here, start with the frameworks. Now
before we get the final design product
of what Seed and Paul has built for us,
I need to show you how the unification
system works. Because after it produces
the design, we're going to run the unify
command. And that'll update these files
here. The first one is the project MD.
This is the context about the project
entirely. So the core value, the current
state of where things are at,
requirements. It also mentions what's
out of the scope. So we're not building
for the sake of building. We have a
clear definition of done. Now as far as
the roadmap goes, this is telling Claude
exactly where we're trying to go with
the project. So what's the current
milestone? It's a point one MVP, minimum
viable product. And it laid out all the
phases of building for us so we know
what we're getting into and Claude knows
what it's getting into as we progress on
the build. And this is probably one of
the most important files here, which is
the state MD. Okay, this is the exact
project state, where the project's at.
And we haven't reached any milestone
percentage and we haven't even completed
phase one. And we can see we planned, it
hasn't updated that we've applied yet
cuz it's building it out currently. And
we haven't unified that yet. So when we
unify it, it's going to check this off
and it's going to check this off. And
the last thing we would have to do is
loop so that we can plan phase two and
then apply that. And that's the whole
system of building with the Paul
framework. And we'll probably notice
that both seams are pretty much the same
on the raw side versus the framework
side. But the back end of things is what
you really have to worry about, and that
is the clear difference here. But the
real question is, would you prefer a
product built that'll last you for
years, or something that was just ad hoc
that was built in 5 minutes? Quick
update since I'm still waiting, but the
raw prompt side only took 5 and 1/2
minutes. But the fully specked out side
with seed and Paul is taking almost half
an hour. And it's taking that long for a
very, very good reason. So, this is the
raw prompt that we have on the left. It
took 5 minutes to just piece together
some placeholders. And yes, it looks
clean, but on the framework side of
things, we can see when each side is
actually going to be built out. So,
we're not just going to get fake data,
and it's going to walk through and test
each phase step by step. Phase two for
the overview, YouTube analytics in phase
three, Instagram phase four, and then in
phase five, we're going to tie
everything together and have the
settings laid out for us. So, yes, it's
not pretty, and you won't get a quick
dopamine hit. But on the framework side
of things, it's taking its time so that
everything is built out correctly. We're
just going to approve it and unify the
files so we can update the state files
that I referenced before. I'll show you
how the updated state file looks so you
can see the progress, but I also want
you guys to pay attention to the lack of
depth that the raw prompt folder
actually hosts. It's just an HTML file.
Versus the framework side, we have the
Paul folder with the state files and the
actual phases plus a summary of what was
built. And it built out a proper tech
stack here. Now, we can tee up the next
phase and start building out the actual
connections. But in this case, all I'm
actually going to run is Paul pause.
It's going to create a handoff document.
While it's doing that, I'll show you
guys the state file so we could see the
phase one out of five is done, so 20% is
done. Phase one is complete. We're at
the unify stage, and as we write a
handoff document with Paul pause,
[clears throat] it'll put that here in
the project file so Claude knows exactly
where it left off. And when we want to
continue the project, we could pull that
back into context instead instead
reiterating with our words and trying to
get Claude to remember. All I'd have to
do is type in Paul resume, it'll pull
this in the context, it'll read the
project files, and it'll know exactly
what to do next on your project. The guy
who told you frameworks are fluff
doesn't have nine-figure products with
acceptance criteria. I do. All the
resources that I've talked about in this
video are linked below in the
description, and if you guys are
enjoying what I'm throwing down and
would like to work further with me on
building agentic systems for your
business, you can click the first link
in the description below. I work with
mid- to large-size businesses to solve
their largest revenue bottleneck in a
60-day sprint. And we do that through
what I call a Charlie OS. It's a direct
copy of my AI operating systems with all
my frameworks pre-installed, and it's
all set up in a live 1-hour installation
call with a one-click deployment system
and a custom roadmap pointed directly at
the bottleneck that's costing you the
most money and time. But ultimately, I
hope you guys got a tremendous amount of
value out of this one. Remember to like
and subscribe, and I hope to see you
guys on the next one.
