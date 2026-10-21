---
titulo: 'Act, Confirm, or Stop? Smarter behavior for AI assistants, wearables & robots — Amit Desai, Roku'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-15'
url: 'https://www.youtube.com/watch?v=Zd5b40Jbp_k'
thumbnail: media/monitoring/youtube/zd5b40jbp-k.jpg
descricao: 'Amit Desai never makes the model better. Accuracy stays pinned at 79 percent for the whole talk, and user pain still drops by nearly half. Desai has worked on voice interfaces for years, at Roku and before that on a widely used smart speaker, and his argument is that teams pour effort into one knob while ignoring a second one that is entirely independent of it. Knob one is accuracy, chased a percentage point at a time through the wake word, recognition, and understanding layers. Knob two is what the system decides to do when it is unsure. Take a thousand music requests where 790 play the right song. The naive system always acts. Give it the option to decline instead, saying it did not catch that, and you now need a confidence threshold, and intuition picks badly. A sensible looking 65 perc...'
resumo: 'A voice AI expert argues that improving assistant satisfaction requires more than accuracy: systems also need to decide when to act, confirm, or stop. He walks through a smart speaker example to illustrate this approach, noting the stakes rise as AI moves from answering questions to taking physical actions via wearables and robots.'
tags: {}
fontes: {}
---

## Descrição

Amit Desai never makes the model better. Accuracy stays pinned at 79 percent for the whole talk, and user pain still drops by nearly half. Desai has worked on voice interfaces for years, at Roku and before that on a widely used smart speaker, and his argument is that teams pour effort into one knob while ignoring a second one that is entirely independent of it. Knob one is accuracy, chased a percentage point at a time through the wake word, recognition, and understanding layers. Knob two is what the system decides to do when it is unsure. Take a thousand music requests where 790 play the right song. The naive system always acts. Give it the option to decline instead, saying it did not catch that, and you now need a confidence threshold, and intuition picks badly. A sensible looking 65 percent is worse than the actual optimum of 43.

Getting there requires admitting that bad outcomes are not equally bad. Playing the wrong song means hearing it, realizing it is wrong, talking over it to stop it, and asking again. Being told sorry, I did not understand costs a few seconds. Desai turns that into a unit cost per outcome and minimizes the total, an approach he named the Outcome User Cost Heuristic, which spells OUCH, which he is delighted about. Adding a third behavior, confirming the guess out loud before acting, splits the confidence range into three regions and two thresholds, and pushes the cost lower again. He closes by moving the same reasoning onto a television, where showing choices on screen changes every cost in the equation.

Speaker info:
  / amit-v-desai  

Timestamps:
0:00 - The power and the pain of voice
2:17 - Why error costs rise with embodied AI
3:22 - Two knobs, and the one nobody turns
4:28 - A thousand requests, 79 percent right
5:36 - Adding the option to stop
6:40 - Choosing a threshold, and why intuition fails
7:43 - Bad outcomes are not equally bad
9:52 - Naming the cost function OUCH
11:01 - Watching the optimum move
13:12 - Adding a confirm behavior
16:38 - What changes in a real system
17:42 - The same method on a television

## Transcrição

[music]
Hi everyone. How's it going? Hey
Patricia, how are you?
>> Uh so last presentation of the day, so
let's make it count. Um,
all right. Let's, uh, let me start with
a little bit of background on myself.
And, um, my background, I'm a voice
subject matter expert. I've been working
in voice AI for a long time across
different surfaces, devices, and um,
both at Alexa, at at Roku, at my own
startups, you know, in the app store.
And my perspective is a little different
from a lot of other voice AI
practitioners. I think it's a
combination of um a deep um voice user
interface expertise and intu intuition
mixed in with new technical approaches
uh that I think can produce really
magical experiences. So I think it's
both sides and I think that's especially
true in this new area that we're in with
frontier tech where the human interface
is basically being redefined. So let me
start with uh I'll just blast through
the first couple of slides then get to
the premise. I think everybody knows
that voice has incredible potential.
There's the power of voice I think
across everywhere. It's the most natural
interface. Humans love talking. And uh
the problem is the other half is the
pain of voice. So it's the power and the
pain. Voice is errorprone. And I think
those errors are going to continue for a
while. And I think the cost or
consequence of those errors is going to
grow, especially as we go fromational
AI bots to embodied AI where rather than
just giving answers that might be
erroneous,
we're going to have AI systems take
physical actions or digital actions
where, you know, if the robot throws
your watch out with the trash, it's a
lot worse than playing the wrong song.
So I do think that a new approach is
definitely needed and here's the TLDDR
of the premise we're going to walk
through today. Um there are two ways to
improve customer or user satisfaction of
a voice AI assistant and that is by
increasing accuracy which people know
about I mean technically accuracy and
the other is a different knob that we
have that we are not using adequately
and I'll call that a system decision
which we will define which is orthogonal
which is different from accuracy and I
believe This approach which I have used
in several different environments and
seen some success I think is a promising
area that we should consider developing.
Um let me walk through this with a
simple smart speaker example and we'll
go step by step with this approach but
it is a scalable approach that I think
uh can apply across different surfaces
and devices. So let's get started. So
suppose we all you know are making a
smart speaker coincidentally called uh
Alexa and Alexa is very simple. It just
allows you to you know ask for music and
it'll play a song and of course it will
play either the song you wanted or a
different song. So it'll be right or
it'll be wrong. This isn't that
different from what you've seen out
there. Um now let's to first talk about
accuracy. Accuracy. Let's say we define
it as we you know take a thousand spoken
requests. We observe the input and the
output. We label it and we look at this.
This is the map of a thousand points and
79% of the time 790 dots here were
actually the correct song. This is let's
say human annotated 20% 21% wrong song.
So that's the accuracy. Now, like I
said, knob one is to spend a lot of time
working on improving the accuracy, you
know, um, percentage point by percentage
point at any layer in the stack.
There's, if it's a cascaded system, you
know, there's a perhaps a wakeword layer
and a speech ASR layer and a NLU layer
which might have intent classification,
entity extraction, a lot of different
layers, VAD, etc. And any of those can
contribute to errors. So we spent time
we might be able to reduce that 210 to a
smaller number that is I think a known
area that we're tackling but I think
knob 2 which is what I was talking about
is what we'll go through here which is
keeping the accuracy exactly the same.
So 79% what could we do in conditions of
uncertainty to improve user satisfaction
apparent and I I think we can do a lot.
So let's start first with the original
system is just acting like I said user
says something system plays a song it's
either the right song or the wrong song
immediately I think just common sense
tells us that we could introduce at
least one system behavior to stop or
rather to reject the hypothesis and do
nothing. So uh there is now one more
option to decide the system may decide
and say sorry I didn't get that or sorry
could you repeat that? Uh the challenge
of course is how how when do we decide
to stop and I mean quantitatively. Um
here's one approach to kind of
visualizing this because if we don't
we'll just take probably some swag like
some guesstimate and I'll prove that if
we just took a guesstimate we would end
up with a worse situation than a more
rigorous approach. So let's just assume
I took those thousand data points and
like I said they've been annotated and
we assign a confidence score a single
confidence score to the hypothesis that
was generated by the system you know
between zero and one and let's say it's
reasonably calibrated. This is a
simplification of if it's a cascaded
system there are multiple layers and
multiple you know confidence scores but
let's just assume that for now. Whoops.
So we're going to have 790 points 200
that are correct 210 wrong. Each one has
a confidence score and we're going to
plot it, you know, plot the
distributions. Uh on the x-axis, I've
just converted from 0ero to one to
percentages. And the question is how do
we choose a threshold t such that
whatever that percentage is um to the
left of it meaning if when the system um
forms a hypothesis if the confidence
score c is less than that t stop and say
sorry otherwise play question is how do
we choose a t so far everything I'm
saying is fairly common sensical but
this is where um intuition will fail us
we might say something like okay I don't
know let's do 65%. It seems you know gut
feeling like okay it's kind of confident
that's probably when we should speak. Um
now here's where we start coming out
with some sophistication.
Any tea we choose is producing bad
outcomes. Bad in the in two fields. One
is obviously on the left side anytime
you stop it's bad. The user doesn't want
it to stop. He wants to they want to
hear their song. The other bad is if you
do play a wrong song, of course that's
bad as well. So these are two two kinds
of bad outcomes. But here's the
important part. Now I've like elaborated
on the um tree diagram on the right hand
side. The bad outcomes are not equally
bad. They're not the same thing from a
user perspective. And obviously let's
let's think about it. If the wrong song
plays, you said play kiss and it starts
playing kiss by Chris Brown instead of
the one by Prince. That's going to be um
the highest user cost. Now I'm defining
user cost from the user's perspective.
First I have to like hear music and
realize that is not Prince. Then I have
to shout over my Alexa and um you know
get it to stop and then I have to
re-request. All of that is a lot of
effort. that is definitely a worse
outcome than the system stopping and
saying sorry I didn't understand that
however we should go further and try to
quantify that relative badness and there
many ways to do it and I think this is
an area to be explored for now let's
just consider this a heristic of if that
outcome happens how many more seconds
additional seconds will it take for the
user to get back to success which is to
play the song they wanted kiss by Prince
and I'm I just put down some numbers
here. Let's say in the case of a bad
song, it's 10 seconds if you add up all
the things I got to do. And if it's a I
didn't understand you, it's 4 seconds
because that's how long it would take
you to respe and and the extra latency.
And now here's where we can start
utilizing that. If we go back to our
distribution curve on trying to find out
where is T. Now we've basically turned
this into a problem of minimizing a cost
function. It's a user cost function. It
is the number of bad acts wherever that
whatever the t causes times 10 because
that was a unit cost we gave plus the
number of stops times four because
that's the the unit cost we gave. By the
way, one thing I should have elaborated
because I work in voice and we like
language and we like puns. So this whole
thing is called an outcome user cost
heruristic. So that spells the word ouch
and that is some expression of pain.
Yes, we are you know language nerds. So
these kinds of things amuse us. Um so
now let's consider that is the cost
function is to minimize the ouch. And
now um that let's see if uh I'm going to
bring up a tool. Let's see if this
works.
Where I have actually gotten or with one
of my coding assistants gotten uh an
interactive
um graph where we have actually plotted
those thousand points and as we vary the
threshold t you can see that the total
user cost here which is that function of
you know x * y + a * b actually changes.
So let's in the very beginning when we
said the system was just playing
the the cost across those thousand
points was 2100 or divided by a,000 is
2.1 ouch points per turn. Then we said
okay let's insert a stop behavior and
let's like wing it and say 65%. That's
when I want the threshold. If we brought
this up to 65 yeah that's better. Now
it's 1904 or 1.9 per turn, but it's not
optimal. As it turns out, if we do
actually um ask for the AI to solve the
uh the problem across this curve, it
turns out 43%. So I'll drag it now to 43
is in fact
the optimal
optimal point of t. This minimizes the
cost function. You can see it's the
lowest point on this graph down here to
27. So effectively we haven't changed
the accuracy at all. The system is not
any smarter in that sense. But with some
clever system behavior, conversational
behavior is what we'd call it and some
optimization and a cost function called
ouch. Um we have from the user's
perspective produced a more satisfactory
assistant. And this is not a trivial you
know accomplishment. Okay. Now, let me
go back to this. [clears throat] Let me
see if I can get this. Oh, great. Okay,
let's continue this. Let's continue this
with by now adding one more behavior.
Let's call it the confirm behavior. So,
there was play obviously, then stop,
confirm. Confirm is basically the system
after you said something saying uh kiss
play kiss by Prince or maybe play kiss
by Chris Brown. And uh you know the user
can either confirm like affirm it or
they can correct it. It is a different
kind of behavior and again this is kind
of how humans behave. Um that's
obviously the inspiration. Now if we go
back to our problem of optimization,
we have a third obviously um option
which is to confirm. And so this would
translate to two thresholds
um two thresholds which are separating
the distribution into three spaces of
stop, confirm and uh act. And the
question is now where are these T's? and
we have now given up on guesstimating
because we know it doesn't work. So
we're going to be a lot smarter and go
back to the concept of user outcome cost
and then you know use it go look for
some optimization in that graph. So
let's uh define what are the what are
all the possible bad outcomes that t1
and t2 um make for. So good you can see
my cursor. So uh of course any stops are
still bad. Then in the middle are
confirmations. Confirmations are bad
because they slow the user down. There
is a confirmation outcome called confirm
yes where they just affirmed it by
saying yeah or no where they had to
correct it. And going back to our
formula these outcomes are not equally
bad. And in fact, nobody will, I think,
argue here from a user's perspective.
Affirming, just saying yes is obviously
less painful than saying no and then
having to restate whatever it is that
you wanted in the first place. So now we
I've assigned values of two or six. And
again, I said it was a heristic. This
would be roughly the amount of time it
would take for the extra for the user to
get to the song they want. Saying
listening and then saying yes is like
two seconds. Um and then now
uh we restate the cost function for this
you know added behavior as this number
of you know bad type one times unit cost
bad type plus bad type two times unit
cost etc. And now we try to minimize
this user cost function and minimize the
ouch.
Yes, I'm going to keep doing that pun.
Um let's go back. So this is now the
interactive graph but
with
um the cost values the unit costs here
and uh you know we're just going to ask
the AI to tell us here's the heat map
because it's now two dimensions saying
that the optimal values are 41 for the
the T1 and 49 for the T2 and if we
employed that then we would go to 1464.
Uh, by the way, whatever numbers I put
in here, like let's say I thought wrong
act was 20. It's really irritating and
painful and takes way longer to actually
correct it when you hear a wrong song.
That would change you know all these
numbers uh and the optim optimal point.
So again it is about how what is the
relative badness of these outcomes also
of course the distribution curves
naturally. Uh let's go back here. Okay.
So, um I'm gonna
speed up a little bit. Uh let's go back
here.
Presentation mode. Okay. So, what have
we shown that if we did the super naive
approach, it's 2.1 act and stop 1.9 then
1.27 then 1.26. We are able to bring
this with every added layer of
sophistication, adding more behaviors,
being smart about outcome, uh, user cost
and optimizing. Um, we have made a
tremendous difference without changing
the accuracy at all. Um, this was a
super simplified example. In real
systems, you're not going to have
obviously some offline decision
threshold or two. It's going to be a
real time, you know, learned decision
model. But the principle is the same.
And I believe this is uh scalable across
all voice AI surfaces. Obviously this is
a smart speaker but if we go across any
of these surfaces you will find the
equivalence. If we um we will find the
analogies with some differences but the
spirit and the I think the the gain will
be similar. So just for example in the
TV AI assistant space if you employ it
here it's going to you're going to have
the same thing when users express
intents like on TV it's you know open a
channel that's one of the most common
obviously um requests on a TV voice
assistant same thing you're going to
find you'll have exactly the same
approach but the difference will be
maybe in the the assignments of the user
outcomes because the UI and the
modalities are different when you have a
TV you have a multimodal interface where
choices can be shown. So instead of you
know asking did you mean ABC you know uh
news live by speech that you will the
system would display choices and not
just one it show ABC News live this that
would be the confirm step and if it's
visual and you can use your remote
control to select something it's less
pain so you would change some of these
values or if in fact launching the
channel would kick you out of your
current state then it would go in the
other direction than cost of you know a
bad act would go much higher. So it's
the same concept but in this new
modalities
um variables can change, values can
change, arguments can change but the
premise still holds and you can improve
from the user's perspective because
we're all about you know making humans
happy. Um you can make them happier and
this as I said in conclusion can be
applied across all surfaces. I did say
at the very beginning, just to recap for
us, that voice is great when it works,
bad when it doesn't. And as we get into
embodied AI, where these AI assistants
are taking actions, physical or even
digital, like making a phone call or
sending an email, it is getting more and
more difficult just to rely on accuracy
to improve user satisfaction. I believe
there's a whole knob the second knob
called smarter conversational behavior
under uncertainty
and um if we actually exploit that we
can uh very much help these AI systems
reach a acceptable user experience
otherwise I think this will continue to
be a bottleneck like a lot of things
will get better but if the voice
interface as experienced by user does
not improve it is going to be a a a
choke point. And um if you just remember
one word or two words from this whole um
presentation, it would be to minimize
the ouch of the experience. Um so thank
you. I'll stick around for questions if
you guys got any. Thanks a lot.
[applause]
>> [music]
