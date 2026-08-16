---
titulo: 'This Skill Turns Fable 5 & GPT 5.6 Into Web Design MONSTERS'
tipo: item_agregado
plataforma: youtube
canal: 'Chase AI'
data: '2026-07-11'
url: 'https://www.youtube.com/watch?v=KBH8P0z2AL8'
thumbnail: 'https://i.ytimg.com/vi/KBH8P0z2AL8/maxresdefault.jpg'
descricao: '🚨Higgsfield: https://higgsfield.ai/s/cli-chase-h-ai-ejnlNI ⚡Master Claude Code, Build Your Agency, Land Your First Client⚡ https://www.skool.com/chase-ai 🔥FREE community🔥 https://www.skool.com/chase-ai-community 💻 Need custom work? Book a consult 💻 https://chaseai.io Animated websites just became extremely easy with this skill that works with both claude code and the new GPT 5.6 Sol. ⏰TIMESTAMPS: 0:00 - Intro 0:57 - The Skill 2:36 - Demo 9:18 - Outro RESOURCES FROM THIS VIDEO: ➡️ Master Claude Code: https://www.skool.com/chase-ai ➡️ My Website: https://www.chaseai.io ➡️ Scroll World (mine): https://github.com/cth9191/scroll-world ➡️ Scroll World (original): https://github.com/oso95/scroll-world #claudecode'
resumo: 'O vídeo mostra como usar uma "skill" de animações de scroll (baseada no Scroll World) com Claude Fable 5 e GPT-5.6, através do MCP da Higgs Field, para gerar imagens e vídeos que, convertidos em frames com FFmpeg, criam sites com animações de scroll fluidas. Explica ainda o processo técnico por detrás, incluindo a coerência entre cenas e melhorias como escalões de orçamento e otimização para mobil...'
tags: {}
fontes:
  - 'https://higgsfield.ai/s/cli-chase-h-ai-ejnlNI'
  - 'https://www.skool.com/chase-ai'
  - 'https://www.skool.com/chase-ai-community'
  - 'https://chaseai.io'
  - 'https://www.chaseai.io'
  - 'https://github.com/cth9191/scroll-world'
  - 'https://github.com/oso95/scroll-world'
---

## Descrição

🚨Higgsfield: https://higgsfield.ai/s/cli-chase-h-ai-ejnlNI
⚡Master Claude Code, Build Your Agency, Land Your First Client⚡
https://www.skool.com/chase-ai

🔥FREE community🔥 
https://www.skool.com/chase-ai-community

💻 Need custom work? Book a consult 💻
https://chaseai.io


Animated websites just became extremely easy with this skill that works with both claude code and the new GPT 5.6 Sol.


⏰TIMESTAMPS:

0:00 - Intro
0:57 - The Skill
2:36 - Demo
9:18 - Outro

RESOURCES FROM THIS VIDEO:
➡️ Master Claude Code: https://www.skool.com/chase-ai
➡️ My Website: https://www.chaseai.io
➡️ Scroll World (mine): https://github.com/cth9191/scroll-world
➡️ Scroll World (original): https://github.com/oso95/scroll-world

#claudecode

## Transcrição

This entire website was one-shot by
Claude Fable 5 in a single skill. It's
not just one scroll animations, it's
several scroll animations that have been
clipped together to give you a smooth,
high-quality, coherent experience. This
is absolutely wild. This is an extremely
premium product and I'm going to show
you the skill that lets you do all of
this. On top of that, I will be showing
you how I not only do this inside of
Claude Code, but will be demoing the
exact same thing inside of the brand new
GPT-5.6
Soul. Because why stop with just one AI
tool these days? And this thing is
really cool. Like I said, if you try to
do this manually without this sort of
scroll animation skill I have found and
improved, you know how difficult it is.
You're making multiple videos, you're
getting the starting frames, you're
trying to make a match up, you're taking
all the frames out one by one by one.
It's a pain in the butt and this makes
the entire process extremely easy to
execute. Now, the real credit for the
skill goes to Peter Wing. He's the one
who made the original version. I found
it on Twitter and he was nice enough to
open source this. So, his Scroll World
will be linked down below so you can
take a look at it. Now, what I did is I
forked this thing and I made a few
improvements, namely introducing budget
tiers so you can do this without having
to spend as much. We don't necessarily
need six different scenes for our
website. And I included a lot of
improvements on the mobile version of
this and sort of SEO related stuff. Now,
the way these scroll animations work big
picture is we start with a single image.
That's what you see right here. We then
take that image and we turn it into a
video. This scroll animation at one
point was a video. But we then take all
the individual frames out of the video
using FFmpeg and then we associate that
with a position that you, the user, are
at on the website.
Now, there's a lot more going on behind
the scenes to make it a smooth
experience and so it isn't completely
janky. And on top of that, we're making
sure all our scenes sort of reference
each other when we build them. That way,
they all look coherent. Like this all
looks like it was from some singular
story. As for how we're actually
generating the images and videos, we're
doing that through the Higgs Field MCP.
And we can do this both with Claude code
and Codex. We never have to leave the
terminal. It's calling all those image
models, namely GPT image 2, and all the
video models, namely Seed Dance, through
that MCP or the CLI. Now, to connect
those tools to your AI agent of choice,
you're just going to go to Higgs Field,
you'll go to MCP and CLI, and you're
just going to follow these steps. You're
just going to copy the URL for the MCP
and then do the authentication process.
The only thing you need after is the
skill itself. And I will have my version
linked down below as well. You can just
copy the URL, give it to Claude code or
Codex, or you can do it as a plugin.
Either way works. Now, as I said in the
intro, I'm going to be demoing this
inside of Codex with a brand new Soul
5.6, but this is what it looked like
inside of Claude code. It's the exact
same process. You go through an
interview, you tell it what sort of
website you're trying to build, what you
want each scene to be, and it's going to
walk you through step by step, and it's
going to make sure like, "Hey, do you
like this image?" before it actually
goes and creates all the videos. You
don't just run the skill and all the
videos just show up and you have no sort
of like say in the process. So, it
really holds your hand throughout the
entire thing. So, inside of Codex, I
have called the Scroll World skill. So,
I just did {slash} Scroll World, and I
said, "I'm trying to create a website
for a boutique Japan travel brand, and
the general art direction I want is
we're going to kind of go with like an
origami style." Now, if you don't know
what style you want, you can just have a
back and forth with AI, and it will kind
of ask you several questions. I already
knew ahead of time kind of what I
wanted, so
don't worry if you don't have an exact
idea when you show up. Now, after you
give it some creative direction, then
it's going to ask you, "What do you want
to do in terms of the budget, and what
do you want to do in terms of mobile?"
Budget-wise, it's saying, "How many
scenes do you want?" For reference, this
was six scenes. This is a significant
amount of video, and it's actually a
pretty long journey from beginning to
end. This is probably overkill for most
people. On top of that, this is going to
cost you a lot of Higgs Field credits.
It cost me about 800 at the end of the
day. So, if you're not on one of the
more expensive plans, you probably want
to stick with four scenes, I think is
like a great sweet spot. The next is
what you want to do with mobile. So, you
can do everything from just sort of like
cropping it safely all the way to a full
portrait chain where it's going to
create extra videos specifically for
mobile. So, you have some room to work
with like how deep you want to go on
that front. But, this video we're just
going to do lean and crop save. So, it's
sort of the cheapest options. And I
understand you can do less scenes as
well. You can get it down to three. This
is just what it offers as a default. It
then is going to take you through the
journey proposal and say, "Hey, this is
what each scene is going to look like."
And it's like, "This is how many
generations it's going to take." So,
it's going to take total four image
generations, four scene dive videos.
There needs to be a connector between
some of the videos and some cross fade.
So, total it's saying nine generations.
And it gives you an expected generation
time, which usually pretty conservative.
It doesn't usually take that long even
though we're talking about AI video. And
so, once you approve that, the next
thing it's going to do is generate the
anchor. The anchor is going to be that
starting frame. And we talk about that
starting frame, we're talking about
something similar to this in the
background. It's just going to be a
still image and it's on you now to be
like, "Okay, I like this. This is the
art direction that's going to dominate
throughout." Or, "No, let's change some
pieces." This is the part you really
have to nail because if you don't like
it and you say yes, everything's going
to be based on it. So, here's a look at
the image it created and I like it. Now,
you probably realize this already. If
you were you doing all this through
Codex and through Soul, well, it has
image generation. We don't need to send
any request to Higgs Field to create
images with GPT image 2 when we natively
have that ability inside of Codex. So,
you can have Codex
do that natively, but you still are
going to have to send it to Higgs Field
for the video generation. On the flip
side, if you're doing this in Claude
code, obviously it doesn't have image
generation. So, Higgs Field has to carry
the load the entire length of the way.
But,
here's the image. I like it. So, what
are we going to do? We're going to say,
"Hey,
this is great.
Can't type.
Move forward."
So, after 32 minutes, it's completed our
website. We got four different scenes.
It took 10 generations, and now it's up
on our localhost ready to go. So, let's
take a look. So, here's our Japanese
tour website. What we're going to do is
we're just going to scroll through it
and kind of just get a once-over. So,
got people traveling, got the subway. We
move here to the house. It opens up.
Then they go to the hotel. It unfolds.
Kind of cool.
And then we have the bird over here,
which starts to fly. So, overall, what
do I like? What do I don't like? I like
the visuals. Like, I think this looks
super cool. You know, I think
individually within each scene, I like
how each individual scene progress.
Like, this part where the subway comes
in,
awesome. My problem here is the
transition. When we go from scene one to
scene two, pretty hard cut. Maybe you
want a hard cut. Kind of I guess that's
there's some creative direction there.
But, that's something we could change.
Same thing here. As we go from scene
two,
incredible detail, to be honest. But, as
we go from scene two to three, again,
another hard cut. Versus scene three to
four, I think this is an awesome
transition. So, this opens up.
Boom, they're in the hotel. But, if you
watch in the background,
notice the scene number four like coming
into frame. Like, at first it's blurred,
pops up, gives like very much like this
3D effect, and gives depth to the
website, and then it seamlessly
transitions
to scene four. Like, that is a great. If
it had that
going from scene one to two and from two
to three, I would be super happy. Um
that's my primary complaint with this.
But, everything else looks good, and
being able to add those sort of scene
transitions and smoothing out, I don't
think it would be too difficult. Because
if you compare that generation, which
again was Soul GPT 5.6, to what we
created in Claude Code, The scene
transitions are definitely a bit more
sleeker here. So, this is scene one.
Right? So, like these things push out of
the top and it is like a seamless
transition into those being like well,
as close to seamless as you get to those
being like wires here.
Same thing right here. We go in. Oh,
boom. It opens up and then we just go
over the top to the next scene. So,
scene to scene with the Fable 5
transitions, I think we're better. Same
thing here. Boom. We're here. Zoom out.
Next scene. So,
that's my chief complaint. I would
definitely give Fable 5 the edge versus
Soul 5.6 here. But, like this is a
one-shot, right? Like with a single
skill. Like I'm very impressed in
general with how well the skills been,
you know, created. This is awesome. Now,
the next question becomes, okay, this is
a visual spectacle.
Can we make this into a website that's
more functional depending on what you're
trying to do? And I don't think that's a
problem at all. I think the technical
problems of creating an animated
scrolling website like this that looks
coherent and doesn't look janky and
doesn't look low quality is pretty
difficult. You know, if I asked you to
just do this on your own, I think you'd
have a lot of trouble and take a lot of
time to get there. With this sort of
base, I think it's really easy to then
like, okay, like what are we doing with
the text? What are we doing with the
CTA? How are we actually pulling the
user from the hero all the way to like
filling out some sort of form? I don't
think that's too difficult. I don't
think we're like,
you know, having to reinvent the wheel
there. That's what every single website
does. And being able to put that on top
of what we have here, again, with Fable
5 and with 5.6 is not a challenge
whatsoever.
And the fact that this is just one skill
in a one-shot is kind of mind-blowing.
Again, if you want to do this on your
own,
3 months ago, 6 months ago, 9 months
ago, it's kind of a pain in the butt. So
again, shout out to Peter Wang for the
original version of the skill. Both
versions, both mine and his, will be
linked at the bottom of the description.
And like you saw, this is like super
straightforward. You really don't have
to do anything. You just need to show
up. You need to invoke the skill. You
need to give it some sort of creative
direction and then it's just going to go
to work. So,
that is where I'm going to actually
leave you guys for today. Let me know as
always of what you thought about this in
the comments. Make sure to check out
Chase AI Plus if you want to get your
hands on my Cloud Code Masterclass and
sort of more AI lessons just like this
one. And besides that, I'll see you
around.
