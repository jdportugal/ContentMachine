---
titulo: 'BASE vs Graphify: The AI Memory Tool You Need to Be Using'
tipo: item_agregado
plataforma: youtube
canal: 'Charlie Automates'
data: '2026-08-31'
url: 'https://www.youtube.com/watch?v=x27XqGdgZkc'
thumbnail: media/monitoring/youtube/x27xqgdgzkc.jpg
descricao: "Download Charlie OS (my full AI Operating system): https://charlieautomates.com/charlie-... Join Charlie Automates Community (Free Claude Code templates & workflows included) 🔗 https://www.skool.com/cc-strategic-ai... 2-Day Intensive (Live Claude Training): https://yourclaudeacademy.com/yt - 🔑 Resources: BASE Playbook & Repo Link (starter commands included) - https://charlieautomates.com/free-res... PAUL Playbook & Repo Link - https://charlieautomates.com/free-res... 🎥 Referenced Videos: Claude Code BEGINNINER Tutorial - • FULL Claude Code Tutorial for Non-Coders i... Skillsmith Guide - • DON'T Build Claude Agents. Build Skills. - Timeline: 00:00 Graphify fixed your memory, nothing fixed your rules 01:54 Three moving parts and the two tiers 04:06 The token math and the MCP you never ins..."
resumo: 'The video compares two AI memory tools, BASE and Graphify, highlighting how BASE creates a dynamic knowledge graph for rules and behaviors in Claude Code, allowing for continuity between sessions and self-learning capabilities, unlike the static nature of Graphify. It discusses the setup, functionality, and advantages of using BASE for managing projects and rules effectively.'
tags:
  - '#Claudecode'
  - '#claude'
  - '#aiautomation'
fontes:
  - 'https://charlieautomates.com/charlie-'
  - 'https://www.skool.com/cc-strategic-ai'
  - 'https://yourclaudeacademy.com/yt'
  - 'https://charlieautomates.com/free-res'
---

## Descrição

Download Charlie OS (my full AI Operating system): https://charlieautomates.com/charlie-...

Join Charlie Automates Community (Free Claude Code templates & workflows included)
🔗 https://www.skool.com/cc-strategic-ai...

2-Day Intensive (Live Claude Training): https://yourclaudeacademy.com/yt
-

🔑 Resources:
BASE Playbook & Repo Link (starter commands included) - https://charlieautomates.com/free-res...

PAUL Playbook & Repo Link - https://charlieautomates.com/free-res...

🎥 Referenced Videos:

Claude Code BEGINNINER Tutorial -    • FULL Claude Code Tutorial for Non-Coders i...   

Skillsmith Guide -    • DON'T Build Claude Agents. Build Skills.  
-

Timeline:
00:00 Graphify fixed your memory, nothing fixed your rules
01:54 Three moving parts and the two tiers
04:06 The token math and the MCP you never installed
06:04 Knowledge domains: rules that fire only when they match
06:59 Star commands
10:58 The relay: terminals that talk to each other
12:32 Devmode: see exactly what got injected
14:47 BASE vs Graphify, honestly
15:25 INSTALL: a brand new workspace
16:51 Scaffold and your operator profile
18:49 Adding PAUL and existing projects
22:26 Live demo: sessions pinging each other
25:27 Wrap up

#Claudecode #claude #aiautomation

## Transcrição

If you're using Clawude Code, you've
definitely heard about Graphify and
Obsidian. Graphify creates a knowledge
graph of all the data inside of your
Claw Code setup, essentially helping you
achieve unlimited memory with Claude.
But what about a system that can do the
same thing, but for your rules and
behaviors? Every time you boot open a
session of Claude, it's going to pull in
your static claude.md file with all of
your rules. But the problem lies in the
fact that the document is static and the
rules can become outdated pretty
quickly. But this new GitHub repo called
Bass solves this. The difference between
Bass and Graphify is that Bass will
create a knowledge graph around your
rules and behaviors and all the projects
you're working on. So whenever you boot
open a new session of Claude, it's going
to become aware of all the sessions and
projects you already have working. And
that's done through a hook and MCP
server that Bass comes pre-installed
with. So, I hope you guys are excited
because that's just the tip of the
iceberg with Bass. We're going to be
going a toz on exactly what it does, how
it works, and how you could set it up
for yourself today. So, here's the
promise. We're going to be giving you
guys a claude code that can remember
everything between sessions from the
first second of the next one. If you're
interested in the tech, it's a Rust
binary and it's one knowledge graph
that's being used with multiple hooks
and the MCP server that I had mentioned.
And the two biggest problems that Bass
is going to be solving for Claude is the
lack of continuity between every session
that it has because every session you
start now starts blind. And the second
one is the Claude MD. It helps you have
all your rules in there, but it's static
and your rules can become outdated
fairly quick and it's loading the same
block of text to every conversation.
Now, what if your clawed MD injected
dynamic rules based on the context of
the conversation that you're having with
full context of every project you're
working on? And I haven't even mentioned
that it self-learns. Anyways, there's
three moving parts of this. Base holds
the hook pipeline, the knowledge graph,
and the base MCP. And there's two tiers,
global or workspace. You could have
multiple base setups for different
workspaces. Let's say you have different
projects with different file systems.
And then we have the global base, which
is all of our global settings. global
tier is going to follow you no matter
what workspace you're in, but workspace
is specific to the repo inside of your
file structure. Now, my biggest gripe
with graphify is that you have to cury
the knowledge graph and you have to
slowly inject all of your context and
projects into that specific knowledge
graph and it's not keeping an overall
view of your workspace and everything
you're working on. Whereas bass does
bass will sit at the top of your context
window. So whenever you start open
claude code you have aclaude folder that
has your claude MD and everything inside
of it but at the top we now havebase and
it's going to host all of your
information here. Base comes with a
viewable dashboard and if I click over
into domain rules we can see I have a
list of global always on rules kind of
like the claude MD and the domain rules
are knowledge domain. So you could see I
have a knowledge domain set around
traits and I have separate rules in
there. This is also always activated as
opposed to something like the dev
knowledge domain. Whenever I use these
specific keywords, these rules will be
auto injected into the conversation that
I'm having with Claude. But with the
session start hook, it's always going to
pull in the global and trait rules as
well as the context around the projects
that I'm working on like your Claude
Academy or my own website. And you can
see all the tasks that are going down
with this particular project. And this
is all injected with the session start
hook. So this is why base sits at the
top of the context window. So, what this
is doing is creating memory that Claude
already has available at session start.
It's not memory that Claude is going to
have to go and fetch. Now, when I go
into the five hooks, there's no tool
call here. It's automatic. Claude never
decides to check its memory. It's
forced. And it also uses the user
prompts hook to read your words. And as
you saw in my knowledge domain
specifically for dev, there were
specific keywords. So, whenever a
keyword is matched, it's going to use
this particular hook. And then it's
going to use the pre-tool use hook, read
the file path, and load the maps for
that specific knowledge domain. And like
Graphify hoped to achieve, this will
actually help you save 70 times on your
token usage because the injection of
data is scoped and dduped. So after a
long session, it's not going to slowly
refill with the same rules and repeat it
over and over. What I want you guys to
remember is that a skill has to be
invoked and a hook is just going to
fire. That's the biggest difference and
why this actually gets used in every
conversation with Claude. Before we saw
some of what was auto injected in my
initial conversation with Claude. We saw
my project that was pulled in. But not
only will Claude know what you were
doing, it's going to know who you are,
your operator profile. So, think about
it as your north star and your values.
And you can add more context in there if
you'd like. It's going to find the open
handoffs that are attached to base. So
when you start a session, it's going to
know who you are, what you're working
on, and where you left off because bass
comes pre-installed with a handoff
system at the top level, the base level,
not just a bunch of handoff documents
that are scattered through your file
system. So now, every new session of
Claude is already going to be caught up
with what's going on in your life. If
you don't already know what an MCP is,
it's basically a bridge between your
clawed code setup and other softwares,
or in this case, your clawed code and
the knowledge graph. Hooks are
automatic. They're little pieces of code
that are always going to fire, whether
it's pre-tool or user submit or session
start. And the MCP is on demand. It
stands for model context protocol. So
hooks are pushes, MCPs are pull. And
that same server can write back data to
the MCP. So any decisions or things
you've done in that session at the end
will be updated and integrated into your
knowledge graph with bass. So we're
conglomerating what it does, how it
works. We're going to get into the
install process here soon. But these are
some of the commands and you can use
them as commands in the terminal or you
could just talk to Claude like hey base
install like here's the repo. Install it
base scaffold. Let's scaffold out the
actual base workspace. And there are a
few other ones here, but we're going to
be doing it with natural language inside
of Claude. But you only have to set it
up once. But let's talk about knowledge
domains, okay? Because I showed you a
little bit about what they look like on
the base dashboard. And if I put in this
prompt inside of Claude, fix the GHL
workflow trigger, go high level, right?
That's my CRM. These are always on
domains global and trades. But only if I
use these keywords will these 12 rules
for go high level be activated. and all
the other knowledge domains with all the
other rules won't be activated unless
these keywords are being used that are
attributed to each one of these
knowledge domains ultimately saving us
context and giving us better behavior
and output more consistent output with
claude every time and there's two ways
to add more rules you could just say hey
with this knowledge domain add this rule
or when I show you specific starter
commands I typically run star end and it
can look through the whole conversation
that you had it could be 999,000 tokens
that you had used in that session or
probably a little bit sooner. But you
you do start end, it'll look through the
conversation and automatically find what
it should update into base and add those
rules accordingly. And I'm going to give
you guys the full playbook on all the
starter commands you guys need. There's
going to be a resource document below.
And I also want to let you guys know
ahead of time, if you're not already
subscribed and you're enjoying this
content, please consider subscribing.
It's the best way to support me. And
definitely like this video. We're going
for 150 likes on this one this time
around. And if you're enjoying what I'm
putting down, I do have a one-click
install for this entire setup, plus all
the other frameworks that I talk about
on my channel. That's going to be one of
the links at the top next to the
resource document. It's called Charlie
OS. If you're interested, click it, book
a call with me, and we can chat. In any
case, with Bass, there are only a few
commands that I'm using religiously. I
use the end command all the time. This
is going to oneshot the end of a session
to create handoff documents, any
lingering tasks and update any rules or
behaviors that it should pick up on. And
that's all autoregistered into base
knowledge graph for you. I also really
like task. I can set up a task inside of
the knowledge graph instead of writing
it down somewhere else. It'll just
register. So the next session start that
task will be available and I can attach
it to any of the projects that are
already registered in that knowledge
graph. And this is something that I've
been using very often. Ping. So, if I do
set up a task, I can run ping and I can
have another session that's opened hand
off that task to a completely different
session. And I'm not talking about a sub
agent. I'm talking about maybe opus or
fable to pick up on that task
automatically without muddying the
context of your current conversation.
And these aren't slash commands or
skills. These are what you would call
tokens that the promp hook recognizes
inside of just normal writing. And if
you want to create new commands, you
could just say base command add and
that'll register a new star command into
base depending on how you're using it.
So if you're curious as to how task
actually works, this is a little graphic
that I had Claude create for me. It'll
write the brief, register it, and relay
into another session if you wanted to or
just keep it in the knowledge graph. And
that closes the loop, puts it away, and
you don't have to think about it again.
And is how Bass is going to teach
itself. So it'll do a fork sweep for all
the sessions of work you've built. It's
going to sync the graph and create a
handoff document. So if you start open a
session tomorrow, it's going to already
know what you did and what you need to
do next. I'm sure you're excited at this
point. So if you want to be registering
new projects with Claude, it's just base
project ad. And I've talked about Paul.
It's like GSD, but Paul is an
orchestration layer that works directly
with Bass. So for every project that you
have in your workspace, if you have a
Paul folder for it, you can register all
those Paul files into Bass. So it knows
those projects automatically. And those
file paths are connected seamlessly. So
at a high level, all the projects you're
working on are available at session
start. Not all the context of them, but
the highest level of knowing what's
going on with them. So your graph is
going to keep the decision attached to
the work that you're putting in. And
handoff is one part of the star command
for end. It's exactly what you can
imagine. If you don't know what a
handoff document is, it's basically a MD
file markdown file that attaches what
you left off in that current session.
But N does it all for you. So I don't
use this by itself quite often. Now when
we talk about forks, this is absolutely
incredible and this absolutely will
change the way that you work forever. So
let's say you're mid build and you see a
real improvement and it's not just a
task. Okay, so normally you're going to
lose that in the scroll back and
whatever you're doing, but a fork turns
it into a forward build spec instead. In
most cases, a handoff is where you
stopped and it's one per project. A fork
is a thing worth building and they're
additive and they're going to be listed
at session start. So, this is not a
backlog that you have to consistently
groom. This is a spec that's already
buildable and that happens the second
you run star end. But now, let's talk
about terminal pinging. Every session
you open up has a code name. And let's
say Raven was my main session. If I have
Falcon, Badger, and Dingo available, I
can send tasks or whatever I want to any
open session. And it's essentially a
relay system available in Bass. And
sometimes when you have a bunch of
sessions open, everything can get messy.
But the best part about bass is that
it's only referring to one knowledge
graph. I already told you guys that bass
is going to get smarter every time you
run the start end command. And this is
just normal protocol. At the end of
every session, you run start end and
it's going to learn. And you don't have
to inject any of that context yourself.
And you might be thinking, why doesn't
the data bloat? We all know that context
is finite. Whenever you have a project
folder that's gone cold, you haven't
opened it in a while, it's going to stop
taking room at session start. And that's
automatic. You don't have to groom this
yourself. Everything relevant to what
you haven't touched stays out of context
and it's not just going to delete it. If
you touch those folders again, the next
reconcile or end you do flips it back to
active on its own. It's like an
automatic archive system. So, one
matching keyword or file path touch
reactivates it. This is how we can keep
Claude super intelligent on who we are
and what we're doing without context
bloat. And this is super relevant when
we're thinking about Graphify because
each app is going to have its own map.
So just like graphify, if you want to
run queries on a specific project, you
can do the same exact thing with this.
But again, this is the top level also on
your rules, behaviors, and project
context. So you're going to get a lot
more than what Graphy can offer you. And
just so you're aware, I've been working
with the builders of Bass, and they are
actively seeking to introduce this to
other harnesses other than just clawed
code. So if you have a harness in mind
and base doesn't fit with it currently,
just drop a comment. We read them all.
We're almost done through the end of
this. If you guys look at the chapters
below, you can fast forward if you just
want to see the install. But let's talk
about dev mode because this is super
important. We can activate dev mode
inside of base and we could see the
context bracket we're in. So Claude can
see what kind of context is left and
talk to you depending on how much
context left you have in that
conversation. You could see what
knowledge domains are loaded in the
conversation. And so for this one, I
have global and traits, which are always
on. And I didn't use any keywords that
activated any of my other domains and
the tools that were used in that last
prompt. So now you know exactly what
Claude is doing at every step of the way
and you don't have to guess. I showed
you guys about base dashboard. So inside
of Claude, you would just say base
dashboard and this opens up in your
browser and we can click into domain
rules and we can add rules at will. I
typically would do this with the star
end command, but if you do want to get
specific, you can come in here and do
this. And you could see I spend a lot of
money with Claude, but this is the
subscription, so I'm not using $22,000,
but if you do want to see the usage,
this is also a cool feature. You could
see where all of your tokens are going
depending on the date. Like today, I've
probably spent $1,300. That's crazy. And
it's only gotten crazier and crazier,
guys. For those of you people saying uh
Opus 5 isn't as good as Fable, um I'd
beg to differ. I know fable is better
but obus 5 is still absolutely
incredible and there's a lot more
analytics that you guys can dive into
but I won't waste your time here on
that. This is just a human's way of
reading your data instead of reading a
complicated toml graph and I touched on
Paul before but bass is going to help
you remember and Paul is going to help
you execute the orchestration layer.
Paul stands for plan apply unify and
loop. So at session start bass is going
to ingest the poll.json file all the
details about the Paul project aka your
project. So all of your Paul phases when
building a project and milestones are
landing in the same graph as your tasks
and decisions. And if we had to take a
look at the comparisons between base and
graphify both are building a graph only
one is always on. So graphify you're
running it at a folder or a specific
project. You have to open the report or
cury it. You rerun it to refresh it and
it understands a codebase or a corpus
document you point it at. Base runs
itself on five hooks reads your sessions
as they happen. It's going to arrive
before you type anything. Autolearns
with star end and runs your entire
operation. Do I still think graphify is
cool? Yeah, but I've switched to Bass.
So now before I get into the install, if
you guys want to pause here, this is
everything that's available with Bass.
It's free. There's going to be an
enterprise version that comes at a later
time. If you have multiple teammates in
a company all wanting to access that
same data at a company level, we are
going to be bringing that to the table.
But I'm excited to show you guys how it
works. So, let's dive into that. Now, if
you guys don't already know about Visual
Studio Code, it's how I use Claude Code.
If you guys want a full tutorial on how
I'm using it in VS Code, I'm going to be
attaching that also in the description.
But in this case, let me just fast
forward and set this up so I can see
everything properly. On my desktop, I'm
going to be creating a new folder. I'm
going to call it tool development. And
then inside of here, I'm going to also
create tool dev. And we're going to open
a new folder. And I'm going to open up
the second subfolder inside of it. We'll
trust and continue. And now we have a
brand new workspace. I still have my
base global set up, but we're going to
be setting up base inside of this
particular workspace. So most of you
guys would just type in claude. I'm
going to be doing it without permissions
skipped. And the first thing that you
guys want to do is take the repo link.
So if you guys go to the resource
document for base, that'll also have the
repo link in there. And you would take
the repo link, paste it in, and just
tell it to download it for you. If you
have any license issues, just say
circumvent it and move forward. You'd
also say create it globally and for this
project inside of tool dev. And you
still need to set up a cloud folder and
a claude MD. When you set up base inside
of the cloud MD that it creates for this
particular workspace, it's going to lay
out the base configuration. So how
claude can read base and know how it
works instantaneously. So with our
words, we're doing the base install. And
inside of my resource that I linked
below, I'm going to be creating a
downloadable file that you guys can feed
over to Claude. And on the resource
document that I attached below for bass,
I'm going to attach all of the base
commands. So, it's probably just going
to be a file you can download it and
feed that direct directly to Claude to
install all the star commands for you.
Then, the second thing we're going to be
doing is base scaffold. And I want you
guys to see this exact prompt that I
have here. You can screenshot it and
give it to Claude, but this is the
overall game plan. set up base for this
workspace. Scaffold it out and there's
nothing here yet. Set up an operator
profile with me. Ask me about the
projects to register into Bass. Now, if
you have a pre-existing workspace and
you have projects, you can set up Bass
for something that's already going. If
it's a new workspace, you could just run
Bass. And if you want to integrate other
projects, you can feed those files or
folders into here and tell Claude to
register them. You could also download
Paul and create Paul folders for all the
projects that you have. You don't
necessarily need the poll files for
them, but I would highly recommend it.
So, if you're retroactively doing this
with projects going, drop your folders
in as you go for the projects you're
working on and say, "Hey, add these to
X, Y, and Z folder here. Set up a poll
file for each project and let me know
when you're done. That way, it can start
asking you about those projects and
register them into Bass." And then, we
wanted to start helping you set up your
knowledge domains. It'll go through this
one by one with you. You want to make
sure you have dev mode on so you guys
can see the available domains, what's
loaded, what tools were used. And if you
don't already have a status line, you
want to tell Claude create my status
line with the code name of the session
so we can ping between different
sessions as well as the context used the
percentage. Also add we want the ping
relay system always set up. And if you
already have a claw MD file, don't use
this exact setup. You want to say make
sure you add the base configurations
into my current claude MD or run this if
it's a new workspace. Now I already have
base set up at the global level. So the
relay system was already set up. It
activated my other sessions. It sees
what's going on. And I won't have a lot
to add here yet, but we could see the
Claude MD was created. We could see the
configurations is set up for us. So
Claude is going to know automatically
how to use base forward. And what we're
going to be doing is retroactively
adding some of my other projects into
this workspace so we could see me add a
Paul file. If you have any questions on
Paul, I have my school link in the
description below, weekly calls, and
full education on the tools I'm talking
about or drop a comment. I could always
do a deeper video on this stuff. And
we'll also be discussing the operator
profile here and how to add knowledge
domains and then see everything we
worked on here in the in the tutorial in
real time. I won't go too deep on this.
You guys have to play around with this
and see how it works for you. So again,
if anything is confusing you, you have
the school link. You can join in on my
weekly calls or you can consider getting
Charlie OS. I'll help you install it and
I set it up for you. So now let's lock
back in. These are some simple
questions. So we got um just some tools
I'm developing here. It's asking us
about domains ahead of time. So I would
say yeah tool dev is always on domain.
So different rules that are always
active. I think these are all solid as
an example. Release. Yep, those are all
cool with me because every time we end a
session, they're going to auto update.
And Bass knows better than I do. And
projects that are registered. So, this
is actually a skill that I created a
while back. And this isn't at the global
level. It's in another workspace. But if
we come back here and I type in the file
path, take this. And I said, grab this
skill from the global level. This is
something we're going to be working on
here. and it's going to be in
development as its own project. And
we'll just submit the answers now. And
I'm not even going to go into what the
base TOML or domains TOML look like
because you don't need to. You could
always pull up base dashboard or just
talk to Claude about what information is
in there. But we could see down here we
have STO which is the session keyword or
the session code name and the context
used. And if you look over here, there
might have been some new files that were
created. This one and this one can be
deleted. If you guys want to see why,
you could just read this right here. Now
at the global level base is going to
have multiple domains. So you could see
all my domains are added here. Now what
we added was workspace only domains and
I have two set of global domains here
because I have my always active global
and then I have my global domains for
this workspace. If that's confusing drop
a comment I can explain and we've added
the meta ad skill domain. So they're
essentially inherited. If you want to
keep your global rules, only update it
from another workspace folder that you
have in the same way that I'm doing it,
you could say only update workspace
level graph stuff in this workspace for
tool dev. Um, and make that an always on
rule for this folder setup. Now, if you
want to set up Paul, you guys can grab
the link. I have it in that same
resource document with base or linked.
So, now I'm going to ask Claude to
create a Paul folder for the meta ad
skill and assign the project to base if
we haven't already. So we could see some
of the rules for never updating global
domain rules is available here in tool
dev which is always on. And I'm not
going to go into creating the operator
profile just because it's pretty simple.
Just say create my operator profile
learn about me. If you have external
contacts you could drop that in videos
about yourself. You might already be
using claude. So you could just set up
based in your current workspace. It
probably knows a ton about you already.
But one thing I would recommend if you
have a super bloated claw MD file, I
would say, "Hey B, can you go through my
claw MD? You obviously added the base
configuration settings in there, but can
you go through everything else and
instead of having it all in there, can
you create knowledge domains for me in
place?" And that way you could have
knowledge domains instead of a bloated
Claude MD file. And it has a great
starting point if you've been using
claude. Now, while this is running and
setting up poll, if I open up another
terminal and I boot open Claude, what
are we working on? At the top, you could
see the relay for the ping system is
activated. This code name is Weasel. And
we could see the only project that we're
working on is the meta ad skill project.
And it could see where it stands. It
created the pole file. So, it has a
unification system here. So, it has full
context of where it left off, what we
were doing. And we could see the dev
mode is available with a lot of our
global domains, but the ones specific to
this particular workspace for tool dev.
So the meta ad skill and release. So now
when I'm done here, I can just run star
end. And if you haven't already given
the file to base around the starter
commands, make sure you give that. It's
from the resource document. So we're
going to see how star end actually
works. It's going to update a handoff,
any tasks, any forks. And let's say I
open up another session here. I say
hello. We could see that this session is
Mircat. I can say ping Mircat for me and
I can ask it to analyze the meta ad
skill using Skillmith audit. I have
another video on that if you're curious.
That's also linked in in the
description. Um, but that's how I built
all my skills and audit it for potential
upgrades. And I can say when it's done,
have it ping you back with updates. So,
if I come over here, we could see that
it was just pinged. So the relay wake
mircat and it's going to go through and
this is actually going to give it a full
brief of what we wanted here. So this
way we could be really organized. See
it's already reading the poll project MD
for meta ads. So it has full context and
every session we open is clear on the
projects we're working on the always
active rules. No bloated uh claw MD
file. So if you guys can't see the value
of this, I would say you're out of your
mind. This is probably one of the best
systems I've ever seen for clawed code
and any harness once this is developed
further. And we could see that we sent
off the particular skill to be activated
the audit skill. It's looking through
the project. So I mean guys this is
really everything you would need and we
could see that the initial session we
started in um for end is coming to a
conclusion. It the global graph has
changed. So we can see at this session
that we started with, it's going through
making the full update for where we
ended with the full install for tool
dev. You could go through the operator
profile yourself and the only other
thing I want to show you is base
dashboard. So if I run this, so now
we're going to see the dashboard
available just for this tool dev
workspace instead of my global or the
one that I use on a daily basis. And
this is our graph for the new workspace.
Obviously, we have a lot of the already
global domains in there. So I had to be
specific actually. I want the base
dashboard for just this workspace for
just tool dev. All right. So this is the
knowledge graph for the global stuff as
well as the specific things for our
project here which is the meta ad skill.
And if we come into domain rules scroll
to the bottom we could see the meta ad
skill project down here the paths for
the particular project folder. Um the
release domain tool dev domain. And
since base is in development, if you
guys come across any bugs, feel free to
tell Claude to push that bug into GitHub
so my team can see what that looks like
and make the update. That's everything I
got for you on this goround. I know this
was a long video, but I'm sure you guys
got a ton of gold out of it and you
might have a lot of questions and that's
all right because this is one of the
best systems I've ever used on Claude
Code or any AI system. We thought the
orchestration layers were cool, but this
is definitely topping the cake. So, any
questions are welcomed in the comments.
You can join the school if you'd like.
But if you're a business owner or
somebody who just wants to get a head
start with these systems and have it set
up for you, you can consider clicking
the Charlie OS link in the description.
Book a time to speak with me and we can
see if it's a good fit for you. And if
you do decide to book a call and maybe
you need more than what Charlie OS
offers or maybe you need some general
coaching, we can discuss that too on
that call. So again, if you haven't
already liked the video and or
subscribed, consider doing both. I'd
greatly appreciate it. And the last
thing I'll say on the self-promotion
side of things is the fact that I did
just launch your Claude Academy. We're
doing 2-day intensive cohorts and it's
maxed out at 20 seats. So, if you'd like
to be a part of that, you can get your
seat. It's on discount right now. Our
last two cohorts did fill out. It's
$5.99 a seat. And we do have an
additional third day for engineers. It's
being run by my partner Sal and I. And
he's a full-time AI engineer. And you
already know your boy here. So, if you'd
like to join that, just click the YCA
link below. You can apply to grab your
seat. So, hopefully I get the
opportunity to see you again one way or
another. But until next time, take care
and God bless.
