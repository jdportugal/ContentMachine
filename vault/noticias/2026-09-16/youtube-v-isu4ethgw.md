---
titulo: 'Connect AI to Billions of Legal Documents — Simon Eskildsen, turbopuffer & Jacob Lauritzen, Legora'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-16'
url: 'https://www.youtube.com/watch?v=V-isu4eTHgw'
thumbnail: media/monitoring/youtube/v-isu4ethgw.jpg
descricao: "Legora's search latency once went from a 100 millisecond P99 to twenty seconds, and the cause was a packing problem invisible in the schema. Legal work arrives as projects, a project gets worked hard for a while and then is never opened again, and when the team sharded document chunks across four thousand partitions the dead projects landed in the same partitions as the live ones. Every query pulled an enormous partition into memory, evicted the last, and thrashed the cache. Jacob Lauritzen walks the whole migration honestly, from one search cluster for everybody, then one per region once clients on three continents each demanded their processing stay home, then into the general purpose database the team already ran, which is where it broke. What worked was making the project the unit of s..."
resumo: 'Engineers from Legora and turbopuffer explain how their legal AI platform handles search across billions of documents, covering two workloads: project-scoped search within client matters and broad legal research across laws, cases, and regulations. They walk through the evolution of their search architecture, starting from a single shared Elasticsearch cluster and the problems that arose as they s...'
tags: {}
fontes:
  - 'https://x.com/Sirupsen'
  - 'https://sirupsen.com'
  - 'https://sirupsen.com/napkin'
---

## Descrição

Legora's search latency once went from a 100 millisecond P99 to twenty seconds, and the cause was a packing problem invisible in the schema. Legal work arrives as projects, a project gets worked hard for a while and then is never opened again, and when the team sharded document chunks across four thousand partitions the dead projects landed in the same partitions as the live ones. Every query pulled an enormous partition into memory, evicted the last, and thrashed the cache. Jacob Lauritzen walks the whole migration honestly, from one search cluster for everybody, then one per region once clients on three continents each demanded their processing stay home, then into the general purpose database the team already ran, which is where it broke. What worked was making the project the unit of storage, one namespace each, so an idle one rests in object storage costing nothing.

Simon Eskildsen then explains why that shape suits regulated customers so well. If the namespace is the atom, every namespace can carry its own encryption key and its own bucket, which is what banks and large firms actually mean when they demand physical isolation and the ability to revoke a key and make their data unreadable. The best detail is an experiment: rather than build encryption into the SSD cache, they disabled the cache outright to see how bad it would be, found the performance acceptable, and left it off. The back half is a crash course in storage engineering: why round trips dominate when object storage answers in 200 milliseconds, why clustering into a tree beats navigating a graph, and why full text search at web scale costs more than vector search.

Speaker info:
https://x.com/Sirupsen
  / sirupsen  
https://sirupsen.com
https://sirupsen.com/napkin

Timestamps:
0:00 - What Legora does, and two kinds of legal search
2:32 - One search cluster, then one per region
4:00 - What enterprises mean by physical isolation
4:48 - Moving search into the database they already ran
5:38 - How hot and cold projects thrashed the cache
6:40 - A namespace per project, and what it fixed
7:56 - Writing straight to object storage
9:58 - Why a namespace is the unit of encryption
12:07 - Legal research, and ten billion vectors
14:25 - Puffing data through the memory hierarchy
15:30 - Why a tree beats a graph on object storage
17:45 - How full text search actually works

## Transcrição

[music]
Okay. Hi everyone. Welcome um to this 20
minute talk about connecting AI to loads
of legal documents. My name is Jacob.
I'm an engineer at Legora.
>> Yeah. And I got to step into frame here.
Uh I'm Simon. I'm the CEO and uh CEO and
co-founder of Turopuffer, a search
engine that we work with Legora and
others on.
>> Neat. Um super quickly
um introduction to Legora. We're a
collaborative AI platform for legal
work. And so that means we have law
firms that are clients and we have uh
in-house legal teams that are clients.
And they use Legora to do reviews of
contracts. They use it to go through an
absurd amount of contracts and make sure
that they all look good. They use them
to create new contracts. They do legal
research which means um looking over all
potential law and they collaborate
inside Lor. So you can think of Lorra
sort of as a linearfigmas
notion GitHub for legal work. It's a
lot.
We um are one of the fastest growing
companies right now. We grow extremely
fast. Yeah, tons of numbers on the
screen. I'll just skip through that.
What we really want to talk about is
search today. So at Lor there's two
types of search that we do. There is
project search and legal research.
Project search is um basically projects
in Lor is like the the unit of work that
you have. So if um let's say you are
SpaceX and you want to acquire Cursor,
then that would be one project with your
law firm. H and so they would go into
Lor and they would upload all these
documents and the law firm that helped
you would go through all of the
employment agreements, all of the
contracts with suppliers. Um I know
cursor is using Turbo Puffer so maybe
there's a contract there they'd look at.
Um
but basically you do the search confined
to a project and projects can be tens of
documents to millions of documents. The
other use case is legal research. And
legal research is sort of a deep
research style workload where we'll
search across tons of laws, previous
cases, regulations, etc., etc. And
people use this to answer questions such
as like how do I handle this specific
thing? And they'll also use it for
litigation. Maybe they want to sue
someone or maybe they are getting sued
and they'll use legal research to
support and help their case.
So if we start at number one, project
search, we've been through a little bit
of a ride here on how we do search. Um,
starting at, you know, hundreds of
thousands of documents all the way into
two billions of documents. And we've
tried a lot of different things.
So first we start started with the very
very simple one, which is just a single
elastic search cluster for all of our
search workloads. Um, that worked
relatively well. It was sort of a simple
setup. All of the tenants, you know, our
clients, our users would be on one big
blob storage where we store the raw
documents and on one big elastic search
where we would do all of the searching,
the indexing and the searching.
Super simple, worked relatively well
initially.
Then we wanted to enter the land of the
free and uh we got some new
requirements. They you know Americans
only want processing to happen within
the US and Europeans only want it to
happen within the EU and Australians
only want it to happen within Australia.
And so we had to basically um well
here's a scaling graph. We had to move
to multiple elastic searches. And what
we actually did was we took the entire
setup and we just basically iterated
over the set that is EU, US and Asia
Pacific. And so we just had this like
multiplied by three or four. Kind of
annoying, lots of overhead, but it uh
got us to where we needed to be.
Then the next iteration of the story is
enterprise. So really big banks, the
biggest law firms in the world, they
have really annoying requirements. And
number one they have is they'll ask for
full physical isolation of all of their
data.
There's probably a little bit of a war
on what physical, you know, isolation
actually means, but essentially means
they want their own database. They also
want customer managed encryption keys.
And what that means is they basically
have a key volt thing where they have an
encryption key and they give us access
to read the key and we then use that key
to encrypt and decrypt all of their data
at rest. And what that gives them is
they can just revoke our access to their
key and then we can't decrypt their data
anymore and so it's safe. And so in a
way that gives enterprises a lot of
control over all of their data because
they control you know the key to to
reading it.
So we moved from elastic search to
Postgress and I imagine a bunch of you
guys are like why would you ever put
your vectors into Postgress. Um it
actually worked surprisingly well and
the reason that we did this was we were
already using Postgress for OOLTP
workloads and uh so we sort of already
had to do this split of multiple
Postgresses and multiple blobs and so it
was really easy for us to try to shift
all of our search into Postgress as well
because then we only have one system. So
the setup here was PG vector uh
specifically disk NNN TS vector for the
search. So not BM25 which was you know
we lost a little bit of retrieval
performance there. And what we do is we
would partition the table where we would
store all the document chunks. We'd
partition it super aggressively like
4,000 partitions and then each project
we'd basically have the project key and
we'd binack them into the partitions.
that actually worked relatively well,
but it was expensive and search
performance wasn't super super good. And
what happened was when we scaled a lot,
uh everything just broke and exploded.
And so what happened was the basically
you can imagine like you have a bunch of
projects and some of them you spin up a
project, you work on it and then you
close it and you basically never go back
to it again. And we have a bunch of
those where like they never get queried
and we have a bunch that get queried all
the time because they're super active
projects. And when we pack them into
partitions, the cold ones and the hot
ones would land on the same ones and the
partitions would get really really big.
And so when we query them, they would
Postgress would pull the partition, put
it into memory, we do the stuff and then
we create another partition and another
partition and it would essentially
thrash the cache all the time. And what
that meant was our latencies would
spike. So we went from like search and
ingestion P99 of 100 milliseconds into
20 seconds, which you can imagine is a
really bad user experience.
So then we went to turbopuffer in about
you know when we're about I think 400
million documents something like that
and what we did with turbopuffer was we
did one name space per project and the
advantages of moving to turbuffer is we
got BM25 real BM25 much better uh
relevancy much better latencies and it
was extremely simple to operate because
we could just have a single turbo puffer
cluster you know we didn't have to have
a bunch of different ones like with
Postgress and it would since it's blob
based it could just query the blobs that
we had anyway and so much lower cost and
it was extremely simple to operate and
we didn't have this problem with the
partitions because if a project's not
used it's just in blob and so it's
really easy and Simon can talk a bit
more about why that works so well.
Yeah. So, Legora has some of and and
legal in general has, by the way, if if
uh Jake and I have similar accents and
maybe even look a bit similar, it's
because we're both Danish. Um,
Turboper has a very has a particular
architecture that supports these kinds
of very regulated environments really
really well. But in order to understand
that, we have to understand what kind of
search engine is Turboper. Why is it
different than the ones that they used
in the past?
Since the very beginning of Turbopuffer,
um the design has more or less been the
same. Uh there may be changes in the
future, but the design has stood the
test of time. When you do a write to
Turbopuffer, we write directly to obic
storage. There is no like disk
replication. There's no paxas. There's
there's none of that. Direct to S3.
That's the fundamental trade-off in
Turbopuffer, right? Hundreds of
milliseconds. If you're like Shopify and
doing inventory uh reservations for a
Kylie Jenner flash sale not going to
work very very good for search because
generally when you're doing search doing
a slow write is fine um as long as the
read performance is is adaptable and
good. So that's what happens on write it
just goes into write ahead log you can
imagine you write 1.json 2.json 3.json
JSON. Obviously, it's a it's a database,
so it's not JSON, but for illustrative
purposes, that's what happens. And in
the background, we build the vector
indexes, the text indexes, the colmer
indexes, and so on to satisfy the
queries that that Jake and other
customers have. Um, so then at query
time, we can go in and then um the query
reaches some namespace. The namespace is
kind of our concept of a of a table. Um,
you can think of it as a directory on S3
that's isolated from everything else. We
go to the node that is most likely to
have it. It could go to any node, right?
It could go to every single node and
they're all read replicas, but we go
with some affinity to the node that has
the highest probability of having it in
cache. We check the memory cache for any
objects, NVME SSD cache, and then
finally to object storage. Everything in
Turbopuffer is optimized around doing as
much work in as few round trips as
possible. Right? S3 has a P99 um on a
like 1 megaby blob size of around uh 200
milliseconds. So, you want to do as few
round trips as possible, right? Ideally,
you do around three. And everything in
Turbopuffer, the database is Oh, I'm
going to need your fingerprint.
>> Got it.
>> Um, everything in in Turbopuffer is
designed around minimizing the number of
round trips. This is also amazing for
modern D discs. If you do a lot of
concurrency and few round trips, you
utilize them optimally. And everything
in Turbopuffer is designed around this.
So, why is this so good for a company
like Lora? Well, object storage native
if you design it around the atomic unit
of separation being the namespace or the
table, every single table could be
encrypted with a different key. Every
single namespace could be in a different
bucket. We have customers that have um
thousands of buckets that they have
namespaces in so that their customers
get the warm IT fuzzies of having the
bucket in their own cloud account. They
can also be encrypted with their own
keys. You can share buckets. You can you
can do whatever configuration that you
need at the namespace level. You can
re-encrypt with different keys. You can
move them around. Um and you can
re-encrypt with other keys. Um for Lora
in particular, this was really important
for this full physical separation,
right? An encryption separation. All of
the namespaces needed to be physically
at rest with different keys and as
separated as possible. S3, GCS, Azure
BOP stories, they pass that um and the
other parts of the hierarchy also except
the NVME SSD cache because in the SSD
cache we consider that to be volatile
like memory um but your customers did
not. So in the uh so what we did was
that we thought we were going to
implement encryption into the disc cache
but instead we just disabled the disc
cache and saw how it fared and the
performance of turbopuffer even without
the disc cache with just the memory
cache was so good that we just kept it
that way for some of the lora workloads
where we couldn't have the disc cache
for multi-tenency turbuffer will support
that in the future but it just goes to
show the um natural point where
turbopuffer allows this encryption and
storage and separation to become fully
multi-tenency uh native. I'll hand it
back to you on what happened then.
>> And then, drum roll please. Latencies
look like this. Um, is my mic working?
No. Could I or I'll start screaming
really loudly. Um, it speaks for itself
if you can't hear me. Okay. Um, latency
is improved in order magnitude
basically. And these are median
latencies. So, P99 were even better. Um,
so obviously this is a huge thing when
you're doing I mean one thing is if
you're doing a single sort of rag style
thing but if you have an agent that does
20 queries 100 queries these really
really add up.
So that was on the project side and then
a more recent thing is legal research.
So legal research um is a kind of a
difficult problem and the reason it's
difficult is that um the corpus is
extremely big. So we're racing towards
10 billion vectors and we're growing
extremely fast. We also have quite high
read. Um so QPS can spike a lot because
we do a lot of fan out. Like if you do a
a sort of legal research query, we will
fan it out to a bunch of different
queries and we'll keep going. And the
reason we do that is we need this um
heavy filtering because essentially it's
a kind of like a graph for a few
different reasons. Firstly, it's um
hierarchical. you know, you have cities
and you have counties and you have
states and you have federal law and it's
the same all around the world. Um, and
so you need to respect that
authoritative sort of hierarchy. There's
also some temporal validity. So, one
judge might overrule a decision that's
been made somewhere else and you need to
also respect that and figure that out.
And then sometimes there's even like a
new regulation that has exemptions or
special cases of an old regulation. And
so if you're finding this one, you need
to find all the other ones as well. So
you can imagine that it sort of explodes
the search
and so we started on elastic search for
this but also moving to turbuffer um
elastic search just got extremely
expensive because we have to have
everything there but um with turbopuffer
we can basically uh take different
jurisdictions and we can make them name
spaces in turbo buffer and that means
some of them here's an example where
like you have the EU that gets quered
all the time that's super hot and some
of them let's say Danish law cuz we're
Danish, no one cares really. It's such a
small country, so like it doesn't really
get queried and so that can just stay on
blob and that's fine. Um, and because
it's sort of a deep research style
workload, if there's 500 milliseconds
latency to fetch that cold blob, that's
okay. That's fine. It's not really a big
problem. So, the way that Turbo is
designed lends itself super well to this
super long scale of like cold weird name
spaces and a few that are really, really
hot. Um, yeah. And Simon wants to talk
more about that.
Yeah. So, um um I was I was talking
about why the company is called
Turbopuffer at another uh talk here
earlier today, but uh one of the other
explanations of the name of Turbopuffer
is that it's about puffing into the
different memory hierarchies and really
mastering when data should be in
particular memory hierarchies. So, you
can think about it here, right, of
something like the EU law might be more
or less part of almost every one of the
legal research uh queries, right? So
that probably sits closer to NVME SSDs
in memory, right? The economics kind of
change as you move up and down this
hierarchy. Um in in memory, you want
things that are queried a lot, right?
Then the economics of memory are great.
NVME SSDs can you can do a lot of things
directly on them, but the economics
change as you move up and down this
boundary, the latency changes and the
way that the database is architected to
take advantage of it in terms of round
trips versus random versus sequential
all changes as you navigate this
hierarchy. Turbopuffer is a database
that is really designed around the
memory hierarchy and all of the smarts
in Turbopuffer is that all of these
namespaces are puffed in and out um of
the cache. You can think of this as we
want to spend as much time have as much
data pushed as far down in this
hierarchy as possible to get the the
best um performance cost ratios. So how
does that apply to search? Well, for
something like vector search for
example, there's two fundamental ways to
do vector search. One is to navigate it
basically design a graph. The problem
with a graph on something like object
storage or disk, again, we want to have
things as far down that memory hierarchy
as possible. The problem with a graph,
this is not a graph, this is a tree. Um,
but in a graph, you have to navigate
from the center of the graph. So, and
then every time you navigate through
these nodes, you're doing 200
millisecond P99 to S3, right? And so,
you're trying to shrink the diameter of
the graph. You're trying to do all these
tricks to make the graph, but
fundamentally you're at odds with the
fact that graph is about a random
sequential trade-off that you have in
memory and in registers, but not further
down the memory hierarchy. The way
Turbopuffer does it is organize it into
clusters, right? Vectors you can think
of in two dimensions just as point is a
massive coordinate system and we can
organize them into clusters. Turbopuffer
then creates clusters of clusters and
clusters of clusters of clusters to
essentially organize all of the vector
data in a tree. You can basically think
of turbopuffer as a very very
complicated B tree, right? Because it's
a tree on this geometry of this entire
space and the clustering of it in an
approximate way. Now the root
centroidids further up the tree you can
imagine are part of every single time
you search right there. We're always
trying to figure out which clusters that
we're in and we're always looking at the
upper levels of the tree. So they're
going to be further of the memory
hierarchy, right? Closer to the
registers almost all in DRAM. Now the
leaves that have all of the actual legal
cases or whatever long document it could
be could be images all of that is
probably going to be on SSDs with that
single 1 millisecond roundtrip at the
end. It doesn't make sense to have all
that puffed into DRAM. This is
fundamentally the cheapest way that you
can run a database period. So for
something like Lora or even web search
which is in hundreds of billion or tens
of billions depending on how much of the
web you've scraped this is fundamentally
the cheapest way to do it. And we have
customers that are indexing massive
parts of the entire web into turbopuffer
which is really also a part of what
legal research is. Full text is also
also really respectful of the memory
hierarchies. The way the text search
works is essentially you can think of it
as a hashmap. You have a big document
and then you take every single one of
the tokens and you put them into the key
in the hashmap. The value in the hashmap
is some set with all of the document IDs
that has that term. So then if you
search for New York population, you're
finding those three places in the
hashmap and then you're taking the three
sets and doing an intersect on the
intersect on the sets. While you're
intersecting, you're also trying to do
some kind of scoring, right? A document
that has York in it is probably more
valuable than a document that has new in
it because York is a more rare word.
When people say BM25, this is the
scoring that they're referring to.
The art of fulltech search is one, we
want to minimize the number of round
trips. So first you download the parts
of the dictionary that are relevant.
Round trip one maybe a round trip one
before that to index into where the
parts of the term terms are. And then
the second round trip is to get these
massive lists. Try to make the list as
small as possible by compressing them.
But also while you're doing the text
search you're trying to minimize the
amount again of memory bandwidth that
you want to intersect these list. You
can probably imagine that at some point
there's a point where you've seen so
many documents with population in York
that have much higher scores that
documents that just have new in it are
irrelevant anymore. This is like a mega
crash course in how text search works.
And counterintuitively to most people,
text search at web scale is more
difficult and more computationally
expensive than doing vector search. I'll
hand it over to you.
Cool. So key learnings from um what you
heard today, retrieval is extremely
important to legora. Um it's key to
legal reasoning. Turbopuffer really
excels when uh for us because it makes
it extremely easy to operate. We have 70
plus tenants. We have 100, we have 200
tenants. You know, if we had to have
separate elastic search databases for
each of these, it would be just hell. Um
but we can do this natively with
turboper with data residency and cmech,
etc., etc. And then it's extremely cost
efficient generally when you have these
types of workflows um or workloads that
we do where there's a long tail of cold
indices basically that you don't need to
query so much and you don't you're sort
of you're okay paying the small latency
cost for it. So now with um turbo buffer
in 4 seconds to go now we can focus on
making lora we can focus on the product
making it really really great and not on
scalability and infra and also David our
CFO is really happy about the cost. So,
it's great. Thanks everyone.
[applause]
>> [music]
