---
titulo: 'Claude Code Built My INSANE AI Second Brain'
tipo: item_agregado
plataforma: youtube
canal: 'Simon Says AI'
data: '2026-05-26'
url: 'https://www.youtube.com/watch?v=0mDjeG8K-cg'
thumbnail: 'https://i.ytimg.com/vi/0mDjeG8K-cg/maxresdefault.jpg'
descricao: '🟠 Simon Says AI Pro Community Claude Code mastery course. Build real systems and get paid by clients. https://www.skool.com/simon-says-ai-pro-9357/about 🟢 Simon Says AI Free Community All my FREE guides and build resources: https://www.skool.com/simon-says-ai-7422/about Karpathy LLM Knowledge Base Gist https://gist.github.com/karpathy/442a6bf555914893e9891c11519de94f Your ChatGPT and Claude history is stored somewhere, but that does not make it memory. This video shows how I turned years of AI conversations into a navigable second brain. I walk through the full setup: the main superbrain, the Obsidian and Karpathy-style wiki layer, how to ingest ChatGPT and Claude exports, a public article demo, a project-specific sub-brain demo, and the maintenance/linting loop that keeps the system use...'
resumo: 'O utilizador pediu um resumo direto do conteúdo do vídeo — uma tarefa de resposta única, sem trabalho criativo nem exploração de código. Vou responder diretamente. Este vídeo mostra como construir um "segundo cérebro" de memória a partir do histórico de conversas do ChatGPT e do Claude, organizado automaticamente pelo Claude Code num grafo de conhecimento com temas, entidades e ligações entre conv...'
tags: {}
fontes:
  - 'https://www.skool.com/simon-says-ai-pro-9357/about'
  - 'https://www.skool.com/simon-says-ai-7422/about'
  - 'https://gist.github.com/karpathy/442a6bf555914893e9891c11519de94f'
---

## Descrição

🟠 Simon Says AI Pro Community
Claude Code mastery course. Build real systems and get paid by clients.
https://www.skool.com/simon-says-ai-pro-9357/about

🟢 Simon Says AI Free Community
All my FREE guides and build resources:
https://www.skool.com/simon-says-ai-7422/about

Karpathy LLM Knowledge Base Gist
https://gist.github.com/karpathy/442a6bf555914893e9891c11519de94f

Your ChatGPT and Claude history is stored somewhere, but that does not make it memory. This video shows how I turned years of AI conversations into a navigable second brain.

I walk through the full setup: the main superbrain, the Obsidian and Karpathy-style wiki layer, how to ingest ChatGPT and Claude exports, a public article demo, a project-specific sub-brain demo, and the maintenance/linting loop that keeps the system useful.

The key idea is simple: do not copy your whole brain into every project. Build one durable memory system, then fork the right context into the projects that need it.

⏰ TIMESTAMPS:

0:00 Intro
2:05 Chat History Sucks
3:03 Obsidian + Karpathy Wiki
5:34 Building the Super Brain
8:48 Demo 1: Article Vault
10:04 Demo 2: Project Brain
11:21 Maintenance + Linting
12:12 Outro

## Transcrição

What you're staring at right now is
multiple years of my chat GBT and Claude
history built into a full memory system
that has basically become my second
brain.
In this video, I want to show you how I
set up this powerful system. How I built
the main super brain. How I created
separate product brains from it. And the
most important parts of setting up a
knowledge graph like this that most
people skip. But first, let's just look
at how big this thing is.
There are thousands of conversations and
entities in here.
You can see how it groups things into
themes across this ocean of
conversations. All these themes over on
the right are color-coded. So, this is
one slice of the brain.
And if you click into the Claude code
topic,
you can see a summary at the top,
other connected topics, and supporting
nodes and conversations that are related
to the Claude code topic. So, these
linked conversations and topics are the
nodes that were linked in the graph you
were looking at earlier.
Now, the most interesting part is apart
from the links in the graph view, I can
go into the document, follow any of
these links
into a different source, topic, entity,
or chat that I had to explore it all.
Now, very quickly, here's a second
digital brain that I have for managing
one specific project, my strategy across
Instagram and YouTube. And it's
basically a sub brain of my main super
brain.
So, I can modify this sub brain, merge
the useful pieces back into the main
brain, or keep it carved out as just a
separate project brain.
This is the ultimate context management
strategy. One main brain for everything
and smaller brains for the projects that
need focused memory.
Now, these links are really hard for
human to explore manually. The magical
part is that we can get an AI agent like
Claude code or Codex to explore these
links for us.
When you enter prompt, it can identify
one topic and then just take these links
to pull in all the relevant context
related to that topic without dumping
the entire brain into the context. The
The craziest part is that I didn't do
any of this manually. The system was
automatically organized by Claude Code.
Now, enough with the visuals and let's
get into it. First, I want to take a
step back and talk about why this is so
valuable and useful. The chatbot large
language models tools like ChatGPT,
Claude, and Gemini store your chats. And
even with AI agents like Claude Code or
Codex
have sessions, but they don't know what
should follow you into future work. So,
you end up with a bunch of stored
conversations. This is absolutely
terrible, and you lose basically all the
decisions ships
you had in the past, especially if you
need to start a new project with a lot
of different useful conversations you've
had about it in the past. And even if
you are able to find a specific
conversation that you want, as soon as
you want context from two conversations,
five, or 10 conversations, you're stuck
trying to copy and paste it all together
into a Frankenstein chat.
You know, something that I used to do
manually
before building this digital brain
system, and that was a really big time
sink.
Then comes in Obsidian, which is the
knowledge graph visualization tool that
we were looking at earlier. Obsidian is
the foundation of our digital brain, and
put simply, it's just a folder of
markdown files, as you can see here,
that can also link to each other. And as
we talked about earlier, links between
these files are pretty simple. It's what
really makes it magical when you get AI
agents to start using Obsidian. So, if
we're talking about something that's
able to identify as related to code and
AI, they're able to find these source
notes, navigate to any number of them
that are relevant to what we're talking
about,
and you drill in deeper to more topics
until it gets to a point where the
entities or notes become irrelevant.
Now, Obsidian is really powerful on its
own, but what really brings the digital
brain to life is this LLM knowledge base
framework introduced by Andre Karpathy.
Now, if you didn't know, Andre Karpathy
uh was previously a director at Tesla,
founded human OpenAI, and PhD at
Stanford. Kind of knows what he's
talking about. And his main point is
that instead of using Obsidian to store
all raw documents, puts all these source
documents, code or whatever it is, into
this raw directory. Then he organizes
this wiki as a collection of markdown
files using AI agents that include
summaries of all the data in the raw
directory
and links into it so that it's all
organized. You can see how this post
really blew up with 21 million views
since early April. Apart from the
high-level Twitter post, he has this
GitHub
link where it dives into the idea
further, breaking things down.
So, apart from the raw sources in the
wiki, which we talked about earlier,
there's also a schema document, which is
effectively Claude MD for Claude Code or
Agents MD for Codex. Want to organize
this in telling the large language model
how the wiki is structured so that when
it goes into ingest more documents,
query documents, and such, it knows how
to work with this digital brain system.
You see how Karpathy intentionally made
this document abstract without details
of how to build this digital brain
system. Everything mentioned here that
would be useful for you and the projects
that you're working on, while ignoring
what isn't actually relevant. And the
easiest way that you can go about it is
simply copy and paste this entire note
into Claude Code,
Codex, whichever AI agent you're using,
tell it what you're trying to do, and
have it devise a strategy for organizing
and starting to build your digital
brain.
So, for my digital brain, I used ChatGPT
and Claude conversation exports. And I
did that because I was like a top 0.1%
chat user in 2025
with thousands of conversations and I
literally talk about work and life,
basically everything with AI. But, you
can actually do this with anything else
like Gmail, yes,
images and videos which you can describe
using text
first, the have notes, and also meeting
transcripts. So, if you wanted to build
your AI digital brain with AI chats like
I did with Claude, what you want to do
is go into settings, privacy, and then
there's this export data at the bottom
here. That's then you just got to hit
export and after minutes to hours,
Claude will organize everything and send
you an email where you can go to
download all your data. And here's what
that email looks like once it's ready.
With ChatGPT, it's pretty similar. We go
to settings, controls over on the left,
there's an export data button here that
basically does the same thing. And then
after you have all the data, what you
want to do is copy and paste
this whole
LLM wiki framework that we talked about
earlier, paste it into Claude code, make
sure you're in planning mode, and tell
it what you want it to do. Point it at
the conversation export that you have
and tell it to create a plan for how
it's going to organize everything. So,
one thing that I actually did with my
conversations is I wanted to reduce
noise because there were conversations
that I had which were completely not
relevant in terms of what I wanted to
store in my brain. These would be like
silly topics like why are apples more
expensive than bananas? So, on digesting
all the conversations I had, I triage
what are the most meaningful
conversations and what are the
conversations that would just add noise
and I should exclude. So, here you can
see in my system, there's a raw archive,
a normalization process that I did,
uh the triage step, and then putting
everything into a wiki system,
notes that are ready for me to review,
and then after there's a layer for
directly retrieving and filtering the
most relevant notes. Another interesting
customization steps that I'll mention is
I had thousands of conversations. So, it
was really tough to get Claude Code or
Codex to process all of them because
that would take a lot of time and use up
tokens across multiple days or even
weeks.
So, what I did, and you should do this
too if you're processing a lot of data
for your digital brain, and just in
general, is get Claude Code or Codex to
write a script that uses a lot cheaper
of a LM model
like Iku or one of the GPT Nano models
uh to process everything for you.
And then what I did is I chunked up all
the conversations into 10
batches so that the script using the
cheaper model could process all 10 in
parallel very quickly without waiting
for one batch to finish before the
other. Now, my actual second brain comes
from thousands of ChatGPT and Claude
conversation. That's too large, too
private, and too slow to build in a
video.
So, for some quick demos, I'm going to
first show the exact same ingestion
pattern we just talked through with a
public article example.
Then I'll show you another example of
what this looks like when I take my main
digital brain and how I carve out part
of that into a project-specific brain.
So, for the article demo, we're going to
use this one from Anthropic
Effective Context Engineering for AI
Agents. You can see there's a lot of
good
meaty advice down here about how to
implement some of these tactics. So,
first step is we're going to head into
Obsidian,
put in a vault name like article demo,
pick a location, and then hit create.
And it's going to give you this largely
empty vault. Then we're going to copy
and paste
all of this as we discussed earlier, and
copy it into Codex, and then point it to
that demo vault that we just created.
And then tell it to process the article.
You can visualize how all these files
are being created
as the system processes them.
And if we look at the file structure
here, we can see how there is the wikis
with entities,
sources, different topics, cloud code,
subagents and specializations, as well
as the raw document here.
And for another demo in terms of looking
through with my main brain and creating
a separate sub-brain for a new project,
we're going to do one for YouTube
strategy, content systems. This is
something I've been looking to do for a
while cuz I've been debating if I should
start a YouTube channel. And I've now
started one. This is like the second
video I'm making. So, it's a good moment
to do this anyway. Which by the way, if
you're this far into the video, really
appreciate you for sticking around until
this point. I'm just starting my YouTube
journey, so really appreciate your trust
and support. Now, if we run that, you
can see it worked for 5 minutes,
but we created what we needed here with
YouTube strategy,
content systems, so on and so forth.
Since it kept the original relative
paths, so
links continue to work.
And the final vault size is 94 files.
And here's what that new knowledge graph
looks like, which is a YouTube sub-brain
of our main super-brain.
Now, we can do anything that's YouTube
specific. So, for example, all these
videos that I'm currently recording, I'm
going to probably have them stored in
this YouTube project so that it's easily
able to access all of the YouTube
strategy content that I previously
brainstormed in the main super-brain.
The other magical aspect to all of this
is that once you have it set up, you
don't have to manually maintain any of
it. As long as you mention in your
cloud.md or your agent.md for codex that
it should search through the vault and
update it with important context. It
should be able to manage updates,
everything for you.
The other operation that Andrej Karpathy
suggests is apart from ingesting data
and querying it, we could also consider
linting. So, this is to periodically ask
the LLM agent, whether it's Claude Code,
Codex, or something else, to health
check the wiki looking for
contradictions between pages,
cleaning up ones that are no longer
relevant, so on and so forth. And if you
wanted to make it easy, you can even
create an automation to have Claude Code
or Codex run this every week, so you
don't no longer even have to think about
it and it just does this in the
background.
And that's it. Now you know how to build
your own managed digital brain
portfolio. Now, if you don't want to
figure out all the details yourself and
want my exact setup, scripts, prompts,
and workflow, I have that as a dedicated
course inside my community, which I'll
drop a link to for the link below. Now,
if you enjoyed this video, make sure to
hit the like button and subscribe if you
want more videos on building actual AI
systems that you can use in your work,
business, and life. Thanks and I'll see
you in the next one.
