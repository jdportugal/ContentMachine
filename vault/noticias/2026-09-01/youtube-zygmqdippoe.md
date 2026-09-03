---
titulo: 'Agent Spending Without Controls — Rodrigo Coelho & Pranav Maheshwari, Edge & Node'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-01'
url: 'https://www.youtube.com/watch?v=ZyGMqdIpPoE'
thumbnail: media/monitoring/youtube/zygmqdippoe.jpg
descricao: "Pranav Maheshwari ran one prompt in two terminals: find the email address of the head of crypto and blockchain at Mastercard. The terminal without a payment skill file returned the company's email format and an invitation to work the rest out. The one with it paid a fraction of a cent to a metered endpoint and came back with the address, the person's location and their handle. That gap is the whole argument. Most MCP servers are free today, and his claim is that the useful ones will not stay free, which leaves an agent only as capable as the tools it can pay for. Rodrigo Coelho sets up the longer history first: Edge & Node built The Graph, served 1.8 trillion onchain queries, and shipped a query micropayment system in 2021 that cited the HTTP 402 spec years before Coinbase released x402. W..."
resumo: 'The video discusses the concept of agent spending without controls and the need for a new infrastructure layer for AI payments, particularly focusing on the development of micropayment systems for the agent economy. It highlights the evolution of payment systems and the collaboration among various companies to create solutions for efficient transactions in this emerging market.'
tags: {}
fontes:
  - 'https://x.com/rodventures'
  - 'https://x.com/impranavm_'
---

## Descrição

Pranav Maheshwari ran one prompt in two terminals: find the email address of the head of crypto and blockchain at Mastercard. The terminal without a payment skill file returned the company's email format and an invitation to work the rest out. The one with it paid a fraction of a cent to a metered endpoint and came back with the address, the person's location and their handle. That gap is the whole argument. Most MCP servers are free today, and his claim is that the useful ones will not stay free, which leaves an agent only as capable as the tools it can pay for. Rodrigo Coelho sets up the longer history first: Edge & Node built The Graph, served 1.8 trillion onchain queries, and shipped a query micropayment system in 2021 that cited the HTTP 402 spec years before Coinbase released x402.

What stops enterprises adopting any of this, in Coelho's telling, is compliance rather than throughput. Traditional rails assume a human somewhere in the decision loop. Agents transact around the clock at machine speed, and a counterparty arrives as a bare wallet address with no identity attached to it. Somebody carrying a title like chief legal officer has to sign off, knowing that getting sanctions screening wrong carries fines in the billions. The closing demo makes that concrete. Two agents hit a metered scraping service, one from an ordinary wallet and one from a wallet flagged as sanctioned. With screening off, both transactions authorize. Switching screening on leaves the first working and rejects the second as a blocklisted address.

Speaker info:
https://x.com/rodventures
  / rodrigoco  
https://x.com/impranavm_
  / thepranavmaheshwari  

Timestamps:
0:00 - The Graph, and paying for queries before x402 existed
2:03 - Prior art: micropayments, the 402 spec, and joining the foundation
3:52 - Rails built for humans in the loop, not machines
4:49 - Why enterprises stall without a compliance layer
5:42 - A wallet address with no identity behind it
6:37 - The officer who signs off, and the size of the fines
7:35 - Agents are only as good as the tools they can pay for
8:31 - Demo: the same prompt with and without the skill file
12:16 - Demo: an agent buying a gift under a set budget
16:14 - Demo: screening a sanctioned wallet
18:04 - Compliance on, and the transaction rejects

## Transcrição

[music]
All right, thanks for having me. Uh,
today we're going to be speaking about
ampersend and agent spending without
controls, which is the missing
infrastructure layer for AI payments. A
little bit about myself, uh, I'm CEO of
Edge and Node. For those that aren't
aware, we were the team that built the
graph protocol. For those that aren't
aware of that, um it is a blockchain
data indexing protocol. It's been around
since 2018. We have a decentralized
network. Um we've served 1.8 trillion
queries over the years of onchain data
to applications um utilizing blockchain
data. Um I've been a serial entrepreneur
myself and we've incubated a project
within engine node called ampersend uh
in the ejected commerce space which is
what we're going to be talking about
today.
And so we're all aware that payments
have been online for the past 30 years.
Um you know starting in 1981 we had our
first electronic transaction
and fast forward we had a multi-deade
transition. Um, and this is from credit
card payments to online e-commerce
payments. Uh, fast forwarding all the
way to today where we have the rise of
the agent economy and unlocking a new
wave of economic value.
And now we have LLMs and agents
uh that can transact
and we have this underlying payment
infrastructure that's been around for
decades and it's being rebuilt today um
for the agentic economy. we've heard
from many speakers here about how that's
being done. We're going to be discussing
today um our perspective and angle on
it. So we we ourselves at edge and node
were really early into the agentic
commerce world. We actually developed um
a microp payment system for queries back
in 2021. We already referenced the 402
spec back back then in a blog post. And
so back in late 2024, we were looking
into agents and how they would interact
with data, how they would pay for it,
specifically for the graph protocol
itself and making microp payments for
queries. And in our research, we were
kind of looking at how this this could
happen. And then a few weeks later, X42
was released by Coinbase. So we jumped
on immediately with the Coinbase team,
with the Google team, started to
collaborate to the X42 spec. We've
joined the foundation. We've contributed
to the X42 uh spec ourselves leveraging
some of our prior art on the um microp
payment system and uh batching protocol
as part of how it could be utilized to
uh reduce gas fees when you're dealing
in nano payments. Um Circle themselves
came out with their own kind of version
of it called nano payments. Um so we
were also working in similar veins
and so we've seen an explosion of uh
companies emerge solutions technologies
and this kind of agented commerce map
you can see on the screen here that
there's a vast array of companies more
added every day. It's really burgeoning.
Um, and you know, we're just one little
piece in here of people developing, you
know, infrastructure,
um, payment protocols, governance, etc.
And so, traditional payment rails though
were built for humans. Um, you know,
when we're dealing with like financial
institutions specifically, there's kind
of a human in the loop that sets uh the
decision making process of uh whether
that payment is allowed to go through.
But we're dealing with agents that
transact at machine speed and around the
clock. And like I was saying is all
these controls and uh policies and rules
were built for humans and not for uh uh
machines that don't breathe, right? So
we're dealing in um in microscond
machine speed and it's just simply not
going to work in the way it has. And so
we need a new method of doing that. Um
you can see a lot of the transactions uh
exploding. This is sort of over time.
We're seeing um transaction growth over
X42. We're all saying this is coming. It
is still early days. We're uh ourselves
we're seeing a lot of experimentation
kind of with uh with OpenClaw people
making retail payments on an
experimental basis. still not on the
enterprise side as we're talking to uh
many teams. Definitely everyone's
looking at it and building kind of the
infrastructure for when um these use
cases break through. And so that's part
of what we're we'll be displaying today
with Amperson.
But in order for this to really break
through, we need a compliance layer
specifically when you're dealing with
like you know uh almost a quadrillion
dollar industry of the the traditional
financial world. there's a set of rules,
policies, and guidelines that need to be
in place before anything can happen. Uh
beyond just a credit card transaction,
we're dealing with trillions and
trillions of dollars um being transacted
um across the globe. And so we're going
to be honing in a bit on that as part of
the presentation today in the demo
that's coming up. But the compliance
layer is literally like is is this
counterparty um a non-sanction entity?
is this counterparty uh have they been
involved in terrorist activity? Who is
the identity behind this? And when
you're dealing with a Gentic systems,
it's just simply you're presented with
just a wallet address with no kind of
background information on that. And we
need these uh infrastructure layers in
place to um to enable and facilitate
enterprises to feel comfortable to adopt
this um technology. All of these systems
exist in the existing world today. But
uh as we're breaking through into this
um agentic world, we really need to have
all of these pieces uh before uh large
enterprises are going to sign off and uh
give the okay to um implement these. At
the end of the day, there is going to be
a human responsible like a chief legal
officer, a chief policy officer and that
will have to sign off and that person
needs to feel 100% confident that the
systems in place will not allow for um
agents to hallucinate, to go off the
rails, to overspend, um to break policy.
Uh a lot of these compliance issues deal
with large fines into the tens,
hundreds, even billions of dollars. So
these are really important um items and
things to think about from a large
enterprise and financial services
perspective
and governance simply hasn't caught up.
We're again we're early days so we are
part of building up what needs to be put
in place from our view in terms of
allowing this to scale.
And that's why we're building Amperent.
Um, so we're dealing with Agentic
checkouts with a financial harness.
And I'm going to turn it over to my
colleague Prrenoff who is going to show
us a demo. Right.
>> All right. Thanks.
>> All right. Like we said, for agents to
be really useful, we need to give them
tools. Right now they're being used for
coding. If you want to use them to build
better things, you need to give them the
right tools. As of right now, as a AI
industry, we've given them a lot of MCP
servers,
but most of these MP MCP servers are
free of cost. So there are two ways to
make your agents really useful.
One way is you can go to all these
websites like Exop, Firecrawl and so
many more which are getting more and
more bigger with more MCP servers coming
to play and you got to put in your
credit card and you make your agent
better or there is a better way that you
can use an aggregator which has all the
important tools to make your agent super
powerful and that's why we built this
marketplace and all you need is a skill
file installed in your agent. You can
give your cloud code this skill file and
everything else will be done for you. So
I'm going to show you an experiment.
We'll have uh this specific terminal
with the amperson skill file and we'll
have cloud code cloud co-working without
the skill file and you'll see the
difference is immense already. The
reason is that most important MCP
servers are going to be paid and you'll
not put in your credit card to all these
MCP servers. Rather what you'll do is
use you'll use the agentic commerce tool
or a platform or a wallet or a credit
card to make that happen. And that's
what what we enable. So let's just go on
the terminal and install the ampus and
scale file in one set. This is our
website by the way where you can go. You
copy the prompt and just like put in
here. I already have it so I'm not going
to uh sort of maybe it's fine. Uh
install the skill file. And here I'm on
the cloud coork, right? It does not have
the skill file. Fair and square. This
has the skill file. Now what I'll do is
I'll tell them find the email
information of the head of crypto and
blockchain at Mastercard. All right. And
I'll put in the same prompt. Uh I'll put
in the same prompt for cloud cowork.
Remember the only difference is this
specific terminal of mine has the skill
file which has paid MCP tools and this
one does not. And you'll see that it is
not able to like specifically find out,
hey, we're not able to give you exactly
the email, but this is how the email
format is for our master card. And you
can go ahead and find that out. But when
I go to my terminal, it is already be
able to it will already be able to give
me the email specifically the Twitter
and what where is he based and
everything and beyond. This is just one
example of telling you that agents are
getting powerful through MCP. But these
MCPs are not public services. They're
going to be charged and you will not
know that you're interacting with MCPS
or installing it or going on their
websites and getting the credit card.
Rather, you'll just have a scale file
which is a aggregator which can take
care of your payments. in the
background. What happened was if you go
over here, you already can see that we
did a transaction so that we could
enable this surge for you because this
is a paid endpoint that we had to
specifically pay for to get what you
specifically needed. So when we go to
transactions, you should be able to find
that we did a small transaction so that
you would specifically get that specific
information.
uh 30 days on the
maybe we skip to the next demo for now
as you can see this spending happened.
Let's skip to the next demo. I wanted to
show you one more demo. In this what
I've done is let's say I'm not a good
kid which generally I am and I want to
buy a father's day gift for my dad and I
can also make that happen directly via
the terminal. Shopify introduced UCP.
Amazon might be coming up with its own
thing, but what I'll do is I'll give it
a specific command using Shopify UCP.
Buy my father a Father's Day gift. Keep
the gift less than $10. Let's see how
this specific thing rolls.
What uh will happen in this specific
terminal case is that it will already be
able to f locate certain shops online
and find out what things can be given
for father's day gift list them for me
and be able to buy that directly via
ampend wallet that we have created. your
agent is as powerful as the paid MCP
tools that you're connected to it and if
you've given it a payment trail.
So currently it's finding that out for
us.
Okay.
See, this was an authorized transaction
it did for finding me the email of that
specific person at Mastercard, right?
And these are all paid services or I
would have to go on Xi and sort of put
in my email card and get that. So, never
leave your terminal. Just have a payment
gateway and paid MCP tools and make your
agents super super compatible and faster
and give them more uh power.
All right, it's currently finding some
stuff for me. Let's see if it's able to
come up with some recommendations that I
can use and I can hear itself do a
aentic checkout so I can buy something
for my dad for Father's Day.
What's good about this specific thing is
that again and again I don't have to put
in my name, my address, my phone number,
anything. The agent already has the
memory where it knows what do I like,
what do I not like, and it will just
give me specific things that I might
want to buy for him. So, it already
like, you know, total $9. This is what
it is. Confirming the order and it
already placed the bet and it already
took $11 for that
order complete. And here is your
receipt. and my dad gets his $10 key
directly via the agent. So this is just
starting of what we call will be the
future of agent e-iccommerce. It's not
just buying gifts for your uh parents
but rather it's much bigger. MCPs are
becoming the normal norms of how your
agents become super powerful and you
might have already seen that Cloudflare
is opening its gateway through X42 and
agentic payments. If you go on a website
and the agent is crawling that website
then ads are irrelevant. How do people
get paid?
It's via agentic payments where a bot
comes through Cloudflare, pays a
microtransaction and gets all the
information that's needed. So to make
your agent super powerful, you need paid
tools. We are in the era where most MCP
tools are free. That's not going to be
the case in the future. they're going to
get paid and it will be it what your
agent would need is good MCPS or an API
which has an integration of MCPS and a
wallet and this is what I'm trying to
show you.
So the specific bet has already been
placed. I'm going to try to show you the
transaction over here that was done and
it's already settled and I might have
gotten an email where Shopify was able
to buy my dad a gift. Let's see if I
open it and show it to you because it
Yeah, it's okay. You guys are friends.
See over here the order has been placed
and I can already it's already getting
tracked all via the agent terminal. I
didn't have to even skip. Let's go to
the last part which is all of this will
be big if and only if like Rodrigo says
there is compliance. Merchants will not
take payments if they think this order
is being placed by North Korean wallet.
And for that you need the compliance
layer to be fitting in as well. The
seller and the buyer side both need to
have that. And I've created this
specific uh simulation for you in which
we have a good claw that you can let me
just close this right now. So now let's
go to the third one which is compliant
transactions
right for the world and Amazon to accept
agentic payments there needs to be
compliance involved to make sure that
the payment that's been done is not
malicious. So we have a good claw that
I'll spinning up that I'll be spinning
up over here and we have a bad claw. The
thing about bad claw is this is again a
simulation but this is a sanctioned
wallet address that means it has either
interacted with North Korean entities or
we were able to simulate it in a way
that it is flagged right so no amount of
transaction should be able to be done
because it is out of the swift scope of
policies.
So if we go over here uh in this
specific thing and I disable screening
and save it,
my good claw and my B bad claw both
should be able to do transactions.
Just to give you a little bit of
periphery, this is the seller that is
accepting payments. We have a small
service of scraping websites and it
needs to pay 0.1 cent to be able to take
that scraping information. Right now
because we don't have regulations both
these transactions are going through and
you can see it's getting authorized over
here. Right now what I'll do is using
compliance I will enable the specific
feature that we have built with TRM
which scans the wallet and make sure
that all the transactions are compliant
and what it will do is that up till now
everything has been working for good
claw and bad claw but things will stop
working for bad claw because using
compliance we are able to scan that and
we are able to give you see over here
the your your transactions are getting
blocked or rejected.
And I can also show you over here that
rejected and denied. And the reason
behind the denying is because you're in
the block listed wallet addresses.
All to tell you that agentic commerce is
becoming real. Agents need commerce more
than humans need commerce. But it will
not be the same. You will not go through
payment guardrails of Stripe or any
other of these checkout flows, but
agents will have their own proprietary
firms either through wallets or through
their own credit cards. Agents would
need to be superpowered. MCPs will get
paid and more and more paid MCPs will
come to reality to make your agent
successful.
That will only happen and only be
successful in UX terms and more if
you're able to empower your wallet with
paid MCPs and a wallet infrastructure.
If you want to know more about it, come
to Ampend. We'll be here outside
chatting if you have any questions.
Thank you.
>> [music]
