---
titulo: 'I Tested GPT-6 Astra for Game Dev (the results are scary)'
tipo: item_agregado
plataforma: youtube
canal: 'Duncan Rogoff | Learn Claude Code'
data: '2026-09-09'
url: 'https://www.youtube.com/watch?v=ZaSmWI0s22M'
thumbnail: media/monitoring/youtube/zasmwi0s22m.jpg
descricao: 'Learn Claude Code + all my resources. Only $9. 👉 https://www.skool.com/claudecodeclub Summary ⤵️ Open AI GPT-6 Astra is the best AI model on the planet, and a brand new free open source repo turns it into a triple-A game studio. I used it to build a fully playable zombie survival game in under an hour, and I barely did anything. FREE Dream Loop Repo 👉 https://github.com/achimala/dream-loop ⏱️ Video Timestamps: 00:00 - GPT-6 Astra AAA games 00:23 - Dream Loop skill explained 00:58 - How the critic loop works 01:18 - What you need to start 01:37 - Installing the Dream Loop skill 01:58 - Dream Loop workflow breakdown 02:34 - First game prompt formula 03:12 - Copy-paste game starter prompt 03:29 - Zombie wave survival concept 03:48 - Improving the game design 04:22 - Room progression and ext...'
resumo: 'The video walks through using an open-source "Dream Loop" agent skill with GPT-6 Astra to generate games, where the AI creates a target concept image, builds toward it, and a critic agent iteratively compares screenshots against that target until quality goals are met. It covers the setup requirements (an agent with image generation and sub-agents, plus optionally Blender) and demonstrates install...'
tags: {}
fontes:
  - 'https://www.skool.com/claudecodeclub'
  - 'https://github.com/achimala/dream-loop'
---

## Descrição

Learn Claude Code + all my resources. Only $9.
👉 https://www.skool.com/claudecodeclub

Summary ⤵️
Open AI GPT-6 Astra is the best AI model on the planet, and a brand new free open source repo turns it into a triple-A game studio. I used it to build a fully playable zombie survival game in under an hour, and I barely did anything.

FREE Dream Loop Repo
👉 https://github.com/achimala/dream-loop

⏱️ Video Timestamps:
00:00 - GPT-6 Astra AAA games
00:23 - Dream Loop skill explained
00:58 - How the critic loop works
01:18 - What you need to start
01:37 - Installing the Dream Loop skill
01:58 - Dream Loop workflow breakdown
02:34 - First game prompt formula
03:12 - Copy-paste game starter prompt
03:29 - Zombie wave survival concept
03:48 - Improving the game design
04:22 - Room progression and extraction
04:47 - Generating the visual target
05:13 - 57-minute build breakdown
05:34 - Playing the finished game
06:20 - Blender setup and MCP

## Transcrição

GPT-6 Astra is the best AI model on the
planet and there is a brand new free
open source repo that allows you to turn
GPT-6 Astra into a AAA game studio. I
used it to build this game right here in
under an hour and I barely did anything.
So, let's break it down. So, I came
across this post on X by this guy named
Andrew and he said, "I turned my Astra
game into a skill. It's called Dream
Loop. Astra dreams up the AAA version of
your game then it loops until it gets
there." And he made this like absolutely
beautiful game. He said he did it in one
shot in one hour and only hit 2% of his
weekly quota and didn't even use any
external assets. So, I wanted to dive
deeper into this. You may recognize this
guy from my last video where we created
some premium mobile apps from just a
couple of prompts. He's a former Y
Combinator founder and a product design
lead at Apple. So, this is Dream Loop.
It's totally free and basically it's an
agent skill that builds a game, app, or
scene with impressive visuals by
creating a closed loop. So, AI dreams up
a high quality target screenshot using
image generation. It then builds with
this target in mind and then a separate
AI critic compares the live screenshot
to the target and provides feedback. The
AI will then loop back to step two until
the critic is satisfied. There's a
couple of things that you need and I'm
going to walk you through how to set it
all up. So, you basically need an agent
with access to image generation. If
you're doing this inside of ChatGPT with
Codex, you already have access to GPT
image two or the latest GPT image 2.5
which just dropped. And you want to use
something with sub agents. And if you
want, I'm going to show you how to
install Blender which is a free 3D
design software which ChatGPT has access
to. So, I'm inside of the ChatGPT app.
I'm using GPT-6 Astra with a high level
of effort. And I basically just gave it
the link to the GitHub repository for
the Dream Loop skill and I just said,
"Can you install this skill?" So, if
you're ever unsure of how to install a
skill, it's literally that easy. And
then one other little tip is you don't
just have to take my word for things.
Like you can literally ask ChatGPT how
things work. So now that it's installed,
I just said, "How does Dream Loop work?
What can I do? How can I ideate a game?"
Right? Just to try to figure out how to
start using this thing. So it says,
"Dream Loop gives the build a visual
target, then keeps improving it against
that target. You describe a game, app,
or a scene. It generates an image of
what the finished result should look
like. You approve the direction. It
builds a working version and takes a
screenshot. A separate critic agent
compares the screenshot with the target,
and then it improves the composition,
lighting, materials, and details until
it reaches an acceptable score and frame
rate or hits a limit." So it says you
can use it for a playable browser game,
an atmospheric world to explore, a
racing or puzzle prototype, an
interactive product demo, or improving
the visuals of an existing project. It's
cool cuz it actually gives me some tips
on how to start my first game. So it
says, "For your first game, start with
one satisfying action in one beautiful
location. Design the first 30 seconds of
play before imagining the whole game."
And then it even gives me this formula
to use. "You are a character trying to
achieve what goal by what repeated
action, but something gets in the way.
And what overall does the mood of this
world feel like?" So for example, you're
a lighthouse keeper collecting washed up
fuel during a storm. Keep the light
burning, but every trip onto the beach
risks getting caught by the rising tide.
And then here's a prompt that you can
copy and paste. Help me ideate a small
browser game. Ask me one question at a
time about the feeling, setting, and
main action. Then propose three distinct
concepts and recommend the strongest.
Scope it to one location, one core
mechanic, and a playable prototype. Once
we choose, use Dream Loop to establish
the visual target." So take a screenshot
of this, drop it into ChatGPT, and start
building your game. So I had this idea.
I said, "You're a marine trying to fend
off a zombie horde. The waves get
increasingly more difficult, and you can
get power-ups along the way." Then I
asked, "Does this make sense? How can we
make it better?" So if you're a gamer,
you've probably played this style of
game before, but ChatGPT had a way for
us to make some improvements. It said,
"This is a solid wave survival game. The
biggest improvement is give the player a
reason to leave safety. Otherwise, the
winning strategy can just become
standing in a corner and shooting. So
then it says, "Between waves, you have a
short window to grab supplies and
prepare for the next round. Zombies drop
upgrades that force you to push into
danger." Couple of things that would
make it stronger like enemies changing
tactics, different types of power-ups
depending on how you fight, and then the
arena creates different decisions. And
there's a finish line, some target,
something you are trying to get to. So
if you spend a little bit more of your
time planning up front, then you can
really just hand everything off to GPT-6
Astra and let it go to work. I literally
walked my dog. So I had some ideas, what
if you have to progress through a map?
After each wave, you unlock a new room
in the location which may have different
qualities and different enemies. One of
the things you can do is use materials
in the room to block potential zombie
entrances like windows and doors until
you reach the extraction point. So now
it has a strong shape of how this game
should play. Enter a room, prepare,
survive the wave, salvage an upgrade,
and then push towards the extraction.
Then it created ideas for the different
types of rooms we should move through
and I just said that's great, use
DreamLoop to build it. So remember, the
first thing it does is it creates this
visual target. Like this is absolutely
beautiful. So it used GPT image two
probably before 2.5 just dropped to
create this render of what the game is
going to look like and this is pretty
freaking cool. Like I'm interested in
playing this. So if we come down here,
we can see this worked for 57 minutes
and 4 seconds. So just under an hour to
create the entire prototype of the game.
Created all the movements and
interactions, all of the zombie
mechanics, and all of these things. And
what's cool is you can actually click
into this to see how GPT-6 Astra went
through the process. It created the
entire environments, all of the
characters, all of the game mechanics.
It went and actually tested and iterated
and took screenshots and videos of the
game itself to verify that it actually
looked good and played well. And so if I
open this up and go full screen here,
you can see this is the game that it
created and it looks a lot like the
reference image. I do think the textures
and materials aren't quite as good as
that render, but honestly for a first
pass, this is pretty awesome. Like I can
move around with the arrow keys. I can
come over here. I can hold the E key to
gather supplies. And as these zombies
start to approach, I can just click and
hold to start shooting. And so some of
the mechanics, maybe of kind of how they
die and flatten is like a little retro,
but I think it's okay. Then you actually
go ahead and you can pick any of your
upgrades or things like that. And the
next round begins. And you have 10
seconds to prepare. And so if I want to
come over here, I can press E to
actually take those supplies that I
gathered and block up this window. So
pretty cool. So let me show you how to
connect this to Blender so you can build
this yourself. So ChatGPT actually
created this last outpost folder for us
for our game. It has all of our models,
all of our textures. Like I can come in
here. I can drop in our little marine
character into Blender. Can click import
and you can see if I get rid of this
cube over here, we have our little
marine. If we go into the shading view,
we can see that this is what he's going
to look like fully rendered. And of
course, we can tell ChatGPT any changes
that we want to make. Blender is super
easy to install. I had never installed
this before. I just searched for Blender
inside of Google and I came to the
website here and you just go up to the
top, click download, download this to
your machine and install it and you are
ready to go. Absolutely free and super
easy to use. Likewise, in the Blender
search, you can see there's this MCP
server page and this is how you connect
ChatGPT up to Blender. So all you have
to do is drag and drop this little
add-on here over into Blender. You
literally just drag it in and it's going
to check for any updates. You're just
going to click okay and allow it to
access some of the settings. See, mine
is already installed. And then you'll
have the MCP connected. And there's one
last step, which is if you come into the
MCP server client down here, I just went
to these setup instructions. If again,
if you don't know how to install
something, if you come down here and
this seems confusing or whatever, you
can always just like copy this link,
come over into ChatGPT, paste this in
and say like, "Yo, install this."
Literally, that is it. There is so much
more that you can do with GPT-6 Astra.
If you want to hear the real story
behind OpenAI and this model, check out
this video right here. I'll see you over
there.
