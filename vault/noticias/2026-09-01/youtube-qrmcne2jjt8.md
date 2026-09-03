---
titulo: 'The End of the Static Screen: Architecting Intent-Driven UX — Gus Iwanaga, commercetools'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-01'
url: 'https://www.youtube.com/watch?v=QrMcNe2jjt8'
thumbnail: media/monitoring/youtube/qrmcne2jjt8.jpg
descricao: 'Gus Iwanaga opens by disowning his own session title, then shows the demo that did not work. His team asked their system for a sales report for Q1 four times over. It returned four different layouts: different KPI cards, different charts, different amounts of text, and copy that drifted from Q1 in one run to January and March in another. He would not ship it. None of that was a model failure. It was the consequence of handing a model a component catalog and asking it to compose the experience, which is precisely what his team had done. The rest is what they built instead, at a company carrying more than 300 APIs. He lays the choices out as a spectrum of control. At one end you ship a fixed component and the agent only decides when to show it, which suits an opinionated flow. At the other t...'
resumo: 'The video discusses the evolution of user experience (UX) and user interface (UI) design towards generative, intent-driven approaches, highlighting lessons learned from building innovative products at commercetools. It also addresses the challenges faced in transitioning from static to dynamic user experiences and explores the emergence of UI protocols.'
tags: {}
fontes:
  - 'https://x.com/guhgoi'
---

## Descrição

Gus Iwanaga opens by disowning his own session title, then shows the demo that did not work. His team asked their system for a sales report for Q1 four times over. It returned four different layouts: different KPI cards, different charts, different amounts of text, and copy that drifted from Q1 in one run to January and March in another. He would not ship it. None of that was a model failure. It was the consequence of handing a model a component catalog and asking it to compose the experience, which is precisely what his team had done. The rest is what they built instead, at a company carrying more than 300 APIs.

He lays the choices out as a spectrum of control. At one end you ship a fixed component and the agent only decides when to show it, which suits an opinionated flow. At the other the model emits markup that renders inside a sandboxed frame, which he demonstrates working and then declines to ship, on the grounds that a business cannot control what it cannot predict. His team took the middle. An orchestrator classifies intent, calls tools, maps the results onto eligible components, and broadcasts a UI spec that renders as native components against a schema, so the output obeys the design system every time. Two problems stay unsolved and he says so. Something must still arrange whatever the agent selected, which his team addressed by teaching it atomic design and inverting the hierarchy to run from components upward. And the catalog becomes the contract between agent and interface, so every property in it matters.

Speaker info:
https://x.com/guhgoi
  / gus-iwanaga  

Timestamps:
0:00 - Changing the title, and what he wanted to talk about instead
2:26 - Forty years of adapting ourselves to the software
3:35 - Three interfaces, and what onboarding people to them costs
5:54 - The question that started it, at an API first company
7:00 - The failure demo: one query, four different layouts
9:20 - How the orchestrator works now
11:35 - Control: shipping an opinionated component
12:44 - Open ended: handing the whole thing to the model
14:59 - Declarative, and the protocols that support it
17:21 - Who arranges the components the agent picked
18:30 - Borrowing atomic design, and flipping the hierarchy
21:56 - When your team stops designing pixels

## Transcrição

[music]
>> How's it going?
The end of uh the end of the conference,
how's everybody feeling? Tired, drinking
from the fire hose as well?
Are you guys a little bit tired of
hearing loop engineering, harness
engineering, software factory, evolves,
pre-training,
post-training data, and whatnot? Uh but
anyways, those topics were more than
valid, right? Uh hi.
Some uh really uh common faces here. By
the way, I'm super excited to be here
and talk to you guys about this title.
And And I'm sorry, when I was when I
submitted the application, I was
thinking of potentially a catchy title,
but I don't like this at all. So, with
all due respect to the organizers, I'll
have to make a change. Uh and the actual
topic that I want to focus here today is
lessons learned uh from a team that is
building proper generative uh UX and UI.
And I was going to uh touch on agentic
orchestration, but come on. Uh over the
last 3 days, this is what we heard all
the time. So, I'd rather focus on what I
didn't hear enough about here in the
conference, and hopefully you walk away,
if not with something very tangible, but
with a new mental model that can spark
meaningful discussions down the road.
Sound good?
All right.
Very good.
So, hi everybody. Uh I'm Gus. I'm a
general manager at commercetools. I lead
product, UX, and engineering for
zero-to-one products. I tell people I'm
in a very privileged position because we
get to build really cool stuff. So, we
cook really interesting stuff at
commercetools.
Fantastic. And this is what you guys can
expect uh at least during the
presentation.
Uh I'd like to make sure that we're on
the same page with respect to the
problem uh the problem space uh followed
by a quick demo of the product because
I'm not sure if you guys agree, an image
speaks more than 1,000 words. So, it
would just uh make it more tangible for
uh everybody here. Followed by a rapid
discussion on the emergence of UI
protocols, and I'm not sure if you guys
joined maybe some of the talks here.
Even the founder of some of these
protocols were here uh this week, and
that was really cool. Followed by and
last but not least, the challenges that
uh my team uh and I faced and we still
face. And some of the mitigation tactics
uh that we put in place to overcome some
of these challenges.
So, with that
let's continue. The problem space.
This is This is more like a a a
statement um
and we're still adapt to the software
that we ship, not the other way around.
Right? And then uh although you could
argue when GPT came out, this was
November uh 2022, we had a really good
glimpse of uh real personalization, but
everything else uh remained static. And
even with AI, we keep shipping a lot of
stuff much faster, but to a significant
extent, it is still static. And my
question is, why?
So, for the uh over the last 40 years,
uh we kept shipping a static
experiences. And if I put it myself in
the shoes of uh some of uh my customers,
they need several SaaS applications for
uh the day-to-day work, and each with
its own mental model and its own way to
get anything done.
And over time, it just kept getting
worse, just accruing uh debt. The
cognitive load that we wanted to remove
and that we wanted to transfer to the
machine, uh it's on us.
And now with AI, things can be
different.
I don't know if you guys agree, but this
is uh what we what we think.
And I do have a couple of examples. I'm
not going to say out loud the name of
these apps, but let's take a look.
Here.
First one.
You know, that icon is very well known.
This is probably the most famous CRM of
all times.
But when I look at this screen, there's
a lot going on. I don't even know where
to start. Right? Not only the
information overload, how many features
how many teams do you think are somewhat
involved just to ship this?
Many, probably. Right? And this is just
one.
Let's have a look. I have a couple of
other examples.
So,
this here.
Look at that.
Oh my god, this is a fancy table. I
don't even know where to start. But
anyways,
one more.
Does everybody know this one here?
Beautiful. Beautiful UI. Right? It's a
fantastic, super intuitive, and
something else just to highlight.
Do you guys know how much time these
companies need to invest in onboarding
people?
So, that was the the trade-off.
Right? So, you got to allocate a lot of
time for a lot of people just to onboard
newcomers. As a result of this
complexity that has been introduced over
time.
So, this is just at least the hardcore
evidence. So, different apps, different
logic every single time. And then more
apps are coming out. So, imagine just
put yourself in the shoes of average
user, and then oh, now I have five five
apps. And then every single one I need
to learn how to navigate, how to browse,
and so on and so forth. And then this
has been the history up until now.
But then this was August last year, I
sat down with my boss. It happens to be
the founder of the company, so big shout
out to my boss. And then we asked this
uh question because at Commerce Tools,
we are an API first company, 300 plus
still counting. And we ask this posing
question, through the lens of artificial
intelligence, what are the foundational
shifts that could be made if we could
change drastically the way that we
interact with software?
Not in a uh static uh fashion. And the
answer to that question led to the
product that I'm going to demo right
now.
Let's go.
How about quick demo? Guys like the
idea?
Give me a thumbs up. Bye. I know
everybody's tired. Let's go. Yeah.
All right. Cool.
Look at this. I know, I'm going to zoom
in. No worries. So,
I have this query here. Create a sales
report for Q1.
And the UX side of me, when I look at uh
what was generated, and by the way,
everything here on the right side has
been auto-generated, guided by us, but
this is AI, right? Deciding on the
placement, on the information
architecture, deciding which uh
components had to be actually retrieved
from the catalog, but I don't like it
at all. Right? It's a Even if you don't
know a lot uh about UX, just let's, you
know, I have at least four different
variations because those were four
different terms for the same query.
Let's check it out. First one here, it
was Q1 uh Urban Thread, whatever that
is. Uh Q1, I see all of these KPI cards.
There's a lot going on here. And then my
intuition tells me, man, this doesn't
add up.
Okay. Second one, it's not Q1 anymore.
Now this is January and March. There's
no consistency.
Does that help? Yes or no?
No, right? This is This is You only
create confusion. If this is a heavy
personalized experience for the user,
imagine if every single time you need to
prompt, and then uh at least the model
will output something different. This is
not good, and this is just the second
turn. Let's have a look at the third
one.
Oh my god, now there is even more stuff
here on the right side. But so, this is
I have this component, the KPI cards, a
bunch of text, a bunch of charts, and
then this this was the beginning of a
journey.
One more?
Yes. Okay, now it's still Q1,
but still for me this still it's
confusing. And because this has been a
very experimental journey,
at least okay, let's let's move on here.
My feedback to the team and to myself
was no, no, and no. There's no way that
I would ship this to to prod whatsoever,
right? And then I have my colleagues
here just to confirm what I just said.
But then things evolved, and I'll like
to demo the current state of the
product. It's much more sophisticated,
and let's have a look. I
I'll like to plan a campaign, and for
what it's worth, I'm going to save you
from all the nitty-gritty details for
everything that is domain specific, but
I'm going to pick this query here,
and then let's see what happens. And
this is the the agentic orchestration
part that I was going to highlight.
Underneath the hood we have the
orchestrator, and the orchestrator can
then just extract the intent of the
query. Based off of the intent of the
query, it can locate the tools, right?
Those can be first party, third party
tools, and the outputs of this different
it could be agents on MCP servers,
combined will give
enough what I call ammunition and
context for the UX agent to eventually
render something that we call
meaningful. So, compared to the previous
turns,
this is this is decent, right? I want to
just remove my bias, but the overall
aesthetics, the look and feel of this
query,
uh it it resonates with me. Would you
agree? Give me a thumbs up if you like
if you agree. Okay, at least um the vast
majority here. And then see, it is
decent. And let me just continue here.
And then once again, right? This was
decided by AI guided by us. I would just
want to make make that clear
here. And then I'm going to touch on the
on the UI protocols and then how you can
make this happen.
But okay, let's see. If I approve here,
and then this is already live. And this
is pre-prod, right? So, uh this is
great. Let me go back to my
presentation.
Perfect. I have one more question. Are
you guys as skeptical
that this is possible? Because I can
tell you this it is possible. If you're
still skeptical, don't worry. I have all
of these guys uh here uh also uh every
day just looking at me and then
challenging whether uh this uh can be
made possible at scale, right? And then
uh okay. And then this is the part that
I'll like just to touch uh touch base on
the
uh on the three ways that you can render
what you just saw. And they're different
UI protocols. Are you guys familiar with
uh generative uh UI? Have you guys
played with it? Let me see here.
Okay, well, that's really cool. Uh okay.
So, uh what I would like just to share
with you guys it's all about how much
control you want to exercise over the
experience. And this matters a lot
because uh you know, as a
non-deterministic solution, uh you can
decide if you want something I really um
like this. So, let's have a look.
Here, this is uh ChatGPT.
And my query was help me find a Japanese
restaurant uh in SF today.
If you guys see here, this component
this component is very opinionated.
Would you agree with that? Here?
Right? So, uh you can have complete
control over this uh component. So,
depending upon the nature of your
business, this works really well, right?
And for that, let me just go back here.
And then this is what I call a control.
Essentially, you ship the component as
it is. The agent will pick and it will
display exactly the way that you
describe. However, depending upon the
nature of your business, at least for
us, right, at my company, where a B2B
SaaS, the there's so much configuration
that we don't want to be over uh
prescriptive because uh the feedback
that I keep getting from my customers,
"Oh, the flows are so confusing. There's
so much configuration. How can you
remove the cognitive load uh for me?"
But uh if you're like Booking, for
example, this approach uh works uh
really well. And then let's see uh how
it works. So,
essentially, you have the agent. The
agent will just pick the component from
your catalog and then it will render uh
as it is, right? And uh in the interest
uh of time, I'm not going to touch base
on the code snippets uh that I have for
this uh three different types. But
afterwards, if you guys are interested,
uh I can share the presentation and then
you can have a look, all right?
Very good. This is uh
at least on the left side. Let's discuss
a little bit on the right side because
this is when you give full autonomy to
the LLM. If you guys remember uh at
least the previous attempts for my
product, this exactly what we did. So,
we just said, "So, hey LLM, how uh how
would you compose this experience
knowing that you have these components?"
Right? But uh that was uh that was a
little bit uh a little bit of our
opinion because uh it has uh it had uh
some of the components available. But it
could happen that uh you can just
delegate fully to the LLM. And then
right now, I'm here on Claude and I
asked Claude, "So, hey, create an org
chart with three levels."
That was it. And then Claude just
rendered this diagram and it works
really well. Right? But here, if I put
myself in the shoes of a company, I'm
not sure I would delegate fully to the
LLM.
Because I cannot control at least the
app the output and the outcome. And me
personally, me guys, as a UX leader, the
UX side of me will always say no. You
got to be in control. There's been a
couple of talks here at least this week
on design, on taste, and judgment. And
this matters a lot. If you guys want to
embark on this journey of leveraging
these protocols, you don't want to
delegate too much
of the actual experience to the LLM. You
got to find alternatives and I'm going
to touch on that in just a little bit.
Okay, cool. And then this is how the
open-ended
approach works. So essentially there
there's going to be an MCP tool and then
this will literally ship the HTML and
then in a sandbox I frame environment,
this will be rendered in the host of
your choice. But it can be a chat. It
can be this
cloud. It could be a chat GPT. It could
be perplexity or it could be any other
chat. If you're willing just to to give
full control to the LLM, good luck. But
the one that I would like to highlight
is
this here and this was our choice that
we call the declarative. Declarative is
in the middle.
If you guys heard some of the protocols
and I don't want to get into the
specifics of each because they have
different characteristics. But HTMX from
Google, JSON render from Vercel, OpenUI
by Thesis,
those are some of the protocols that
will give you this in between here. And
let let's have a look, right? So at
least for my product, the one that I
just showed, the orchestrator agent will
eventually, if you think of the whole
traversal, the user will enter the query
and then there's going to be the intent
classification. Based off of the intent
classification, then the tools will be
invoked, the data will be retrieved, and
somewhat somewhat in between Alice,
there will be the mapping of the
eligible components from your catalog to
the entities of of the tools, right? And
then the orchestrator will just
broadcast this UI description. It's like
a UI spec. This UI spec will be also we
have this component catalog here and we
use the Zod schema and then you got to
be compliant with this protocols. This
is just one of the requirements and then
you just render that. Right? And then
their final output will be the native
UI, in this case the React components.
This is it. The good thing about the
declarative approach
is
it will be compliant with your design
system everywhere. This matters a lot.
So, in our case, we did not want to
delegate to the LLM because you guys saw
over there you can't change the copy,
right? So, it's not key one. Sometimes
it's going to be March January to March.
It matters a lot. So, within UX we have
different factors, right? We have the
actual UX's if you think of the overall
experience. There is UI, there's also
copy, UX writing, and so on and so
forth. But this approach gives us this
in between. It is less deterministic and
I think this is a really good segue to
some of the challenges because imagine,
I'm going to use my example once again,
the orchestrator will just fetch the
eligible components for that query, but
then it's up to the LLM how to place in
the UI. And this can get really really
messy, but
and those are the challenges that I'll
like to share with you guys here.
I got to be careful because I've got
only 30 minutes, but let's go.
So, the first challenge is if the agent
picks the components, who's in charge or
what entity is in charge of arranging
them? And this is information
architecture, right? This is a critical
aspect of UX. And then once again, if
you put yourself in the shoes of the
average customer, it matters a lot. So,
if you're just left alone, the placement
can be totally random. So, at least in
my team, we borrow this concept of
atomic design. And atomic design, it
goes like this. Let me just change here.
Uh yeah. So, as you can see, atomic
design, and then I have the definition,
is a methodology composed of five
distinct stages working together to
create interface design systems in a
more deliberate and hierarchical manner.
This helps a lot. Why? Because those are
the individual elements, and if you
think of the overall structure of the
page, it gives me the ability to steer
it as I see fit. And what we've done in
my team, so this UX agent, we harnessed
this UX agent. So, we eventually taught
this this UX agent what good looks like,
what is the optimal layout for a given
situation, and we have a catalog of
different templates. But I'll like to
show you at least this because this
detail is very important.
Here,
it is the overall hierarchy hierarchy.
So, if you remember part of the
orchestrator, the orchestrator will
eventually just fetch the eligible
components to accomplish the query of
the user, but then the next big question
is, how do we arrange that? The approach
that we used was think of this
hierarchy. So, you have the overall
page, the layout. The layout will
contain different slots. So, think of
this one here, the header. You can have
the main, and then you can have sub sub
slots. Sub slots can have sub slots.
And within the sub slots, you can have
eligible component categories. And then
this will allow us to steer eventually
the the the optimal placement of the
components that have been retrieved by
the orchestrator. So this is literally
us codifying
our UX knowledge into this agent. So the
next time, you know, it doesn't matter.
We'll just follow the same approach. And
then this is the hierarchy that we're
using. So layout to slot to sub slot to
components. However, because of the
orchestrator, we we flipped the order.
So from components, components will map
to sub slot, sub slots to slots, and
slots to templates. And then we can just
arrange as needed.
So this was a hell of a challenge. It is
still a challenge, by the way, and it's
a really good segue to the second one,
the second challenge, which is
the actual design, your design system
and your catalog. This becomes the
heartbeat of the whole thing, right? I
cannot stress enough, if you guys see
the potential of leveraging this UI
protocols for your product, this is
going to be a big deal, right? So we're
pushing the boundaries and then we're
testing and we keep on testing these
different protocols that I mentioned,
ATUI, JSON Render,
Open UI, and so on and so forth. But
this has been a quite challenging
because the the catalog is the contract
between the agent and the UI.
So every property mat- matters. And not
only for the catalog, but for the layout
as well. So if you remember, the layout
has its own components, the slots and
the sub slots. Each of those components
will have its own attributes. And all of
that, this curation, let me just
encapsulate into curation. This curation
is absolutely needed so that you can
deliver something meaningful. Not as
some demo that you will see out there
for the sake of demo, right? So, this
this this will give you a control will
allow you to steer from UX perspective.
Very good. And last but not least,
one significant challenge that we had is
that my teams do not design the pixel
anymore. I don't know if you could see
that, right? So, we're not here
designing at the entire flow. Now, AI
can
dictate that to a significant extent,
but the nature of the work shifted quite
a bit and it's been an interesting
journey to say the least, a really good
one, but even for the non-technical PMs
and UX designers, it it was a big hit
because right now we talk about the
schema. Let's talk about this curation
of the catalog. Let's talk about the
rules. Let's talk about the synthetic
data that we can generate. How can we
generate the queries that will map to a
given component as part of this mapping
logic? Let's talk about interaction
patterns. So, this
if once again, if you guys are going to
embark on this journey, be aware that
the people element is very important.
When I talk to other leaders, I I talk
about the three P's: people, product,
and process, right? And then a very
lightweight process, but this matters a
lot.
And with that,
I'm going to leave a couple of resources
here. So, and by the way, those are
talks from AIE
for what it's worth. So, yeah, people
that have been talking about this
protocols over and over and over. So,
please just take advantage, take a
screenshot.
And if you guys want to connect with me
here,
yeah, my LinkedIn or just take a
screenshot. I'll love to talk more about
the topic. I can tell this is just a
matter of time, right? So, this is
coming. So, thank you so much.
>> I know.
>> [music]
