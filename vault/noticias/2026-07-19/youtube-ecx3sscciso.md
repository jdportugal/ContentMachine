---
titulo: 'Cerebras Killed Notion, Obsidian, and Your "Second Brain"'
tipo: item_agregado
plataforma: youtube
canal: 'Nick Saraev'
data: '2026-07-19'
url: 'https://www.youtube.com/watch?v=eCx3SSCcISo'
thumbnail: 'https://i.ytimg.com/vi/eCx3SSCcISo/maxresdefault.jpg'
descricao: "🔥 Join Maker School & get customer #1 guaranteed: https://skool.com/makerschool/about 💎 Get this system for FREE here: https://skool.com/maker-zero/about ➡️ If this is your first video: I'm Nick Saraev. I run LeftClick, an AI growth agency. We've done work for large brands you're likely familiar with, like Mr. Beast, Anthropic, OpenAI, and a few others. In plain English: I help people build systems that generate leads, close deals, and scale businesses, mostly using new AI models like Claude. I also make videos about how to do all of that here on YouTube. It hasn't always been this way. My family immigrated from a Soviet bloc country (People's Republic of Bulgaria) in the 90s. They worked extremely hard, and we were broke for a very long time. I grew up wishing for more security, stabili..."
resumo: 'O vídeo explica como construir uma base de conhecimento interna para empresas usando RAG (retrieval-augmented generation), inspirada no sistema criado pela Cerebras, que integra dados de fontes como Slack, Confluence e repositórios GitHub num espaço de embeddings pesquisável por IA. O apresentador demonstra o conceito e constrói um sistema semelhante passo a passo, contrastando-o com abordagens ma...'
tags:
  - automation
  - 'content creation'
  - 'ai content'
  - 'google sheets'
  - chatgpt
  - wordpress
  - openai
  - blogging
  - 'openai api'
  - 'small business'
  - $20K/mo
  - 'cold email'
  - AIAA
  - 'ai automation'
  - 'ai automation agency'
  - 'ai automation guide'
  - n8n
  - 'n8n guide'
  - 'n8n aiaa'
  - aiaa
  - 'ai n8n'
  - 'n8n agents'
  - 'n8n ai'
  - 'n8n ai agent'
  - 'claude code'
  - claude
  - 'claude opus 4.6'
  - 'opus 4.6'
  - 'gemini 3.1'
  - 'gemini 3.1 pro'
  - antigravity
  - agents
  - 'ai agents'
  - coding
  - kimi
  - 'kimi k3'
  - agi
  - 'ai agent'
  - ai
  - 'artificial intelligence'
  - asi
  - AGI
  - AI
  - Claude
  - Codex
  - ChatGPT
fontes:
  - 'https://skool.com/makerschool/about'
  - 'https://skool.com/maker-zero/about'
---

## Descrição

🔥 Join Maker School & get customer #1 guaranteed: https://skool.com/makerschool/about
💎 Get this system for FREE here: https://skool.com/maker-zero/about
➡️ If this is your first video: I'm Nick Saraev. I run LeftClick, an AI growth agency. We've done work for large brands you're likely familiar with, like Mr. Beast, Anthropic, OpenAI, and a few others.

In plain English: I help people build systems that generate leads, close deals, and scale businesses, mostly using new AI models like Claude. I also make videos about how to do all of that here on YouTube.

It hasn't always been this way. My family immigrated from a Soviet bloc country (People's Republic of Bulgaria) in the 90s. They worked extremely hard, and we were broke for a very long time. I grew up wishing for more security, stability, and freedom over my own life.

I don't have a programming degree, and never took formal education for IT or workflows. I learned it all myself from YouTube videos like you're watching now.

As for my path: I started my first business at 19 in college. We threw events (mostly parties) at campus bars, and charged people for entry. 

That failed to provide me enough money to pay my bills, so at 21, I started another business, a door-to-door marketing agency.

From there, every year, I would start several companies, over 10 in total. I did this in a variety of industries, like e-commerce, photography, videography, SaaS, credit repair, etc. Each grew slightly more profitable, until, at 25, I would start 1SecondCopy, a content writing company. A friend and I eventually scaled that to over $90,000/m. It was my first taste of financial independence, and the reason I'm here today.

Since starting my journey, I've grown a fair bit of capital. To diversify my income, at 30, I began investing in other businesses and advising companies on how to grow in the AI era. These include SaaS businesses, like Clairvo, agencies like Dental Connect, and more. 

Collectively, the businesses that I own outright or are a part of generate over $10 million per year. This returns far more than the 5-10% you would get in the stock market, but also requires a lot of my active time. There's a tradeoff there, but I greatly prefer active investing, where I have control over the entities I'm a part of.

It wasn't easy to get here. But I did in the end! For anyone on their own entrepreneurial journey: I wish you luck, happiness, and perseverance. You can make it!

Summary ⤵️
The end-to-end, definitive course on Claude Code for beginners! I'll take you through a full four-hour masterclass where I start by teaching you how to set up and install Claude Code in a tutorial, how to configure your IDE or integrated development environment (we'll use Antigravity), how to utilize your CLAUDE.md file as your project brain, how to build your first project in Antigravity using Claude Code in under 15 minutes, advanced Claude Code functionality including hooks, slash commands, and more. 

I also teach you how to spin up multiple Claude Code instances and have them work on your behalf; how to parallelize work using sub-agents; how to use Git work trees to accomplish many hours of work in just a few minutes; how to conserve tokens and use context management to crush your coding and software projects; how to deploy things to the cloud using Modal and related services, and in general... how to be awesome at Claude Code! Hope you enjoy the tutorial.

Chapters

## Transcrição

So this is really interesting. This
company Sarah just built a knowledge
base that I think is not total
And that's hard for me to say because up
until now, virtually every instance of
knowledge bases or second brains or
whatever have just been total hot air.
This is a massive AI hardware company
that essentially creates super quick
inference chips and they're used by a
variety of major billion dollar
companies that you guys probably have
heard of. And at no point in this big
tutorial which they just wrote a big
blog post on did they show an obsidian
graph. At no point did they show a
floating 3D brain in a tube. Uh you know
instead what they showed is they showed
a very reliable and robust data
ingestion pipeline that allows virtually
anybody in their company to answer any
question that they have about the
business and about things that other
people have done in the business. What
they've done is they've fed in like
gigabytes of text and image data from
platforms like Slack. Every Slack
conversation that's ever occurred in the
organization. Um their wiki and
Confluence, all of their code repos and
GitHub, you know, their net list PRM
docs as well as custom databases.
They've just slashed or rather I should
say squashed all of this data into
embedding space which you can just think
of as concepts that an AI could query
over. So that if somebody said, "Hey,
what was that thing Paul was talking
about 3 days ago?" ago, it had something
to do with, you know, embeddings. It can
actually go in to the Slack without
having to, you know, query the API or
whatever and just always know what's
going on in the business. So, what I
want to do in this video that isn't
clear is I want to show you what this is
and then I actually want to build one
alongside you. I've already built one of
these for my own business and it's quite
useful already and that's just as a
small team. I can imagine how if you
have thousands of people in your
organization, stuff like this starts to
scale and get really, really good. Um,
and this is also just as mentioned like
one of the ones that's not
There's a lot of noise on YouTube
obviously because everybody's trying to
push their own particular way of doing
things. This is not my way of doing
things. This is this major company's way
of doing things um that I happen to
think is actually quite practical and it
it is not all about visualizing
something goofy for people that are non
super technical. Now to really
understand this system you first need to
understand what retrieval augmented
generation is. Also commonly referred to
as rag. Rag is the cornerstone behind
more or less all of this stuff. So, it's
worth at least a brief refresh if this
is the first time that you're
encountering this or not. What rag
basically does is it allows models to
answer questions specifically about a
set of data as opposed to just in the
general sense over all data. Like for
instance, if I were to type how tall is
Nick into my AI model chat box right
now, it would probably ask me who are
you talking about? Who is Nick? Well,
that depends broadly on which Nick
you're talking about. if you meant, you
know, Nick Jonas, Nick Jonas is XYZ
tall, right? That's a very general
question. So, it's likely that you're
going to have a general answer. And the
reason why is because it's enumerating
over all of its data. So, just to
visualize this and because I thought
it'd be kind of funny to make the whole
world know I'm taller than Nick Jonas.
Um, you know, here's a quick
visualization of maybe all the possible
answers you could get. You know, how
tall is Nick? Which Nick? Nick Jonas is
5' 10. That depends, right? Um, now the
question is, imagine hypothetically if I
didn't just say, "How tall is Nick?"
Imagine if the prompt that I fed into
this model was Nick is 6'2
and then I added some lines over here
and then I said how tall is Nick. What
do you think the model would say? Well,
it's pretty obvious how tall Nick is,
right? We just fed in a piece of
information immediately before the rest
of the prompt that just says how tall is
Nick? So, what do you think the model's
going to say? Well, the model's probably
just going to say, well, Nick is 6'2. It
doesn't even know really which Nick I'm
referring to or anything like that
because it just has the information
directly in its prompt, right? And so
what we've done is we basically
collapsed all of the possible responses,
all the variability of the model and
made it hyperspecific. We basically made
it like exactly what we want every time.
Okay? So all rag really is retrieval
augmented generation is it is augmenting
the AI generation of a piece of text by
retrieving information like Nick is 6'2
from some database. Okay. Now the key is
it's not just a database. It's not like
we just hypothetically have height of
every man on planet earth and then
somehow we store the right entry in.
What it usually is is it it sort of
exists in a language that LLM and large
language models can intuitively
understand called the embedding space.
You should know that all retrieval
augmented generation really is is just a
system that allows us to store
information before the question. Now,
hypothetically, let's say you were
trying to build a really cool knowledge
base that just answered all the
questions inherent in your business,
everything that's gone on in the last 24
hours. Um, well, you can apply that same
approach. Like if I just asked my little
knowledge base, hey, what did Peter say
at that talk last week? It's probably
going to go and collapse into or rather
spread a variety of different answers
and be like, who the hell's Peter? What
talk are you talking about? I don't even
know what year it is, man. I'm just a a
text corpus. So, what we need to do is
we need to find a way to insert
information. So, you know,
realistically, what we want is like uh
Peter Jackson delivered a talk last week
at 6:52 p.m. on Thursday. in the talk he
discussed X Y and Z. What we want is we
basically just want a way to prepen that
uh before the prompts, right? So all
this knowledgebased system from Sarah
does is it just allows you to basically
retrieve a relevant piece of information
from your company and then inject it
above your prompt such that if this is
your main prompt over here, this is just
like your sort of injected and I don't
know, I'm just going to call it like
help. I mean, the model doesn't know the
difference, right? the model just sees
the text whether it saw this or whether
it saw all of this combined. You know,
it's going to heavily change what the
model says, but it doesn't know that
like, you know, we've helped it out by
providing the information. It just
thinks like this is part of its training
data essentially. Um, and so that's
that's more or less what the system
does, and that's why it's so powerful.
It allows anybody in the whole company
to answer any question about anything
that's gone on. It also allows you to
store things like, hey, you know, here's
a set of problems and then here's a set
of solutions that we use to solve those
problems. So that if a newbie comes on
board, they're I don't know trying to
make some code fix or something and it's
not entirely working, they can just go
to the knowledge base and say, "Hey,
like why isn't this thing working?" And
they would say, "Ah, you're probably not
pushing to prod as we do in this
business. It's a little bit different
than most other businesses. Let me show
you guys how it works." All right, so
the first thing to know is this is made
by three handsome gentlemen, Isaac,
Daniel, and Mike. At least they're the
ones that wrote the the blog post. Uh
and currently they're being asked
something like 15,000 questions every
day. Now, this is obviously a pretty
long blog post. There a bunch of really
cool visualizations and stuff, but for
the sake of your time, um, let me find
the most important sections of this so
that you have everything you need. Okay,
the first is the anatomy of the
knowledge base. And so this knowledge
base is composed of three major
sections. The first is it's a platform
for collecting and storing internal
data. And so that makes sense, right? We
need to find a way to basically get
knowledge from outside of, you know, the
system into the system. So the knowledge
inherent in conversations let's say of
people in the Slack the knowledge
inherent in the SOPs of the company the
the checklists the processes all of the
emails all of that stuff we basically
need a way to take all of this in the
ether and then stick it into this
knowledge base in an appropriate manner
so that it doesn't get super crappy. The
second thing we need is we need a
platform for querying that data. And so
now that we have the data in the the
brain so to speak the knowledge base we
need a way to extract it and kind of
pull it out. And so this for instance
would be if I asked my knowledge base a
question about hey grab stats on the
last week of performance for XYZ
software platform and then apply that
cool lens that Nick talked about in his
most recent uh company seminar to it.
That would be an example of you know
using this platform which we've
collected and stored internal data into
uh to query it and then pull something
out. And then finally, because Cabris
is, you know, a big company and they're
dealing in hardware, they also need a
layer that enforces authentication and
authorization with auditing, analytics,
see who's like accessing the data, why,
and so on and so forth. Now, I'm pretty
small relative to this massive hardware
company that works with billion-dollar
businesses all over the world. So, I'm
not going to do that. And really, the
thing that's most important for us is
just going to be one and two. And I'm
going to assume anybody in my business
is probably okay. So, let's actually
visualize what this looks like.
Basically, at the very top end, we have
a bunch of different data sources that
they're constantly ingesting, right, for
that first section to to to store a
bunch of data in their platform. And the
first is Slack. So, as I'm sure you can
imagine, any major business will have
some sort of messaging platform these
days. And basically what this does is it
scans through all of the messages that
are sent in all of the accessible
workspaces and then converts them into
what are called embeddings. And
embeddings are just another way of
referencing or referring to that data.
It's just uh it's a little easier for
models and LLMs to understand. And so
you can imagine, you know, if you guys
use Discord, you do this on Discord. If
you guys use email, then you wouldn't do
Slack. You just do it over email. But
the idea is you just need a place where
people are talking. The second is, you
know, most companies at least sizable
businesses will have some form of wiki.
So whether it's wiki, whether it's
Confluence, whatever it is, there's some
place that you guys have already tried
to sort of store all of your
information. And typically that's done
naively through like checklists and SOPs
and onboarding pages and welcome to
Clairvo. You know this company is going
to walk you through blah blah blah blah.
And so just like Slack, you sort of
stick that into this embedding space.
Next up are code repos. This is
particularly important if you're a
software business or software adjacent.
Now in my case I consider my business
software or software adjacent. So you
know scanning through all the code repos
is going to be important. Net lists, PRM
docs, you know this stuff is going to be
more uh relevant again to software
businesses. And then also any sort of
custom databases you have that store
information. And so maybe you are doing
some sort of sales or rather
spearheading a sales department I should
say and you guys have databases that
have info on all of your KPIs. So you
know uh how many sales are being made,
how often you know what we can do is we
can actually take all of that data that
formatted database information and then
just store it as these embeddings. Okay.
And that sort of takes me to that idea
of like what is an embedding? Well an
embedding basically just takes the raw
data itself. So let's say hypothetically
um the data that we want to stick in
this pipeline is just a I don't know
it's it's Nick thinks
I don't know penguins are cool. So what
we have is we have this naive sort of
text data here and then what we'll do is
we'll actually pass that through a large
language model. So this might be I don't
know man like chat GPT claude it could
be an open source model it could be
anything. And then we just ask it
questions about this and we actually say
hey who's Nick? Why did Nick say that?
What time did Nick say that? Where did
Nick say that? when did Nick say that?
And so on and so forth. And so at the
end of it, we don't just have this naive
piece of text that says Nick thinks
penguins are cool. We have Nick thinks
penguins are cool, you know, time 5:32
p.m. We have, I don't know, subject Nick
Sarafh. We have description, you know,
and then here maybe it would say
something like, you know, oh, Nick is
the founder of XYZ organization. Nick
does blah blah blah and blah blah blah.
So what we're doing is we're basically
taking a core piece of data which you
can visualize sort of like I don't know
just this cube over here and then we're
adding metadata to it. We're adding a
bunch of additional kind of outcrop
pieces of information. So it's no longer
just that tiny little nugget in the
middle. Now it's like okay now we know
the time of date it was sent. We know
who it was sent to, where it was sent
from, who the person is, why any of this
is relevant, what the point is that Nick
was trying to make, and so on and so
forth. And I mean it's pretty similar to
I don't know like the way that a camera
works if you think about it. When you
take a photo with a digital camera,
right? You have the photo which is the
core data, but then you also have a
bunch of metadata around that. You have
stuff like, you know, what time it was
taken, what the color scheme of the
photo was, where, you know, a lot of
these cameras are now storing GPS info,
which is kind of terrifying. But anyway,
what I'm trying to say is we're not just
storing the data itself, okay? What
we're doing is we're adding a bunch of
stuff to that data and then storing it
in a way that the machine understands.
And the reason why that's important is
because much like your brain remembers
new things more better and stronger,
this system remembers new additions to
the knowledge base more, better, and
stronger. And so we have the source, we
have the time stamps, we actually know
how to weight the data. If the data
comes from some super low-level employee
3 years ago, we're going to weight it a
lot less strongly than if the data comes
from Nick, the founder of the business,
and it happened 30 seconds ago. You
know, if somebody asks for information
related to some recent seminar or
something like that, it will go through
not just all seminars equally like most
naive language models would do. It'll go
through the most recent seminar uh sort
of preferentially. It'll be like, okay,
recency. What does that mean? Okay,
what's the date? Okay, cool. And then in
this way, you're going to have a bunch
of these like quote unquote memories of
this brain um that are sort of uh uh
ranked based off the date, based off the
time, and based off the relevance. Okay,
so that's that. And then the third step
is obviously actually quering that data.
And so that data is queried you know via
MCP you know web UI AI agents uh people
are sending messages into a chat box
whatever it is you know what what occurs
really is it goes in reverse the query
goes through the embeddings the
embeddings represent a particular form
of information say a slack message okay
and then you know we what we do is we
pull the embedding version of that uh in
order to get the answer key practical
example of this with a slack message and
you know I think the examples I provided
here do kind of suck so bear with me But
there'll be a message maybe sent in the
CKPT- support thread. Okay. And at 9:14,
Amaya will say, "Hey, the restore stalls
after manifest load on the larger
cluster, but small runs are fine." Maybe
Owen then says, "Yeah, I can reproduce
this with 128 hours." The log stop
before the cache warm-up, though.
[gasps] Basically, what happens is
question summary, right? Maybe here's a
resolution a few minutes later. We take
all of the information in a chat thread
sort of divided into these windows and
it'll all be done for you automatically.
And then it'll be essentially created as
a structured artifact which you guys
can't really see here. Let me zoom in.
And so this is the metadata that I was
talking about because we'll have a large
language model um take this information
and ask questions about it. You have
things like question, hey, why does the
restore stall after the manifest load?
Well, guess what? The summary of the
sort of this conversation is well large
restores stop before the cache warm-up.
you know, if I knew what the hell this
meant, it might be a little bit easier.
But maybe it's like, hey, uh, who was
that guy that came in to do that talk on
Thursday? And maybe the summary is now,
um, you know, the guy that came in to do
the talk on Thursday was Nick. You maybe
there's a resolution step if it's
something that needed to be resolved.
And so in this case, you can imagine how
now we have the ability to actually, um,
I don't know, like literally store the
answer to this question permanently
inside of the company, meaning the
company grows more efficient and more
effective over time. You then store a
bunch of things like maybe code rest
maybe that just means I don't know like
what is the slack thread where all that
information is stored and maybe systems
that includes a bunch of systems that
these people are working on. It'll
actually store a list of them. Obviously
it includes other information as well.
It includes like the source ID. It'll
include um I don't know the time and and
and date and so on and so forth. But
yeah, I mean like that is that's about
how detailed we're getting under the
hood. Uh but we again don't need to
store any of that stuff. Just wanted to
make sure you guys all understood what
was really going on under the hood. So
you don't need to know everything that I
just said. That's okay. I'm going to
walk you guys through the actual build
process. And the good news is I don't
even know, you know, what 90% of this
stuff really is. I just understand it at
a high level, which is sufficient. Um,
as long as you understand the way that
the data flows, we can actually take
that and use that to build our knowledge
base. You don't need a crazy dev to do
this. Um, you know, we can 100% do this
ourselves. Okay. So, how do you actually
build something like this in practice?
Well, the very first thing you need to
do, so you need to whip up a coding
agent of some kind. And so, in my case,
I'm using Claude Code V2.1.211.
I'm not saying you have to use Claude
Code. You can use one of many available
open models, but this Fable 5
intelligence right now just happens to
be the best, aka the most performant,
and um I have the money to to pay for
it, so might as well. Okay, so you can
get whatever model you want, Claude
Code, you know, Chat GPT, Codeex,
whatnot. As long as it's like a coding
model, it can actually make changes on
your computer, you're good. And if you
want more information on that, then just
um you know, check out my my 4-hour
Cloud Code course. So, all we really
need to do to make this work is I'm just
going to copy this whole blog post in.
Okay? Okay. And then I'm going to go all
the way back to my Ghost TTY terminal.
That's just what I happen to be using
for this. Then I'm going to paste this
in. Then I'll say, I'd like you to build
something like this for, and in my case,
I'll say Nick Sarif Media LLC. I have a
Slack, a set of emails, an email
address, a GitHub, and a YouTube
channel. I want us to build ingestion
pipelines, which is just how we get data
into the system for all of them. I'll
also say PS this is a demo. I've already
done this. Given that it's a demo in
front of the audience, I want you to
build it totally fresh. The reason for
that is because I've just already done
this several times. And what I wanted to
do is just pretend that it's totally
new. Now, the cool thing is, as
mentioned, you do not need to know how
this works. You can just go through
this, okay, entirely autonomously. Uh
the model can sort of figure it out. Um,
if for whatever reason Cabris has, I
don't know, put a block on their page,
then rather than feeding the URL, you
can also just go back to Google Chrome,
copy the whole thing manually, and
literally just paste it in. Um, the
important thing is you just need to get
it in to like this intelligent model.
Now, what it'll do next is it's going to
attempt to log in to all of the various
accounts of reference. So, in my case, I
actually have accounts that are already
logged in. For instance, I have GitHub
logged in. I have my YouTube channel
logged in. I have my email addresses
logged in. And so it'll do so almost
entirely automatically basically at
once. But I want you guys to know that
that might not necessarily be the case
for you. And so if you guys wanted for
instance an email to be set up, all you
have to do is just open up a new
instance of this. And I'm just going to
go claw-bose. I'm just going to zoom in
a little bit so you guys could see both
of these. And then I'll say something
like, "Hey, I want you to get access to
my email." Then I'm just going to give
it one of my emails, nickleclick.ai.
You should be able to query each email,
read all of my emails, send emails,
basically have full admin access. How
would you do this hypothetically if you
didn't already have access to it? Now,
I'm asking that question because it
already has access to my emails and I
don't want to sit here for 4 hours
showing you guys it say I already have
access to your email. And as you can
see, it eventually will tell you here's
how you'd set it up from scratch. You
would have to open up a Google Cloud
project, enable the Gmail API, do an
OOTH consent screen to create an OOTH
client, some sort of desktop app. Uh you
would have to do a one-time consent
form. You'd also need some sort of
refresh token. I guess what I'm trying
to say is it's not it's not very
difficult. This would realistically take
you about 5 to 10 minutes for every
pipeline of information that you want to
set up. And so that's the cool thing
about agents. I mean, you can just ask
them, hey, how do you do this? And if
the model is smart enough, in our case,
Opus 4.8 8 in this instance, but you
know, you could use Fable 5, you could
use GPT 5.6, so you can use whatever you
want. Um, you'll get the credentials
that you need in order to build set
injection pipeline. And on the left hand
side, speaking of it is currently doing
the building. Now, because I kind of
want to see what's going on, I'm
actually just going to go /btw. That's a
cloud code specific feature. And I can
say, visualize the system you're
building for me in ASI. In case you guys
didn't know, ASKI is just a text way to
demonstrate sort of visually like what a
di like a diagram or something like
that. And so what this is now doing is
while the main thread is running, I have
another thread that's going on on top of
that just asking it various questions.
And so I'm saying visualize the systems
you're building and asking. And here's
what it's doing. It's actually showing
me it so we could see. I'm just going to
zoom out. And so what we're doing is
we're basically building that exact
thing. We have a Slack over here. We
have three accounts over Gmail. We have
my GitHub. Then we also have my YouTube
channel. And so these are the
connectors. So this is sort of like the
APIs and uh MCPs and whatever it is that
allows us to pump stuff in there. We
have the distillation step like I talked
about earlier where we're basically
taking each thread passing it through a
ha coup in this case feeding in a
question a summary resolution systems
and then we're ingesting this into our
actual pipeline or database if we're
giving it just one table in what looks
like postgrql. And then down at the very
bottom here we have retrieval setup with
full text where we look for exact
matching tokens and stuff like that and
then embedding. Okay. And right after
this is all done, I'm just going to jump
to it and then I'll show you guys how it
actually works when you query it. And
now you can see sort of a artifact that
shows the same model asked the same 20
questions, one with the knowledge base
and one without. You can see that the
one that had the knowledge base answered
17 out of 20 questions correctly. Um the
other three were just honest partials or
declines. It just said, "Hey, I don't
really know what's going on cuz I don't
have the evidence to to back it up."
Whereas without the knowledge base, I
answered zero. So I mean, these are
really high quality. Um, comparatively,
you can imagine how rolling this out
inside of any companies would
significantly improve both the quality
and then the speed with which you get
things answered. So, for instance, which
AI company recently pitched a paid
sponsorship for the Stacked podcast? In
case you guys didn't know, I run a
podcast with Jack Roberts called
Stacked. And, you know, we're we've
recently grown a little bit. Um, I think
we're up to 5,600 subscribers now. So,
Jack and I kind of screwing around,
having a fun time. We're almost at 6K.
Well, uh, you know, if you just asked
that question natively, obviously you
wouldn't get the answer. But in our
case, we know it's abacus.ai. They
pitched a paid sponsorship for the
stacked podcast promoting as chat LLM
all-in-one AI super assistant. The
outreach came from insert person here.
Did we take it? Absolutely not. Or how
about this one? Which GitHub repos did
NSM push to most recently? We actually
have the exact GitHub activity right
over here. And it was just me replacing
sort of a defunct company name with a
new company name. Now, this isn't super
impressive. any sort of rag could do
this. What is impressive is the fact
that this data is now being ingested
completely automatically and I have
nothing that I need to do with it. It
occurs entirely without my control not
without my control but not requiring my
management. Um this will occur in the
background and so I can ask this any
sort of question that I want at any
point in time. Unfortunately, the thing
you can't really visualize is just how
much better this is than naive rag,
which most other people are doing
because we have information like the
date and time, aka how long it's been
since the thing was created. We also
have significantly higher quality rag.
You know, I only had 640ish documents
inside of my system. A company like
Cerebrus probably has, you know,
hundreds of thousands. Uh the thing that
differentiates like a crappy
knowledgebased uh system that you ask
questions to and have woven into your
business with the ones that are good
isn't necessarily how many documents you
have, but it's how effectively can you
retrieve the highest quality ones, the
most relevant ones. And uh that's the
question that this sort of rag system
answers. So I'm not going to tell you
have to use this one. There are
obviously a bunch of different rags that
you can use and there also a bunch of
different approaches to knowledge bases,
but this is done by a major business.
These guys aren't screwing around.
They're not going to do something like
this if it doesn't actually generate a
return on their time and energy. And as
it stands right now, this is definitely
generating a return on time and energy.
It's generating a return on time and
energy for me because I have this
syndicating and then embedding and then
pulling and and enriching all content
that I'm currently creating and that is
going into my business. It's it's a it's
an abundant training resource that
immediately gets everybody up to speed
in my team. uh you know, it's something
that I can just dole out literally with
the snap of a finger and I can give them
the same brain that I personally have
spent a long time creating. And the
unfortunate thing is doesn't look super
sexy. I don't have a big floating brain
in the middle of my screen, but you
know, it's the same resource that's
accessible via Obsidian or via, you
know, Graphify or whatever the heck. Um,
this is just probably the higher ROI way
of doing it because of the frequency
waiting, the time waiting, and stuff
like that. Anyway, as you guys could
tell, I'm uh singing hard for Cerus. a
dope company and this is not a sponsored
post in any way, shape or form. I was
not paid any money to do this. I just
think it's probably the coolest
knowledgebased implementation that I've
ever seen. So yeah, that's that. If you
guys like this sort of thing, definitely
check out Maker School. It's my
day-by-day accountability roadmap where
I show you guys how to acquire your very
first customer for an AI or automation
service, which could be building rag or
knowledgebased systems like this um for
money within 90 days, or you get all of
your money back. So I have a refund
guarantee because it works really well
and obviously I would love to see you
there. Have a lovely rest of the day.
Subscribe to the channel if you haven't
already.
