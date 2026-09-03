---
titulo: 'Tribal Dungeons of Global Shipping: AI Agents at Global Scale — Dmitry Buykin, Maersk'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-29'
url: 'https://www.youtube.com/watch?v=dQ-_i1tZiws'
thumbnail: media/monitoring/youtube/dq-i1tziws.jpg
descricao: "Maersk's standard operating procedures were screenshots. A sequence of images showing what a person sees and where they click, which is a perfectly good record for a human and useless to an agent. Dmitry Buykin calls the gap tribal dungeons: the knowledge exists, just not in a form anything can execute safely. An agent version of the same procedure needs preconditions, decisions, identifiers, backend calls, validation, recovery and evidence that it actually worked. Most of the project was that translation, negotiated with the people who own the process, because experts own the what and agents own the how. His sharpest point is about where the engineering actually lives. The agent loop is not the system. The refining loop around it is, and the corpus of procedures outweighs the runtime roug..."
resumo: 'The video discusses the challenges of automating complex global shipping operations using AI agents, focusing on the need to translate messy operational knowledge into executable procedures. It emphasizes the importance of aligning expert knowledge with AI capabilities to handle exceptions and improve operational efficiency.'
tags: {}
fontes:
  - 'https://x.com/tzakus'
---

## Descrição

Maersk's standard operating procedures were screenshots. A sequence of images showing what a person sees and where they click, which is a perfectly good record for a human and useless to an agent. Dmitry Buykin calls the gap tribal dungeons: the knowledge exists, just not in a form anything can execute safely. An agent version of the same procedure needs preconditions, decisions, identifiers, backend calls, validation, recovery and evidence that it actually worked. Most of the project was that translation, negotiated with the people who own the process, because experts own the what and agents own the how.

His sharpest point is about where the engineering actually lives. The agent loop is not the system. The refining loop around it is, and the corpus of procedures outweighs the runtime roughly twenty to one, because the same shipping step means different things in different countries. Accuracy was not designed up front in a diagram, it was earned through more than 100,000 corrections over nine months, with heat maps turning traces into priorities and a single cell often costing the team a month or two. A correction only counts once it becomes an executable change, which is the line between an opinion and a production fix. Discovery needs agent freedom, production needs a cage, and a harness exists to make the dumb mistakes impossible rather than to give the model more room.

Speaker info:
https://x.com/tzakus
  / buykin  

Timestamps:
0:00 - The long tail is the expensive part
2:04 - What an SOP has to become for an agent
3:56 - The refining loop is the system
4:50 - Running 200 instances against legacy backends
5:47 - Triage, traces, and shared evidence
6:44 - Where vibe coding and spec driven work run out
7:39 - A hundred thousand corrections, and heat maps
8:36 - Please be careful is not a guardrail
9:31 - Five moves, and compounding improvement
10:31 - Composite tools, and why they skip MCP

## Transcrição

[music]
Hello everyone. Um, this is a
practitioner report uh from real
production work. So, let's get into it.
Um, I'll skip the generic uh yet another
loop agent intro. This is about the hard
part most agent demos skip.
and about turning messy operational
knowledge into something an agent can
execute safely.
This comes from real work uh in my
company I'm working for supporting
global shipping operations and grounded
in production.
On paper it's uh one workflow usually
but uh in reality every shipment is an
orchestration of many parallel state
machines. While they agree the happy
paths work the moment one drifts you get
exception work.
The easy majority is already automated
in many companies. What's left is the
long tail and more exceptions than
system built uh to handle them. That
tail is uh the expensive part.
And then there's my favorite category.
And it comes with a special uh plate
here. See for EI builder dreams and
their laptops. This what you can find
outside of AI bubble in San Francisco.
The signal process uh depends on many
systems being coherent at once. If any
step uh can't complete the happy path
breaks and then it takes expert uh
archist expert orchestration across uh
multiple incomplete systems.
All these uh variations um path pathways
should be captured in SOPs. SOPs is a
standard operating procedure common and
regulated industries. So an expert and
the model read them uh the same way.
That gap is the hard part. Stable intent
detection tool calls you can guarantee
are safe integrating with legacy back
ends and results evaluated with experts.
Uh I call this uh tribal dungeons. Uh
the knowledge exists but not in a form
uh agent can execute and you can safely
run a process. You can't safely run a
process. The organization cannot
represent
standard legacy SOPs [clears throat]
bunch of bunch of screenshots organized
in sequence and but screenshots not uh a
process. A legacy SOPs explain what a
person sees and clicks. And an agent SOP
needs a more complex uh setup,
preconditions, uh decisions,
identifiers, back end calls, validation,
recovery, and evidence of uh successful
execution.
Experts own the what, agents own the
how. And exception becomes a guardrail.
Most of the effort is the translation
and negotiation between them to align on
common sense.
Three parts here um in this architecture
it's SOP memory uh organized as SOP
corpus
execution runtime and theme feedback
capture. The agent loop is not the
system. The refining loop around the
agent is the system
and it's the most complex part. Oh,
sorry SAP is okay. It's this slide for
UK. This is correct one. So and it's
good illustration why the the same thing
is means different and uh describing
differently in different countries and
it's creating a lot of variations
between each country
and that corpus is a asset the company
company's process memory uh modified and
aligned with every country um conditions
and far bigger than than than runtime
you could see the proportion 20 to1
So and this is concurrently operating
system
and this is the scale we run in
production today
over 200 instances and spikes and
latencies deviates from few minutes to
up to 10 minutes.
Um and mainly yeah the mainly main
reason for it that u we depending on
many legacy system which is uh so cannot
be faster than agent loop itself.
Expert time is the bottleneck. So the
theme bench uh does the triage for us.
It clusters the failures and hands back
something you can act on. Not just look
at look at it.
The trace is the shared evidence that
lets an expert and an engineer review
the same case and agree on what
happened.
A correction only counts when it becomes
an executable change. And that's the
line between an opinion and a production
fix.
And and this is where quality comes
from. not from vibes uh not from a
bigger model from replaying real
examples with u disabled rights to uh
protect the production systems and
checking whether behavior improved.
You can see here on the
uh cognitive proportion u or this effort
ratio uh between each activity in our
project. So usually uh pipe coding ends
here.
Here there ends um
specdriven development because it cannot
uh grow improve accuracy more than this
stage on this scale. And this is uh
where the real work starts. Nothing
exotic. It's engineering common
engineering sense applied at scale.
So if uh you don't know all this uh
terminology which developed over lastuh
30 years in software development
argument to check because this is what
every AI agent uh AI coding agent should
know uh to help you develop reliable
production systems
and accuracy it's uh wasn't designed uh
in one diagram up front it was earned
one small correction at the time at the
scale you see here. So we have over
100,000
corrections over last 9 months in the
system when we developing it
[clears throat]
and this um heat maps uh turned
thousands of traces into priorities. is
how we keep experts and engineers uh
looking at the same problems and
prioritize where the the most beneficial
work for them. Every cell is a group of
tracked scenarios we have and uh usually
to turn one block in red it's around one
two months of force for the whole team
whole team of engineers and also AI
agents
um the agent failed is uh where the
investigation starts not where it ends
each failure maps to a specific uh fix
discovery needs agent freedom and
production needs a cage. Uh the harness
isn't there to give the agent more room.
It's there to make the dumb mistakes
impossible.
So on this scale please be careful is
not a guard guard. Uh if we have wrong
workflow then classifier eval. If it's
wrong right then right gate. If it's
wrong assumption then it's a mere view.
A preventive measure eliminates the
unsafe path
on critical paths. U review and approval
stay in the loop. The engine engineering
focus is uh to build safe hands offs and
a trail you can trust.
The real outcome
uh wasn't the agent in the system. It
was the [clears throat] methodology we
built around it. If you want the
blueprint, then it's uh these five
moves. Make work representable. Make exe
execution bounded. Make behavior
observable for every agent and make
correction cheap. And last thing is make
improvement compound. So gradually
systematically improve the quality of
the system.
AI native um operation is more than
agents in workflow. It's a system that
learns from what works and fold folds it
back into code as new composite tools
adapting to the applications and the
people around it. The best AI models um
oriented intelligence for us. The
adaptive architecture we built is the
asset, the final asset
and
we aggregating all um repeatable
sequences of steps successful scenarios
and uh merging them into bigger tools
which uh combine the disproven scenarios
into the reusable snippets by other
agents. So and then um it's possible to
roll out them not only for one country
but for hundreds country in one go.
So this is um um all for the talk and
little time for questions and I'll be
around afterwards. And the final
reminder you know if you you know if you
are AI builder if you emotionally
attached to tools not MCPS
we're not using MCPS because uh for us
it's uh always not the best choice. So
because all all systems usually really
bloated and we have to distill responses
and uh tune the tools through function
calling uh to our agents then we can
control quality of um our software and
ensure that uh it's correctly
processing assigned tasks.
Thank you. Any questions?
Okay, then um thanks for your attent u
attention. Then I will be around so you
can ask me questions if you want.
[applause]
>> [music]
