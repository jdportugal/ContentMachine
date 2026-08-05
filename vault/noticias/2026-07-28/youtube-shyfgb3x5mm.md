---
titulo: "The #1 Trending Github Repo Just SOLVED Claude's Search Problem"
tipo: item_agregado
plataforma: youtube
canal: 'Chase AI'
data: '2026-07-28'
url: 'https://www.youtube.com/watch?v=ShYfGB3x5mM'
thumbnail: 'https://i.ytimg.com/vi/ShYfGB3x5mM/maxresdefault.jpg'
descricao: "⚡Master Claude Code: https://www.skool.com/chase-ai 🔥FREE community: https://www.skool.com/chase-ai-community 💻 Need custom work? Book a consult 💻 https://chaseai.io Let's dive into the open source repo that solves one of Claude Code's least talked about weaknesses: web search. ⏰TIMESTAMPS: 0:00 - Intro 0:40 - last30days 4:05 - How it Works 7:03 - Demo 9:35 - Outro RESOURCES FROM THIS VIDEO: ➡️ Master Claude Code: https://www.skool.com/chase-ai ➡️ My Website: https://www.chaseai.io ➡️ last30days: https://github.com/mvanhorn/last30days-skill #claudecode"
resumo: 'Este vídeo apresenta o "last 30 days", um repositório open-source de GitHub que permite ao Claude Code recolher e sintetizar informação de várias plataformas sociais (Reddit, Hacker News, YouTube, TikTok, Instagram, GitHub, entre outras), como alternativa intermédia entre a pesquisa web simples e a deep research demorada. É mostrada uma comparação prática de relatórios sobre o que se diz do Claude...'
tags: {}
fontes:
  - 'https://www.skool.com/chase-ai'
  - 'https://www.skool.com/chase-ai-community'
  - 'https://chaseai.io'
  - 'https://www.chaseai.io'
  - 'https://github.com/mvanhorn/last30days-skill'
---

## Descrição

⚡Master Claude Code: https://www.skool.com/chase-ai
🔥FREE community: https://www.skool.com/chase-ai-community

💻 Need custom work? Book a consult 💻
https://chaseai.io

Let's dive into the open source repo that solves one of Claude Code's least talked about weaknesses: web search.

⏰TIMESTAMPS:
0:00 - Intro
0:40 - last30days
4:05 - How it Works
7:03 - Demo
9:35 - Outro

RESOURCES FROM THIS VIDEO:
➡️ Master Claude Code: https://www.skool.com/chase-ai
➡️ My Website: https://www.chaseai.io
➡️ last30days: https://github.com/mvanhorn/last30days-skill

#claudecode

## Transcrição

Cloud Code has a research problem and
this open- source GitHub repo solves it.
It's called last 30 days and it helps
you with a dilemma you face whenever you
try to have Claude Code search for
something. Either we rely on web search
which basically is a glorified Google
search or we have to use something like
deep research which takes 5, 10, 15, 20
minutes, hundreds of sub aents and
potentially millions of tokens. Instead,
the last 30-day skill gives us a perfect
middle ground where we can scrape a ton
of different platforms and figure out
actual user sentiment, not just the
headlines and not just the articles that
ranked really well on SEO. This is the
perfect tool for anybody who does
research with Claude Code. So, you're
going to want to stick around for this
one. So, the last 30-day skill is an
open source GitHub repo that's got
55,000 stars. It's been the number one
GitHub repo of the day. And essentially
what it does is it allows Claude Code to
scrape all the major social media
platforms. I'm talking things like
Reddit, Hacker News, Polymark, GitHub,
YouTube, Tik Tok, Instagram, all of
them. Goes out, finds the information
you need to know. Again, we're getting a
lot of user sentiment here that you
wouldn't get with a normal web search.
And then it synthesizes it all and gives
you a report. And that report looks
something like this. So, what I asked
Claude Code was, "What are people saying
about Claude Opus 5?" On the left, we
have the report using the last 30-day
skill. And on the right, we have the
Claude Plus web search. Now, first
looking at the Claude Plus web search,
you know, sort of output, what do we
get? Well, we essentially get a summary
of the major articles you would find on
Google if you said, "What are people
saying about Opus 5?" So, it's not
necessarily wrong, but it's not going a
layer deeper and seeing what people are
actually saying on things like Twitter
or Reddit and that sort of thing, right?
It isn't getting to the quote unquote
grassroots level. Meanwhile, if we look
at the last 30 days report, you can see
that we cast a much wider net. And
that's super obvious when we take a look
at the actual sources. So for this
report, it looked at 22 Reddit threads.
It had 13 exposts, 14 YouTube videos, 26
Tik Toks, 13 Instagram uh reels, 16
stories from Hacker News, 25 items from
GitHub, and a thing from Poly Market. So
that's a lot of information. And so what
it's able to tell us is stuff that's
again very nitty-gritty in terms of what
people are saying. So it's talking about
how it did on Instagram and how it did
on Tik Tok in the short form space. It's
talking about, hey, here's sort of like
the framing that's been going on, mainly
like Opus 5 is like half the cost of
Fable 5. Talks about some of the
backlash that lives in the comments, not
just the post. So, it's one thing to
again read sort of the headlines of
these posts on like Reddit. It's another
thing to see, okay, like what do the
comments say? You know, are they
agreeing with what the original poster
said or is there like a ton of fights
going on? And lastly, it tells us in
terms of professional reviewers what the
general sentiment is. it and beyond that
it collects everything and puts it into
a JSON file like all the transcripts,
all the comments, all those things. And
so like that's what we see right here
and there's a ton of stuff, right? So
just way more data for you to sift
through. Now before we jump into more
details on this skill, a quick word from
today's sponsor, me. So inside of
Chaseifi Plus, I have just released the
cloud code masterass and it is the
number one way to go from zero to AI
dev, especially if you don't come from a
technical background. focus on real use
cases. This is constantly updated. So,
if you're someone who wants to level up
their AI game, but doesn't really know
where to start, I highly suggest you
check us out. There's a link to it in
the pin comment. Hope to see you there.
Now, when you see all that, it becomes
very obvious what the value ad is here
with the 30-day skill, and that is the
sources. These are the sources you won't
necessarily be able to see with out of
the box clawed code. And it's pretty
expansive, you know, like we have the
big ones like Reddit and YouTube and
Hacker News and all that, but you also
can do LinkedIn, Pinterest, Blue Sky.
You really have everything. Now, some of
these are really easy to set up. And
when you install this skill, which again
is one single line, I'll show it in a
second. It'll just automatically be set
up for you. There's no like API key
required. We don't have to pay for
anything. Other things require certain
dependencies, and again, Cloud Code will
walk you through that when you run this
skill. And lastly, there are certain
platforms that just require an API key,
like things like X or Twitter. Yeah, you
will need an X AI API key to actually
scrape Twitter. That being said, it's
not particularly expensive. Like
Twitter's really the most expensive
thing here. And for every single run I
did, it was usually on average about 10
cents. Now, the other thing you want to
pay attention to is the ones like Tik
Tok and Instagram reels. It requires an
API key from Scrape Creators. However,
when you run this and install this
skill, what it's going to do is it's
going to set you up with like
essentially a subsidized account that
gives you several thousand free calls.
You could essentially run this for free
without ever paying scrape creators for
like 6 months if you use this every
single day. So, this is actually like
very easy to set up and you get access
to a ton. And inside the repo itself, it
breaks that down to which sources
require what and what they cost. Now,
before we go into the install on the
demo, let's talk about how it works. So,
basically what you're going to do is you
are just going to invoke the skill
inside of Cloud Code. So, you're going
to do slashlast 30-day skill or just say
use the 30-day skill. And you're going
to give it a topic just like I showed
you in the example where I said, "What
are people saying about Claude Opus 5."
It's then going to take your crappy
prompt, whatever that happens to be.
It's actually going to make it a little
bit better. And then it's going to
spread out and start hitting all these
different platforms. Like it says here,
it's smart about it. So if you said
something like, "Hey, I want you to
search for things about Kanye West." It
knows which sort of subreddits it should
look at and which sort of Twitter
handles are relevant. It then searches
all them in parallel. So this is
actually a pretty quick process. And
then it goes in depth. Like I said, we
aren't just looking at titles of posts.
We are looking at the comments. From
there, it's going to rank the
information and synthesize it into a
brief. So if it's seeing the same things
over and over again on different
platforms, it's going to rank that way
higher. There's not just going to be
like one Reddit comment that kind of
like messes it all up and it's telling
you report like this is the big deal.
This was the comment that got up vvotes.
It's like okay, hey, if I saw this on
Reddit and I saw this on Twitter and I
saw this on YouTube, there's probably
something here. Now, for install, very
easy inside of cloud code. It is a
single line of code. This will be in the
GitHub repo and I'll have that linked in
the description. Furthermore, this repo
goes pretty in-depth in terms of other
sort of agents. So, if you're using
anything else like codeex and cursor and
all that, very simple to do and also
even shows you how to do it on the
claw.ai AI web app. So now that you
understand what it does, how it works,
and how to install it, let's just demo
it real quick. All right. So to use this
skill is very simple. Like any skill, we
can invoke it by simply doing forward
slashlast 30 days or we can use natural
language. From there, we are just going
to give it our prompt. So our prompt is
do some research on what people are
saying about the last 30 days skill. Now
you saw all those different sources we
had available to us. If I don't give it
any like flags or additional
information, it's going to use all of
them if it thinks it's relevant. You
also have the ability to kind of like
scope down. So I could say, hey, I want
to know about last 30 days, but only
check Reddit or only check Twitter or
only check YouTube. So it's pretty
flexible in that regard. And remember,
if you don't know like what your options
are in terms of how to use the skill,
well, Claude Code knows. The fact that
it downloaded the skill, it knows about
the skill. It can sort of teach you the
best practices if what you want to do
isn't covered, you know, in this video.
So, when I go ahead and run it, it's not
really going to ask me any questions or
anything. There's no back and forth.
It's just going to invoke the skill and
get to work and start running a bunch of
agents in parallel to hit all those
different platforms. So, to actually
execute the search, it runs a
deterministic Python script to go ahead
and scrape everything. So after 5
minutes, this is what it came back with.
It gives us sort of that oneliner
summary at the beginning that this repo
is growing faster than the hype post can
keep up with it. And it gives us sort of
the live numbers. Talks about sort of
like the general through line for it
all, which is that it fixes generic AI
research. And it sort of shows me like
where other people are talking about
this in terms of YouTube and Twitter. It
talks about the key patterns it saw from
the research and then it also breaks it
all down by platform. So five threads
from Reddit, 22 from Twitter, etc., etc.
And then it shows us where we can find
the raw results. So just the raw
markdown. And if you want the raw JSON,
you can ask for that as well. For
contrast, let's see what we get back
when we give it essentially the same
exact prompt, but we tell it explicitly,
do not use the 30-day skill for this.
And here's the report it gets us with
the pure web search. And again, it's not
wrong. It's just much more shallow. And
we don't really get things like user
sentiment. Even when it talks about what
people praise, it's rather generic
because it's not looking at comments and
it's not looking at transcripts or
anything like that. Which means using
web search for like your dayto-day on
like random questions inside of cloud
code isn't wrong. We don't have to use
last 30 days for everything. In fact, I
would suggest you don't because it is
kind of like a big deal. But I think
what this really buys us is something in
between web search which is surface
level and doing hey let's do slashdeep
research dynamic workflows you know
3,000 sub agents right this is that in
between and especially and I would argue
might even be better than deep research
when it comes to user sentiment. So,
like you saw there, really simple to
use. The install is one line. The only
thing that will possibly trip you up is
when you're trying to connect specific
sources, but again, Claude Code, just
throw out this repo link and it will
walk you through the specific ones you
need. And really, the only one that's
going to cost you money is going to be
the X and Twitter one. And for
reference, every time I've used this,
like I said before, about 10 cents, give
or take. So, as always, let me know what
you guys thought of this video. Make
sure to check out Chase AI Plus if you
want to get your hands on my Claude Code
master class.
