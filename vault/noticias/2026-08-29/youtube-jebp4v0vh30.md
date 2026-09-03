---
titulo: 'Agentic Sites: Building Hyper Personalized Websites — Carlos Sanchez, Adobe'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-29'
url: 'https://www.youtube.com/watch?v=jebp4V0vh30'
thumbnail: media/monitoring/youtube/jebp4v0vh30.jpg
descricao: 'Carlos Sanchez types a request for a coffee machine he can use while camping, and the page assembles itself in under two seconds. Not a search result. A page, with camping appropriate machines, rewritten copy and a set of tips, generated for that one query. Adobe calls the goal an audience of one, which is the thing marketers have wanted for decades and could never afford. The site he demonstrates is a fully generated example, and the same tool will build one for any URL you hand it in about an hour. He did it to the AI Engineer site last week, and it produced a side by side comparison of two conferences on the fly. What keeps this from being a hallucination machine is how little it actually generates. Brand guidelines are strict, so the whole site becomes a corpus and retrieval grounds ev...'
resumo: 'The video discusses the development of Agility Sites, which are hyper-personalized websites that adapt in real-time to user intent and behavior, utilizing AI to enhance user engagement and conversion rates. It showcases how Adobe Experience Manager leverages various AI technologies to create tailored content experiences without compromising brand guidelines.'
tags: {}
fontes:
  - 'https://x.com/csanchez'
  - 'https://csanchez.org/'
---

## Descrição

Carlos Sanchez types a request for a coffee machine he can use while camping, and the page assembles itself in under two seconds. Not a search result. A page, with camping appropriate machines, rewritten copy and a set of tips, generated for that one query. Adobe calls the goal an audience of one, which is the thing marketers have wanted for decades and could never afford. The site he demonstrates is a fully generated example, and the same tool will build one for any URL you hand it in about an hour. He did it to the AI Engineer site last week, and it produced a side by side comparison of two conferences on the fly.

What keeps this from being a hallucination machine is how little it actually generates. Brand guidelines are strict, so the whole site becomes a corpus and retrieval grounds everything produced from it. Only certain blocks change, the hero, the product list, the navigation, the calls to action. Model choice is treated as a per site question rather than a global one, evaluated continuously across providers for accuracy and, unusually, for speed, because a page that takes six seconds has already lost. On their example the fastest configuration averaged 1.1 seconds against 4.6 for the runner up. His point is that this does not need a frontier model, since the work is choosing and arranging blocks.

Speaker info:
https://x.com/csanchez
  / carlossg  
https://csanchez.org/

Timestamps:
0:00 - What an agentic site is trying to do
2:20 - Personalizing blocks, not whole pages
3:26 - Grounding generation in the site itself
4:37 - The architecture behind the blocks
5:46 - Evaluating models per site, for speed as well as accuracy
6:58 - 1.1 seconds against 4.6
8:07 - Why this does not need a frontier model
9:16 - Pre generating a page before it is asked for
10:23 - Letting marketers define the personas
12:44 - Audience of one
13:53 - Live demo: signals, buckets and a For You page
15:09 - Asking for a camping coffee machine
16:20 - Swapping models on the fly
17:29 - Turning any URL into an agentic site
18:42 - Where this goes next

## Transcrição

[music]
>> Hello.
Thank you for coming. Um I'm going to
talk to you about Agility Sites, how we
call it as building hyper-personalized
websites. I'm not going to just talk
about it. I'm going to show you what
we're building.
Um I've been working on on this project
for for a bit now, and
we'll try to show you what is possible
today with with AI.
Uh I work at Adobe at a
I'm a principal scientist at a product
that not many people know, Adobe
Experience Manager, content management.
We run a lot of
uh
website properties for big brands, and
my background is in in open source, uh
contributing to to a lot of foundations
and projects.
What are Agility Sites, and how are we
building this thing?
So, we're looking for sites
that are
uh
looking at the what intent the user
browsing
uh has. What is the user doing? What is
the user trying to achieve? And the end
goal is to personalize these pages for
the for the current user browsing, so
that eventually this
uh drives uh higher engagement or uh
conversions, whatever the marketing
teams want to want to achieve.
And these pages are personalized in real
time based on the on the user that is
uh accessing the site, and what is the
what is the user doing.
The stack we're using is AMH delivery.
So, this is the part of the product we
we have,
uh where all the content is on the edge
and then we have back end service that
powers this experience with
different LLM providers LLM services
we use Cerebras for fast inference or we
can use also we tried bedrock and and a
bunch of others. I'll be showing
Cerebras today
and you will see the reason why.
The
the engine that is personalizing this
this bits is
using the rich content and blocks.
So different blocks on the site are
customized depending on on what the user
persona is.
We don't want the the whole site to be
generated. I mean if you talk to
marketing people they they have a very
strict brand guidelines. You don't want
to just
come up with our have some
hallucinations there. So the what is
personalized is different sections of
the site and we use the whole site as
a corpus. We built a rack from the whole
site. So what is generated is grounded
on on the existing site.
We tried to solve the problem where one
size fits all. We want
hyper-personalized experiences. Also we
want to help
our customers to do more automatic
authoring. So not having to create
thousands of different variations of the
site but use AI for this
and then do these multiple layers of of
personalization.
Some examples of what we're doing or
I'll show in the demo. It's
instant persona adaptation, query
generation when the user search for
something on the site, the page with the
results is customized for them and also
uh, something like recommendations where
after you browse the site for a period
of time, we
we can create a page that recommends
something based on on on what you are
what we think you are looking for.
For marketers, uh, they can define this
strategy on natural language, and they
can use analytics to
to drive the loop of personalization,
and what is the end goal, and how this
goes back again to change to adapt the
personalization to improve that uh,
whole cycle. Everybody's talking about
loops in this conference, so that's
that's one of the loops there.
How the architecture look like? So, it's
a dynamic front end with some blocks,
what I mentioned before, and with uh,
edge delivery services is basically you
compose these blocks, and uh, they are
updated on on real time through with the
AI.
The back end,
uh, we
we do the um,
evaluation of the models and the
providers,
and one thing we realized is is that
this is very dependent on the site. So,
we have a bunch of prompts, and we look
uh, we run it across a huge variety of
uh, models and providers, and then we
look at the accuracy, we look at the
speed, but this is going to depend
highly on what type of site, like how
big is the site, how I don't know, what
different
what different um,
area is the site targeting, what what
type of commerce it is, and so on. So,
we we run this this
um, evaluation continuously. We use uh,
Promptfoo. Uh, anybody heard about
Promptfoo?
Okay, some people.
So, Promptfoo allows you to evaluate
models um, prompts against my multiple
models, providers, and you can do local
models and any of the
a bunch of open AI compatible
uh providers and and uh
a lot of them, basically.
We look for two things. Why? Accuracy.
That's that's typically what people look
for, but also we want the speed because
we don't want the site generation to
take
more than 1 or 2 seconds,
right? Because people uh
this is already
uh proven that people want the the
faster the site, the more conversions it
it generates or the the better the
experience it is for the user.
Um yeah, what I mentioned is different
sites may have different requirements.
Uh so, you may have to run this uh
evaluation of models depending on the
site.
This is a an ex
uh we we secured this
some of these queries, so we have a 15
prompts for this example site.
Um we have uh
at the top you can see with Cerebras on
the Gemma 4 model that was announced
last last week,
we can get an average latency of 1.1
seconds generating a page.
You you can compare that to the second
one, which is 4.6 seconds, right? So,
the difference is huge.
And that's why uh we use Cerebras for
for this use case.
And uh
you can see that different providers,
different models have different
um
different speeds. And here is uh let me
I can show you the whole
thing here. Not this one, this one,
right? So, at the at the bottom we have
other other tasks.
Sometimes uh maybe some of them may be
good.
They don't need to be perfect, but
they're good enough if they're fast
enough. So, that's going to be the the
kind of
decisions that you need to make on
whether the model is good enough for
your use case or not.
Yeah, we're looking Yeah, average 1.1
seconds. And then the the next ones are
going from 4 seconds higher.
And you don't need a huge LLM to do this
sort of work because you are generating
text, you are deciding where to put
blocks and how to organize the website,
you don't need a lots of information for
that.
So, this browsing and the queries
uh is are being recorded. So, these are
the metrics or the
the the data we gather from the user,
and this is fed into the LLM to
personalize the site. And then in this
example, we personalize the hero card,
the products, the blog feeds, and and
the navigation based based on the
persona.
Also, what are some of the buttons like
our call to action navigation, you can
also we can also personalize those.
We
we create and I'll show you the a for
you page, which is a recommendation.
And this is a interesting one because
this you could
pre-generate, right? As the user browses
your site, you gather these signals, and
you could keep generating in this. So,
in this case, you wouldn't need so such
a big speed.
But
but that's interesting because it it
would be if a user wanted to
buy something, you could just say,
"Okay, for you, I will recommend these
three products or or something like
that."
Um
Yeah, and then they can see this
recommendation, and if they go there,
that that could be pre-fetched for them.
And obviously, you have to keep updating
it as the user navigates around the site
and and so on. So that that's also
something to consider on the cost cost
implications of doing multiple
generations, multiple LLM calls.
When
when the user runs a query, dynamic
personalized page is shown to them.
When the
these queries are also grouped into
personas or intent types. So what is
this guy what is this guy trying to do
in the site? Is trying to buy something?
Is trying to just get information? So
you can get marketers to decide what
type of groups, how many groups you want
to have, how you want to deal with with
customers. And the AI will choose the
the blocks and the suggestions for for
those groups of people.
Um
And we can adopt yes, the the different
blocks, the the the sequence of the
blocks and
media. You could also do media. One of
the things we consider is
there was some a model announced
today or yesterday the
the nano banana light. So you could even
generate images
very fast on the fly.
Obviously not as fast as text, but
that's also something that would be
I don't I don't know if it's that
something like marketing people would
want to have generated images. That
depends on on the quality a lot if it's
on brand.
And
the site in this example we have a a
product site
and then we have guides, experiences,
blocks and the whole response of the LLM
is grounded there. And
there's comparisons. We can do
comparisons between products that are
tailor and the product pages can be
tailored for the for the user.
Okay, this is this is a bit of the
stack.
Um not going to spend too much time
here, but the browser you have some
layers. You have the browser where the
signals get
get
uh
from I got I got I got so
I got from the from the user and then we
have the back end. Uh
we can have the back end. We run this
some of these in in Google. Some of
these are in our Cloudflare. So, the
back end is basically just calling the
LLM and doing some reasoning using the
rack that is built on on the site to do
the generation.
And you have obviously you have to have
the vector database, the inference uh
machinery and uh
that obvious business manager is doing
the serving the
the
at the edge is serving the the pages and
the static content.
So, let me show you because I think this
is uh so, we call this uh audience of
one
because the idea of in marketing
they they always dream on being able to
personalize things for each individual.
So, we call it yeah audience of one. So,
I have this this site. Uh this is a site
that is absolutely generated
uh example site. It's a coffee
uh machinery. So, I can go and and read
some stories
and I can go and look at some products.
Let's go and look at this product.
I can spend some time here.
Uh
let's go and click
here.
Okay, so I'm I'm browsing around the
site and I have this debugging tool
thing uh which
Uh
let me go here, I think.
Let's see.
So, down there is the signals that the
that the browsing
is giving us. So, I don't know if you
can see it much because I cannot see it
much. The So,
the user is bucketed into the exploring
category. We have the pages that have
have visited, and then we have how much
time is spending on each page. All of
this data is now available for the LLM.
So,
if I go here, I already have a for you
page that was generated for me
and
based on my browser.
And you will not notice that it's
slightly different than everything else,
but if I go here and I run a query
like I want I'm looking for a coffee
machine to
uh prepare
coffee
while camping.
The site is this was just generated for
me.
And then you're going to see some things
like the text is customized. Camping
shouldn't mean compromising on your
uh whatever routine.
Uh the coffee tips for camping um
machinery that are being recommended are
coffee agile and um or the nano, which
are
good for
for the
for a camping trip, right?
So, you saw how fast this was.
I'm going to run it here something
similar that I had here and I can run it
on the debug mode here.
And you will see, let's make this
bigger.
Total time 164 seconds to generate the
page. So, this includes a round trip to
the LLM. This is using Cerebras Gemma 4.
So, the the Gemma model from Google
running on Cerebras on their
very fast chips.
Uh we get
2,300
tokens per second.
Which is not bad.
I would say.
>> [snorts]
>> And if I run it again, uh probably
something like that.
Uh the LLM time is 1 second. And again,
2,200 tokens per second.
This is something that we only dreamed
about before.
On the on this site example site, we
have some other options.
Uh
so, because we we've been showing this
to customers, so we have the
the ability to change the different
models, temper temperature, tokens, and
so on. And we can uh
we can
show uh and try the different models and
see how they behave. Besides the
automatic test with Prompt Full, then we
can uh manually come and and click
things and see and see how that how that
works.
And uh
we also have
uh OfOneLabs.
So, we have we build this tool that
generates an agentic site for any site
we want. So, if somebody wants to have a
demo for a customer, come here
and enter the URL. In less than an hour,
you have an agentic site. I did this
last week with the AI engineering site.
And
I got this site that is just a search
box and a few things.
And
let me open it here, the full page. Not
this one. Yeah, okay.
So, I could say
Europe AI conferences.
So, these suggestions are also AI
generated. And I get a page that is
more focused on
It should be more focused on on the on
this European conferences.
If I go back, did I go
I can search for anything the same way I
did with with the Arco. So, I as a
specific There was someone that was
generating a good comparison side to
side.
Let me see if this one. Okay, here. This
one.
I went and this generated a page with
a pretty good comparison. If I'm looking
at two conferences and I need to decide,
if I figure out that the user wants to
do that, this is great because that
gives them a side-by-side comparison on
the fly.
Now,
this this is I think this is cool
already, but then we have I have this
idea that
probably the I'm
a bunch of people are we are talking
about is the web that is is the web the
future still and so on. Nobody knows.
But we can also do something with this
with this audience of one, this
generative sites. So, imagine you have
you have your personal assistant and you
ask a query through in this case through
Google and you say I want to buy I don't
remember what the query said. It was
something like I want to buy a machine
and I get this on my Google TV.
Right? So, this is absolutely
personalized to my query.
Okay? No, go back.
This is absolutely personalized to my
query. So, I'm there in my living room.
I don't need a phone, I don't need a
computer, I don't need anything, just my
voice and something that will
kind of show me
something that is absolutely
personalized to to me.
Okay, so that one.
So,
what I was trying to show and hopefully
you remember from this session is that
this is now possible.
It's only going to get better from here
on. It's only going to get cheaper, it's
only going to get faster.
And you will uh be able to have uh
huge personalization options for sites
and for other things.
And you can do this with intent driven.
So, what is the what is my user trying
to do? What does my user want to buy?
These sort of questions. And you can uh
assemble a page just for them.
And you can also do this with uh
multiple models and and eventually
it's just going to be
faster and faster, right?
So,
that's it. Um
thank you for coming and I hope you you
got the idea. Thanks.
>> [applause]
