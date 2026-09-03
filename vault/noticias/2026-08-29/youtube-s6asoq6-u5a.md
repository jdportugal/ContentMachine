---
titulo: 'Tell the Robot What You Want — Sandhya Subramani, AWS'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-29'
url: 'https://www.youtube.com/watch?v=S6aSoQ6_u5A'
thumbnail: media/monitoring/youtube/s6asoq6-u5a.jpg
descricao: 'Sandhya Subramani asks her rover how many people it can see, a question nobody ever trained it to answer. It thinks for a second, checks its front camera, and reports two, one near the speaker monitor and one further right. Scout is a small four legged robot running a Raspberry Pi, reaching the internet over a SIM card and a 4G connection, and its whole personality comes from an agent layer sitting above the movement policies it already had. The demo goes exactly as live robot demos go. It falls over, gets coaxed upright, announces a signature performance and then just turns its headlights off. The idea underneath is neat and portable. If you give an agent software tools, you can also hand it a hardware tool and let it choose which preset policy to fire, which turns a robot trained for a f...'
resumo: 'The video demonstrates how a robot named Scout can understand and respond to natural language commands, showcasing its ability to perform actions autonomously using a Raspberry Pi and a 4G connection. It highlights the advancements in robotics through the integration of an agentic layer and open-source frameworks, allowing the robot to think and act independently.'
tags: {}
fontes: {}
---

## Descrição

Sandhya Subramani asks her rover how many people it can see, a question nobody ever trained it to answer. It thinks for a second, checks its front camera, and reports two, one near the speaker monitor and one further right. Scout is a small four legged robot running a Raspberry Pi, reaching the internet over a SIM card and a 4G connection, and its whole personality comes from an agent layer sitting above the movement policies it already had. The demo goes exactly as live robot demos go. It falls over, gets coaxed upright, announces a signature performance and then just turns its headlights off.

The idea underneath is neat and portable. If you give an agent software tools, you can also hand it a hardware tool and let it choose which preset policy to fire, which turns a robot trained for a fixed task list into something you can address in plain language. She wires it up in about five lines using an open source AWS framework that already covers 40 or so robots. Scout actually runs three agents at once, one thinking about the environment, one talking to her over Telegram, and a voice agent she disabled so it would stop interrupting her on stage. Her framing is that the agent decides what to do while the trained policy decides how, and the robot doubles as a rig for collecting the next round of training data.

Speaker info:
  / sandhyasubramani  

Timestamps:
0:00 - Meet Scout, running on a Raspberry Pi over 4G
1:27 - Answering a question nobody trained it for
3:46 - Giving an agent a hardware tool
5:02 - Five lines to hand a robot to an agent
6:09 - Three agents running at once
7:17 - The four layers, and how observations travel up
9:35 - Splitting work between cloud and edge
10:45 - Where policies go if they scale like language models
11:53 - Asking it to do something complex
14:12 - What is actually under the hood
15:21 - Mirror mirror, over Telegram

## Transcrição

[music]
Hello everyone. My name is Sandy and
meet my co-host today, Scout. This is my
friendly rover. And one would think that
rovers can't really think for
themselves, right? We have to tell them
what to do or we have to very
specifically program them on how to
think. But this little guy here actually
has a brain and he can think for
himself. Let me show you my screen. Oh
no, it's going to the wrong screen. I'm
going to see how I can stop this and I'm
going to see how I can move to my
screen. Um,
give me just a second. I'm going to end
show and then we get to this. So this is
what Scout here is looking at. And Scout
here has a small little brain. And Scout
can understand what I'm saying in
natural language. For example, if you
can see my screen here, if I say, "Hey
Scout," I'm going to type to him. Say,
"Hey Scout,
turn on your headlights
and say hi to everyone.
he is actually going to be able to
understand and do those actions. But how
is he able to do these things, right?
It's going to take him a couple of
seconds to think about it because how
this has been set up is he is working on
with a Raspberry Pi installed and that
Raspberry Pi is actually in a small
little location in New York City and
he's got a SIM card here which is then
connecting to that to that Raspberry Pi
and then he's getting signals. So, this
is via 4G connection. And so, he's like,
"Oh, hey, on it." And if you can see,
you know what? I'm going to tell him,
"Spin around." And oh, no, he's actually
speaking to me. He's like, "I see the
stage and it's looking great. Big
presentation screen to the left, bright
stage lights, and rows of seats for the
audience.
How How many people do you see?
I've never trained him to answer this
question, but he's Let's see if he's get
getting it.
He's thinking for like a second or two.
Let me take a good look from the front
camera. I see two people. One near the
speaker monitor set up at the back of
the stage and another person further
right. Nah. spin 360
and he's also saying is this session
about to start right so he's going to
spin 360 any second now and then he's
probably going to be like oh wow I did
so this little robot here is a next
generation of robot where ah there we go
he is spinning 360 now and he's probably
going to tell me what he's seeing
and he's saying let's spin. Right? So
this new generation of robots is to it's
different from our traditional robot
training because I have given this guy a
little brain. And what do I mean by I've
given him a brain? I've given this robot
an agentic layer and I've given it it's
called strands agents which is an
open-source framework which was built by
AWS and I'm going to quickly go back to
my slide deck
we can see it right and so here what
happens is we have these existing tools
that the robot can do he can take
certain actions by himself but only
those actions by himself so what we can
do is we can add a layer of LLM or even
better add a layer of agent to it so
that the agent orchestrates which tool
to call and how to really get the robot
to start doing the things we want. So in
traditional software with traditional AI
machine uh like AI engineering we can
give agents software tools. Similarly,
we can give the same AI agent a hardware
tool called a robot which has access to
preset functions or programmable
policies and then the agent can decide
which policy to implement when. So all
it takes is one robot agent for us to be
able to do new innumerous tasks and have
it understand what we're teaching it in
natural language. So how do we get
started with it? All it takes is five
lines of code. This is through uh the
agent harness called strands. And all we
have to do is import the strands agent
and call the robot tool. And we say ro
tools equals the robot and then we say
pick up the red cube and should be able
to pick up a red cube assuming that the
robot has that capability. Yeah. Now
he's seen someone and he's like oh let
me go towards that person. So he gets
pretty excited. This guy is pretty
special because he doesn't have just one
agent. He's got three different agents.
All three of them are strands and all
three of them are working
simultaneously. One of them is the
thinker agent and that's the part of him
that's constantly thinking and assessing
the environment and like what do I do
next? And that guy's that part of his
brain is constantly thinking. Then
there's the other communication part of
it where and I'm going to show you that
in a bit, right? and I've connected him
to my telegram app as well as to my web
app. And so he is able to have a
conversation with me in natural language
and then take actions based on what I am
telling him to do. Apart from him just
perceiving and thinking and figuring out
what he wants to do. And the third
agent, the third type of agent that he's
got access to is a voice agent. I did
have to disable it because every time I
speak, he's going to think I'm speaking
to him and so he's going to keep
chatting away with me and it's just not
going to be fun because we're going to
have our co-host interrupting me all the
time. So, I've disabled that feature for
the time being. But essentially all
three of these agents work in tandem
with this one robot and thereby this
gives him the ability to do way more
than what just what he's been trained to
do more than just the policies that he's
learned. Now
what is a quick overview on this trans
package itself? This turns package has
more than supports more than 40
different robots under eight categories.
And all of these are just simple robot
tool calls. And how is this all set up?
Four different layers. The first one is
the agent layer, the topmost one. And
there are two parts to this. One is how
the actions go in and the second is how
it observes and the observations go up.
So if you notice it's very birectional.
So first when we give it an instruction
we would be talking to this trans agent
which is the agentic layer that would
then decide which policy to call and the
policy provider again stands agent
supports a bunch of different policy
providers and we can then train our
policy based on our traditional robot
training. So in our policies we would
collect data and then we would train on
it and we would sim create more
simulation data and that policy then
becomes a VLA model which then the robot
would have access to strand agents would
have access to and then it would invoke
that specific policy based on the
question that we're asking it or the
command that we're giving it and that
policy needs to sit somewhere right so
that sits in the back end which could be
your simulation environment or it could
be a real hardware chip, your hardware
environment. That is the back end on
which that is the interface on which the
policy is running. And finally, the
output actually takes place in the
physical hardware which is the robot.
And so the robot ah see so now it's
responding this even if he falls down
he's supposed to be fine. He technically
shouldn't um he technically shouldn't uh
get hurt. He should be able to pick back
up from where he um stops. Ah, okay. So,
I'm telling him to go back a bit. Back
off. Let's see if he actually backs off.
Um, so that is the four layers of how to
get started with building this, right?
And what's happening under the hood,
like a more picturesic view of what's
the architecture of what's going on
under the hood. We want everything is
basically strands agents on the edge as
well as on the cloud. We want to be able
to train the VLA and the policies on
with using agent core. Um and we want
that to happen on the cloud but we also
want to be able to call it directly on
edge so that our robot can uh execute
functions and policies faster. So this
is sort of like a hybrid model where a
part of it happens on the cloud and
another part of it happens on the edge
and strands can decide when to call
which part of it. And so this helps with
massive amounts of training as well when
it's constantly collecting information
and it's train able to train on that
information and learn from itself but
also just execute at runtime really
really quickly. Now, like I said, the
agent decides what to do and the policy
decides how it should be done. But he's
pretty smart. He should be able to pick
himself back up if he's not fully fallen
down. And he should be able to continue
moving along.
So, I think he's okay. Now, where does
this leave us? And why is this so
special? We started off with very
traditional robots. Robots have existed
since forever, right? And they've always
just been programmed, pre-programmed to
do to autom be automated and do a
certain set of tasks autonomously.
But there is a future in this world
where this these robot policies, these
VLA models could be so advanced that we
wouldn't even need to do this. They
could be as large as our large language
models. So that ah wait hang on he's
falling back again.
I'm gonna see if I can get him to move
back up.
Good boy. Stop.
Then he's fallen off again. Um we get to
a point where these large language the
the VA models could be as large and as
amazing as our larger language models
and they know they have all the
information in the world and we wouldn't
even have to do this. we might just have
to feed in one simple model and then we
could give it to him and then he would
know exactly what to do. But until that
point where we don't have to fine-tune
on top of existing VAS and existing
policies, we can do this. And this is a
stepping stone towards a future where we
don't need to train robots anymore. So
now if we wanted to do more things than
just the tasks it's trained on, give it
an agent and see what it can do. And so
let me quickly go back to my demo and
I'm going to show you how it's actually
working.
Okay. So this is my so this is strand
here. This is scout here. And I've been
telling him to do a bunch of things. So
I can say, "Hey, do something
complex."
That's not complex. He's going to be
thinking now. Ah, he's going to fall
off.
So he's saying, "Let's spin. Full 360.
Done. Still safely on the stage. I can
see the bright stage lights and the
audience seating area." All good. What's
there? A challenge.
>> So he's speaking. I called this my
signature performance,
>> but he's not doing anything. What are
you doing?
He clearly seems to be speaking, but
what are you doing? Please do something.
He just turned off his headlines. Cool.
Okay, now he's calling. So, do you see
it saying calling rover speak, which was
the function that it called because I
said do something complex. So now it
spoke, but now I think it should have
been attempting to do something and it
fell off because it tried doing
something.
I've actually seen it do like a funky
dance, like this funky dance move. But
he's got a mind of his own right now.
What's going on under the hood here?
Couple of things. The first thing is
here, I can use this. What is the point
of creating him? I can use him to create
my data sets because I'm able to also
manually move him. I will get him to
navigate in the direction that I want
him to and then I can create training
episodes and I can get information on
how he's responding and how he's
reasoning based on the questions that I
ask. And this is super good information
for me to then be able to make him do a
better job of it. So that's one part of
this whole process and this experiment
of getting of giving him his own
autonomy and getting him to do things so
that I can create more data but also
apart from that uh this is my
configuration. So over here under the
hood strands agents which is your
harness SDK is using currently anthropic
claude opus 4.8 under the hood. So that
is the brain and then this is my simple
prompt where system prompt where I'm
telling it what it's supposed to be
doing and I'm telling it all of the
rules and I'm also giving it access to
all of the rules that it's already got.
So I'm telling it what each of these
rules are meant for. And so that's how
strand decides which tool to invoke
based on what I'm asking it to do. And
the voice that it's using is the one of
open AI real time. And I've also given
it more information for it to be able to
like just safety and guard rails to
ensure that it's doing really well. Now
it's this is these are two of the
agents. The other thing that it can do
is also chat with me on Telegram. This
is amazing because when I'm not at home
and I still want to get it to speak to
me, I can say, "Hey, scout.
Who is turn around uh spin around
analyze?" Uh-uh. Don't fall off. Analyze
the room and tell me who is the best
looking person. This is always a hit or
miss. Don't blame me if if Scout decides
to be biased. I tried playing with this
because it makes me feel like Snow White
um and the queen saying mirror mirror on
the wall who is the fairest of them all
and so let's see if Strand can give us
that. So it's saying haha on it on there
spinning around to scope the room. Is he
spinning? He is. Looks like he's making
and let's look at what he's looking at
now. He's still looking at the stage.
This is the other miller. He's looking
at me. Hi. Am I the prettiest? Oh, you
flatter me. But no, turn again. Spin
some more, please.
He's decided not to spin more. Let's see
what he says.
Spin complete. Here is my analysis of
the room. You can see my screen, right?
I have no way of zooming this in. Um,
spotted six to seven people total. A
couple seated front center near the
bright stage, right? A few more in the
audience. One person a dark outfit on
the stage. The rocking khaki jeans and
sneakers. Thank you very much. Oh, no,
no, no. The person in front of me, cocky
jeans and sneakers, clearly walking with
purpose on stage. Uh, best looking.
Honestly, from my low angle ground view,
it's tough to tell faces, but the person
on the stage right now gets style points
for the confidence stride and the
dramatic blue curtain backdrop. Thank
you very much. Every time I need a
confidence boost, I can be the only
person in the room and ask this guy
who's the prettiest of them all, and he
will always choose me as the answer.
[laughter]
Thank you very much.
>> [music]
