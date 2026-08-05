---
titulo: 'Claude Skills + Hermes Agent = 24/7 Agents'
tipo: item_agregado
plataforma: youtube
canal: 'Charlie Automates'
data: '2026-06-15'
url: 'https://www.youtube.com/watch?v=UWhc6Uwkl-c'
thumbnail: 'https://i.ytimg.com/vi/UWhc6Uwkl-c/maxresdefault.jpg'
descricao: 'Work With Me Directly To Scale Your Business With AI: https://charlieautomates.com/charlie-os-vs/ ———————————— Join my community with 3,300+ business owners (choose premium for weekly calls): https://www.skool.com/cc-strategic-ai/about ———————————— I hope you guys enjoyed this one, all the resources are attached below. Now, this is where the future is heading and without these skills you will fall behind. I say that for business owners/employees alike. But this is my current method of creating 24/7 Cloud Based agents for my business directly from Claude Skills. Take heed, enjoy the content, and start building! 🔑 Additional Resources: • Pt. 1 of this series (Create Claude Skills in 10 minutes with SkillSmith): https://youtu.be/FOvN28l_q9s?si=MUefdH8qIbkGDn71 • Hostinger KVM 2 Purchase Link...'
resumo: 'Este vídeo mostra como transformar uma Claude skill num agente de IA a funcionar 24/7 de forma autónoma, sem intervenção manual. Apresenta e compara três abordagens — o Hermes alojado num VPS (com foco detalhado na sua configuração de A a Z), os agentes geridos da Claude na cloud e um workflow no n8n — explicando as vantagens e limitações de cada uma.'
tags:
  - entrepreneur
  - money
  - business
  - education
  - development
  - entrepreneurial
  - 'Digital Business'
  - ai
  - replit
  - claude
  - 'chat gpt'
  - 'go high level'
  - gohighlevel
  - businessgrowth
  - artificialintelligence
  - startup
  - aitools
  - automation
  - salesfunnel
  - funnelbuildr
  - digitalconsulting
  - marketingautomation
  - businessconsulting
  - aiforbusiness
  - aiconsulting
  - techentrepreneur
  - futureofbusiness
  - aihustle
  - 'ai funnel builder'
  - 'replit tutorial'
  - 'gohighlevel alternatives'
  - 'ai automation tools'
  - 'build sales funnel with AI'
  - 'Ai for entrepreneurs'
fontes:
  - 'https://charlieautomates.com/charlie-os-vs/'
  - 'https://www.skool.com/cc-strategic-ai/about'
  - 'https://youtu.be/FOvN28l_q9s?si=MUefdH8qIbkGDn71'
  - 'https://www.hostg.xyz/SHJVa'
  - 'https://www.hostinger.com/support/how-to-get-started-with-hermes-agent-on-hostinger-vps/'
  - 'https://openrouter.ai/'
---

## Descrição

Work With Me Directly To Scale Your Business With AI: https://charlieautomates.com/charlie-os-vs/
————————————
Join my community with 3,300+ business owners (choose premium for weekly calls): https://www.skool.com/cc-strategic-ai/about
————————————

I hope you guys enjoyed this one, all the resources are attached below. Now, this is where the future is heading and without these skills you will fall behind. I say that for business owners/employees alike. But this is my current method of creating 24/7 Cloud Based agents for my business directly from Claude Skills. Take heed, enjoy the content, and start building!

🔑 Additional Resources:

• Pt. 1 of this series (Create Claude Skills in 10 minutes with SkillSmith): https://youtu.be/FOvN28l_q9s?si=MUefdH8qIbkGDn71 

• Hostinger KVM 2 Purchase Link (For Hermes): https://www.hostg.xyz/SHJVa
COUPON CODE: CHARLIEAUTOMATES

• Hostinger/Hermes support doc: https://www.hostinger.com/support/how-to-get-started-with-hermes-agent-on-hostinger-vps/

• Openrouter (AI Models for Hermes): https://openrouter.ai/


Timeline:
00:00 From Claude Skill to 24/7 Agent
00:19 Three Automation Options
01:42 Why Hermes on VPS
02:33 4 Part Breakdown
03:02 1. Deploy Hermes Hostinger
04:25 2. Connect OpenRouter Models
04:54 3. Hook Up Slack Chat
06:54 4. Import Skill via SSH
09:07 Add MCP and Schedule Cron
10:04 Live Run Results Wrap
10:21 Final Thoughts

## Transcrição

Last video, you created a Claude skill
in 10 minutes. Only problem, it only
works when you type in the command. So,
today we're going to fix that. I'm going
to show you three ways to make them run
as your personal 24/7
AI employees. No babysitting, just
watch. Here are my personal
recommendations. First, we have Hermes
set up on a hosting or VPS. Second, we
have Claude's managed agents. And third,
an ANM workflow. But today, we're going
to go balls deep on Hermes. I'm going to
show you how to set it up on a VPS and
how to turn your Claude skill into the
24/7 AI employee that I told you from
the get. Before I go all out on Hermes,
I want to give you guys my perspective
on how I choose to use which one and
when. The reason we're starting with
Hermes today is because you own your
personal setup. Since it's hosted on a
VPS, it's on 24/7, it self-improves, and
it can text you from your phone. This is
going to be your default if you want
full control of the AI models you're
using with your AI employees. And the
good news video is we're going to do a
full A to Z on how to set this up with
your Claude skills. As far as Claude's
managed agents, these are cloud-hosted,
meaning they're all on Claude's server.
These are best if you're less
tech-inclined and you don't want to
touch any VPS servers. The only problem
with these is that you're vendor locked
to all of Claude's AI models, so you
can't use GPT if you wanted to. But
again, they're super easy to set up. I
did these from my terminal. I just
plugged in my pre-existing skills and
within 10 to 15 minutes, I had these
running. And third, ANM is still a valid
option, especially if you're using it
for other types of deterministic
workflows. ANM workflows activate off of
triggers, and this one goes off of a
schedule, but I can change that to an
inbound text from something like
WhatsApp, Telegram, or even Slack. Now,
why run it on a VPS instead of your own
computer, especially if you have a
really powerful one? While your laptop
has to close at some point, it's not
fully reachable from your phone 24/7 if
that's the case, and it's not 100%
isolated from all of your personal
documents unless you have another
computer sitting around. But on the VPS
side, it's always on. You can always
reach your AI agent. It's completely
sandboxed in its own environment, so
it's not touching all of your personal
business files, and they're very
inexpensive. Plus the fact you can use
any AI model. So now, as we move into
Hermes, it's live on your own personal
server right here. This setup is sick
over time because it's going to evolve
with you. Only difference between this
and Claude code is the fact that this is
not a subscription. You're running API
usage with your API models. But you get
the autonomy to use it 24/7. And if
you're out on the go and you want to
text it from your phone to take care of
some tasks for you, you can. So let's
dive right in. We're going to break this
video up into four separate parts. The
first one, I'm going to show you how to
deploy Hermes on a Hostinger VPS. I'm
going to show you how to set up the AI
model with Hermes. We're going to show
you how to connect it to one of your
text platforms, and then we're going to
schedule the skill and see it run live.
As a quick side note, if you guys are
enjoying this video and getting some
value, please like and consider
subscribing. After using Claude to
analyze my channel, I found out only 10%
of my viewers are actually subscribed,
and it's the strongest way to support
me. So if you guys want to continue
seeing me post content like this, go
ahead and click that sub button. Now, to
set up Hermes, I opted into using
Hostinger's VPS because they make it
super easy. It's a one-click deployment
right through their VPS platform. You
won't have to worry about any manual
configuration for this direction. And as
you can see, these prices are quite
affordable, and they give you exactly
what you need for your agents. I'm using
the KVM 2, and if you guys want to
follow along, I have the link in the
description so you can purchase KVM 2.
And the guys at Hostinger sent us a 10%
off coupon code, so if you guys want to
go with the 12-month or 24-month plan,
just type in Charlie Automates to get
your 10% off. Now, once you have the KVM
2 plan, all you have to do is head over
to hpanel.hostinger.com
and look for the Docker Manager. You
would click catalog right here, type in
Hermes, and grab the Hermes agent right
here. The setup is super simple. You can
change your username, your password, and
you can add some API keys if you know
about these ahead of time. If not, we
can always add them later. But for now,
make sure these are typed in and click
deploy. And for those of you business
professionals wondering how safe this
is, they use a very complex gateway key
on the first install, so you guys are
good to go. Once you've done that, head
back over to projects and you'll see
your agent right here, and all you have
to do is click open. This is called a
CLI, it's a command line interface for
the Hermes agent, and there's going to
be a setup process. I'm adding the
support document below because I've
already went through this, but you would
go through a full setup. And what we're
going to be choosing is the Openrouter
model right here, because with
Openrouter, you just fill your wallet
and you gain access to all the different
AI models that Openrouter already has
attached. So, it's just one API key from
Openrouter that we're plugging into
Hermes, that gives us access to all the
models that we're going to need for this
video. Openrouter is also going to be
attached below, so create an account
with them, fill in maybe 10 bucks into
your wallet. Once you have your account,
click new key and set it up, grab it,
and give it back to Hermes. The next
thing that you're going to configure is
your platform in which you want to
communicate with Hermes. I chose Slack.
As you guys can see, I can chat with
Hermes directly through Slack now, and I
can do it through my phone app. All you
have to do is head over to
api.slack.com/apps
and create a new app right here. As you
can see in sessions on my Hermes setup,
I have the connected platform for Slack
right here, but all you have to do is
click create new app from scratch, click
the workspace you're in, and name it
whatever you want. Once you have that
done, just click into the app and you're
going to see a dashboard just like this.
There are two particular keys we got to
grab from Slack in any case. So, on the
Slack side of things, you would scroll
down and you would see OAuth and
permissions, and this is going to be
your OAuth token for the bot side. I
personally click channels inside of
Hermes, went over to Slack, clicked
configure, and this bot token would go
right here. For the app token, on Slack
side, it's under basic information, you
would scroll down and you'll see app
level tokens. You generate the token and
scopes right here. So, name it whatever
you want and you can give it all these
permissions here. It's also fair to
mention inside of OAuth and permissions
inside of the scopes section, you guys
can see all the bot token scopes that
I've given it access to that allow it to
perform exactly how I need it to inside
of Slack. So, you guys can pause this,
take a look, and copy the exact scopes.
And every time you change a scope, you
need to reinstall it back into your
workspace. Once you have both the
tokens, just save and enable. And it's
also going to show you the tools that
you want to set up. I went ahead and
activated everything. We can always add
keys for video generation and image
generation after the case. So, we've
deployed Hermes, we've connected the
Open Router model, we've connected Slack
in place of Telegram. And last but not
least, we're going to take the skill
that we created in the last video and
turn it into an autonomous agent that
runs independent of any commands. And
just as a quick backstep before we head
into creating the autonomous skill, head
over to models here. And right now, I
have it set to DeepSeek. But for this
agent, I'm going to opt into using
Claude Sonnet 4.6. So, we'll just switch
that here. Now, that is our main agent.
What I'm about to show you is the
complete cheat code to take everything
from Claude code directly from the
terminal here and migrate it directly
into your VPS from your Claude chat.
Before I do that, I know this might be a
bit small, but all I'm showing you is
the skill file for what we created
yesterday with all the corresponding
files, the right anatomy of a skill. But
this is exactly how we're going to
connect Claude code's chat directly to
the VPS. There's something called an SSH
key that we're going to get from the VPS
that allows Claude to tap into it. So,
the first thing that you're going to do
with your Claude chat is to give Claude
this exact prompt, "Make me an SSH key
if I don't already have one." And again,
I apologize if this is a little tight,
but I need to show everything. So, take
the prompt, plug it in here. So, if
Claude doesn't show you the public key,
just ask it to show you. And step three,
you can have Claude code use browser
automation into your H panel and add the
key or you can do it yourself. I had
Claude start, but I need to show you
exactly how to do this in any case. So,
in dev tools, click VPS, click manage,
click into your settings here. You'll
see SSH keys right here, and then you
want to add a key. So, grab the key that
Claude gives you and make sure that it's
formatted correctly. After it's pasted,
just click okay. Now, we're just going
to ask Claude to test the door and see
if it's connected. Now, we can see the
login works. The good news about all of
this is that you only have to set it up
once. Then the door is open and Claude
can communicate directly with Hermes and
take these actions on your behalf, and
Claude code can become your agent
factory or your skill factory and
migrate everything over as you please.
But, this is where things get really fun
because now we can just tell Claude to
take the skill file and give Hermes
access to all the MCPs that the skill
actually requires. And in this case,
it's just Pie. So, now, all I have to do
is ask Claude to send over the skill
folder as well as configure the MCP
connection to Appy Pie directly in
Hermes. And just as a golden nugget for
getting this far, on Skill Smith, you
can create an exact workflow that mimics
exactly what we're doing here. So, every
time you want to create an autonomous
agent, it could just be called {slash}
24/7 and it routes directly into Hermes
and attaches the right skill folder and
MCP {slash} API connections all for you,
all in one command. Cool enough, we can
see the skill right here inside of our
Hermes dashboard. And now, Claude is
configuring the MCP connection to Appy
Pie to access all the different actors
for the YouTube and Instagram scraper
because this skill is configured to do
an audit on my social media. Now, we can
see the Appy Pie MCP added here as well.
And if we look here on the left, there's
something called Cron, and this is what
allows us to set up time-sensitive
triggers. So, maybe I want this skill to
pull the data from Appy Pie once a week
every Monday, so I can tell Claude to go
into Cron and set that up for me. And
just like that, inside of Cron, we have
the scheduled job. It's going to run a
weekly viral audit on our social medias
at 9:00 a.m. EST and post it directly
into our Slack. So, now I have this
agent with this entire workflow set up
autonomously in the cloud within Hermes.
I can operate it directly through Slack,
and I'm having the conversation right
from my phone right here. So, I just
clarified my tag directly from my phone
on Slack. So, now it's running and it's
pulling the MCP, obviously all going
through my phone. I'm just showing you
guys on the computer here. And now we
ultimately went through and created our
autonomous 24/7 agent as planned from
the beginning of this video. We
scheduled it, but I did want to show you
that it fully works inside of Slack now.
And the full audit is done for my
YouTube long-form as well as my
Instagram posts. And it gave me a slight
synopsis here below. I hope you guys got
a tremendous amount of value from this
video. It was a lot of fun for me to
create, and I'm looking forward to
creating more autonomous agents that I
can showcase on this channel. And if any
of you guys are business owners looking
to scale your operation using AI
systems, consider clicking the first
link in the description below. You could
have a fully operational AI operating
system on your computer in 1 hour
through what I call the one-click
deployment of Charlie OS. The goal of
Charlie OS is to solve your business's
biggest revenue bottleneck in a 60-day
sprint. No technical background needed,
no complex API setups, just a one-click
deployment of my personal OS with a
custom roadmap pointed directly at your
biggest revenue bottleneck. So, if
you're interested in that, just click
the first link below and see if it's a
good fit for you. But on that note,
subscribe, and I hope to see you guys on
the next video.
