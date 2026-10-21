---
titulo: 'I Think GPT-6-Astra Just Changed YouTube'
tipo: item_agregado
plataforma: youtube
canal: 'Nick Saraev'
data: '2026-09-11'
url: 'https://www.youtube.com/watch?v=MujaqZS9wtM'
thumbnail: media/monitoring/youtube/mujaqzs9wtm.jpg
descricao: "🪄 Get Genjutsu & Wan 3.0 here: https://higgsfield.ai/s/wqcOCK 💎 Get all prompts 100% free: https://skool.com/maker-zero/about 🔥 Join Maker School & get client #1 guaranteed: https://skool.com/makerschool/about ➡️ If this is your first video: I'm Nick Saraev. I run LeftClick, an AI growth agency. We've done work for large brands you're likely familiar with, like Mr. Beast, Anthropic, OpenAI, and a few others. In plain English: I help people build systems that generate leads, close deals, and scale businesses, mostly using new AI models like Claude. I also make videos about how to do all of that here on YouTube. It hasn't always been this way. My family immigrated from a Soviet bloc country (People's Republic of Bulgaria) in the 90s. They worked extremely hard, and we were broke for a ver..."
resumo: "The video demonstrates how to apply AI style transfer to video, turning ordinary recorded footage into animated/stylized clips by combining the source video's motion with a generated style reference image passed through a video model. It walks through both a simple API-based consumer method and a more economical, scalable pipeline using AI agents and self-hosted models."
tags: {}
fontes:
  - 'https://higgsfield.ai/s/wqcOCK'
  - 'https://skool.com/maker-zero/about'
  - 'https://skool.com/makerschool/about'
---

## Descrição

🪄 Get Genjutsu & Wan 3.0 here: https://higgsfield.ai/s/wqcOCK
💎 Get all prompts 100% free: https://skool.com/maker-zero/about
🔥 Join Maker School & get client #1 guaranteed: https://skool.com/makerschool/about

➡️ If this is your first video: I'm Nick Saraev. I run LeftClick, an AI growth agency. We've done work for large brands you're likely familiar with, like Mr. Beast, Anthropic, OpenAI, and a few others.

In plain English: I help people build systems that generate leads, close deals, and scale businesses, mostly using new AI models like Claude. I also make videos about how to do all of that here on YouTube.

It hasn't always been this way. My family immigrated from a Soviet bloc country (People's Republic of Bulgaria) in the 90s. They worked extremely hard, and we were broke for a very long time. I grew up wishing for more security, stability, and freedom over my own life.

I don't have a programming degree, and never took formal education for IT or workflows. I learned it all myself from YouTube videos like you're watching now.

As for my path: I started my first business at 19 in college. We threw events (mostly parties) at campus bars, and charged people for entry. 

That failed to provide me enough money to pay my bills, so at 21, I started another business, a door-to-door marketing agency.

From there, every year, I would start several companies, over 10 in total. I did this in a variety of industries, like e-commerce, photography, videography, SaaS, credit repair, etc. Each grew slightly more profitable, until, at 25, I would start 1SecondCopy, a content writing company. A friend and I eventually scaled that to over $90,000/m. It was my first taste of financial independence, and the reason I'm here today.

Since starting my journey, I've grown a fair bit of capital. To diversify my income, at 30, I began investing in other businesses and advising companies on how to grow in the AI era. These include SaaS businesses, like Clairvo, agencies like Dental Connect, and more. 

Collectively, the businesses that I own outright or are a part of generate over $10 million per year. This returns far more than the 5-10% you would get in the stock market, but also requires a lot of my active time. There's a tradeoff there, but I greatly prefer active investing, where I have control over the entities I'm a part of.

It wasn't easy to get here. But I did in the end! For anyone on their own entrepreneurial journey: I wish you luck, happiness, and perseverance. You can make it!

Summary ⤵️
Thought I'd show you guys a cool way you can animate your videos using WAN 2.2, WAN 3.0, and Higgs Field's new Ginjutsu operating system. 

Super cool stuff and probably going to redefine the future of creator-led media, but what do I know? 

Chapters
00:00 I turned myself into an animated character
01:00 How video style transfer works
02:43 Astra as your production manager
04:39 10 animation styles to try
05:47 Setting up Higgsfield
07:05 Creating a style with GPT Image 2.5
08:31 Animating your footage with Genjutsu
10:22 Using style transfer for ads and content
12:00 Wan 3.0: a cheaper alternative
13:39 Automating the workflow with GPT-6-Astra
16:03 Resolution, credits, and silence removal
17:35 Self-hosting Wan 2.2 Animate
18:40 Reviewing the animated results
20:25 Learn more with Maker School

## Transcrição

This entire video is going to feature me
in an animated style. The reason I'm
doing this is twofold. First, I
genuinely believe this is the next level
of creativity for creators and people on
the internet like myself. Just like we
used to wear different clothes or adorn
ourselves with jewelry, so too is
modifying the actual style of a piece of
media going to be how we express
ourselves in the future. Two, I wanted
to show you guys how to do it. I'm going
to open source my approach to doing this
in a very scalable way. I'll show you a
consumer method that you can use first
that's easily available and requires
just an API call. And then afterwards,
I'll show you guys a pipeline that you
can use to optimize with GPT6 Astra and
other cuttingedge AI agents to allow
yourselves to generate these more
economically. I'm also going to give you
everything that you need to do this, all
the templates and the skills and
everything down below. Uh, so you guys
can just copy and paste this approach
into your own business or your own media
production pipeline, whatever the style
is. So, how exactly does this work?
Well, the actual flow all starts with a
normal recorded video. In the past, you
had to use complicated motion capture
systems and stuff like that in order to
achieve the stylistic transfers that I'm
showing you. But today, you don't have
to do any of that. You just record a
video without a lot of noise in the
background, ideally. Uh, as you guys
could see over here, I'm going to be
feeding in a source video that looks
something like this. My hair is going to
be a little bit different. And then the
background of the video is for the most
part um, you know, just like a white
wall. What we're going to do is
somewhere between the original recording
and the ultimate stylized look, we're
going to provide a style image. And you
can think of that style image as
basically multiplying, okay? You know,
my core recording times the style in
order to get the output. We've been able
to do this for a while now with um
images. And actually, one of the very
first things that I worked on back in
2019 was how to apply like image style
transfer uh to a variety of different
things, you know, like abstract art,
pictures of myself, and so on and so
forth. Uh but right now is the first
time we're actually able to do so
meaningfully with video. In a nutshell,
here's how it works. The core video is
going to supply motion. We're then going
to generate using GPT image 2.5 or
another model a style reference based
off of that initial image. That's very
important. Needs to be the same
character sort of just with some
stylistic additions. And then we're
basically just going to multiply the
two. Okay. Uh pass it through this
Genjutsu model and I'm going to show you
some alternative models that you guys
could optimize on your own hardware as
well for cost purposes if you wanted to
do this at scale like one three turbo
for instance. And then basically you
achieve this stylized clip. The way that
I've set up the system and there's
probably a bunch of people in the
comments that can come up with a simpler
and easier way is Astra basically acts
as the production manager. Um I've tried
using a variety of other models to do
this. I find that you need to provide it
a lot more like oversight if you want to
do a good job. So I'm just going to be
using Astra, but keep in mind maybe if
you give it the exact SOP, you guys can
do away with a cheaper model that might
use fewer tokens. Essentially, what GBD6
Aster is doing is it's going to plan,
run, inspect, and that's important, as
well as retry any failed gens because
this technology is not 100% yet. So, the
flow looks like this. We're going to
feed in a piece of footage, okay, the
core thing. We're then going to use a
model known as Genjutsu to apply the
style transfer. We're then going to
stitch the outputs together, and then
we're going to post-process them using a
tool called Solero uh voice activity
detection. and then ffmpeg. We're going
to do all this at a specific frame rate
to in my case give it like an animated
sort of stall and then at the end we
review and we export. As mentioned,
there's a fair bit of splitting going
on. And so really what's happening
because of the current limitations of
these uh models including Genjutsu is
you can't just feed in the whole video
in one go and be done with it. Uh the
models just you know aren't that good
yet. Maybe they will be in 3 to 6 months
from now but certainly not right now.
And so in order to achieve this you need
to take you know let's say a 5 minute
video and then you need to divide it
into chunks. So this might be kind of
your first 20 seconds here. This might
be your second 20 seconds here. This
might be your f your third 20 seconds
here and so on and so forth. And the
good news is you get the original audio.
um your model aka GPD6 Astra basically
is just going to inspect every join to
make sure there are no major
discrepancies between the genens because
as you'll see if you're feeding in
multiple runs to this Gin G2 model uh
and I'm going to show you guys an
alternative to that in a moment as well
uh you know sometimes the the
stylization is going to look a little
bit different from person to person. Now
the first thing you have to do in order
to make this work is you need some form
of style transfer. And so what I've done
here is I've taken a single frame of my
face and I've basically had GPT image
2.5 make a bunch of modifications to it.
So I'm going to run you through an
actual practical 021 of me like
literally making the prompt and then
generating the image in a moment. But
first I just wanted to show you what all
of these styles that you guys saw
earlier started as. You can see that one
was a 3D sort of painted style and
that's the one that I chose just cuz I
think it's pretty cool. This one here is
a prestige handdrawn anime. You can see
the original image was this and this is
sort of the stylized one. Next one was
that sculpted stop motion effect which I
think is super cool. Obviously it kind
of changed the fundamental nature of my
sweater but it is what it is. This one's
the ink and graphic novel. We have oil
painted animation which I thought was
really cool too. Uh watercolor/graphite
layered paper relief premium feature
film CG. Lean Claire animation. And I
don't actually know how to say that, but
I think the style is really cool. Kind
of reminds me of that Caner Reeves
movie. And then charcoal and chalk as
well. Okay, so we need to actually
generate these. That's sort of step
number one. So what I'm going to do
first is actually walk you guys through
how to do this. Probably the simplest
and easiest one-stop shop for all of the
stuff is Higsfield. These guys basically
have just aggregated every image and
video model known to man and stuck it
all in one place. And so that is what
I'm using here more for my own ease of
use than anything else. I'm going to
walk you guys through how to sign up and
then set everything up um right now too.
So you can do the same thing. And what's
really useful is they also host their
Genjutsu model which is the model that
I'm using initially as part of this
demo. Uh I'm going to run you guys
through some other software that you
could use at the end of the course as
well, but this is definitely probably
the easiest way of doing so. So they
have some sort of discount up. I'm just
going to click get my discount and then
um I'm going to go continue with email.
Make sure to actually agree to those
dang terms. Let's put in my email
address. I was previously running a
bunch of influencer campaigns with uh
you know user generated avatars and
stuff like that. Marketing, you know,
the skincare brands and so on and so
forth. That's what all of those are. Um
I don't think there's any way to remove
that. Or maybe there are and I'm just
being silly. But, uh, the way that this
interface works is basically over here
you have all of your previous
generations. And then right down over
here is where you feed in the image that
you want to modify. And so I'm going to
be using GPT image 2.5 Sunburst. That's
the model that I want. And then what I
want to do is I just want to feed in a
frame of my face. Okay, which I've
actually already done over here. As you
can see, I'm looking kind of silly. Um,
you can get this frame any way that you
want. I mean, you could literally just
take like a screenshot of any video that
you've recorded or something like that
in order to get it. Basically, you just
need a frame. Okay? And then afterwards,
you convert it to uh some sort of style.
So, I don't know. I think that like I
want a cool 3D style. Let's say convert
this to a cool 3D avantgard
stylized,
you know, style while making sure the
man's features and everything else about
him are recognizably him. Okay. So, the
idea is, you know, I want this to be
recognizably me. What you'll find a lot
of the time happens when you do stuff
like this is uh they come out and then
they're not recognizably you for
whatever reason. Uh you know, they like
try and make you look prettier than you
really are. I don't want to look
prettier, man. I want to look like Nick
Sarrive. So, let's give it a go. After a
few moments, an output like this is
going to show up. As you guys can see,
it's stylized with really big eyes, and
I kind of look like a cartoon character.
My hair looks pretty dope, though. I'm
not going to lie. This gives you some
ideas. Um, and now you have everything
that you need in order to actually do
the style transfer, assuming we also
have the core video. There's also this
one here where I'm kind of blocky, and I
really like the blocky one. Um,
actually, I think it looks really neat.
So, I'm going to give that a try. So,
I'm going to do is click download in the
bottom rightand corner. And now, I
actually have this on my computer so I
can continue with the next step. And
now, you need some sort of video of
yourself. So, I have a 50-second clip
that I recorded kind of just as a demo
cuz I wanted to show you guys how you
can do this on a short clip. Obviously,
short clips also keep costs low. Um, but
I'm going to run you guys through what
that whole process looks like here. The
only issue is I don't have my my
headphones, I think. So, that's one
thing I'm going to have to change, but
that should be okay. The model to choose
is video and then go to Higsfield
Genjutsu. This is the thing that allow
actually transfer the motion. And then
what we're going to want to do is we're
going to want to feed in that video with
the still that I showed you guys a
moment ago. My case, that's right over
here. I'm just going to feed that in.
And uh we do have to trim it to 30
seconds in order to use it just because
what we're doing here is a test and I
just want to show you guys something as
quick as humanly possible. Let me just
move it to when I start talking. There
we go. And then after that, we're just
going to add uh this here, which should
add the stylized
image after it's all ready to go. See,
we now have that sort of 3D version.
That's what I want to select. And it's
the same thing that I showed you guys
earlier with like a cool 3D effect. Last
thing we have to do is add a prompt. And
so the prompt I'm going to use here is
transfer the exact same performance from
the reference video. That's this to the
same man in the style reference image.
That's that. Preserve his recognizable
identity, face shape, curly hair,
stubble, gray sweatshirt, and black
microphone. Now, because I used this for
a previous prompt, it says the reference
image defines only the painterly 3D
rendering style and character design.
I'm just going to remove the painterly
and I just say 3D rendering style. But
the video is authoritative for head
movements, gaze direction, blinks,
mouth, mouth shapes, lip articulation,
shoulder movement, and so on and so on
and so forth. So you need So yeah, I
mean, I'm going to give you guys the
whole prompt below. You don't need to
read it all, but um we're basically just
saying, hey, keep it as simple and as
close as humanly possible to the
original gen. One of the reasons I think
this is so cool is because you can use
another model to actually generate the
video and then you can stylistically
flip it or style transfer the generated
video with a generated image style. And
so obviously I'm doing this here on my
own footage. And the reason why is
because I think this is genuinely like a
step towards all creators having their
own distinct visual style and so on and
so forth. But you don't have to, you
know, just use your own footage for
this. You guys could use whatever the
heck you want. This is extremely
powerful um for ads because a lot of the
time human beings according to where
they live and their culture and their
preferences and their markets, you know,
we like different things. And so I'm
imagining a future where you basically
just have one ad, you know, a video ad,
and then you flip it such that it's like
20 or 30 different styles, split test
all of these futures, determine which
one delivers the larger return on
investment, say if it's like a like a
30-second ad or something like that, and
then just put all your money in in in
that basket, uh, instead of spreading
them all out like most people do. And,
you know, somebody that's run a fair few
number of ad campaigns myself now, I
could see something like that working
really well. And that's where the
economics of video production start to
actually make sense. You know, right now
this costs uh what 32.5 credits in order
for me to do this on a 5-second run. I
think mathematically if you were to do
this for like a 20 second run, it'd be
like 130 credits or so. You know, 130
credits is not free, right? Like
obviously you're spending money for it.
But if you do this across, let's say, a
number of ad sets and then it helps you
determine what one specific ad that has,
I don't know, 1.5 to 2x the conversion
potential because that style just
happens to be in or it's much more
visually appealing. uh then you make
your money back on that in the first few
clicks that you get. Now, it's worth
noting you can use the exact same
approach that I talked about here, but
then use Juan 3.0. Uh Juan 3.0 is
basically just another model that's very
similar to Kenjutsu and probably related
to what they're doing underneath the
hood, but um it's just the core
intelligence. And so what I did here is
I actually ran a test of one 3.0 where I
fed in this reference image as style and
then this video over here as the actual
motion. Then I said transfer the exact
performance from the reference video to
the same ad the style reference image.
And you can see for the same length of
time rather than it taking uh over 32.5
tokens or credits I think it was before
now we're down to 9 at 720p at 16x9. And
so the quality is not the exact same but
it's still pretty good. Occasionally a
transfer will fail and so this was one
that failed with Higsfield Genjutsu over
here. uh when that happens, you have all
your credits refunded and then you know
it's just because of usually some
mathematical aberration, not because you
got censored or whatever, unless you're
using a copyrighted character, in which
case you probably will get um but then
anyway, you can just click retry and
it'll rerun it. By the way, I'm going to
show you how you can do this not just
manually, but also with GPT6 Astra in a
minute. I want to make sure to build out
the logic so you guys know the steps you
have to follow in order to get it done.
Okay, and you guys could see we've
actually now finished it and the results
are pretty cool. We animated just like
this. And the reason I'm making it is
because I think this is a Right. So, an
important thing to note here is we just
did this for 5 seconds. Um, aside from
that though, it did pretty well. I mean,
it captured virtually all of my
features. It captured like everything
about my apartment room. And, uh, I also
had some pretty cool sexy lighting. What
I did now, if you think about it, is I
just showed you guys how to go from zero
to an actual generated version of the
output. Um, that's really cool and
pretty straightforward to do. But if we
want to, you know, go beyond just
generating like a 5-second or a
10-second or a 20 second stitch, we kind
of have to level up. And I could sit
here doing it all manually, or I could
just give it to GBT6 Astra to do it for
me. So, how do you do this in codeex?
It's fairly straightforward. Um, all you
need to do is, you know, open up the
codeex app, which you guys can download
from chatgbt's website. It looks
something like this. Then give it a
folder to work in. In my case, I'm
working in the example folder. and then
just, you know, make sure that it's it's
inside and can hear what you're saying.
Um, my model picker here is set to GPT6
Astra high. Obviously, you guys can go
as high as you possibly want. I'm not
going to go much higher than that. Then,
if you guys head over to Maker Zero,
which is the second link in the
description, um, it'll have links to
full prompts with everything you need in
order to stylize your videos, just like
how I showed you guys a moment ago. The
one that you guys are going to want is
this Genjutsu prompt. Uh, assuming you
guys want to do the Genjutsu workflow.
And then from here it'll say, "You are
Astra, my a video production operator.
Help me turn a recording of myself into
a polished animated or illustrated video
using the Higso plugin and Gutsu from
the work with the available tools." And
so on and so on and so forth. So all you
have to do is just double click this and
then go back into codeex and then paste
it in. It's already reading the pasted
requests. And what it's first asking for
is for me to attach my video or to give
it its accessible local file path. So I
have my minute long video here. I'm just
going to drag and drop it right in. And
I'll say here's the video. Now, it's
going to use that to come up with that
cool file path. Uh, as discussed
earlier, once it's done, you then have a
little snippet or a frame that it's been
taken from. It defaults to painterly 3D
animation, which is the style that I'm
using for this video, but obviously you
guys can do it however you want. Um, I'm
going to say yes, use that default just
because I think that style is super
dope. And now it's what it's going to do
is check Higsfield's available models,
account status, and pricing before
preparing the quote. It's then going to
send the media to Higsfield. And then
the benefit is it's even going to ask
you if you guys are open to spending X
credits for X result. The reason why I
put that in there is cuz I just wanted
to make sure that there was a
verification step before you continued.
Now, if you don't have Higsfield
connected as a plugin, this is where
you'd have to add it. The way to do that
is really straightforward. Just head
over to plugins up here, type Higsfield,
click the that puppy, and uh click the
install plugin button. So, if you don't
already have that in, what this will do
is install the plugin for you. After you
run that OOTH, it'll then verify what
account you are part of. In my case,
that is my account. And now it's
actually going ahead through the uh
production range. So here it's giving
you some options. It's saying, do you
want to do the full 60 seconds, a short
test only, or specific range? You know,
what sort of resolution do you want? Do
you want 1080p, 720p, 480p? Do you want
to keep the original pauses, cut pauses
longer than8 seconds, point4 seconds, or
a custom threshold? Uh, what I'm going
to say here is, you know, let's do the
full 60sec. We'll do 720p. Actually, I
don't want this to take forever. Maybe
we'll do 480p. No, we'll do 720p. And
then I'll cut anything longer than um
0.5 seconds. Okay. The idea there is,
you know, that's just what you need to
get one of those cool YouTube jump cuts.
And that's obviously my my style. It'll
get back to you with information on how
long the uh tests are going to take
creditwise. Um, it's going to run the
painterly 3D still just using Nano
Banana Pro to save on credits. And then
as mentioned, I mean, this is just one
way I could do it. I'm using u Ginjutsu
here, but you guys could also use Juan
3.0 if you wanted and then cut that down
an order of approximately three, so it'
be a little under 100. If you wanted to
do that, you go down to one 3.0 prompt
for Higsfield. Um, so just going to copy
that in and then I'd paste that back
into codeex just like I did a moment
ago. It's gone ahead and actually
generated that style for me. As you guys
could see here, style is a little bit
different from the example that I
provided earlier. Um, obviously every
time you generate an image, it's going
to be a little bit different, but that's
still pretty cool. Um, so now I'm going
to say yes, go ahead and do the whole
thing, all 60 suck, then join and serve.
Now, worth noting, you could actually
download a slightly dumber version of
this one, 2.2 animate 14 bill, and run
it locally on your computer. Um, and so
that's actually what I have done with uh
this inference pipeline because I've
tried to optimize this to run basically
as quickly as humanly possible. So while
these big providers are obviously
fantastic at getting you up and running
and then showing you guys what is
possible, you know, if you did want to
serve like a dedicated pipeline and do
this really quickly across all your
videos, you can just direct an agent
over to this link right over here,
huggingface.co/w-ai2.2-animate-14
bill. And uh you guys can actually
legitimately download this and then do
whatever the heck you want with it. You
can run it on other hardware as well.
I've experimented with a variety of
different ways of doing this. If you try
to run it on your own, it could take a
fair amount of time as well. Just keep
that in mind. But yeah, that is that is
how you do it. Um it basically just
allows you to animate and then replace
characters. And you can do whatever
sorts of optimizations that you want to
it to to make it even faster. So,
obviously I don't want to make this
super ridiculously technical, so I'm not
going to include information on all of
that here, but um wanted to make sure
that you guys understood that was an
option as well. Okay. And first clip is
in. This whole video is going to be
animated just like this. And the reason
I'm making it is because I think this is
a glimpse into the future of what most
media will look like. Not this exact
style, obviously. I'm not going to be
painted in this exact way, but pretty
soon we're all going to have the choice
to consume media in format. And as you
guys can see, there were some minor
issues with like mouth movements and
stuff like that. I find that this tends
to happen when um you're using kind of
more stylized artistic forms like this.
You know, I'd say the one that I'm using
right now is also quite stylized and
artistic, but this one seems a little
bit softer around the edges, and I think
that's probably impacting its ability to
like track my mouth. Um, also, I don't
know, maybe I'm just not really moving
my mouth around that much. Still pretty
reasonable quality, I would say. Okay,
so I'm going to wait for it to continue
and uh make the other two segments and
then combine them all. So that's one
reason I wanted to do that. The second
reason is I want to show you guys how to
do it. Naturally, this was done using
GPT6 Astra as well as a couple of cool
cool. So hopefully it's clear. Um you
know, this has now interpreted and
inherited the style that I trained it
on. And uh yeah, you guys could do
something like this yourselves. You guys
could upload this to YouTube. You guys
could clip this in a variety of
different formats. I genuinely think
this is probably one of the simplest and
easiest things that anybody could do
right now to make themselves
fundamentally stand out. Although
obviously there's some packaging things
that I think people need to get right.
If you have any questions whatsoever,
just leave them down below. I actually
get my ideas for future videos from
comments from people just like yourself.
Um really excited at I think the next
phase of, you know, human creativity in
media. We're seeing some extremely cool
breakthroughs in AI virtually every day.
And I thought this would be a cool way
to expand that conversation. [gasps] If
you guys like this sort of thing and
want to learn how to monetize your own
media production, automation business,
um, AI service business, or anything
that has to do with the technology that
you saw today, definitely check out
Maker School. It's my 90-day
accountability program that shows you
everything you need to do in order to
land your first customer. By the end of
that 90 days, if you have yet to acquire
your first customer, I actually give you
all your money back. So, it's 100% money
back guaranteed. Anywh who, thank you
again and I'll catch everybody here in
the next
