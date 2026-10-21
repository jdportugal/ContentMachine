---
titulo: 'Where RL Will Take Search — Maximilian-David Rumpf, SID.ai'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-16'
url: 'https://www.youtube.com/watch?v=iJVxxxHM_Oc'
thumbnail: media/monitoring/youtube/ijvxxxhm-oc.jpg
descricao: "Somewhere between 30 and 50 percent of an agent's tokens get spent on searching, almost all of it up front, before any of the work you asked for. Maximilian David Rumpf treats that number as the whole opportunity. Handing a task to an agent rather than a query to a search engine roughly doubles the odds of finding the right documents, but it costs a hundred to a thousand times more and takes minutes instead of milliseconds. His diagnosis of why the classical alternative cannot close that gap is the sharpest part. A traditional pipeline rewrites the query, hits a backend, reranks, and returns, which means every decision was frozen at design time and every question receives the same fixed budget of compute. The reranker can often tell that the results it is holding do not answer the question..."
resumo: "A talk on applying reinforcement learning to search: agentic search delivers much better retrieval quality but is 100–1000x more expensive and far slower than classical pipelines. The speaker argues for training a dedicated RL search sub-agent to replace hand-designed, locally-optimized retrieval pipelines that can't adapt at query time."
tags: {}
fontes:
  - 'https://x.com/maxrumpf'
  - 'https://maxrumpf.com'
---

## Descrição

Somewhere between 30 and 50 percent of an agent's tokens get spent on searching, almost all of it up front, before any of the work you asked for. Maximilian David Rumpf treats that number as the whole opportunity. Handing a task to an agent rather than a query to a search engine roughly doubles the odds of finding the right documents, but it costs a hundred to a thousand times more and takes minutes instead of milliseconds. His diagnosis of why the classical alternative cannot close that gap is the sharpest part. A traditional pipeline rewrites the query, hits a backend, reranks, and returns, which means every decision was frozen at design time and every question receives the same fixed budget of compute. The reranker can often tell that the results it is holding do not answer the question. It has no way to act on that. It returns them anyway.

What accumulates instead is a long tail of failure, patched with edge cases that can never be exhaustive. Rumpf argues search is now following a path we have watched twice already, in computer vision going from hand written edge detection to narrow detectors to general models, and in chess going from a machine full of human authored rules to a system that learned its own. Search suits reinforcement learning unusually well because the reward is verifiable, you either found the correct document or you did not, and the environment is grindable at thousands of attempts per second. His results show a specialized model landing about twenty times faster than a frontier model on the same task, five seconds against two minutes, at roughly one hundredth the cost.

Speaker info:
https://x.com/maxrumpf
  / maximiliandavid  
https://maxrumpf.com

Timestamps:
0:00 - Agentic search is better, and far more expensive
2:11 - Where the classical pipeline breaks
3:27 - Machine design beats human design, again
5:08 - Why search is an ideal target for RL
6:46 - Twenty times faster, a hundred times cheaper
7:36 - Keeping bad results out of the main context

## Transcrição

[music]
Okay, today I'm going to talk about
where reinforcement learning will take
search and some some background on me.
Uh I'm the founder and CEO of Citi.
We're a stealthish AI lab for search.
We're backed by some pretty amazing
people. Uh, and we're hiring.
Okay. Agents are a new paradigm for
search. You can now get vastly higher
quality results.
Um, twice as likely to find the right
documents.
But it's incredibly expensive.
um about a hundred to a thousand times
more expensive than what you'd get out
of a classical search query and it is
extremely slow. You're looking at
minutes and not milliseconds like
turboroper.
And what this means is that agents spend
30 to 50% of their tokens on searching.
And this is usually at the beginning of
some task. It finds the right context to
then do whatever you ask it to do. And
the idea here is quite simple. First,
instead of having the main agent do the
searching, you pass the searching to a
sub agent and you train a model to be a
great sub agent.
And the question here that we'll answer
today is how much cheaper and faster can
we make this with reinforcement
learning?
And this is really our target. So this
is a benchmark across legal, finance,
knowledge basis, science, email, a bunch
of different tasks, some academic
benchmarks, some internal benchmarks.
And this is where
you currently are. You see reranker and
vector only performance at the bottom
and you can see frontier models
essentially kind of like go through here
at the cost of spending many many you
know minutes per question. And can we
get a model to kind of like be extremely
accurate um have extremely high recall
but also be incredibly fast and cheap.
And let's quickly look at classical
search.
This is the pipeline that many of you
guys will be familiar with. A question
comes in, you might have an LLM that
rewrites the question. You then execute
that on a search backend. You might have
a reranker. Um and you get your results
at the end of the day. Um, it is
essentially a pipeline of chained
locally optimized models and all of the
decisions are baked in at design time
and you expend a fixed amount of compute
per question. And this one is really
important. The re-ranker might know that
the results are insufficient at
answering the question, but the
re-ranker can't take action. It can only
essentially return the results even when
they're bad. And what this means is that
in practice a pipeline like this acrrues
a long tail of failure where unexpected
questions come that you know the
designer didn't have something for. And
in practice this usually means people
add lots of edge cases um to essentially
fix these. But of course you can't
design infinite edge cases and you can't
add infinite tweaks.
And so the strategy here is one that
we've seen before. machine design
outperforms human design. Um, and we saw
this in computer vision where you had
your, you know, primitive edge detection
algorithms. You then had the box around
a dog generation of models um that were
very good at this like very narrow task
and locally optimized for it. And then
you had VLMs that were extremely good at
all parts of the search pipeline. You
saw this again with chess with IBM Deep
Blue being largely a collection of human
written rules. um stockfish bridging the
two and then alpha zero and mu0ero
essentially completely just putting it
all inside of the model and we're going
to see something similar happen to
search where we have our existing
algorithms like BM25 and page rank um
then we had an evolution from that with
small models that did some task very
well like vectors and re-rankers and now
essentially this new paradigm of pure RL
where we actually don't bake any design
decisions into the model. And what this
looks like in practice is
um something like this. Um so you have
one model uh it goes back and forth with
the database. It can search, it can read
results, it can iterate. Uh it can
search again until it is happy. It can
set metadata filters on the fly. It can
constrain its search. It can try as much
as it wants. Uh and in the end, it
produces a ranked list of results.
Uhhuh. And what you get is a model that
makes all of the decisions and can adapt
to any question on the fly and for
example use much more compute if a user
asks a very difficult question.
And what helps us here is that search is
verifiable. Um and reinforcement
learning needs rewards that are
verifiable and grindable. Verifiable
here means for a given question, did you
find the correct document? And we can
design this and tell this quite easily.
Um, and is there an environment where
the model can attempt this question
loads and loads of times and in practice
for us this means uh thousands of times
per second during a training run.
Uh, and the second part is kind of like
can we turn the models that are
currently very general and very general
purpose into something that is much more
specialized and it turns out we don't
actually need most of the parts of a
language model to be extremely
performant at search. Uh and similarly
with like CPUs and GPUs and AS6, um a
CPU in theory can do anything that a GPU
can do, but you would never want to use
a CPU to do LLM inference, for example.
Um because the much more specialized
version is much more effective. And this
really makes search an ideal target for
RL. Um and this is what happens when you
train a model on this task. And so
again, here we added the vector and
reranker only baselines. This is of an
earlier task. And what we see is that
search quality increases very
predictably with compute. And we can mix
in other rewards like latency um and
different kind of like retrieval
strategies to make it even more
performant. Importantly, we don't really
tell the model what to do. Much like in
Alpha Zero and chess, we want it to
discover its own strategies and its own
tricks to essentially search. Well, uh,
and we don't know whether this method
has no ceiling, but we're definitely not
yet seeing a ceiling to this approach.
And these are the results. Uh, so this
is the same chart as before. And this is
SID one and then SID one um with some
parallel execution on the left hand
side. And so what this ends up meaning
is you get you're about 20 times faster.
So instead of taking around 2 minutes,
you take around 5 seconds on average.
And it's about a hundred times cheaper
than using a frontier model for this
task. Uh there's some more detail here,
but like yeah, the cost and kind of like
speed are just completely incomparable.
Uh you can yeah um it's not quite at the
latency of a vector and reranker
pipeline, but in practice we think we
can get there quite quickly.
And how does this look like in
production? So this is a usual kind of
like agent execution trace. The agent
does some searching here. It finds some
good stuff. It finds some bad stuff. Um,
but all of the bad stuff that it finds
is essentially polluting its own context
window. And what we can instead do is
use um a sub agent here that does all of
the searching and thinking and iterating
for the main agent. And the main agent
only ever sees great results. And this
means that the main agent sees more good
stuff, which means it's more likely to
be correct. And it is also um extremely
cost- effective uh where those 30 to 50%
tokens that were earlier used by the
main agent to do searching can now be
passed off to this just you know 100x
cheaper search sub agent.
Uh
and
where will this take us? Scaling RL will
give us arbitrarily good search in any
domain
and RL models will become even faster
which will allow them to be used in
things like voice and e-commerce.
They'll become even cheaper than we are
currently. Um so the charts that you saw
there but like I think we can move even
further. Uh and better search will
unlock more knowledge work tasks. Uh the
web is actually quite small uh in
comparison to the entirety of data that
is there that is out there. Uh and the
most valuable information is not on the
internet. For example, how to run JP
Morgan is nowhere on the web but it is
deep inside of the databases at at JP
Morgan. That's it for me. Thank you.
[applause]
>> [music]
