---
titulo: 'Why DeepSeek Harness Just Became The Fastest Growing Github Repo EVER'
tipo: item_agregado
plataforma: youtube
canal: 'Chase AI'
data: '2026-08-20'
url: 'https://www.youtube.com/watch?v=f51ICIoHcjY'
thumbnail: 'https://i.ytimg.com/vi/f51ICIoHcjY/maxresdefault.jpg'
descricao: '⚡Master Claude Code, Build Your Agency, Land Your First Client⚡ https://www.skool.com/chase-ai 🔥FREE community🔥 https://www.skool.com/chase-ai-community 💻 Need custom work? Book a consult 💻 https://chaseai.io The brand new deepseek harness is a step forward in harness design, but does it leap ahead of claude code and codex? ⏰TIMESTAMPS: 0:00 - DeepSeek Harness 5:39 - Plugins 9:57 - Better Than Claude Code? RESOURCES FROM THIS VIDEO: ➡️ Master Claude Code: https://www.skool.com/chase-ai ➡️ My Website: https://www.chaseai.io ➡️ DeepSeek Harness: https://deepseek.com/harness/en/ #claudecode'
resumo: "The video reviews DeepSeek Harness, DeepSeek's newly open-sourced coding agent harness (analogous to Claude Code) that became the fastest-growing GitHub repo ever, explaining its plugin-based, fully customizable and self-modifying architecture, its installation and web UI, its different execution modes (standard, PTC, minimal, creator), and whether it's worth switching to from tools like Claude Co..."
tags: {}
fontes:
  - 'https://www.skool.com/chase-ai'
  - 'https://www.skool.com/chase-ai-community'
  - 'https://chaseai.io'
  - 'https://www.chaseai.io'
  - 'https://deepseek.com/harness/en/'
---

## Descrição

⚡Master Claude Code, Build Your Agency, Land Your First Client⚡
https://www.skool.com/chase-ai

🔥FREE community🔥 
https://www.skool.com/chase-ai-community

💻 Need custom work? Book a consult 💻
https://chaseai.io

The brand new deepseek harness is a step forward in harness design, but does it leap ahead of claude code and codex?


⏰TIMESTAMPS:

0:00 - DeepSeek Harness
5:39 - Plugins
9:57 - Better Than Claude Code?



RESOURCES FROM THIS VIDEO:
➡️ Master Claude Code: https://www.skool.com/chase-ai
➡️ My Website: https://www.chaseai.io
➡️ DeepSeek Harness: https://deepseek.com/harness/en/


#claudecode

## Transcrição

So, Deepseek just open sourced their own
harness. It's called Deep Seek Harness.
Think of Claude Code for Claude. This is
Deepseek Harness for Deepseek. And it
has become the fastest growing repo
ever. We're at 167,000 stars in under a
week. Now, the thing you keep hearing
with this harness is that everything is
a plugin. And this can get kind of
confusing because we already have
plugins in other harnesses like Claude
Code and Codeex. oftentimes those look
like skills or MCPs or CLIs, these
different outside tools. We can tack on
to the harness itself. How is Deepseek
harness any different? Well, the Deep
Seek harness allows us to actually edit
and change the harness itself in ways
you just can't do with these other
harnesses. And big picture, what
Deepseek is really trying to sell you is
the infinitely customizable harness that
can be somewhat self-improving in
certain respects. It can write its own
plugins for its own harness that could
be custom to whatever it is you're
trying to do. And so the response to
this has been an exploding ecosystem of
DeepSeek harness plugins that you can
attach to whatever your build looks
like. On top of that, this is something
we can make completely local. We can use
this with any sort of model we want. So
there's a lot to work with here. But the
big question becomes, does this live up
to the hype? Is this new harness
actually worth 167,000 stars in a
handful of days? especially if you're
someone who's already using Cloud Code
or Codeex. Well, that's exactly what
we're going to talk about today. And I'm
also going to break down how this thing
actually works so you can make an
informed decision about whether you
should move to this harness. Now, first
let's talk about installing this thing.
If you just search for the Deepseek
harness on GitHub, you will find this
page. To install it, you'll just run
this command in the terminal and it's
going to bring up a web UI that looks
just like this. Now, this setup should
be pretty familiar as it follows pretty
much all the conventions of all the
other harnesses. Over there on the lefth
hand side we have our workspaces which
is just the folders. Underneath here
you'll have your different chat windows.
Right up front we have our prompt
window. Again you can choose your
workspace and then we have a series of
modes we can choose from. So we have
standard mode, PTC mode, minimal mode
and creator mode. So standard mode
exactly what it sounds like. It's
standard. This is like what you do if
you're inside a cloud code. PTC mode is
all about multi-step tasks. So in
standard mode, if you wanted it to do a
bunch of tool calls, it would do those
tool calls one by one. In PTC mode,
instead it would create a script where
it runs all the tools essentially in one
go. The idea is if you're doing like
complex tasks, this will reduce some of
the context bloat that you get when
you're just calling the tools one by
one. We then have minimal mode. That's
for very simple tasks. And then lastly,
we have creator mode. And we'll touch on
that later, but creator mode is where
you can create your own plugins for this
harness. We press this plus button. We
have a number of different slash
commands. Again, some of these should be
familiar to you. Things like compact,
things like goal. I then have a
permissions for readon, workspace,
write, and full access. And then lastly,
we have our model in our effort. Now,
remember this is an open source tool.
You don't have to use deepseeek for
this. In my case, I'm not using the
Deepseek API. I'm using open router. So,
I have access to, you know, essentially
every single model out there. Now, in
order to set that up, I'm going to come
down and go to settings.
Going to go to models. And then you just
add a provider and insert your API key.
Over here in general, just sort of your
standard stuff. One thing I'll mention
is the enter behavior while busy. This
is similar to what we see in codec. So
if it is executing some sort of tool
call, you can have it by default be Q or
steer. So if it's on Q, it's going to
wait till it's done doing what it's
doing before it takes your next message
versus steer. That's where it's going to
finish its current tool call and then
bring in your additional message. Next
we have plugins. So over here on the
plug-in list, this is essentially every
single thing you can do inside of the
DeepC harness specified as a plugin. And
then we have the plug-in configuration,
which shows us things we can actually
edit inside of the harness itself. Like
when we look at something like the agent
loop, this is literally like a core part
of the plumbing of the DeepC harness
that you can begin to edit just in terms
of parallel tool calls. And you can go
deeper with these sort of edits using
creator mode. This is the difference
between deep sea harness and cloud code.
Claude Code, you can sort of approximate
a lot of these changes that we make at
the harness level inside of the Deep
Seek harness with things like skills and
hooks, but you aren't changing the
actual plumbing under the hood. You can
do that here. But on the flip side, with
all this customizability comes some
annoyances. For example, like the web
search. If you want to be able to use
web search out of the box, you need to
provide the DeepSeek API key. Well, if
you don't have that and you're using
some sort of other model, well, you now
need to find a plugin that allows you to
do web search with with essentially any
model. For example, I installed this
free search plugin, which allows me to
use things like Bing or Duck.Go for my
web search. So, I found this plugin on
GitHub. Again, there is a huge exploding
library of different DeepSeek harness
plugins and simply follow the
instructions to install it. Now, does
that seem somewhat sketchy that I'm
installing some random Chinese plugin?
Well, first of all, I actually had
Claude Code take a look at it to make
sure it wasn't sketchy. But two, this
whole idea of sketchy plugins is
something you want to pay attention to
and is a major sort of red flag with the
whole Deep Sea Caress concept because as
of right now, out of the box, remember
this is in developer preview. Every
single plugin you add to Deep Sea
Carness gets full shell access and full
access to your entire file system. So,
like if you install a random plugin into
Deep Sea Caress, again, this is very
reminiscent to like when Open Call first
came onto the scene, be wary. Make sure
you know what you're doing because if
there is a bad actor that has some sort
of sketchy plugin that can take a look
at your API keys because it will
essentially have permission to do so,
you can be in a bad spot. So, just be
careful there. Hopefully, this is
something that changes. And again, that
is one of the differences between
DeepSeek and Cloud Code. Now, let's say
I want to install a plugin. great
resource right now is the awesome DeepS
harness plug-in page on GitHub which has
a huge curated list of a bunch of
different ones. Let's say I'm looking
for UI enhancements because again since
we can edit the harness itself, we can
do things like change what the actual
user interface looks like. Let's say I
want to add an effort slider. Well, then
brings me to that GitHub page and then I
just follow the instructions for the
install, which in this case is just
running this command inside of the
terminal. So, I run the command. I then
restart the Deep Seek harness and I now
have this effort level slider. Now, one
thing you're going to see on a lot of
people's sort of like pages and content
related to this Deep Seek Harness and
plugins is the idea that when I there's
like a built-in hot reload system for
the plugins where you don't have to
restart the harness. That is kind of
sort of true. It depends on the plugin
itself. For certain plugins like the one
you saw here, yes, you do need to
restart the harness and it takes like 2
seconds. But that's as easy as
installing plugins is. Now, if I want to
create my own plugin, which I think is
the big cell for this thing, is I'm just
going to go into creator mode, and I'm
just going to describe the type of
plugin I want to create. So, let's keep
it kind of simple here with the UI and
say, I want you to create a plugin that
changes the UI color scheme to look like
the Matrix with the falling asy
characters
in the background.
can't type.
So, another thing I want to point out
here as it begins creating its own
plugin. So, right here at the bottom, we
get a little more information than you
might necessarily get with other
harnesses. I can see things like the
cash hit percentage. I can see the input
and output tokens. I can see my tokens
per second. All this cool stuff. Also,
in terms of what I actually see on the
screen here, I think it goes a little
bit more in depth than where we see uh
what other models show us, right?
context injection. I can see the actual
system prompt itself. And what's really
cool is this thing up here called the
trajectory where I get like deep insight
into everything that is happening like
the user, the context, the assistant,
the tools. If I click on these things, I
can get even more information, right? A
summary, a preview, raw source. And this
is like way more insight and way more
visibility than a harness like cloud
code is going to give you. So, in the
situations where you're having to do a
lot of troubleshooting, like where is
stuff actually going wrong? Well, this
is where I can find it. And if you see
this thing called Cordis all over the
place, well, this harness at its core,
the kernel, the engine of this thing is
this program called Cordis. This is what
it all runs on. And so, Cordis is like
just this chassis of the car and the
plugins is the engine and all these
other things we're putting on it. Now,
you also have the ability to download
the entire session log, which goes into
a zip folder. And that is going to show
you all this inside of JSON. And I can
also break it down by turns and calls
and all this stuff. So, that's the other
big cell for this harness that I think
it does better than anything else is the
insights to what's actually going on
under the hood. And the second thing is
this this idea of custom plugins that
affect the harness. Because again, take
this to like its extreme once this thing
is actually pretty flushed out. Well,
imagine changing how the harness works
at its most base level so that it is
perfect for your particular problems.
Again, we try to approximate that in
things like cloud code and codeex with
skills and hooks and really you see that
with dynamic workflows, but this lets
you get extremely granular. So, here's a
look at what it created. Obviously, this
is ugly as sin, but this silly example
shows you how easy it is to create
custom plugins that affect the harness
itself. Now, one thing to note when you
create custom plugins, it doesn't save
it. It's not permanent. You have to tell
it that you want it to be saved. So,
that is just a prompt to say, "Hey, I
want to save this plugin or I want to
save this plugin with a toggle,
something to that effect, or else you're
going to lose it at the end of the
session." So, that's the DeepS harness
in a nutshell. Again, the two big cells
here are the custom plugins, the fact
that we can edit the harness at an
extremely base foundational level, and I
would say this trajectory, right? the
ability to actually get really deep with
what the AI system is actually doing
under the hood when we're giving it
prompts and things of that nature. We
just don't have that same level of
control with any other harness. Plus,
the fact that it's open source and it
can be fully local, all that is great as
well. So, with all that being said,
should you leave Cloud Code? Should you
leave Codeex and jump ship to the Deep
Sea Caress? M probably not. Don't get me
wrong, I think the Deep Sea Caress is
awesome. I'm glad they didn't try to
just create a Claude Code clone or
create another harness that looks like
everything else out there. This
everything is a plug-in approach. And
the fact that it's extremely
customizable is awesome. And the dream
of this, you know, self-improving
harness that can create its own plugins
and adjust itself to your task sounds
awesome in theory, but in reality, I
don't know if it's ever going to
actually happen. And maybe it will, but
not today. And there's also the question
of like, is it worth it to do something
like that? Is it really moving the
needle so much to have this custom
harness for your problem? And is it also
worth the time and energy to get to that
place? Maybe, maybe not, maybe
eventually will. Either way, today I
think it's tough to say it's really a
step above claw code at any sense. But
it's free. It's open source. It's fully
local. So why not use it? Why not tinker
with it and give it a shot? Because we
can use both of these things. We aren't
stuck with any one tool. And in general,
we should definitely be tool agnostic.
So definitely try it out. Let me know
what you think. As always, make sure to
check out Chase AI Plus if you want to
get your hands on my Cloud Code
Masterass. And besides that, I'll see
you
