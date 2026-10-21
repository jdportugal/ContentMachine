---
titulo: 'Deepseek did it again...'
tipo: item_agregado
plataforma: youtube
canal: 'Matthew Berman'
data: '2026-09-11'
url: 'https://www.youtube.com/watch?v=U-rsvXds9ck'
thumbnail: media/monitoring/youtube/u-rsvxds9ck.jpg
descricao: 'Check out MindsHub: https://tinyurl.com/5cra6js2 Join My Newsletter for Regular AI Updates 👇🏼 https://forwardfuture.com My Links 🔗 👉🏻 X: https://x.com/matthewberman 👉🏻 Forward Future X: https://x.com/forwardfuture 👉🏻 Instagram: / matthewberman_ai 👉🏻 Discord: / discord 👉🏻 Spotify: https://open.spotify.com/show/6dBxDwx... Media/Sponsorship Inquiries ✅ https://bit.ly/44TC45V Links: https://www.deepseek.com/en/news/deep... https://x.com/The_Alex/status/2096294...'
resumo: "A walkthrough of DeepSeek's new V4.1 Flash release — its benchmark claims against Opus 5 and GPT 5.6, and its 552B-parameter mixture-of-experts design with only 8–16B active parameters for speed and low cost. The creator also notes that in their own hands-on testing the model underperformed relative to those benchmarks."
tags: {}
fontes:
  - 'https://tinyurl.com/5cra6js2'
  - 'https://forwardfuture.com'
  - 'https://x.com/matthewberman'
  - 'https://x.com/forwardfuture'
  - 'https://open.spotify.com/show/6dBxDwx'
  - 'https://bit.ly/44TC45V'
  - 'https://www.deepseek.com/en/news/deep'
  - 'https://x.com/The_Alex/status/2096294'
---

## Descrição

Check out MindsHub: https://tinyurl.com/5cra6js2

Join My Newsletter for Regular AI Updates 👇🏼
https://forwardfuture.com

My Links 🔗
👉🏻 X: https://x.com/matthewberman
👉🏻 Forward Future X: https://x.com/forwardfuture
👉🏻 Instagram:   / matthewberman_ai  
👉🏻 Discord:   / discord  
👉🏻 Spotify: https://open.spotify.com/show/6dBxDwx...

Media/Sponsorship Inquiries ✅ 
https://bit.ly/44TC45V

Links:
https://www.deepseek.com/en/news/deep...
https://x.com/The_Alex/status/2096294...

## Transcrição

Deepseek V4.1
Flash is here. Incredibly cheap,
incredibly fast, and according to the
benchmarks, it is as good as Opus 5 and
GPT 5.6 Soul. And we continue to see
this pattern where we have the absolute
frontier progressing very quickly. Then
about 6 months behind that, we have a
completely open-source open weights
model that is as good as the previous
generation. And then 6 months after
that, we can put it on our local
computers. It no longer requires this
massive machine to run. And this is an
example of it heading in that direction.
And the efficiency gains that Deepseek
was able to squeeze out of their
training run has been incredible to
watch. And there's one big butt here.
I've put it through a few tests and it
didn't perform as well as I would have
hoped, especially compared to the
benchmarks, but I'm going to show you
that later. Let's go through the blog
post. Introducing Deepseek V4.1 Flash.
smarter, faster, and more efficient.
Now, going right into the benchmarks, we
have Deepseek V4.1 Flash, Kimmy K3, GLM
5.3, and these are the three best
open-source Chinese models on the
market. We have Opus 5, which is, I
guess, a previous generation of
Anthropics models. And then we have GPT
5.6 Soul, whereas just now a week ago,
it is the previous generation prior to
Astra. And look at this terminal bench
3.0 It scores a 30 compared to the only
one beating it is Opus 5. We have Deep
Suite probably the most accurate
benchmark out there scoring a 74.2
beating Opus beating GPT 5.6. So it is a
552 billion parameter mixture of experts
model. And I'm going to explain what
that means. 552 billion parameters is
actually relatively small. It's kind of
right in that middle range at this point
where GPT 5.6 was a trillion parameters.
Astra is probably 7 8 maybe even 10
trillion parameters. Same with Fable.
It's up in the 10 trillion parameters.
So this is definitely kind of former
generation size of model. But that's not
what's most impressive. What they've
been able to do with the efficiency and
specifically the mixture of experts is
truly mind-blowing. So, if you're not
familiar with mixture of experts, it is
the model's ability to only use a part
of its weights to give you the answer.
It basically figures out, okay, what
question am I being asked? Let me send
it to this small portion of my weights
that is, you know, quote unquote the
expert in that subject and get the
answer. And so, when you do that, you
get much higher efficiency on the actual
inference. And what's crazy is there's
only 8 billion active parameters for
input and 16 billion for output. So of a
552 billion parameter model, only a tiny
fraction of the weights are actually
being used. This technique has been very
successful in the past for being able to
run larger models on smaller machines.
And now we have that even further. And
let me tell you something, this model is
so fast. It is so very fast. And I'm
going to show you the output speed
because it is reminding me of like the
Gro GRQ early days. It's not quite as
fast as Cerebrris, but it is extremely
fast. And it even beats DeepSeek V4 Pro,
which is their previous generation
full-size model. Now, you can see the
benchmarks here. I'm not going to read
them all. I think a few that I would
like to point out is Cyber Gym, which is
a cyber attack and defense benchmark. It
scores an 88.1, beating all the other
models on the list. We have exploit gym,
which is that benchmark that OpenAI's
model escaped containment on and went
and hacked HuggingFace. This is that
benchmark. So, we have Exploit Gym
coming in at a 15, which is well below
GPT 5.6 Soul and Claude Opus 5 and even
further below Fable and Astra. And if
you want to try Deep Seek V4.1 Flash,
you can use it in the sponsor of today's
video, MinesHub, along with any other
model that you want all in one place
with one bill. Let me tell you about it.
Open agents should mean more than just
the source code. It should mean choosing
the model that fits the work. Something
I talk about all the time on this
channel. MinesHub lets OpenClaw, Hermes,
Claude Code, and other agents run on
every single model all through one API
key and one bill and one place to track
all usage. And you can keep using your
familiar OpenAI and Enthropic SDKs or
the OpenAI compatible API easily. And
MinesHub also includes automatic
failover. So if one API fails, it'll
automatically shift it to the next one.
And if you're building something cool,
MinesHub has something called Foundry.
You apply to this program, tell them
about what you're building, they will
give you up to $5,000 in matching of
whatever you're spending with MindHub.
Crazy value. Go apply. I'm going to drop
a link down below so you can go apply.
And thanks to them. Back to the video.
And then look at the progression of size
here. So really what they're pointing
out here is with this model, the KV
cache, which is the model's memory
basically, only needs a fourth of the
HBM, high bandwidth memory. And if
you're not familiar with that term, the
prices of high bandwidth memory, which
is kind of like RAM, has been absolutely
exploding. So let me show you how those
HBM prices have been trending recently.
So you can see for decades, they've been
trending down. This is dollars per
gigabyte. And all the way up until AI
started being a thing. If we zoom into
that, we can see the prices start to
actually increase. And that is because
AI is basically eating up all the
memory. They need more and more. The
supply is constrained and thus the
prices rise. Here it is again. Here's
DRAM price by generation. And as you can
see, at the beginning of 2025, we saw
this massive spike in price. Look at how
big that is. From like $25 per gigabyte
all the way up to where it is now at $10
per gigabyte. So, if you've noticed that
the latest phones, the latest computers
are more expensive and you get less
memory with them, this is why. So going
back to Deepseek, the fact that they
have cut the HBM requirement on their
new model by 3/4 is pretty astonishing.
And then they also need 1/8 of the SSD
storage, which is just a hard drive. And
this is what China is known for, making
things more efficiently and more quickly
than anybody else. and not necessarily
better, but definitely they're taking
something that is existing and making it
more efficient and building it more
quickly. And so this is their solution
to the memory crunch that they're seeing
in the global economy. They are
basically saying, "Hey, now we are
requiring far less through algorithmic
unlocks in the model memory HBM
specifically and SSD. These prices are
increasing. Great. We need a lot less of
them now." And this is basically the
total size of the memory footprint. From
Deepseek V1, all of a sudden to V3.2, we
had eight times smaller. Then to V4
flash, 13 times smaller. And then
another 4x smaller going from V4 to
V4.1. And what does all these efficiency
gains get them? Well, they're able to
serve many more tokens without
necessarily having to pay for all that
memory. And it actually shows up for us,
too. The model is so fast. It is so
fast. I will show you that in a moment.
But first, another consequence of being
able to have that hyperefficiency now is
the pricing is crazy. It is crazy cheap.
So, they basically differ between peak
and off- peak hours because they're
trying to get companies to basically use
the GPUs when they're being less used in
general. For a million input with no
cash, it is 15 cents per million input
tokens during off- peak hours and 30
cents during peak hours. with a cash
hit. It is a fraction of a penny for
off- peak and peak hours. And then for
the output, 60 cents per million output
during off- peak and $120 per million
output during peak hours. And the best
part about all of this, it's open
source. It's open weights. You can
download it. You don't have to give
Deepseek your data. You can literally
download it yourself. You can use any
NeoCloud that you want. You can
potentially when it gets quantized run
it on your local computer if you have
enough VRAM. So super impressive. It's
all about efficiency. This is literally
what Deep See is known for. This is what
happened when the initial version of
Deep Seek came out and they kind of blew
the world away because they were able to
train a near frontier level model for a
fraction of the price that the labs were
doing. But here's the thing. Here's kind
of how that game played out. What we saw
is the difference between the absolute
frontier and the frontier of open
source. That is where a lot of the value
gets created. But I made a video about
this. More and more tokens are being
generated by cheaper, more efficient,
faster open-source models. And that's a
good thing. But again, paying for the
best answer, which is a open AI model or
it's an anthropic model, paying for that
best answer is extremely expensive. And
I mean, we're talking about orders of
magnitude difference, $50 per million
output tokens instead of what we're
talking about here, pennies per million
output tokens. But when you need the
right answer, that's what you go with.
However, the vast majority of the
economy doesn't need the best answer
possible. I mean, we're talking about
creating websites and creating PDF
documents. Models that are much cheaper
in price are definitely capable enough
for 95% of the use cases out there. So,
it's definitely interesting to see how
these tokconomics are playing out. And
because it's open source, open weights,
they did put out a full white paper on
it. And the cool thing about DeepSeek is
they go into crazy detail about their
techniques, about the algorithms, about
how they were able to achieve this
efficiency. So, I'm very grateful to
Deepseek for putting all this
information out there because anybody
else, a startup can take it and build
their own and build on top of Deepseek's
research. And so, let me just show you
how fast this is. The first demo I'm
going to show you is literally just a
speed demo. So, give me a thousandword
essay about DeepSeek. Watch this.
Look how fast that is. If I had to
estimate, I would probably put that in
like the 200 tokens per second range.
So, all in all, maybe it took about 6
seconds to output this thousandword
essay. But here's the thing, it's not
always great. So, my first attempt to
create a Rubik's Cube simulation, first
of all, it finished in like 12 seconds,
which is crazy. But if we run it, it
looks okay. If we scramble it, that's
when we see the problem. I haven't had a
model fail the Rubik's Cube simulation
test in a while. So, this is quite
disappointing. It basically just
completely breaks. Now, I not only
tested this in deepseat.com, I also
tested it in codecs. And yes, you can
plug any model you want into Codeex. If
you didn't know that, it's actually
super valuable. So, it's very easy. You
literally just tell codeex add this
model. You give it an API key and as
long as it has a responses API
compatible endpoint, you can do it. So
you can obviously see the problem here.
Let's solve it. And it just snaps back
to the original. Now the actual
simulation, the actual physics and how
it looks, it looks good. So there's
there's not much wrong there. You can't
actually move each corner or each side
at all. But uh yeah, a little bit
disappointing. So, I put the exact same
prompt into Codeex using DeepSseek V4.1
Flash. And let me show you what it
built. So, it looks different. And
remember, this is using Codeex's
harness, but DeepSseek V4.1 Flash model.
So, it's kind of awkward, but you can do
it. And here it is. Now, if you look
closely, you can almost see that each
square on this Rubik's cube does not
actually seem connected to all the other
squares. They're kind of floating
independently.
Very interesting. Very interesting. So,
let's scramble it
now. Can you see what's wrong here? As
it's scrambling, the sides, the colors
are actually changing incorrectly. Let's
scramble it again. If you're looking
closely, you can actually see the sides
change color. Not because of movement,
simply because they're just changing,
which is so weird. It's just so weird
that Deep Sea could not do this. So,
let's solve it. And even as it's solving
it, I can see the colors changing
incorrectly. Now, I have a feeling Oh,
look at that. It didn't even solve it.
Even though it was cheating, it couldn't
solve it. Let's click solve one more
time. Nope. Replaying zero. Oh,
interesting. So, it's actually just
replaying the moves in reverse. It is
not actually using an algorithm to solve
the Rubik's cube, which is wrong in so
many ways. So, let's refresh it. I'm
going to scramble it once. Okay, I still
see the colors moving. So, it's
definitely wrong. And then let's solve
it. Okay, so it's replaying those 22
moves in reverse. Okay, so here it got
back to the original state. Now, that is
not correct. If I hit scramble twice and
then reverse once, it's going to end up
in a scrambled position again. This is
just completely wrong. It's the same
simple prompt I've given to every other
model I've tested this on over the last
year and a half, and it just completely
failed. All right, Alex from my team put
out a post. This is kind of what he's
known for now. It's called Paintbench,
and he basically asks the next Frontier
model to paint him in Microsoft Paint.
He gives him the reference image and
look how well Astra does on this. It is
literally as close to the actual image
as possible using the most basic image
editing software around Microsoft Paint.
By the way, 6.5 million views. Go follow
him if you're not already at the_ex. And
the reason I'm showing this to you is
because I want to show it to you as
context for how Deepseek V4.1 does. Now,
I'm not using Windows right now, so I
don't have Microsoft Paint, but I let
DeepSseek V4.1 Flash in Codeex use
Codeex's browser and a website that
basically replicates the functionality
of Microsoft Paint. And here is the
final result. Now, this is definitely an
older version of Microsoft Paint, so
maybe there's a little bit of an issue
there, but here's the reference image,
and here is what it created. It's
actually not bad. It's very stylized,
but like there's no detail. And so the
technique that Astra used of these
paintbrush strokes one over another over
another, it did not know how to do. And
thus we got this very abstract version
of my headsh shot. Okay. And the last
test I gave it was to create a 3D
simulation ray tracing everything of a
bullet going through a drop of water.
And let's see what it does.
Yeah, look at that.
Let's see if we can do it in slow
motion.
No, it's too fast, but it looks okay. I
wouldn't say it's great. So, we can
change the muzzle speed, the caliber,
the projectile mass, rifling spin, yaw,
engine attack. It's actually quite a
good app that it built, but the actual
simulation itself leaves something to be
desired. So, this is a great workhorse
model. very efficient, very inexpensive,
very fast. Go enjoy, download it, do
whatever you want with it, fine-tune it,
use it to your heart's delight.
Definitely opensource is extremely
important. And I don't know if this
model is better than GLM 5.3, which I
did a full video on. Check that out
right here.
