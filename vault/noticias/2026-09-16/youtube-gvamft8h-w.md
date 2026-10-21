---
titulo: "Your Agreements Are a Database You Can't Query — Hiral Shah, Docusign & Sean Sodha, NVIDIA"
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-16'
url: 'https://www.youtube.com/watch?v=_gvamfT8H-w'
thumbnail: media/monitoring/youtube/gvamft8h-w.jpg
descricao: "Ask your own company what it actually contracted for with a given vendor and nobody can tell you. The number exists, negotiated carefully, sitting in a pricing table inside a signed PDF no system ever read back. Hiral Shah puts a figure on the aggregate: roughly two trillion dollars of negotiated value locked in agreements that organizations never return to, because recovering it means human reading, human review, and manual work across disconnected systems. The scale on Docusign's side is its own engineering problem. Close to two million paying customers, a billion users, and about a million agreements flowing through each day, all of it needing to come out structured, queryable, and useful. Agreements also refuse to be flat. One governs another, which amends a third, so answering a simpl..."
resumo: 'Docusign and NVIDIA product managers explain how agreement data — pricing, terms, obligations locked in unstructured PDFs and scans across millions of contracts — goes unqueried and unused at enterprise scale. They walk through the technical architecture built to process documents at that volume and share evaluation findings from testing different models for each stage of the pipeline.'
tags: {}
fontes: {}
---

## Descrição

Ask your own company what it actually contracted for with a given vendor and nobody can tell you. The number exists, negotiated carefully, sitting in a pricing table inside a signed PDF no system ever read back. Hiral Shah puts a figure on the aggregate: roughly two trillion dollars of negotiated value locked in agreements that organizations never return to, because recovering it means human reading, human review, and manual work across disconnected systems. The scale on Docusign's side is its own engineering problem. Close to two million paying customers, a billion users, and about a million agreements flowing through each day, all of it needing to come out structured, queryable, and useful. Agreements also refuse to be flat. One governs another, which amends a third, so answering a simple question often means traversing twenty years of a business.

The specific thing that breaks is tables. Pricing tiers, rate cards, SKUs and service levels are exactly the terms people need, and they are exactly what generic extraction handles worst, because reading a page line by line destroys a merged cell or a nested column. Shah and Sean Sodha describe the model they built together for that one job, an unusually small vision language model at roughly 900 million parameters, designed as an extractor rather than a generator. It replaces a stack of separate layout and table models with a single pass that returns reading order, semantic structure and preserved tables. Their headline lesson is about restraint: a purpose built small model ran table extraction around twenty times faster than the general alternatives they tested, and lower context meant lower latency and lower cost at their volume.

Speaker info:
  / shahhiral  
  / sean-sodha  

Timestamps:
0:00 - Why agreement data is an engineering problem
1:42 - A million agreements a day
2:19 - Two trillion dollars nobody goes back for
3:59 - Why tables break generic extraction
5:12 - Building retrieval models in the open
7:03 - An extractor, not a generator
8:32 - Demo: an order form becomes structured data
10:30 - Structuring agreements across an organization
11:18 - Purpose built models beat general ones
13:12 - What comes next
13:44 - Q&A: is OCR going away?

## Transcrição

[music]
Uh, welcome everyone to our session. Um,
I'm Haral. I'm a senior director of
products um at DocYsine and I'm joined
by Sean.
>> Hello everyone. I'm a product manager at
NVIDIA. So today Sean and I are going to
talk about a massive problem that every
enterprise faces which is agreement data
largecale agreement data. Agreements are
a big part of any relationship any B-2B
organization kind of goes through day in
day out and a lot of that data is
captured inside that agreements and it's
very critical whether it's pricing
tables a lot of it and it's all in a lot
of different unstructured format and
that's kind of what we're going to show
is how docysine partner with Nvidia are
fixing that on making that data
available readable usable for a lot of
our kind of organizations.
So here is kind of just a quick map of
our talk today. We'll start with just
the stakes. Why does this matter? Why
the scale is so large? And then we'll
dive deep into the technical
architecture of how we are approaching
it, how we have tackled this thing,
especially this document processing at
at scale. And then finally, we'll cover
what we have learned from our evaluation
of all of the different models we've
tried for different purposes. and share
our learnings with you. So why like you
know just to understand why this is a
big problem when you think about
docysine raise your hands how many of
you have used docuign anyone who's
employed probably the HR docs right so
it's a massive scale everyone uses docy
for us it's a massive engineering
problem as well because just look at the
scale we have 1.9 million customers who
are paying us and a billion users what
does that imply we process us a million
agreements a day that needs to now
structurize make it readable make it
querable usable and you know in the past
we've worked with Deote on a study and
it says that there's $2 trillion
captured in this agreement negotiated
value that no one capitalizes no one
goes back and gets that um data back
right and why it's because they have to
do a lot of human reading human reviews
there's disconnected systems lot of
manual workflow flows that are there. So
that's kind of why docysine built AM an
intelligent agreement management
platform that takes the entire like
applying in an AI first way the entire
agreement life cycle whether you are
creating agreements genai helps a lot
with that whether you're negotiating to
understanding and redlinining all the
way to after signing storing and making
a lot of insights from this data. So
when you think about the challenges
involved right in an agreement there is
the unstructured data it could be a PDF
it could be a PNG and what are people
wanting to do is simple questions they
can't get that that is the data that is
trapped inside one agreement but also
the whole corpus of millions of
agreement 10 years 20 years of business
has kind of put into that. So for
agreements aren't flat they're also
hierarchical in nature they are like you
know one agreement governs the other the
other kind of does something else so you
always are needing lot of things to
answer this question simple thing I'm
sure you all are using a lot of right
claude and all especially at your
company's organization simple thing what
did we contract for the total tokens
right with claude no one knows that's
captured inside this agreement in
different forms and fashion so you need
to be extracting this data to find
things but also insights and push it
downstream where you're tracking doing
more things and when you analyze an
enterprise contract a big set of things
are captured what I call vital terms
like pricing tiers the skues the
information SLAs's rate cards they're
all in table format now traditional
document extraction tools or a generic
VM BLM completely fail here like you
know we've tried we've definitely done
this because they're reading text line
by line which breaks a lot of that
concept within the table. A merge sells
some things are not boundaried. So this
makes a massive operational overhead for
the downstream legal teams, procurement
team, sales teams to get that queries
and get that answers done and they spend
hours and hours digging through this um
to even just locate a basic thing. And
that's kind of where we partnered with
Nvidia and leveraged a purpose-built
model like you know tool which is
literally like you know making our
architecture for table extraction take
us to make and solve these complex use
cases. We're trying with this we're
making things scalable. We can
understand it with the layout but also
deliver really accurate results. And to
share more about how we're leveraging
the Neotron, I'm going to hand it to
Sean.
All right. Hello everyone. Uh so real
quick on uh the Neotron retriever
initiative. So for those who here knows
about Neotron, you raise your hand real
quick. Awesome. Uh so Neimatron is all
about building worldclass open-source
models and publishing the data sets, the
techniques, uh the quantization
approaches, distillation approaches,
pruning approaches, every technique
possible, blueprints to go with that,
you name it. Um throughout the Neimatron
portfolio, we have specifically Neatron
Retriever, which is building embedding
models, reranking models, and document
extraction models. Um so real quick here
we sort of our first initiative is if
you're a large scale enterprise that
deals with pabytes scale data our first
initiative is how do we make sure that
you find the right document given a
certain query your agent sends you know
set of queries to the corpus afterwards.
Once you find those top five quer
documents whatever it may be then we say
okay you found the right document now
how do you then find the right
information within the document and this
is where the work with the docysteine
team has gotten really great where we've
uh worked with them to build the neatron
parse model to focus specifically on
table extraction which is a really
complicated technique um if you think
about it the number of permutations of
tables are quite vast when you think
about nested tables merged cells merged
columns merged rows whatever it may be
and that can get really really complex
and really hairy of a
Uh so real quick as I mentioned before
right our team is responsible for uh
leading a lot of the leaderboards in the
retrieval space. So Vidori V1, V2, V3,
MTB, MMT. Um so our team knows how to
build world-class retrieval models given
a lot of leadership uh given a lot of
leaderboard winnings that we've had in
the last year or so and then of course
as I mentioned before we open source
everything right so we share the open
source model weights the techniques and
then we release with those blueprints
and skills that agents can use then
afterwards um so to touch a little bit
on the actual model that we are working
with with docuign was the neatron parse
model so when you think VLM you
generally think a multi-billion
parameter model it's very heavy It's
high latency. Um, this is a very small
tiny C radio VLM. It's about 850 900
million parameter model. Uh, designed to
kind of be that all-in-one package sort
of model where you deploy it and instead
of having small, let's say, YOLO X
models that do table extraction or page
element extraction, whatever it may be.
This is a singleshot model that you can
feed a document in and out comes the
semantic formatting layouts the text uh
the reading order uh the the preserved
structure of the table etc. Um this can
be served via the NVIDIA NIM or via the
LLM as well too. Um and so it's a tiny
small model that you can use. It's not a
generator. It's more of an extractor at
the end of the day.
Uh so real quick as well too um we
always want to make sure that we're
building towards benchmarks that matter
most to the enterprise space. So we want
to make sure that both on the paro curve
of accuracy versus performance, we're
make sure that we're going to be
releasing a world-class models to the
ecosystem too. So you'll see here
generally is just a very standard
benchmark of table extraction. I believe
this one was RD table bench and we
compare some popular open source models
here and then we compare how our neatron
parse model does compared to that
industry and we continue to kind of
strive to improve this as time goes on.
>> So that I think we believe we have a
demo as well. Yeah,
>> just press one. Yeah. Okay, there we go.
>> Let me show you how easy it is to turn
any agreement into structured usable
data with agreement manager, which is a
central repository of every agreement an
organization has ever signed. Let's look
at this. So when we look at the
agreement manager view here, you know,
we have an ability to see the entire
list of agreements, but also go and
upload a new agreement. So I'm uploading
a new order form into agreement manager.
As you can see, I can select from my
computer, import from other places. The
moment I select the agreement, it starts
uploading and starts processing with AI.
And just like that, you can see that the
jobs engine has processed it. Let's take
a closer look at this agreement. So when
you go into the action, you can go and
browse the file. Within seconds,
agreement manager has extracted a rich
set of metadata. Everything from key
terms to commercial details are
automatically structured, highlighted,
and immediately you can jump to that
section where the details are found.
Builtin goes deeper. This is where the
Nvidia's model comes in that it's
extracted all the structured pricing
data around this agreement. It goes in
breaks down these complex t tables into
order details.
And as you can see, we can break it
down. We can download all of this data.
This is powered by the advanced parsing
leveraging Nvidia's Neotron model,
turning every even dense tables into
something that is instantly usable. And
of course, you can take this data with
you. You can see when we've downloaded
into CSV how we've structured all of it
for your finance team, procurement team,
even further analysis. All of it is also
available through API. And that's how
agreement manager has transformed
agreements into actionable insights in
seconds leveraging Nvidia.
So I think you know what you saw there
from um a demo perspective we've tried
to shortened it. It's like we have a
whole repository what you see a list we
get customers which has thousand
agreements to all the way millions of
agreements within but the big piece is
how do we understand and get that data
that makes it very valuable to an end
business user right a legal person a
procurement person a salesperson who's
doing a lot of the deals or even a
leader right like a business unit the
CTO goes and asks what did we do this is
how we have making each of the things a
lot more structured so we have our own
proprietary agreement data model which
we are structurizing each agreement but
also at a whole organization level and
leveraging a lot of the NVIDIA things
we've been able to do a really good job
especially with all of those tables like
pricing SLAs's and then make that
available and then we also have a like
robust kind of search that is um on top
of it so when we think about what have
we learned right when you think from a
neotron plus docyign we one of the
biggest things for us we definitely have
done lot of different models mod for
different purposes. So a purpose-built
model for the job you're trying to do is
a big big part of how we've been
thinking about and that's kind of where
we've been able to accelerate bring
things to market much faster. The second
big piece around like the model
efficiency. So for you know as Sean was
talking about the number of parameters
yes context and stuff matters in the you
know in a different environment for
different things. For us the lower kind
of context basically also meant lower
latency lower cost to deliver the scale
that we are talking about last around
the faster extraction. So um we we ran
this against a lot of the other open
source models when we think about how
many tables can it extract per second
Neotron was 20x faster which helps us
when we're talking about the millions
and billions of scale that we're kind of
serving for all of our customers. So a
lot of it is like having that smaller
purpose-built things is the way for an
enterprise as an organization to go and
leverage and then serve that from an
enduser perspective.
Um and then what's next? So I'll let
Sean talk through those.
>> Yeah. So working with the docuign team
uh has been awesome so far. Uh and we're
going to continue to deepen that
partnership as well over the next few
months. So uh with them we started with
the how do I extract as much possible
information from a page and now we'll
scale to how do I now find that page to
begin with. Um so we'll start a little
bit with the neatron retriever uh effort
and then of course we'll talk a little
bit about the NVIDIA agent toolkit with
them over the next few months um and
then actually start scaling into into
more production scale agents then.
>> Perfect. I think that's what we had. We
have time for a couple questions. I went
in the room.
Okay. Someone there.
So just to recap for everybody if you
didn't hear it was the question is right
like OCR is always a thorn in the whole
process so are we thinking about letting
the go of that and starting from agentic
from the get-go I can talk from my
perspective but so I think for us right
like there are different use cases at
different points in time many times if
you are reactive you have a question and
you're coming some of that can can work
dynamically at a smaller scale. The
question is the latency when I am
quering at that scale of thousands I do
need to have pre-processed have
identified. So that's one. I think the
second big part of the use case for us a
lot of times businesses want to use this
data to do a lot of downstream work. So
an example is a procurement team. This
is my pricing table. I want to put it
into Koopa to make sure when I'm paying
that works at that time there like you
know the agent is kind of helping but I
can't do that on a one document by
document. That said, there is ways that
we are compressing. That's kind of why
Neotron worked for us is like how do you
do it from a layout understanding just
for that purpose but I would like let
you add.
>> Yeah, I think it depends on the use case
a little bit. Um I think for this
specific instance, right, you have
pabytes of documents that you want to be
queriable at some point, right? So you
are heavy on the compute at the upfront
side with all the OCR so you don't have
to worry about it later on, right? I
think there's some instances where
people may upload a contract to begin
with for Q&A and that's a very high
that's a very low latency use case,
right? So you have a high throughput
versus low latency use case and in that
scenario your different batch sizes,
your concurrencies, your different
techniques on how you process the
document will be different and where you
spend that compute in that cycle will be
changing between the different use
cases.
>> Okay, one more there.
We we do a lot of like more of what I
call hybrid approach and a purpose-built
for like the needs and the use cases. So
from a table piece it does kind of you
know do the whole layout along with
extracting we still do OCR from a lot of
other fields and metadata and a closet
like all of the text kind of thing. So
that I think we had a architecture where
we have our pipeline going through two
different routes for that. Um as a
follow we have a blog out there how are
we really solving this at scale across
and if you look at that there's a lot of
different peacemail modules and stuff
together.
>> Yeah one moreization
>> so we this model is currently on FP16
but there are paths towards going on to
FP8 and VFP4 in the next few months as
well too. What about center? Are you
using the 16 or
>> we do use that and then we're also kind
of using some of the older ones and
that's the journey as a partnership is
to kind of go tweak as you get more of
the customers.
>> Yeah. So for this there are many
techniques on how to improve the
performance side right so quantization
right so we're trying to move everyone
to blackwell right so that's why NVFP4
is the big thing now um as well as
multi-token generation for this it's a
VLM architecture right so your encoder
decoder techniques can definitely be
further optimized so not right now this
model just generates one token at a time
you can do multi token generation of
course too so there's plenty of
performance things right now we're
focusing on the accuracy side like are
we adding value to the system and then
from there we'll then push out that paro
curve on the performance
Are you guys using
>> I believe they just deploy via directly
recall
when you do that.
>> Oh, this is just an extraction. This is
now a retrieval.
>> Yeah.
>> Yeah. Yeah, you're asking question.
>> It's coming from that agreement data
that we've kind of extracted and stored.
>> So yeah, maybe I can chat with you
offline and how like we our architecture
kind of works fully as well.
No, we're almost coming up on time
there. Um, but I think that's kind of
all we have. I'm happy to hang around uh
in the back with more questions. Um, and
good luck with a lot of your uh
challenges with AI. So, thank you.
>> Thank you.
>> [applause]
[music]
