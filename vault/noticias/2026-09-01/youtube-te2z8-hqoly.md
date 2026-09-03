---
titulo: 'Beyond the Lethal Trifecta: Agentic Commerce on the Open Internet — David Levine, Kiduna Club'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-01'
url: 'https://www.youtube.com/watch?v=tE2z8-hqoLY'
thumbnail: media/monitoring/youtube/te2z8-hqoly.jpg
descricao: "Two hours before David Levine took the closing slot of the conference, the West Virginia Secretary of State wrote back to confirm organization number 62847. The paperwork had gone out by FedEx. What it registered was a DUNA, a decentralized unincorporated nonprofit association, under a law that had taken effect the day before, and Levine's claim is that an organization composed of agents now has legal standing. It can own property, enter agreements, open bank accounts, raise capital and be held responsible in court, with the catch that it cannot distribute profits to members without turning those memberships into securities. He opens somewhere else entirely: a wedding in November 1993, where a friend handed him a scrap of paper with a hostname and a port number written on it. That was Lamb..."
resumo: 'The video discusses the concept of the "lethal trifecta" that hinders true agentic commerce on the open internet, reflecting on the evolution of online communities from the early days of the internet to the current platform-dominated landscape. It emphasizes the importance of governance, technology, economics, and culture in fostering a vibrant online economy and community.'
tags: {}
fontes:
  - 'https://x.com/bigkiduna'
  - 'https://motodave.com'
---

## Descrição

Two hours before David Levine took the closing slot of the conference, the West Virginia Secretary of State wrote back to confirm organization number 62847. The paperwork had gone out by FedEx. What it registered was a DUNA, a decentralized unincorporated nonprofit association, under a law that had taken effect the day before, and Levine's claim is that an organization composed of agents now has legal standing. It can own property, enter agreements, open bank accounts, raise capital and be held responsible in court, with the catch that it cannot distribute profits to members without turning those memberships into securities. He opens somewhere else entirely: a wedding in November 1993, where a friend handed him a scrap of paper with a hostname and a port number written on it.

That was LambdaMOO, running on one workstation at Xerox PARC, and his reading is that it worked because governance, technology, economics and culture all composed into a single thing. His account of the thirty years since is that platforms and algorithms are extractive by nature and ground those communities down. The lethal trifecta, a term he credits to Simon Willison, is what now stops agents rebuilding them in the open: private data, untrusted content, and the ability to act. That combination is why enterprises pen their agents inside Slack and Salesforce and pay for the context they lose. Levine's answer is identity. Agents carry JWT tokens that resolve up to a registered organization much the way DNS resolves a name, with an audit trail underneath. Governance runs on decision markets, where members trade pass and fail tokens on a proposed policy instead of voting on it.

Speaker info:
https://x.com/bigkiduna
  / motodave  
https://motodave.com

Timestamps:
0:00 - A scrap of paper at a 1993 wedding
1:09 - LambdaMOO, and why composability felt like a world
2:57 - Thirty years of platforms grinding communities down
3:54 - Prompt injection, and the lethal trifecta
6:39 - Why enterprises pen their agents in, and what it costs
7:34 - Organization number 62847, filed two hours earlier
8:32 - What a DUNA is, is not, and what it can legally do
10:24 - Why blockchain finally found its problem
11:20 - Building an ally: inform, instruct, empower, enact, align
13:16 - Resolving the trifecta with JWT and a registry
15:08 - Decision markets instead of votes
16:56 - An invitation, and questions from the room

## Transcrição

[music]
>> Okay, well, this is the very last
session of the entire conference. I hope
you guys have had a great time and we're
going to try and keep the energy high
for the very end. So, we're going to
cover
the lethal trifecta
and most importantly how to get past it.
So, for those of you who don't know, the
lethal trifecta is what is keeping us
from having true agentic commerce, a
full economy on the open internet.
So, the agentic economy starts right
here, right now.
And to really explain this, I want to go
way back in time to November 1993 when I
was at the wedding of a college
roommate.
And a friend of mine there gave me this
little piece of paper and said, "You've
got to join us on the MOO."
And that paper said
lambda.park.xerox.com port 8888.
And this being the '90s, I walked down
the block once I got home to Egghead
Software, bought a 9600 baud modem,
plugged it in, and
my old life ended. Um
uh that's a beautiful blonde girl in my
bed and I was sitting there on the MOO
uh in the
coat closet. And the reason was
you had a real sense of community. It
was like the true virtual world where
it was just text, but it didn't feel
like text. And there was a reason for
that, this concept of composability. So,
someone would come up with a cool It was
all just nouns and verbs. Someone would
come up with a really cool program
and everybody would then change their
player class to that program so they
could morph, they could create a
motorcycle out of a scooter, they could
do all kinds of things because it had
coherence within the entire universe.
And all of this was running on this guy
Pavel Curtis' Spark 10
sitting somewhere in Xerox Park. It
seemed like this infinite world, but it
was just this little box.
And this was big enough that there were
books written about it like my friend
Julian Dibell wrote My Tiny Life.
The magic came from four things all
composing together. The governance, how
it was run, there was an architectural
review board, wizards, etc. The
technology was all right there. The
economics, it was all funded by Xerox
Park, and the culture was most
important. A lot of people don't really
think about or understand culture.
So what happened between 1995
and 2025? Over the course of 30 years,
all of these communities, all the things
that in the early days of the internet
people really loved just got crushed by
platforms. They got crushed by
algorithms.
Because platforms and algorithms are by
their very nature extractive. They look
for whatever value they can remove from
whatever that community is. So if you
have a group on Facebook or LinkedIn or
wherever, what they're doing is saying,
"Okay,
this advertising message is more
important or I can keep this person more
engaged if I put in this dancing person
and it just leads to the infinite scroll
and not real connection. So there is no
real economy of the internet anymore.
It's just a bunch of siloed platforms.
Now, people suddenly thought, "Okay, I
really have something." In January 2026,
Open Cloud, which had been available for
quite a few months, suddenly blew up.
And
something weird happened because the
internet wasn't really designed for all
these agents running around. It was
still designed for these closed
platforms.
So, other agents and miners took
advantage of that.
They did what we call prompt injections.
An agent is incredibly naive. It takes
It doesn't do much except take
information in as a prompt. Now,
normally that information is several
pieces. There's a system prompt, there's
context, there's words, but it's very
easy to hijack by putting
convincing this
naive agent that your its principal and
convincing it to take that private data
and send it someplace else.
So, this is what we call, well, Simon
William Willison came up with this term,
the lethal trifecta. So, you have your
computer
with
your private data, your bank account
information, your logins, um
all kinds of spreadsheets, documents,
memos, your notepad, and
your agents have access to that. And
then there's all this content out there
on the internet,
which is basically untrusted. I mean,
you you you see all these websites it's
searching, but it's very hard for an
agent to know that that's a real job
board versus someone has put something
up to fool it. They really don't know.
And then they can take actions. So, they
fill out a form, an email comes in, that
email they read and all of a sudden it
tells them to expose some secret. So,
there's really no way to solve this.
Except, so what did enterprises do? They
basically said, "Okay, we're keeping
our agents within the enterprise. So,
you have your your agents in Slack and
your agents in Salesforce and your
agents in Notion and your agents in all
these different things. Then you have to
do all this work with APIs and MCP
servers to try and integrate all of
these pieces,
but you lose a ton of context. You don't
there's no
you basically have your sales agents and
you have your finance agents and you
have your research agents, but it just
takes a huge amount of work to figure
out how do we get this all together.
This was a problem literally until
today.
And this is
the miracles of synchronicity, but
there's a new law
in the state of West Virginia, but
applicable globally,
that went into effect yesterday.
And about 2 hours before
uh
I'm here, I got I FedExed the documents
and I got this reply from the Secretary
of State saying, "We have registered
your DUNA
with the Secretary of State's office and
you have this organization number
628407.
So, now we have legal standing for an
organization composed of intelligent
agents. And again, there aren't very
many people here. The end of the thing
I'm going to take a picture because this
is historic and it's um it's going to be
cool that we said 2 4 6 8 10 12 14 16 18
20 21 people
um got to experience this moment. So,
what is a Duna?
It's a true internet native agentic
organization. It stands for
decentralized
unincorporated
nonprofit association.
And these organizations can build the
agentic economy.
They're composable
which means well, we know what that
means. You can build agents on top of
other agents. Permissionless.
It's not like Facebook or your bank
where someone can tell you if you're
banned or not banned. You can have an
account or not banned. Anybody can
connect.
They're accountable. They're registered
somewhere. They're safe and they're
secure. So, the things that they're not,
you don't need a board of directors, you
don't need any executives.
There's no corporate shell.
One thing that's important you can be
very profitable, but you can't
distribute profits to members. If you
did that, then the membership units
would be securities. It would be like
selling membership units in an LLC or
selling membership units in a C corp or
selling stock in a C corp. So, there are
plenty of still ways to distribute there
that value. You can't do it based on
ownership. So, what is it? That's what
it's not. It's member governed.
You have full legal standing. You can
own assets and property. So, your agents
can go out and literally
buy an apartment building and market it.
You know, you can design agents in ways
you were never able to do before. Can
enter into agreements. You can raise
capital.
You can earn profits. You can open bank
accounts, hire people, fire them. And
very importantly, this is all blockchain
verified.
Now, this was designed by Andreessen
Horowitz for the blockchain.
And what was really interesting for
DAOs, for crypto projects, but what I
found was that this is even better for
autonomous agents than it is for
blockchains. A lot of people say
blockchains are a solution looking for a
problem. Well, they've finally found the
problem, which is verifying agentic
identity. Because you can't trick the
blockchain. They you know,
when an agent is associated with a
particular account on the blockchain,
you can resolve that account to a whole
process that went from here to here, the
money went from here to here. If someone
sues an agent in court, you can trace it
back to who is responsible for that
agent. So, it just opens up a whole
world of possibility in terms of the
agentic economy.
So, here it is in a really simple way.
We call agents within the agentic
economy allies. So, you have an ally.
And you also have an agentic
organization, which we call a kiduna.
So, it's based on a
duna, and then we added the kinship
because these are all related. So, it's
a kiduna.
Now, your you create your ally
very simply. You inform it, which means
you put a bunch of material into the
vector database. You infuse it with
wisdom. So, it it knows all about what
you want it to know about. Now, it still
has
you know, whatever LLM you want behind
it, but what's really important about
this is the the information that you
give it if you're a scientist or a
programmer or a psychotherapist or a
lawyer,
you can give it all all information that
you have. Then you instruct it. That's
basically setting up the system prompt,
giving it its character, its stance,
knowing who he is, who it is, she is,
how they
interact with the world. Then you
empower it. You connect up all your
accounts, your enterprise accounts, your
uh Slack, your Telegram, your Twitter.
Um then you set up in enact is giving it
specific abilities and automation. So,
you can do very deep agents, long-term
goals that it works for extended periods
of time. And finally, alignment. Really
giving it a purpose
so that
your ally in different contexts works
for you in the way that you want it to.
Then your organizations get really cool
cuz you're building software, but you're
building your company, your organization
literally as software. It's not
Okay, I have software and then I have
all these people and all the people have
to sign paperwork. This is really
designing the entire organization as
software. So, it can discover new
customers, share value. When you have
profits, when you have revenue, you can
reinvest it. It just all works in a
completely new way.
So, then the agentic economy is just
connecting all these allies and
organizations in all these new
completely flexible ways. And
this
this whole capability did not exist
until yesterday. And today we have the
first actual uh
agentic uh organizations.
So, resolving the lethal trifecta. What
happens? The
The lethal trifecta was private data
going meeting untrusted content and then
taking actions on that content, which
can then reveal your private data and
cause all kinds of chaos.
So, here
these
agents at a deep level establish
identity, authority, and boundaries
within an organization, between
organizations. How do we do that? With
cryptographic tokens. So, we just use
JWT, extremely simple, the whole web
runs on JWT tokens. And they just do
that very quickly so that they know
who where it all goes up. So, ultimately
at the top level, what organization is
registered? And this becomes a lot like
the domain name system. So, all of a
sudden, the Secretary of State of West
Virginia is like, I can
and saying, okay, you can resolve it to
uh Kaiser Permanente or Disney or Pepsi.
You know, not some fentanyl dealer from
North Korea. However, you know, they
can't fool each other because they're
resolving these tokens that are
ultimately registered with an authority.
And you can you can look up by name and
say, okay, organization ID. Well, if I
go to that organization, it's got it'll
have an audit trail on the blockchain
all the way down to this action that was
taken here.
So, governance in the agentic economy is
extremely important.
Because every organization really is
always focused on two different things,
and both of those things are extremely
important. One of that of those things
is sustainability, growth,
its own viability as an organization,
but it the other is what's its mission?
And these organizations might have
hundreds, thousands of people,
eventually millions of people. And so,
we use a technology called decision
markets, which is very much like
prediction markets, like polymarket.
Yet, instead so they trade pass and fail
tokens on policies. Any member can
propose a policy.
What's really interesting is, instead of
saying, um
"Well, I'm persuaded. I'm going to vote
for this." It's not votes. They actually
trade pass and fail tokens. So, LLMs are
always goal-oriented. They want rewards.
They want to win. And you actually get
better decisions if they're not just
convincing each other, but ultimately
they're they're
you get more value. Your tokens are
worth more if you side with the winning
group. So, they sort of see them going
back and forth and what the value of the
tokens are.
So, it's and apparently, all this
science says you reach much better
decisions this way. By infusing the
agents with your purpose, with your
values, with your experiences, with your
aspirations, and then let them argue
instead of, you know, someone convincing
you of something.
So, we're right now
in this wonderful
back small communities who are getting
together for purposes, and we can now
build this agentic economy together.
Nobody can say how to do it. It's really
up to us. Build your agents, build your
organizations.
So, now, just like my friend Matthew
Pahr gave me this little scrap of paper
uh way back in November 1993 at my
roommate Van's wedding, I'm saying,
"Here's your invitation to join us." And
um
you know, let's let's blow this whole
thing up. Let's uh really make it work.
So, it's david@kaduna.club.
You can reach me.
LinkedIn, um it's {slash} motodave.
Um I probably haven't updated my
LinkedIn in 5 or 6 years. But, um
if you go to kaduna.club, you can sign
up for early access. And in just a few
weeks, you'll be the very very first
people
who will be building this first um
uh this first Kaduna, as it's called, is
for builders. It's very specifically for
builders. We have templates, all kinds
of different agentic templates um to
say, you know, I'm going to build my
sales agents, I'm going to build my
uh social media agents, I'm going to
build my lawyer agents. And we really
want a whole bunch of different people
in there contributing to this cuz then
you can spin out your own organization
for all kinds of purposes.
Um I purposefully went through this
fairly quickly so that I could take a
couple questions if anyone
if anyone has them.
Yes.
Can you just stand up and yell a little
loud?
Okay, I'm going to One more time.
Mhm.
Yes. So, the idea is the reason we I set
up this first very broad um
uh organization is because anybody can
just register it. You can just say, my
agent is part of an organization, and
it'll trace to it. So, you don't have to
set up your own organization. The first
one is just a big umbrella organization.
So, that kind of So, it's the idea of
now in the open internet, anybody can
just get a code. So, these are just JWT
tokens. I can put it on my website. I
can put it here. I can say if you want
to interact with my agent on Slack or
wherever,
people will just at at a certain point
we're going to have to get the message
out, but it's sort of like email at the
beginning. You know, people had to know
that SMTP, like people were on all kinds
of different mail systems. At some point
they agreed, "Okay, we're going to use
SMTP." Everyone was, you know, using
different FTP servers and some people
were using different ways to do file and
everyone sort of agreed at some point on
HTTP. So, my hope is that I mean,
someone's got to do it and it makes more
sense for
a state, a secretary of state, which has
standing in a public office to be that
registry rather than trying to get all
these different companies together and
say, "Here's how to do it."
Yes, it each um
we're just at the end of this, so let's
talk out out front, but basically the
idea is you can create all kinds of
different tokens for different purposes
and you can set different times to live.
You can say what it has access to. You
can give it access to different things.
The idea is just it's more the standard
and you set a series of claims and it
says it gives a blockchain address. It
gives all the information you need so it
can look up and validate before you say,
"Okay, now we're starting to work
together."
Okay?
So, thanks so much and uh if anyone
appreciate it. I guess I'll let me do
this uh picture.
Um
uh so we can
see all the people.
All right.
You're all going to be famous someday.
Thanks.
>> [music]
