---
titulo: 'The Unreasonable Effectiveness of Separating the Task from the Model — Maxime Rivest & Isaac Miller'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-07-23'
url: 'https://www.youtube.com/watch?v=GgLQ02aO-hs'
thumbnail: 'https://i.ytimg.com/vi/GgLQ02aO-hs/maxresdefault.jpg'
descricao: "By declaring a task's inputs and outputs without initially considering model capability, you create the space needed to determine execution later. DSPy's promise is that AI engineering should happen above a particular prompt template or provider API shape: the Signature. That remains useful in a world of tools, RLMs, and Skills. Define a task strictly through its inputs and outputs, and the underlying implementation becomes flexible: experiment with models, settings, weights, templates, and output formats without touching the workflow. The talk covers DSPy 3.5 and previews DSPy 4.0, where models can write code beneath a signature and programs can learn directly from interactions with users while still respecting the signature's inputs and outputs. Speakers: Maxime Rivest — Core Contributor..."
resumo: 'Este vídeo apresenta a DSPy, uma biblioteca open source em Python que aplica aos programas de IA as propriedades das funções — reutilização, composição, testabilidade e otimização — separando a definição da tarefa (interfaces de input e output) do modelo e dos detalhes de implementação. São dados exemplos concretos, como extrair valores fiscais de faturas, corrigir gramática a partir da área de tr...'
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
  - 'https://x.com/MaximeRivest'
  - 'https://linkedin.com/in/maximerivest'
  - 'https://maximerivest.com'
  - 'https://x.com/isaacbmiller1'
  - 'https://www.linkedin.com/in/miller-isaac/'
---

## Descrição

By declaring a task's inputs and outputs without initially considering model capability, you create the space needed to determine execution later. DSPy's promise is that AI engineering should happen above a particular prompt template or provider API shape: the Signature.

That remains useful in a world of tools, RLMs, and Skills. Define a task strictly through its inputs and outputs, and the underlying implementation becomes flexible: experiment with models, settings, weights, templates, and output formats without touching the workflow. The talk covers DSPy 3.5 and previews DSPy 4.0, where models can write code beneath a signature and programs can learn directly from interactions with users while still respecting the signature's inputs and outputs.

Speakers:

Maxime Rivest — Core Contributor, DSPy
Maxime builds tools and content that make LLMs more accessible and powerful. He is a DSPy core contributor and an open-source Python library author.
X/Twitter: https://x.com/MaximeRivest
LinkedIn: https://linkedin.com/in/maximerivest
Website: https://maximerivest.com

Isaac Miller — Lead Maintainer of DSPy; Co-Founder, cmpnd
Isaac leads DSPy and co-founded cmpnd, building an open-source framework for self-improving, modular AI systems.
X/Twitter: https://x.com/isaacbmiller1
LinkedIn: https://www.linkedin.com/in/miller-isaac/

## Transcrição

[music]
Please welcome to the stage Maxim Rest
and Isaac Miller. [applause]
Wow. Isaac, myself, all of the DSPI
community are so grateful to be here
today to get to talk to you about AI
programming DSPI and the unreasonable
effectiveness of separating the task
from the model, its harness, and all of
the implementation details.
When you think about it, in programming,
if we want to repeat the tasks often, we
make it a function.
We believe the same should be true for
AR programs.
Functions are awesome. Functions are
reusable, composable, testable, and
optimizable.
To make a function, you give it a name.
You define some inputs, some outputs,
and then you have some implementation
logic inside of it.
You get to reuse your functions
thousands of times. You can optimize it,
but you can also compose it into bigger
programs.
One of the really nice things about
functions is that you can also package
it and distribute it and someone else
can use it and they just need to know
about the contract on top of it to use
it and they can treat it as a black box.
DSPI brings all of these properties to
AI programs. And so DSPI is an open
source software in Python that lets you,
like I said, bring these properties to
your AI workflows and AI programs. And
it gives you all of the toolings you
need to do that.
Why do you want that? Well, we have been
inventing a lot of terms in our fields
in the last three years. It's growing
fast. We have new models coming every
other week. We have new techniques, new
strategies. And if you're like me, you
want to try all of them. But will any of
these new specific techniques coming out
at a different time really help on your
task, on your job? Well, these are all
just implementation tactics
and you want to put them inside of clear
contract. If for your repeated AI task
you define an input interface and an
output interface, you get to play in the
internals. You get a lot of agility.
Let's make it concrete for AI.
So my first AI program I made when I
discovered DSI was that I had some
invoices from my farm and I wanted to
extract them to do my taxes. I wanted to
extract the tax values from there.
Then another AI program I did is that on
my keyboard in my computer, I have a
little command that reads my keyboard
shortcuts, read my clipboard, and will
correct the grammar for me. Sometimes I
actually wanted to also rewrite for
clarity. So I have another program that
takes text, just rewrites it for
clarity, put it back on my keyboard
keyboard, and that's a command, and then
I can like have a lot of agility and
bring it different places inside of
that. I can change it however I want. A
new model comes up and I can change
that. It's super easy because my
interface is fixed like that. I'll skip
that one.
But they're not uh restrained to very
easy things and small input outputs. You
can be very ambitious with AI programs.
So in this examples, you could have your
entire inbox and a new email coming in
and you want to compose a new drafted
reply.
We can do that in the Aspire with RLM
recursive language models. This is an
idea that came from around our community
or more like things we probably all do
agentic engineering or vibe coding. You
can give it a spec a repository and you
get a PR. Those are repeatable tasks.
And so as I have been telling you when
you fix that boundary you can focus on
the how on the top and then inside of it
you can have a little chat with just a
simple prompt. You can integrate on that
prompt. Agents come out you change it to
be an agent. Tools gets invented you add
tools and then we get into loop
engineering. You put that inside of it
too. Anything on the outside of it
doesn't change your integration and and
anything else doesn't change. And when
you have such a hard boundary, you can
also start to automatically optimize.
But how can you automatically optimize
with just that simple signature? This is
not enough. This is not enough to
specify your task. And even before Chat
GPD came out, the creator of DSPI had
started to land on this idea that you
need three things to specify your task.
And if you have this language and this
ability to express your task in a
programming language, you can start to
automatically optimize and delegate away
the implementation details. So the first
one is what should happen. This is
instructions. The signatures that I've
been showing you are part of that. Here
on the screen, you see the beginning of
a real script in DSP. You set your model
at the top. you configure that and it's
fully independent of the signatures here
where you have natural language
instruction to extract all taxes and um
and if it's illeible to output zero then
you say it I'm going to give you an
input it's going to be a string I want
you to give me an output and it's going
to be a string and a float this is
natural language expressing my needs
this is very powerful and efficient if
you think about it if you have a friend
over coming to play a board game with
you and You give them the instructions
and they're ready to play. But if you
want to do like alpha go or alpha zero
and you tell them you're just going to
learn from example, you're going to have
a long night.
And then the second one is what must
happen. There are some constraints you
have that they have to be listened to.
They have to be enforced. The best way
to do that is with code. So I want you
to go to the third line, a fourth line.
You have self extract and selfrecheck.
You can see we're doing a predict on the
extract taxes and we're doing a chain of
thought on the extract taxes.
The first one is a vanilla program. The
second one makes it do some reasoning.
Now I'm taking them inside in the
forward and you can see in the if not
bread tax. This is a requirement I have
that if my first simple vanilla program
doesn't extract my taxes, I want you to
rerun with more reasoning. I mean, I got
to get my taxes right. And then another
requirement I have is if the value is
below zero throw, I want to show that to
a human. I don't want to let you go.
This will not change. Like even if I
have AGI, I would hope it doesn't make
mistake. But whatever is in the
predictor, if they make these mistakes,
I still want these things to be true.
So the last one is what good looked
like.
And when I was young, I was on the farm
with my dad and I asked him, "How do you
know that this tree is a maple?" And he
couldn't tell me. He couldn't give me
the instruction on how to know this tree
is a maple. And he certainly couldn't
give me code on how to know this tree is
a maple. And so through time with
example I learned how to know that a
tree is a maple. But this is not limited
to things like classifying plants. It's
also for all of the long tails in your
specifications that are things that are
more latent. These are sometimes a
reason why you would do internship and
you would have a mentor and a mentee.
You're looking at a lot of of examples
and there are long tales of successful
behaviors that you have to see and
learn. Now that you have all of these,
you have express fully. You have all
these three languages you can put
together. You have the specs, the code,
and the evals.
And now your goal is fully specified.
And so you can start optimizing. You can
use things like Japa on your metrics and
on your program. And you can start
optimizing. At the beginning of the
Aspire, the chip didn't exist. The
models were not good enough to optimize.
And so we were using code to find few
shots examples to make the base models
uh act in the proper way. Then models
got better and so we could automatically
optimize instruction. And in the future
we are starting to be able to be
liberated more and more from the
implementation details and delegate that
away. And at the end our hope in the
Aspire is that you can stick to all of
that and then just the news and the
implementation details will be automated
for you. Isaac will talk to you a lot
more about what has been released in the
last year, what we're releasing now, and
all of the future plans we have. Thank
you.
[applause]
Thanks, Max.
So, we've given you a pretty big
abstract overview of specs, code, and
evals, but these aren't things that are
just restricted to the academic sphere.
These are used in production by some of
the biggest enterprises for massive
gains. And we see two main benefits when
you use DSP in the enterprise. First is
that your implementation becomes
cheaper. When you're flexible to what
the implementation is, you can use the
bitter lesson to search over different
solutions, find something that solves
your problem cheaply. And you can use
this to scale to data sizes that weren't
possible with a more expensive
implementation.
Shopify 550 times cheaper. They're able
to do that because they went from an
expensive model to a cheap model, but
they could keep the same emails, keep
iterating on their business logic
inside, and try new things.
There's three awesome case studies here,
and you should check them out after the
talk. They give you a lot of details on
how you can do this in your own
enterprise.
Now, part of the reason why you want to
build in the DSPI ecosystem is that
we're constantly adding new techniques
for you to try. And it's important to
know none of these techniques we add
will definitely solve your problem
because that's your job. What we can do
is we can solve sub problems for you
that make your implementation easier.
For instance, Alex Zang, a PhD student
at MIT, came out with this paper called
recursive language models. Recursive
language models are a way to solve some
kinds of long context programs. And
guess what? We can bring this in to DSPI
for you to try see if it helps your long
context tasks. Maybe it will, maybe it
won't. But the thing is, it's one line
and your signature stays the same.
That's what's important here. Everything
gets to stay constant and you get to see
if this solves your problem or not.
And we've had a number of examples of
this just in the last year from people
building in and around the DSPI
community. We've had RLMs. We've had
Jeepa which is an incredible prompt
optimizer out of Berkeley. Better
together multiodule gpo. All these are
incredible research innovations that you
get to try in your implementation just
by being in the DSP ecosystem.
And we have more coming in DSP4. I'm
excited to talk to you about two of
those today. DSPI Flex and Qualitative
Learning.
DSP.flex is a new kind of module. In
DSpay, when we let you optimize things,
it started with few shot examples, then
it became prompts, and now that's
becoming code.
for any function that you want to
implement. You can actually learn a
harness over time to solve that
function. And this is completely custom.
And you don't care about the
implementation as long as it solves your
business problem. What you've created
ways to measure because you've defined
the three core parts of specs, code, and
evals.
The second thing I'm excited to talk
about is qualitative learning.
One of the hard hard problems in AI
engineering is building evals. And
there's a few reasons why this is hard.
One is that defining what good looks
like is really challenging for any real
world problem.
The second is that when you define good
often times you have to lose detail. If
an email is good or bad contains a lot
less information than if you know what
could change in that email in order to
improve.
And the third is that whenever you
create a hill and a data set, you're
really trying to create a proxy for
reality. What if instead we could use
reality to inform our ebs automatically?
What qualitative learning asks is how do
we decrease this question? How do we
decrease assistance? And it's a research
question right now. But what we believe
is that models are now good enough to
interpret whatever textual feedback is
present in the environment and convert
that into evals and a hill that the
model can climb. And so as you get more
feedback from production, its traces,
its user actions, its product analytics,
it's asking you, it's the model asking
you questions about how data should be
represented. As you do this, the model
can iteratively refine the hill over
time and continue climbing it to solve
your actual business problem.
And DSP focuses on these kinds of last
mile problems. We have a really strong
research ecosystem and we collaborate
really closely with them. And that's
part of the beauty is that we can see
the problems that happen in applied AI
engineering. Sol define them, build a
benchmark and then solve them with
techniques and then we get to
democratize the results of that to
everyone because it's open-source open
research.
Now, one common question is what happens
when we have AGI?
Well, even when we have an incredibly
smart model, the model won't know how to
solve your problems. It won't know how
to do your tasks or have your context.
And so this genre of last mile learning
is trying to ask how do we efficiently
do this learning
intelligence is very different from
being all knowing.
If you were to ask Albert Einstein to
help you with your emails, he'd probably
ask what's an email.
But if you AGI will know how to do your
emails. Nevertheless, it won't know how
to actually solve your problem and
interact with the people you need to
interact with. It won't understand your
relationships without learning this
context over time.
Since 2022, DSPI has been focused on
these three core ideas of specs, code,
and evals, all defined as a programmatic
interface.
We've certainly evolved over time, and
new techniques are incredible. We've
gone from evolving few shots to prompts
to now harnesses and now evolving your
emails over time, too.
But what you need to ask for any of
these new techniques is how do they help
you solve harder problems or solve your
own problems better?
And you should ask this question in a
datadriven manner.
You should look at this new technique
say how can I apply this to the business
problem that I have? You should define
your problem and you should hold your
prompts, models, and code accountable to
the problem that you need them to solve.
And what's awesome about when you build
in this way where you have flexible
implementations,
what you unlock is you unlock the
ecosystem of all the techniques that
anyone in this room is constantly
inventing. You unlock access to the
collective intelligence of everyone
here, all sharing techniques together.
So, if you want to build reliable AI
software, I encourage you to come check
out DSP. We're completely open-source,
open research, and we're here to help
you solve your problems by building
reliable software.
We have a Discord that you should come
join. And when you come up with the next
technique, you should come contribute it
to DSP. And we can help you distribute
it and make this awesome technique
available for everyone. Thank you.
