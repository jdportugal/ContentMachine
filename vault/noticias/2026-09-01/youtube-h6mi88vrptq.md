---
titulo: 'x402 isn’t good (yet) — Jan Curn, Apify'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-01'
url: 'https://www.youtube.com/watch?v=h6mi88VrPtQ'
thumbnail: media/monitoring/youtube/h6mi88vrptq.jpg
descricao: "Between the moment an x402 server verifies a payment signature and the moment it settles on the blockchain, nothing stops the buyer spending that money somewhere else. Jan Curn's point is that a client can mint a thousand signatures against one wallet, so any seller starting real work on the strength of a verification is exposed. The workaround is to do the work only after settlement, fine for a fixed price API call and awkward for anything slower. Curn is bullish enough on x402 to have shipped it two days before this talk, adding 20,000 Apify tools to a marketplace that previously carried about 2,000, and critical enough to spend the session on what it still gets wrong. He pitches it as a sequel to a talk here last year arguing that MCP was not good yet. The gaps compound. x402 wants HTTP..."
resumo: "The video discusses Jan Curn's evaluation of the X 402 technology, highlighting its current limitations and rough edges, while drawing parallels to the earlier critique of MCP technology. Curn expresses optimism that, like MCP, X 402 can evolve into a robust integration tool within the AI marketplace."
tags: {}
fontes:
  - 'https://x.com/jancurn'
  - 'https://apify.com/jancurn'
---

## Descrição

Between the moment an x402 server verifies a payment signature and the moment it settles on the blockchain, nothing stops the buyer spending that money somewhere else. Jan Curn's point is that a client can mint a thousand signatures against one wallet, so any seller starting real work on the strength of a verification is exposed. The workaround is to do the work only after settlement, fine for a fixed price API call and awkward for anything slower. Curn is bullish enough on x402 to have shipped it two days before this talk, adding 20,000 Apify tools to a marketplace that previously carried about 2,000, and critical enough to spend the session on what it still gets wrong. He pitches it as a sequel to a talk here last year arguing that MCP was not good yet.

The gaps compound. x402 wants HTTP 402 as the server's first response and MCP wants 401, and since one response cannot be both, companies stand up a second hostname purely for payments. Curn calls that an antipattern and asks how anyone would feel about a separate Amazon for every credit card. The original exact scheme charges a fixed fee per call, which does not fit tools that run for seconds or for hours, and the up to scheme meant to fix metered billing left the double spending window open anyway. Apify settled on charging in full and refunding the remainder, at the cost of a second chain transaction and a trust assumption pointing the wrong way. He is watching batch settlement, and has shipped a markdown page an agent reads to buy a prepaid token, deliberately not an API.

Speaker info:
https://x.com/jancurn
  / jancurn  
https://apify.com/jancurn

Timestamps:
0:00 - A callback to "MCP isn't good yet"
2:02 - Apify's 45,000 tools, and 10x'ing the x402 catalog
4:47 - The standards pileup, from L402 to Agent Pay
5:39 - Why crypto fits agentic payments, disputes included
8:25 - A 30 year old status code finally picked up
9:19 - The x402 flow, and the double spending window
11:10 - When x402 wants a 402 and MCP wants a 401
12:59 - Exact, then "up to", and what it still did not fix
13:55 - Charging in full, refunding the rest, and batch settlement
16:38 - AGI, an agent general interface
19:26 - Where the volume actually is today

## Transcrição

[music]
>> Hello everyone.
Uh last year here at AI Engineer World
Fair, uh David Cramer from Sentry had a
mildly provocative talk called MCP isn't
good yet. And back then, MCP was the new
kid on the block, right? Uh it was like
a lot of hype around it. People were,
you know, were excited about it. But
also like it wasn't really developed
back then very much and it was very
clunky. And in this talk, David argued,
"Hey, like this technology is cool, but
it has like a lot of rough edges, right?
So just you know, go play with it, but
you know, have your expectations low,
right?"
By the way,
they went on and actually built one of
the best MCP servers on the market,
right? Like Sentry MCP is is is really
like very well-designed server, like
nice nice design and so on.
And actually over the the last year, uh
MCP became like a standard that's
actually widely adopted, you know,
across the industry. Like the top AI
engines like Claude and ChatGPT,
actually uh Claude offers MCP
connectors, right? So you can plug tools
into your AI agents. Uh
uh ChatGPT calls it MC calls it apps,
but you can also like, you know,
directory of different services you can
plug into your Claude. And it actually
became a standard thing for
agent-to-agent interaction, right? And
uh
uh despite a little little hate also
about MCP, you know, I have yet to see
CI connectors in any of these agents,
right? So MCP won.
And so, inspired by David's talk, uh
today uh I have a similar talk uh which
is called X 402 isn't good
yet.
And in that talk, I would like to argue,
you know, that uh while X 402 is very
exciting technology, it has also still
some rough edges, you know. And perhaps
if I do it as well, we'll also build one
of the best integrations with our X 402
on the market like Sentry did with MCP.
My name is Jan Černý. I'm the founder
and CEO of Apify. And uh for those who
don't know, Apify is the largest
marketplace of tools for AI.
Uh
we have about 45,000 of these tools. Uh
we call them actors.
And they are spending use cases like,
you know, extraction of data from social
media sites, e-commerce, hospitality,
travel, search engines, maps, but over
time also like
AI agents or agentic use cases, you
know, different automations and so on,
right?
And some of these tools, some of these
actors are built by our community, some
are built by us, and our community is is
making now more than $1 million per
month on on payouts by selling these
tools, right? So
they build the tools, we sell them to to
to users, and you know, we pass the
money to them. And so it's a thriving
marketplace.
And I guess by now everybody understands
like why we are so excited about agentic
payments, right? Because we really want
our tools to be easily accessible to
agents, like wherever they are, with
whatever protocol is out there.
Uh just 2 days ago we launched uh
together with with Coinbase our our XYO
2 integration.
Uh I would say the the launch went
pretty viral. We got like 1 million
views.
Uh
and
before before this launch, there were
about like 2,000 tools available on the
on the agentic uh
market uh basically on XYO XYO 2.
We brought another 20,000 tools. So
basically we 10X 10X the size of the of
the agentic market uh on XYO XYO 2. So
this is very exciting for us and I I we
we believe also for the community.
And actually we're very excited about
this topic like long term. So actually
last year here at AI Engineer World's
Fair, I was the only one talking about
the agentic commerce and agentic economy
in general.
And really argued that that like in a
couple of years, like the most most of
the economic economic activity in the
world will be done autonomously between
agents.
And actually it looks like uh
uh
really
uh
it picked up. Uh I think like now
everybody understands that agents, in
order to get work done or like longer
jobs without human intervention, they
will actually need to have budget as
well, right? It's It's not like
you can do a lot of things in this world
without without money. And once agents,
you know, can be trusted with money,
they will complete like far longer and,
you know, more complex tasks than they
can do now. So, obviously, a lot of
people understand this
across the industry.
So, over the past year or so, we saw a
lot of new standards or agentic payments
protocol coming to the market because,
obviously, every player in finance or or
payments is very excited about this
opportunity because everybody wants to
get a part of this of this like huge
future
agentic economy and
and transaction volume.
So, first was L402.
It was like in mid 2020.
But then, MasterCard Agent Pay, we have
X242 like Coinbase in my last year, KY
Pay from Skyfire with Visa,
API 2 by Google, ACP by OpenAI and
Stripe, Tab by Visa,
UCP by Google and Shopify, ACTP by
Alipay, MPP by Stripe and Tempol, AMP by
Alipay, Apple by UnionPay, APP by OKX,
and finally, Agent Pay for Machines by
MasterCard. You can see this is really
becoming a heated battleground
and for the future of the agentic
commerce.
And [snorts]
it's really hard to sort of keep track
of all the standards
and all the technologies. We're trying
to.
But, I would say for crypto world,
actually, Swix asked all the speakers
not to use sloppy AI-generated images in
presentations, but I couldn't resist
myself here. Um
I think for crypto world, like the
agentic commerce has been like super
exciting news like because finally,
there is like really solid use case for
crypto except for like trading trading
and gambling, buying illegal substances
on marketplaces and hiding money away
from your spouses, right? So, finally,
agentic commerce really brings the like
long-sought like use case for crypto
that that I actually think is is pretty
solid. And I'm not like a crypto bro
myself. I I'm not holding anything.
Actually, I was fairly skeptical to
crypto all the time.
But I feel that crypto is really well
suited for agentic commerce or agentic
uh payments. Why? Because the
traditional payment methods
uh designed for people are super
expensive, right? And you know,
uh like credit cards, PayPal, or you
know, ACH and bank debit, they cannot be
used for microtransactions. They are
just very ineffective uh as we saw in
previous presentation as well.
So, but there's another problem.
There are buyer disputes. Like anybody
who's selling things online,
you know that uh there are some people
who sort of like buy certain services
from your website and then dispute the
payment and ask for refund, you know, or
or dispute it through their bank and you
have to pay for that. Like
in like traditional like well, human
economy,
there are some trust signals you can you
you can do with the credit credit cards,
you know, uh
like Stripe and and others have
different services to prevent fraud and
so on.
But in agentic interaction, like you
really have no idea who the agent is.
Like there's no agent identity. I mean,
there are some standards being
developed, but it's really not clear uh
who are you transacting with. So, you
just can't allow
them to dispute the payments. You really
need it it needs to be a one-way
transaction and it needs to be like safe
for you.
Right? So, I think uh
crypto is is is a super position for
that. But also, there's important part
uh
crypto, if done well, is like truly
decentralized blockchain where where no
company has like majority of of, you
know, of the of the of the of the vote
in the network,
it can be really decentralized and it
can be like a public standard, you know,
not owned by a single company who, for
example, like Visa or MasterCard, sort
of would abuse their dominant power, you
know, to to extract fees from the from
the from the system.
So, I am I'm very very bullish on crypto
in this in this space and uh
there are basically like currently two
largest uh crypto payment uh providers
for any payments. One is XRO2 from from
Coinbase and second was in this MPP,
machine payments protocol from Stripe.
Looking at the stats uh just yesterday,
uh XRO2 is like 20 times larger in the
number of transactions and the the the
the volume.
Uh so, obviously, we went first with
implementation of XRO2, but we added in
MPP in the process as well. And today,
I'm going to share like a bit of our
experience. This was also presented in
the slide before. Uh
XRO2 builds on the
uh status code introduced like more like
almost 30 years ago in the original HTTP
specification uh called 402 payment
required. So, this status code was
waiting for 30 years patiently
for someone to pick it up. And
obviously, Coinbase took that
opportunity because obviously, it's
great for marketing, you know, we're
finally make uh internet money work.
It's awesome.
So, how it works? I'll just go quickly
because we saw it in the last
presentation as well. So, first, the
client initiates like calls server with
some API request. The server responds
and actually, this is important part.
Specification enforces the server to
respond 402 payment required. There is
no other way to do that. It it needs to
use the 402.
Then, you know, the client creates a
signature uh by basically withdrawing
money from the wallet uh or you know,
allocating the budget from from from the
wallet. Sends uh the signature to the to
the server.
Oh, the server verifies with a
facilitator, which can be like Coinbase,
for example, whether the transaction is
correct. It gets uh confirmation that
the transaction is is right.
And then, it's supposed to do the work,
right? But there is a problem there.
I'll get to that uh in a second.
And after the work is done, uh
you send a transaction you send a
request to the facilitator to settle,
which means like, you know, physically
transfer the money to to your own
wallet.
And then facilitator performs operation
on the blockchain, transaction is
confirmed, everything is settled, all
good. But there is a problem here.
In this moment, like until until the
transaction is is actually submitted to
the blockchain,
the buyer can use the same wallet and
same money to other transaction.
Basically, there is like nothing
preventing the the client from double
spending. So, you know, uh
it can just create like 1,000 signatures
like that, send it to 1,000 requests,
and then, you know, like um
maybe it will not get the result, but
let's say if it's like a simple API
call,
you know, where there is like a like
zero marginal cost for you as a
provider, you can do it. You can do the
work, you know, before uh you settle.
But imagine there is like some
non-trivial work, or maybe you have to
pay external service, and then you
realize, "Oh, the money is gone, right?"
Uh
the the the client skipped out on the
bill, which is not great. So,
there is a work around that. You can
actually uh just do the work after the
transaction is settled. You just need to
make sure you actually get the work
done, you don't fail, and so on, because
then the clients would be pretty angry,
I guess.
But there is a work around for this, and
then you send like 200 OK and payment
response, all good.
So,
there is a problem though. Like um
as I showed before, like uh the standard
requires HTTP 402 uh as a first response
from the server.
But MCP Alt requires HTTP 401.
So, there are like two conflicts in the
standards, and you know, each of them
are actually enforcing it, and you
cannot like sort of like, you know,
return two error codes or two status
codes at the same time.
So, how do you companies resolve that?
Well, quite often they create a
dedicated like hostname host uh like
let's say x402.alchema.com
to serve just the agentic payments
gateway. So, they implement basically a
new API host just to serve the the HTTP
payments and then they have like
Sorry, mcp.alchemy.com
and maybe mpp.alchemy.com, right? But it
sounds like anti-pattern. Why would you
have to duplicate like your
API host for different payment
providers? It's like
imagine like you had to like amazon.com
for different like credit cards. You had
like 20 different Amazons. Like it
doesn't make sense, right? So
So,
I think it is like one of the
weaknesses. It's like I I understand
like using HTTP 402 is great for
marketing, but
I think there should be in protocol some
way to circumvent that and use use just
purely headers, you know? So for
example, the payment payment required
header without the the the status code.
And then
originally when X-Request-ID was
created, it was designed for
like fixed payments. Uh
The the first payment scheme they
introduced
was called exact and it's like for
simple API calls. Like there's like a
fixed fee per transaction, fixed fee per
per call, which is great for I don't
know simple APIs. But unfortunately,
Apify actors are tools on the
marketplace. They typically perform bad
jobs and you know, they can run for, you
know, a few seconds, but they can also
run for a few hours and consume a lot of
resources on the way. They are like
typically like built
as-you-go kind of like meter billing.
So how to do that? Like how to put this
on X-Request-ID 2? So in May 2025,
Coinbase introduced
X-Request-ID 2 with exact payment
scheme. In December 2025, they announced
the version two of the protocol, which
was promising the up to payments payment
scheme to kind of fix this problem of
like meter billing.
But it it took actually another like
half a year almost
to release the the up to finally. It was
just like two or three months ago. So we
were super excited about that. We were
like finally
we can make this work for our services.
But then we realized actually the same
double spending problem which is on on
on exact is also with with up to. I
mean, up to it was just a small sort of
like improvement to the protocol where
when you, you know, call the tool, you
say, "Oh, my maximum is $5." And then
the the the server can charge anything
up to $5. But it doesn't prevent the
double-spending problem. So, basically,
we are sort of stuck again. So, you
know, we have to go back to the drawing
boards and figure like how to do that.
And so, one way we we did it was we just
use exact payment scheme to kind of like
fixed
uh like charge a fixed payment. And then
after the the the the job is done, we we
would like refund the the the wallet
with the with the leftover money,
basically, the money that
that wasn't spent. I mean, that worked.
But uh
so, you charge, you do the work, you
refund the remainder. But that means
like there's suddenly like two
blockchain transactions. That means like
uh
there's a time, you know, to to settle
those transactions. There might be like
some fees associated with that and so
on.
And also, the clients will need to trust
the server
to kind of like, "Hey,
I give you the money, but you give it to
me back, right?" But I think that that
that's that's a minor issue. But it just
feels somehow clunky. Like, why would we
need to do these workarounds, you know?
This protocol should support these
things out of the box.
So, just 2 months ago, uh
Coinbase introduced like new payment
scheme uh which is called batch
settlement, which looks very promising.
We haven't implemented it yet, so we'll
report soon on that. But basically, just
quickly, it works like So, first, the
clients like deposit some money. So,
basically,
it's actually like like real transaction
on blockchain using some uh Ethereum
virtual machine black magic. Basically,
uh the money is put in the escrow.
And then
the the client gets back a voucher like
from the server. Say, "Hey, here is some
cryptographic voucher, and you can use
it to sign like microtransaction as part
of this batch."
And then
like the client sends sends requests,
for example, like API calls or, you
know, for like individual tokens,
whatever, and uh use this like passes
this voucher to sign the those like a
micro payments. But this transactions
are the basically off-chain. They are
just like sort of like cryptographically
guaranteed locally, but you don't need
to like write this like to the to the to
the blockchain, which again is
inefficient, slow, you know, uh
expensive. And then at some point,
you're like, "Hey, I I I accumulated a
lot of these like micro transactions, so
I just like settle them in batch." And
then like you basically perform the
transaction on blockchain. And then you
can do this a couple of times and
eventually refund the rest of the money
like sort of like release the escrow,
right? So actually this looks really
cool, very exciting. We'll
we're currently working on implementing
it now, so we'll see.
But so how did we resolve like all this
to make it work, right? So we didn't
really want to create like new new
endpoints for different payment
services.
We didn't want to like the like hack the
variable usage, you know, to our
services. And also, we really don't want
to like
tweak too much our API because we have
like tens of thousands of customers
depending on that API. So we just can't
like sort of like, you know, do whatever
like new
agentic payment standard is out there,
just implement it right away.
So so kind of to fix these problems,
let me introduce you
let me introduce our newest service on
Appify, which is called agi.appify.com.
And AGI
is not
what you mean it is. It's actually it
stands for agent general interface. So
instead of like application programming
interface, we have agent general
interface that can change anytime but
because it's used by agents, so they're
flexible, right? And
on AGI, it's just a simple website with
like one markdown document. It's not
designed for people, so it kind of looks
ugly, but it's okay. And there's
instructions for agents like, "Hey, how
can you actually
buy things on Appify yourself through
Xapo 2, MPP?" We can iterate quickly on
this on this on this website or on this
service because, you know, agents can
pick up new version. It's not like fixed
like API
needs to be basically, you know,
backwards compatible all the time.
And the way it works is like the agent
comes to agent.apify.com
gets basically can buy a prepaid token.
So basically they say like, "Hey, here's
$5. Give me Give me a Apify token." We
give them like Apify token back and then
they can use the Apify token
through our normal API or through MCP to
run our jobs and services, you know,
normally.
And it it works pretty well.
There is like no skill required. You
just like point your agent to
agent.apify.com. It picks it up.
And I have here like a short demo.
We're running out of time. So this is
just like example how it works like.
Actually, the X road ecosystem is like
really early so that even, you know,
we have to build our own like local
wallet
tool which you can like create like
local like cryptographic key to charge,
you know, with money and show QR code.
Like I mean these things are still not
not not not existing. I mean the
ecosystem is like super super early.
So
I have local wallet with $10. Then I
call like IGI.apify.com
allocate like token with $1. I get back
402 payment required. So there is like
this like super long like payment
required signature.
I use Apify
I use the I use our MCPC just like MCPC
client to sign
the payment and then I can send the
Oh.
Oh oh oh.
This demo didn't work as expected.
Here we go.
Mhm.
Well, anyway, I have 1 minute left.
>> [laughter]
>> Demo more come later. Sorry about that.
So, to conclude my presentation, like
really like try it out and like try to
build try try to use it like actually I
try I tried to use it for the first time
like a couple of weeks ago because I
always thought that oh my god it's
somehow complicated, you know, there is
a lot of this like weird crypto lingo,
you know, like I have I like I don't
know what it what it means. But actually
it's really really simple to to play
with it. You can
you know, get to get up and running in
like 10 minutes. And uh
it's still super early. Uh
I think there's like $1 million
transaction volume like per month. Like
this is nothing, right? Like the economy
is much much bigger. But I think it will
sooner ramp up. And I think
once uh really this like era of like uh
token subsidies will end, I mean when
the when you really will have to pay for
the tokens, you know, that your agents
consume, I think suddenly this decision
whether to build or buy will, you know,
make more economic sense actually to buy
external, you know, services for a lot
of things rather than build from
scratch. And I think at this time really
the agentic payments will explode and uh
very soon uh um
uh the agentic commerce might uh
overtake the normal commerce.
Thanks a lot for your attention and
please come uh to join us uh
in our booth. And actually I have
another talk coming up in 2 hours uh
about MCP and CLI, so you can check that
one as well in Expo stage two.
Thank you. [applause]
