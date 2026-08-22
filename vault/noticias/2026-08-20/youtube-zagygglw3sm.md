---
titulo: 'Unlock Agent Autonomy: The Runtime for AI-Native Systems — Tushar Jain, Docker'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-20'
url: 'https://www.youtube.com/watch?v=zaGyGgLW3SM'
thumbnail: 'https://i.ytimg.com/vi/zaGyGgLW3SM/maxresdefault.jpg'
descricao: "An agent that had quietly emailed him a nightly summary for weeks decided one morning to post it as a pull request instead. Nothing had changed. The model simply judged that publishing would be more helpful. The report held Tushar Jain's own notes on how his team was working, which is precisely the sort of thing he did not want landing in a repo. His point is that the fix in that case was trivial, since the agent never needed write access at all, and that almost no real case is that tidy. The example he builds on is an agent investigating a latency spike. It reads logs, then wants logs from a second service, then GitHub history, then Slack for related chatter. Every step is what a competent engineer would do, and every step widens the blast radius, until a single process holds access to ev..."
resumo: 'The video discusses the evolution of AI agents towards greater autonomy, emphasizing the need for ensuring their safe operation as they become more capable. It highlights the challenges of managing agent behavior and access, using anecdotes to illustrate potential risks and the importance of implementing appropriate safeguards.'
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
  - 'https://www.linkedin.com/in/tusharj'
---

## Descrição

An agent that had quietly emailed him a nightly summary for weeks decided one morning to post it as a pull request instead. Nothing had changed. The model simply judged that publishing would be more helpful. The report held Tushar Jain's own notes on how his team was working, which is precisely the sort of thing he did not want landing in a repo. His point is that the fix in that case was trivial, since the agent never needed write access at all, and that almost no real case is that tidy.

The example he builds on is an agent investigating a latency spike. It reads logs, then wants logs from a second service, then GitHub history, then Slack for related chatter. Every step is what a competent engineer would do, and every step widens the blast radius, until a single process holds access to everything at once. Traditional software let you declare permissions up front because behavior was fixed, whereas an autonomous agent works out what it needs at runtime. His proposal is a runtime layer sitting beneath any model and any harness, resting on three things. Containment, where the controls live outside the boundary the agent runs inside. Capabilities scoped per task, rather than one sandbox that accumulates them. And access granted against the intent of the original request, so that a sudden ask for email during an incident investigation is refused or escalated to a person.

Speaker info:
- https://www.linkedin.com/in/tusharj

Timestamps:
0:00 - Intelligence is not the blocker, safety is
2:08 - The nightly agent that published itself
3:12 - Widening scope during a latency investigation
4:17 - Why you cannot rely on one model or one harness
6:24 - Containment, with controls outside the boundary
7:29 - Just in time tools, scoped to one task
8:30 - Intent based access, and what to refuse
10:33 - Docker solved portability, now safety
11:49 - A sandbox with injected credentials and stubs
13:58 - Splitting one job across two scoped sandboxes
16:06 - The same sandbox, moved to the cloud
18:10 - Orchestrating scoped agents together
20:26 - A prototype that grants access on the fly

## Transcrição

[music]
>> All right. Can we start? All right,
there you go.
Um hey everyone, welcome.
Uh I hope everyone's enjoying the
conference. This is uh a really fun
conference. I've enjoyed all the talks
and the presents here.
Okay, so we're going to talk about
unlocking agent autonomy and what that
means. These last years have been crazy.
I'm sure you all felt it, right? Like 2
years ago we were talking about chatbots
and here we are.
We're now in this world where
we all see the autonomy we get from
agents. Agents have become powerful and
they'll continue being so.
Um
at this point, the next big challenge
like we spent the last 2 years trying to
make agents more intelligent and
powerful and that'll keep going and I
think we're almost there. I think the
next challenge in front of us is
actually harder and more important,
which is how to make them safer. At this
point, I don't think intelligence is the
next big blocker for us to leverage
agents. It is actually how to do so
safely so we can give them all the
access and autonomy they need.
Just as a story, this is a small
anecdote. I'm sure everyone here has
some version of this. Um
this is one of many agents I run. This
runs every night. It looks at some repos
I care about and you know, just does
some analysis for me. What activities
happen, who's been doing what, what
progress has been made. Um I have others
that might do some more, might analyze
the code review comments, have some of
my own analysis in there, be like, what
was the tone, who did what, how were
they acting? I'm a manager. This is not
meant for public views, just meant to
help me keep a pulse. But still, it's
not something I want shared. It's for my
own knowledge, something to keep up.
This agent has been running for weeks
just fine. Runs every night, sends me an
email, I look at it.
Randomly one day, uh it decided to post
this report as a PR on the repo. Why?
Nothing's changed, just the model
decided to be helpful. Um so
>> [laughter]
>> Thank you. Um
But this is a fundamental thing, right?
Like agents do stuff. They try to be
helpful. They increase and change the
goal they're doing. Either cuz they
themselves are just trying to be
helpful, or they get confused, they make
a mistake, or they get prompt injected,
right?
Um
This is a simple example, honestly. Like
it's easy to fix this. That agent should
never have had write access to GitHub.
It should have just had read access, and
that's an easy fix. Um
But it's not that simple, right? That's
a very easy case. Let's take Let's take
another example. Let's imagine I have an
agent, and I'm asking it to do
investigate a latency spike. Check out
latency spike. Great, it starts. It's
looking at the logs.
It sees, "Oh, I think there's another
service here. I want the logs to for
that service. Let me get that access."
"Oh, I see this uh might be related to a
recent check-in. I would like access to
GitHub, to the repos, to read the recent
commits."
Uh this looks like it may have happened.
Let me look at Slack conversations to
see has there been any chatter about
this to learn from there.
Great, it asked for Slack access.
These are all reasonable steps, right?
This makes sense. This is what I would
expect an engineer to do.
But what's happening is that each time
as it's expanding its goal, expanding
what it's doing,
it's crossing the trust boundary. It's
increasing the scope of the task. And
this is fundamentally where we run into
trouble. How do we know it's okay to
give it access? We now end up with an
agent that has access to everything at
the same time, and so anything becomes a
vector where the blast radius expands.
This is fundamentally the big difference
we're running into and the big
challenge. Earlier, traditional software
was deterministic, you could define the
permissions. But now as agents become
autonomous um
and they gain and they try to solve more
problems, what they're doing changes at
runtime. The access they need changes at
runtime.
And right now we haven't truly solved
this. We haven't solved how to give them
exactly the access they need,
how to do this in a safe manner, how to
know if it's correct. And this is the
fundamental thing I think we have to go
solve now
to actually unlock autonomy. And so we
go away from like can it do this to like
should it do this and how do we give it
that access?
Also, this is something we can't just
rely on the next frontier agent being
really good and not making a mistake.
We're going to use more than one model.
I just think fundamentally we're all
already there, I think. No one is going
to bet everything on a single model or
even a single frontier lab. You'll use
models from different frontier labs as
they make progress. And importantly, we
will all use open models. We're all
living through the GLM 5.2
um
uh amazing progress last few weeks. And
this is just the start, but there'll be
more and more of this. So we'll end up
wanting to use different models for
different reasons.
Privacy, cost, etc. So we need a
solution that runs across them and
doesn't just rely on the model itself
being good.
We'll also use multiple harnesses. You
won't just use a single harness from a
single provider. One, you should like
betting entirely on a harness
from a frontier lab makes it hard for to
get choice across models from labs and
across open models.
Two, there'll be harnesses for different
use cases. Right now we're all very
focused on coding, but we're going to
expand.
Uh the open claw moment happened, but
it's still not landed fully, right? You
can imagine sales people, marketing
people having claws running doing stuff.
So the kind of harnesses and agents will
use will grow and you'll build your own.
So we need something that works across
harnesses and works across models.
Um and we need something that it just
doesn't just depend on no mistake
happening,
but constrains the environment around
it. So what we want is
an environment where the agent runs,
where something goes wrong, there's
limited blast radius, and we only give
the access it needs, and we do this in a
safe and correct manner.
We think the best way to do this is to
create a runtime. Is to have a runtime
that all agents run on. So, this runs
across any agent, any harness, and
across models. And that's where we
create these uh
uh artifact these these capabilities
that we want.
There are three core pillars here.
First is containment. You need to create
an environment where it's controlled
what the agent can get. Um this does
mean sandboxes. And look, you can like
throw a rock and find many sandbox
something at this point, but it's more
than that.
So, when you have a you have a sandbox
in which you can you run the agent and
it gets only what it needs. And
importantly, you run the agent inside
the untrusted boundary, and you run
controls outside, so outside of the VM
boundary.
Second, you scope access.
This is more than just what network can
you access, or even what tool can you
access.
But you need to give actual scoped
capabilities. So, in our example,
the agent now wants to access Slack to
search for any conversations around this
incident.
Well, I could give it read only to
Slack, but that's still more than what I
want to give it.
Maybe there's a single channel with only
conversation with the incident, that's
great. Often times that's not the case.
It could be spread across many channels
or a team channel with other
conversation and I don't want this agent
to get access to other content. How do I
do this?
The upfront predefined tools typically
don't aren't that fine scoped. What what
the runtime should do is maybe create a
just-in-time tool that composes over
existing Slack MCV tools or anything
else, but restricts access to just
conversations about the incident. And
that's what the agent gets access to.
We create and use and instead of having
a big sandbox that we keep adding
capabilities to, take that part, run it
in a scoped sandbox for that task with
just the scoped capability it needs.
This now starts to build the runtime and
fabric for us where we can give agents
fine-scoped access,
break down work into tasks across
security boundaries, run those in
contained sandboxes with just access
they need.
This feels much better and now we're
getting to place where we can be safer,
but we're still not done cuz the core a
what access should you get? If this is
asking for Slack, is that correct? Um if
it's asking to read this read from the
Slack channel or have write access to
something, should that be allowed? How
do you differentiate between what is
correct, where it's making a mistake or
being incorrectly eager, or where it's
being prompt injected? This is where we
have to This is what intent-based access
becomes. We need to understand the
user's intent or the task intent, take
the context in in account, and then
decide what access you get and how that
should be run in which contained
environment.
And so that becomes the next big
challenge for us to do, which is how do
we safely evolve the capabilities the
task gets. So in this example, it makes
sense. Okay, investigating this
incident, you're asking for read access
to Slack for that incident. That seems
rational. Let's do Let's do that. All of
a sudden, you would like email access.
Why? Nothing about the prompt said you
should have that, so I'll deny that or
I'll raise it up for human approval. But
do this not just space in the frontier
lab of the model that's running, but do
this independent running at like a
control layer in in the in the control
sandbox layer in the core governance
aspect independent across all models and
all harnesses.
This is sort of This starts to get us to
a world now where we can actually have a
runtime layer
and run agents safely
in a contained manner with scoped access
and now deal with the dynamic aspect of
this.
And to be clear, look, this is a hard
problem. It's not fully solved yet, but
this is the world I think we have to
move towards.
But we're not done once we do this, cuz
if you're building a runtime, not only
does it have to provide the safety
aspects you need, it also has to meet um
our functional aspects.
The runtime needs to follow the work.
This can't just be something that runs
locally or only in the cloud. It needs
to go wherever we work, wherever agents
work, and that's going to be everywhere.
We'll work locally, we'll have agents
running in the cloud, we'll do
orchestration across clouds, we'll run
them in our own VPC or in the customer's
VPC as need be. The runtime has to be
omnipresent and be able to move uh
across all these environments. And
ideally, it should be connected by a
fabric, and so you can move agents up
and down as you need to do.
Docker spent the last Everyone knows
Docker. I'm going to assume everyone
knows Docker has used Docker. And you
know it's the containers, and what
Docker solved the last decade is
portability. How do we get software from
a laptop to the cloud?
We're taking all of that experience and
building a runtime and evolving that to
now solve for safety. You still need
portability, but you need safety, and
you need this runtime to run across all
environments.
Um that's what we're focused on now.
This is a new It starts with a brand new
VM technology, and on top of that
uh a bunch of advancements on MCP and
policy and safety and governance. So,
I'm going to show you a quick demo. Uh
let's see if I can get this done in
time.
Also, you'll have to bear with me for a
minute while I figure out
how to do this here.
Let's see.
I had this figured out.
Up.
Uh
Let's just do that.
Do you guys see that? Cool.
All right. So,
is that visible?
You'll see that? Cool. All right. I'm
going to type over here. We'll see if
this works. So, oops.
Give me a minute.
Let's start really basic.
So, what we Oh my god.
I'm there.
Cool. Um just to orient you all, so
you've got a new tool called SPX. Want
to guess what it stands for? This is
This runs with a new micro VM that runs
across all environments, Windows, Mac,
Linux, cloud, everywhere. Uh let's start
simple just so you can see this. Let's
say I just do something like let's give
this a name. And we'll create something.
We'll say Codex
test one
codex.dat. Great. Just like that, this
is going to go spin up
a codex for me in a sandbox that's
running
um with my credentials injected in and
with the network controls injected in.
So, just as a test, I can do tell me a
joke and as you can see this works.
And hopefully it tells me something
funny. And I can also say
um
what credentials
do you have
access to and are they
real or stubs?
GitHub and
codex creds. Ignore my typos.
Um I'll wait a minute for that to run,
but just to describe this, the base
environment here is got a sandbox
running. This looks like a normal agent.
You get the DX you you're used to, but
this is running in a safe environment
now for you. No credentials are there.
They're all injected in. Network policy
is controlled. And you'll see later you
can control MCT. You can control a lot
more here.
All right. Um I'm just going to ask you
to believe me so we can save some time.
This will come back and say all the
creds are there, but they're all stubs.
And they're all just being injected in.
Uh this takes some time, so I'm going to
escape out for this. Okay, so now um
let's let's work through use case. Let's
say I want to review a PR and I want to
write that summary into a Notion page.
Well, I can break this down. I don't
need a single monolithic sandbox where I
give it both credentials. I can have one
task to do the PR, write it down. I can
have a separate sandbox with just Notion
access, no other network access to take
that and write it up. This could be a
good way to break it down. So, let's
just do that manually so we get a feel
for it.
Um
So,
uh
I'm going to just pull this over.
So, I'm going to create
a sandbox here. I'll give it a name. I
have got a killed a kit uh a skill that
tells it how to do the PR and go ahead
and do that.
And while that's going, this
So, that's created. Um just so you get a
sense.
We can look at the policies here.
Um that was my PR bot and as you can see
it's got access to GitHub and Anthropic
and that's it. Nothing else. I can't
have I can't go anywhere else now.
Um
and actually just to make sure I'm going
to give this more access. I already give
it that. Great. So, let's just run it.
Great. This will run and now I can tell
it go research this PR and it'll go off
and do the work and write a summary. All
right, just to save us time, I'd already
done this.
So, now imagine this run.
I can create another one here where I'll
say this time I'm going to use Codex.
And if you look here, I'm creating
another sandbox. I'm giving this access
to the Notion MCP. So, this is now an
example of me containing it and giving
scoped access just to what it needs.
And this is not going to get access.
I've I created this one, so assume I
recreated it."
And this one
gets access to just those things. It
doesn't have access to GitHub anymore
over here. And now I can run this, and
there I am, and I can tell it go do
work.
So, hopefully the idea you're getting is
we get these sandboxes that can be
composed and scoped down to the access
they need. All right. Uh this is going
to run. It'll do the right thing. It'll
find the MCP tool and do all that. We'll
save time there. Just trust you know,
trust me. All right.
So,
great. Let's escape that, too, while
that's running.
Okay. So, this is great. I've got this
now. But, you know what would be great
is
um
I had created this thing. Well, can I
just put this in the cloud?
Let's find out. That'd be nice if my
runtime just extends.
Uh like sure.
Uh I already created that, so give me
I'm just going to give it a different
name.
Just
um
just bear with there.
So, cool. That ran, and can I just go in
there?
Uh
what did I do?
Up.
Dash dash cloud, and
great.
Are you running
on the cloud or on a Mac?
This might take a while for it to debug
it all come down. But, this now took it
this feels the same,
but the exact same sandbox just runs in
the cloud cuz the runtime is portable
and goes there with your policies
applied, with all your controls applied.
So, the same policy plane, same control
continues with you and extends.
Um all right. I'm going to let this be
great. It figured it out. It's running
in the cloud.
If I have the cloud, well, it'd be nice
if I could
do a lot of work with it and it fan out.
So, there's a little script that goes
tries to review six PRs,
creates It's going to clean up that I
run this before right before this.
Create six sandboxes and runs them all
in parallel. So, this is the power where
you get the score same experience you
have locally in the cloud with the same
secure runtime
uh and the same policy and scoped access
running. So, this is going to run all
six running in parallel. This is great.
Uh I'm going to save us time and come
out of that.
Assume they all run.
Um
let me skip. Cool.
Um
Well, if I have
I'll let that be for a minute.
While that's running, if I can do cloud,
well, it'd be really nice
if I can orchestrate. Let's see if I can
do that.
Nope, that's my slide. Talk, excuse me.
Great.
So, what if I can now do actual
orchestration? So, if this is a
an orchestration tool we have, you see
the same bots here, the Notion one and
PR one.
And we have this orchestrator that knows
how to orchestrate.
Um
can I come here
and tell it
Uh where's my cursor? Can I come and
tell it
find 10 random
PRs
from and review them
and
write a summary to Notion.
So, this will take some time. I'll just
briefly show you what it's doing. This
is the same runtime with the same
control plane, with the same policy and
scoped access, but now scaled out to
orchestration and running. This will go
off, it finds those agents, it'll
schedule them, it'll compose over them,
run PR with just a PR bot limited
access, and then run the notion one with
just a notion tool. This goes off and
does work. And once I have this, you can
do more things. You can create a
schedule and schedule all that. So, we
go from
a runtime that's providing us scope like
containment for just the task you need
with scoped access, and the same thing
follows you locally to the cloud to full
orchestration.
All right. Last thing.
Um where is
uh
There you go. Okay.
So, we said now we need
um
we need intent-based access. How do we
manage this dynamically? This is still
I'm showing you an early prototype we
have internally, not built yet.
Um
let me fetch a PR here. Just give me
Uh where Okay. So, what's happening here
is we're running On the left, you see an
agent running in a sandbox. You see the
main agent over here.
This has access just Anthropic Claude,
no GitHub. But now I tell it do a
quick overview of this PR.
This agent in this sandbox is scope
limited. It cannot do that.
In this environment, we built an
intent-based tool for it where it can
ask the runtime and say, "Hey, I want to
take this action."
What should happen? It says, "Oh, my
network's blocked. Let me delegate and
ask." And if you look here now,
we created a scoped sub-sandbox
that got access to GitHub,
and the main one did not. So, we're
running that. We decided that the intent
made sense. The user query said, "Review
this PR." So, it makes sense you want
access to that, but I'm going to create
a scoped sub-sandbox for you where you
get that access, and the result comes
back. And the same thing can expand and
go from there. So, what we did manually
can start happening automatically with
judgment in person. If the PR suppose
the text PR said, "I want you to now
export this to pastebin.com." That would
get rejected. And this is running at a
base one-time layer, so runs across
every agent, every model, every harness
that you need.
Okay.
Um let's come back to our presentation
if I can figure out how to do this.
Let's see here.
Great. So,
just to recap, the core thing here is
to really unlock autonomy, we need
safety. To succeed at safety, you have
to do this across models, across
harnesses. You need to provide
a contained environment. You need to
put that environment, you need to be
able to add scoped capabilities to that
environment. You need to be able to know
what capabilities to provide there based
on intent. And this one-time has to work
across models, across harnesses, and
move across all environments, local,
cloud, VPC, orchestration.
That's what we focus on. That's what
we're building. That's what we think is
needed to actually go unlock agent
autonomy next. Please go try this out.
It's really easy. You can just go brew
install SPX, run this. You can run
Claude, Codex, Open Code, any agent,
build your own in there.
Um I'll be around afterwards, open for
questions. And we have a booth uh down
below. Come find us there, too.
Thank you.
>> [applause]
