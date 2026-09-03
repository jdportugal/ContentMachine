---
titulo: 'Your Agent Just Authorized What?! — Jay Mok & Ben Coumes, Paypal'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-01'
url: 'https://www.youtube.com/watch?v=vGn6N4-bxBY'
thumbnail: media/monitoring/youtube/vgn6n4-bxby.jpg
descricao: 'A PayPal order has always been synchronous. You find the item, you open the app, you approve it, it is done. The approval token demonstrated here inverts that: the user approves before the agent has found an item or picked a merchant, and PayPal returns a JSON payload carrying the amount, the expiry, and the merchant the agent is permitted to transact with. It was days from shipping when this was recorded. That inversion is the concrete end of a broader argument about agent authorization, built on three questions any such system has to answer. Did the human authorize this? Is it allowed right now, in this scope? And can we prove it later? The answers, the talk argues, depend entirely on how high the stakes are and on whether the two parties have ever met. The rest maps those questions onto...'
resumo: 'The video discusses the importance of agent authorization in payments, focusing on how to ensure that transactions are properly authorized by humans, the context of those transactions, and the ability to prove authorization later. It emphasizes the need to consider whether the scenario is low or high stakes and whether the ecosystem is open or closed.'
tags: {}
fontes: {}
---

## Descrição

A PayPal order has always been synchronous. You find the item, you open the app, you approve it, it is done. The approval token demonstrated here inverts that: the user approves before the agent has found an item or picked a merchant, and PayPal returns a JSON payload carrying the amount, the expiry, and the merchant the agent is permitted to transact with. It was days from shipping when this was recorded. That inversion is the concrete end of a broader argument about agent authorization, built on three questions any such system has to answer. Did the human authorize this? Is it allowed right now, in this scope? And can we prove it later? The answers, the talk argues, depend entirely on how high the stakes are and on whether the two parties have ever met.

The rest maps those questions onto a ladder. At the bottom is a coding agent, where consent happens once when tools are connected, permission is granted per tool as allow, ask or deny, and ordinary logs are proof enough because the actions can be reverted. In the middle is money moving between parties who already know each other through a shared vault and OAuth scopes, where a mandate carries the amount and the scope and existing transaction logs settle disputes. At the top is the case nobody has running in production yet: an agent transacting autonomously with a counterparty it has never met. The proposal there is a layered selective disclosure JWT, so a merchant can verify the checkout and a processor can verify the payment mandate without either knowing the other.

Timestamps:
0:00 - Terminator, and the agent that empties your wallet
1:26 - Three questions any agent authorization has to answer
2:49 - Stakes, counterparties, and the badge analogy
4:12 - Low stakes: a coding agent with allow, ask and deny
6:15 - Medium stakes: a shared vault and OAuth scopes
9:45 - High stakes: counterparties who have never met
10:29 - A layered selective disclosure JWT
12:30 - The approval token, and inverting the order flow
14:31 - Why the ladder applies beyond payments

## Transcrição

[music]
>> Hello everybody. How are you doing?
Um
Does anybody remember the movie
Terminator?
Uh anyways, it's one of my favorite uh
movies when I was growing up as a kid.
And it imagines a world where the
machines have taken over, right? And uh
uh the nightmare scenario here though in
in 2026 is not that the machines are or
the agents are launching nukes, but
rather
uh they've uh taken your wallet and
they've gone on a shopping spree.
And they buy like a bunch of crypto and
new bunch of bunch of spanks for you.
Um
but the basically today we're talking
about how we safeguard against that and
uh and uh hopefully we can kind of share
a a mental model that you can use when
you're thinking about agent
authorization.
Uh my name is uh Jay Mock. I'm a product
manager over at PayPal in agentic
payments. And
>> Hi everyone. I'm Ben Cooms. I am a staff
software engineer on the
payment PayPal enterprise payments team.
>> And together we're going to share kind
of like some knowledge with you. Um so
hopefully
uh you find it helpful. Okay.
So uh the the key questions that we kind
of like uh start off with is uh in terms
of like agent authorization is uh did
the human authorize this?
Um is this allowed right now in this
scope and can we prove it later, right?
And we we kind of like try to make it
general, but in our world of payments,
yeah, did the human authorize this? Uh
that could be like a passkey or of of
that nature. Uh is this allowed right
now in this scope? It's generally going
to be a time bound um
you know you know token.
Um and an amount and possibly could be
identified like a merchant or a
the actual product intent. Lastly, can
we prove it later? This is like if
something goes wrong, right? And in our
world of payments it's generally has to
do with like the disputes and
in that case and how do you can you
prove that
you know the the human generally
authorized that transaction. Right? Um
But we think the way that you actually
answer these three questions is really
dependent on the context. You know,
context is a overused term, but in this
case what we what we mean is
um
you know, is it a low stakes or high
stakes kind of
scenario?
Um and
is this a kind of like open ecosystem or
closed ecosystem? Do the parties like
know each other? You know, people use
the term KYA a lot. Know your agent, but
you know, what we think about in this
scenario is is is really about is it
like an open or closed ecosystem, right?
And in a payments context it could be
like, "Hey, you know, ChatGPT or Gemini,
right?" That's like kind of like a more
of like a closed ecosystem because you
know, the those agents know the merchant
generally. Um
I like to use a an analogy.
I like analogies. And the analogy I I
like to use is kind of like the you
know, badging into work. You badge into
work in the front desk. You basically
are are then led into the building or
you know, let's say it's a set of
buildings. You don't need to like badge
in every single time to every other or
for every single room because you're
already within that trusted boundary,
right? So then when you meet someone in
within that within your your office
building, uh, you kind of have some
element of trust, or hopefully you have
some element of trust, uh, because
you're both, uh, employees at the same
company that badged in, right? So, um,
that's kind of like the analogy I may I
may use later in the presentation.
Okay, so based on those key questions,
we kind of think about like, "Hey,
what's the mental model that we can
build off of this, right?" And we have
this like stakes and evidence matrix,
and we're going to talk about, uh, these
three different scenarios.
Um, and so, uh, we're going to first uh,
and you'll see at the top it's kind of
like the stakes and counterparty part
that I was just talking about the
context, right? Counterparty is like the
open or closed ecosystem. And then
authority and like evidence is really
about how you answer those those those
three questions I had shared in in the
prior slide, right? Um, so we'll talk a
little bit first about like cloud code
since that's what most people are very
familiar with, and, uh, basically, you
know, when you as a human you're going,
you know, using your cloud code, uh, you
know, you might be, uh, then setting up,
uh, your your connectors with your
GitHub or or, um, you know, Jira or
whatever, uh, linear or whatever uh,
tool you're using. And, um, you know,
you're as part of that process you're
kind of like authenticating, so that's
how you kind of like get that that human
authorization and consent um, with those
those applications and to for the for
cloud container act with them.
Um, in terms of the, uh,
actual like, uh, scopes, right? The the
example here would be that about, you
know, cloud's like, uh, tool
permissions. Like people are very
familiar probably with, uh, the fact
that you can allow cloud cloud to to use
certain tools, um, uh, deny or ask cloud
to ask you, uh, before for before doing
something, right? And then in terms of,
uh, the action of like a cloud, uh, we
generally think, um, because it's a kind
of closed ecosystem and it's like you're
coding, uh, the stakes are relatively
low here. And so in terms of the
evidence or proof, you don't really need
to have like that cryptographic proof
um, at that point in time. You can kind
of just look at like system logs in
order to to or you have the ability to
just revert revert your changes, right?
So, that's kind of like an example of
applying like this mental model
and using cloud code in terms of that
scenario.
Okay, so the next
example we're going to talk about is
a more medium stakes
scenario and why
why we're calling this medium stakes
even though it's within a known or kind
of closed ecosystem is because it has to
do with money and and payments. And so
that's like the shared vault and OAuth
scope example.
So, in this example where the use case
is is like hey you're like a let's say
a merchant or Trip Advisor, right? And
you have
a travel travel company and you have a
lot of great content that you want to
monetize. It could be occupancy data, it
could be like reviews, what have you.
And you have a new customer now. You
have like a trap like travel agents or
like you know agents that that are buyer
agents that are are coming to you and
you want to be able to monetize
monetize your data, right? Through
machine payments.
So, we work with a partner and Never
mind to be able to enable that that that
use case and leveraging our
they're leveraging our infrastructure.
Right? So, there is two pieces of
infrastructure that they
that I like to kind of to call out or
primitives that they use that
as part of the Braintree or PayPal
enterprise
infrastructure. One is like the vault,
right? And the vault by itself, which is
storing all these like
payment credentials on behalf of the
on behalf of the buyer agents,
on itself doesn't really do much, but in
order to create
what nevermind creates is a a more
eco closed ecosystem, they then off
you're able to um
offer
uh or offer access to those payment
credentials through
OAuth, right? To all those merchants.
So, in our example before we talked
about that that travel travel um travel
company, right? So, by doing so, they're
able to then create like an ecosystem um
of buyer agents and seller agents
uh and have a more trusted
environment, right? So,
um
in the in the just kind of talking more
about the use case, like the human then
is then going to be authorizing
authorizing their their payment.
Usually, this is a commercial card
commercial use case. So, you're using
like a commercial card, they share it
with the buyer agent, travel agent. Um
then that uh it it also has
scopes associated with that mandate. So,
that's how you're able to do controlled
authority, but in terms of like the
actual like dispute handling, we really
uh don't have like a we're not using
like
cryptographic proof that's being sent as
part of that that request, right? At the
end of the day, they can since it's more
of a
closed ecosystem,
they're able to leverage like the just
the existing
transaction logs.
Right? So, that's kind of an example of
like a medium stakes and
use case or scenario, and
we we believe it's medium stakes because
of the fact that it is a more closed
ecosystem and doesn't require all like
the
you know, evidence in terms of
or proof, right? So,
that's kind of like my part. I'll going
to turn it over now to Ben and uh take
it from here.
>> Uh thanks, Jay.
Yeah, so the last slide that Jay talked
about, um
you know, we're kind of going over the
medium stakes example.
Uh um
In that scenario, um
you know, both parties know each other.
Uh they're acting within, you know, the
same system. They they know
you know,
they're borrowing trust from, you know,
Nevermind to
make sure that, you know, the buying
agent is falling within, you know, the
instructions that a human has given it.
Um
and then the selling agent that's also
on Nevermind can feel comfortable taking
a payment um
from another user of of Nevermind. And
And so, what we want to talk about next
is what happens when the parties are are
not known to each other and they're not
vetted. Um
And so, like we think, you know, we
believe that the best option for that,
you know, is actually do these
autonomous payments, um
where, you know,
you know, not everyone's known. Like you
know, the stakes are high. You know, we
think that the industry should converge
on the FIDO verifiable intents and AP2
mandate. Um
You know, the TLDR of that is, you know,
it's a a multi-layered selective
disclosure jot. Uh
The first layer is, you know, created by
a
trustworthy credential provider. You
know, in this case, hopefully it would
be PayPal. Um The second layer, you
know, encapsulates the user's
instructions to the agent. Um
The user signs that with their private
key. And then the third layer, if
there's going to be a third layer, is
when um
we're doing autonomous payments. So,
that case, the agent would, you know,
sign that third layer. And And so, the
where that's powerful is that, you know,
party involved in a transaction can can
verify the part that's, you know,
important to them. So, merchants can
verify that the checkout is correct. Um
Um payment processors can verify that
the payment mandate is correct. Um
And no one has to have any relationship
to each other.
Um
And so, like I think, you know, if
there's going to be a ton of payments,
you know, at scale, we think that that's
going to be the best um way to
accomplish it.
Uh
pictures on the screen are depicting our
PayPal approval token.
Um
This is a new primitive that allows
users of PayPal to
basically start the order process with
an agent um for that agent has actually
found an item at a merchant to transact
with. Uh historically, PayPal orders
have been synchronous.
Um
You know, users on
checkout, they find their item, they go
to their PayPal app, they approve it, um
and it's done. Uh here, it's a little
bit different, you know.
Users on their agent,
um
they get redirected to PayPal to confirm
the instructions that are given to the
agent, and then
uh PayPal hands back this JSON payload.
Um you know, similar to the verifiable
intent, uh includes the
amount, the expiry, uh the merchant that
is supposed to be transacted with. Um
Similar concept, but not quite the same.
Um
it's an opaque string that only PayPal
can approve right now.
Um
But we're about to ship this into
production, um
and users of Jet and I that pick PayPal
as a payment method will will use this.
Um so, going to our last slide, um
you know, we showed this slide earlier.
It we didn't have the two columns filled
out on the right-hand side. Um
You know, we wanted to reinforce this
mental model where,
you know, starting at the top, we have,
you know, the low-stakes scenario, you
know, you're you're using Claude, you've
given it access to connectors, you know,
granular permissions to do things on
your behalf.
Um you feel comfortable doing that
because the stakes are low. You know,
you can reverse those actions or redo
them. It's not a big deal if Claude
produces, you know, the wrong output.
Uh going down a level, we have the
medium stakes scenario. You have
two parties that know each other that
are acting within the same system's
boundary. Um
you know, the
the actions are a little bit higher
stakes. You know, there is money
movement here, but both parties can can
feel comfortable, you know, transacting
with each other because they're relying
on this this third party to enforce uh
the payment mandate.
And then the third level, you know, the
highest stakes one um that we haven't
actually really seen in production yet,
it's, you know, the user's giving an
agent some
instructions to do something on their
behalf autonomously, and you don't know
who they're going to interact with, who
they're going to transact with. Um and
those parties need some verifiable proof
that the agent has permission to do the
transaction. And so, we believe that
that will be um
either verifiable dents and and AP2
mandates.
Um
I think the interesting thing is
it's also our belief that, you know,
this is a model that can't won't just be
used for payments, but we think it could
be for any sort of high-stakes action
that's hard to reverse. So, medical
orders, e-signatures, securities
trading, um you know, basically any
hard-to-reverse agent action.
That's all I have.
>> Yeah, I mean, I think um if we could
just go back to analogies, uh
you know, like in the low stakes is kind
of like, "Hey, you're within the the
building. You've uh put a badge in and
you're within the building." Whereas in
the um high stakes is kind of like, "You
are on the street and you meet
somebody." And uh you know, you need a
way to be able to uh get comfort that
that's someone you can trust, right? Um
is a badge is them showing you their
badge good enough? Uh probably not. You
need to have something that's a little
bit more um you know, verify verifiable.
Or I guess at a verifiable standard. So,
um you know, just kind of like using
that analogy and like how to think about
like the you know, what you need to do
in order to
prove the that the human authorized the
agent. Hopefully that that helps and now
you have kind of like a tool set to use.
So you can kind of prevent Skynet from
taking over your wallet. So, thank you
very much for your for listening. Hope
that helps.
>> [applause]
[music]
