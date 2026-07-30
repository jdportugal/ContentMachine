---
titulo: '10x Better Claude Answers By Making It Argue'
tipo: item_agregado
plataforma: youtube
canal: 'Simon Says AI'
data: '2026-06-01'
url: 'https://www.youtube.com/watch?v=m5LjHMGV2Cs'
thumbnail: 'https://i.ytimg.com/vi/m5LjHMGV2Cs/maxresdefault.jpg'
descricao: '🟠 Simon Says AI Pro Community Claude Code mastery course. Build real systems and get paid by clients. https://www.skool.com/simon-says-ai-pro/about 🟢 Simon Says AI Free Community All my FREE guides and build resources: https://www.skool.com/simon-says-ai/about Claude, ChatGPT, Gemini, and Codex are incredible tools, but they can be weak for judgment calls. Ask for one answer and you often get something that sounds smart, agrees with your framing, and misses the opposing view. In this video, I show how to run an LLM Council in Codex: multiple AI subagents with different perspectives, a review layer, a final council report, and follow-up questions grounded in the full 360-degree analysis. I also show where the idea came from, how to create the skill, how to run it, and how to remix it into...'
resumo: 'Este vídeo explica porque é que modelos de IA como o Claude, o ChatGPT e o Gemini tendem a concordar com o utilizador (devido ao treino por RLHF), dando conselhos pouco fiáveis. Mostra como contornar esse problema enviando a mesma pergunta a um "conselho" de vários agentes de IA com personas independentes, para obter perspetivas menos enviesadas, e como personalizar esse conselho como o próprio qu...'
tags: {}
fontes:
  - 'https://www.skool.com/simon-says-ai-pro/about'
  - 'https://www.skool.com/simon-says-ai/about'
  - 'https://docs.google.com/document/d/e/2PACX-1vSvw_Mk4iq4DkeMM3YVcvHgkzY-bsmnkXBC2TaEVBUDMjU4RtwDrKdxenpc-x7Vnzw5THGA4wVJd-LX/pub'
---

## Descrição

🟠 Simon Says AI Pro Community
Claude Code mastery course. Build real systems and get paid by clients.
https://www.skool.com/simon-says-ai-pro/about

🟢 Simon Says AI Free Community
All my FREE guides and build resources:
https://www.skool.com/simon-says-ai/about

Claude, ChatGPT, Gemini, and Codex are incredible tools, but they can be weak for judgment calls. Ask for one answer and you often get something that sounds smart, agrees with your framing, and misses the opposing view.

In this video, I show how to run an LLM Council in Codex: multiple AI subagents with different perspectives, a review layer, a final council report, and follow-up questions grounded in the full 360-degree analysis. I also show where the idea came from, how to create the skill, how to run it, and how to remix it into your own custom board.

LLM council skill: https://docs.google.com/document/d/e/2PACX-1vSvw_Mk4iq4DkeMM3YVcvHgkzY-bsmnkXBC2TaEVBUDMjU4RtwDrKdxenpc-x7Vnzw5THGA4wVJd-LX/pub

Use this when you want stronger decision support for business, research, growth, product, or life decisions instead of a single agreeable AI answer.

⏰ TIMESTAMPS:

0:00 Hook
0:16 Why AI Advice Breaks
1:00 Where LLM Council Comes From
3:30 Run the Council in Codex
5:10 Council Report + Follow-Ups
7:51 Customize Your Own Board
8:59 Outro

## Transcrição

Claude, ChatGPT, and Gemini are all
great tools, but they all absolutely
suck for giving you business, work, and
life advice. They all give you one
answer, and that answer usually tends to
agree with you and make you feel smart
rather than telling you what you need to
hear. If you pitch it one idea and ask
why it's going to work, it's going to
give you a really compelling case about
why this is going to be a good move.
But, if you take the same idea and ask
it why it's going to fail, it's going to
build an equally convincing case about
why this is not the move to make. Same
facts, both sound convincing, and that's
why this is such a dangerous trap. So,
now for the hardest and most important
questions, I no longer ever ask one
model, but instead send my question to a
council of AI agents instead. So, now
after one prompt goes in, we have
multiple isolated AI agents forming a
council. Each one attacks the question
from a different angle. They each have
an independent persona, which gives us a
minimal biased and 360 view of the
situation. So, in this video, I'm going
to show you exactly how this works, how
these models tend to agree with you in
the first place, how to run the council,
and most importantly, how you can
customize the whole thing into your own
board of advisors that is most powerful
and personalized for whatever it is that
you're trying to do. But, before going
to details, I thought it's first useful
to understand the problem of why AI
advice typically tends to agree with
you, and so it doesn't work. So, here's
me asking Claude why models like Claude,
ChatGPT, and Gemini tend to agree with
the user too easily, and if Claude tends
to agree with me too easily. And it said
if it's giving me an honest answer about
all of it, the short answers are yes and
yes. As you can see here, it all has to
do with the model training process. So,
fundamentally, all these large language
models are trained to be helpful,
coherent, and aligned to human
preference. Specifically, there's this
post-training step called RLHF, which
stands for reinforcement learning from
human feedback. And what that means in
simple terms is after training a model,
there's a step where people actually
compare answers and actually rate what
better answers looks like in sharing
their feedback. And then the models are
then tuned with that feedback to be more
likely to produce these higher-rated
responses from the human feedback. As
humans, we tend to rate agreeable
validating answers higher than
challenging ones, even when sometimes
the challenging answer might be more
accurate or useful. So then the model
learns that agreement feels better to
most people, more so than disagreement,
so tends to respond that way. So all of
this is really great for tasks where you
get clear answers, good instruction
following, and really useful default
behavior. It's just that there's this
bad side effect where the model will
tend to optimize for supporting you
instead of doing a hard pushback when it
needs to, which makes it dangerous when
you're leaning on it for judgment calls.
And that's where the magic of the LLM
Council solution comes in.
So all this started with Andrej
Karpathy, who was part of the founding
team at OpenAI and a PhD at Stanford. So
he vibe-coded this large language model
Council web app. He shared in this X
post, and you can see how it really blew
up with 5.3 million views. The app is
open source here on GitHub. You can see
here in the GitHub, it's got in 19K
stars, and there's some installation
instructions further below.
You can configure API and key, all of
that. So it's workable, but pretty
annoying to set up. Then Ollie the Dev
here remixed it into a Claude skill
that's much easier to install and use
with Claude code or any other AI agents
like Codex, Antigravity, or others. It's
a pretty long post that he made, and he
has a link to the skill here. If we
click in, it looks like this.
Easiest way to install it is to copy and
paste this whole thing, paste it into
your favorite AI agent tool. So here I'm
using Codex.
You can just say, "Use the above to
create a skill
called LLM Council." So, I want to show
you a demo of it working end-to-end, but
before we do that, I wanted to first
explain and help you understand how
fully it works cuz it's pretty cool. So,
the entry point is the top here and it's
a question that we initially enter and
post to the AI agent system. And after
that, the question gets distributed to
five sub-agents that have different
personas. This part's really important
because with sub-agents, what that means
is that each one of these evaluators or
revisers gets its own thread. So, it
assesses everything in isolation without
bias from the rest. And a quick
secondary benefit is that it all runs in
parallel, which means that it speeds
things up without one waiting for the
other to finish. So, from left to right,
we have the contrarian first, which
hunts for fatal flaws and missing
evidence. Then we have the first
principles thinker, who takes a step
back to question assumptions and think
about is this even the right question to
begin with.
Then we have the expansionist, who looks
for the hidden upside. The outsider
tries to catch jargon, confusion, and
blind spots. Then there's the executor,
which tries to find the shortest path
from insight to action. And then comes
the really powerful part. So, all the
analyses from the sub-agents are pulled
together, anonymized, and randomly
shuffled before passing it off to peer
reviewers here. This is a really
effective way to stress test the
original analyses because each of these
peer reviewers are also different
persona sub-agents, and they are
analyzing the original results in a way
that is anonymized. Finally, at the end,
the chairman of the council takes the
original analyses, the results from the
peer review, comes up with one final
verdict. And as a nice bonus, it even
gives you a full executive report with
everything at the end.
Now, this definitely does take more
tokens, but you can really see how
interesting and robust this whole
process is. All right, now enough theory
and let's see the whole thing in action.
So, I'm going to do this in CodeX to
show that even though it's cloud scale
that we can run it here, but you can as
easily do it in cloud code or any other
AI agent. So, start by typing dollar
sign LM Council to ensure that the
council skill is being used
and then we'll paste in our question.
So, for a demo question here, I wanted
to make it something concrete and a
question that I've actually went to the
LM Council for.
So, pretty new to the content creation
world then creating some short-form
content on Instagram, TikTok.
Got some traction after 2 months and is
it a good time to start a YouTube
channel? Added a bit more context and
ultimately asked if you think this is a
good choice. All right, so after
executing it you can see how we spawned
five AI agents here, each with their own
different personas that we discussed
earlier. And after that finished, you
can see how we went through the peer
review step.
You can see how they came back with two
outputs. So, there's a full transcript
of all the analysis
and there's also this nicely formatted
council report.
Goes through all the meeting notes and
discussions by the council, almost like
an executive report. So, it starts with
the chairman's verdict at the top. Yes,
start YouTube now. But seriously,
I define it as a 12-video experiment,
which is what I'm doing here. This is my
third video, I think. Further below
that, you can see the map of what the
council said,
where it agrees, where the council
clashes.
Further below, we can dive into even
more details like what each of the
advisors originally said.
Another really powerful aspect of this
is you can even ask a follow-up question
and it's going to be pretty high
quality. And that's because it's going
to answer with the same context of the
entire discussion. Means follow-up
questions will be well informed with the
360-degree view that that council
provided. All right, so now we've seen
how the whole thing works end to end.
So, using the LM Council as is is
already really interesting and valuable,
but we can take it a step further to
customize it to our whatever board that
we want for our specific use case. So,
as an example, I took the LM Council and
made a different version specifically
called the Board of Titans for business
advice. It replaces the five agent
personas with five entrepreneurs each
with different flavors of building
business. Steve Jobs agent, the Elon
Musk agent, the Jeff Bezos agent. And
all you got to do is come up with the
characters and perspectives that you
want on your own council. For example,
if you wanted to debate psychology or
philosophy, you can have different sub
agents representing different schools of
thoughts.
You can make a growth board, a research
board. You can even mix in other models
like Claude, GPT, Gemini, Grok.
If you make a lot of decisions where
being wrong costs real money or time,
this is one of the clearest places where
I found AI agents really shine. So, try
out the skill. I'll leave a link to it
in the description further below. And if
you want a guide on using the skill
beyond this video, have that in my free
community, which I'll link below as
well.
Lastly, if you're interested in trying
the Board of Titans that I created, I
share that and help folks master AI
agents in my pro community, which I'll
link below as well. If you enjoyed this
video, make sure to give it a like
below. Let me know in the comments what
you think. Subscribe for more on
learning and building with AI.
