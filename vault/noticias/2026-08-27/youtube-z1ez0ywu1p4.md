---
titulo: 'This feels illegal...'
tipo: item_agregado
plataforma: youtube
canal: 'Matthew Berman'
data: '2026-08-27'
url: 'https://www.youtube.com/watch?v=z1ez0yWu1P4'
thumbnail: media/monitoring/youtube/z1ez0ywu1p4.jpg
descricao: 'Join My Newsletter for Regular AI Updates 👇🏼 https://forwardfuture.com My Links 🔗 👉🏻 X: https://x.com/matthewberman 👉🏻 Forward Future X: https://x.com/forwardfuture 👉🏻 Instagram: / matthewberman_ai 👉🏻 Discord: / discord 👉🏻 Spotify: https://open.spotify.com/show/6dBxDwx... Media/Sponsorship Inquiries ✅ https://bit.ly/44TC45V Links: https://www.darkbloom.dev/'
resumo: "The video discusses a project called Dark Bloom, which allows users to share their computer's processing power to create a distributed data center, offering a decentralized alternative to traditional data centers. It highlights the potential for users to earn money while contributing to AI model inference, emphasizing the benefits of local AI and peer-to-peer connections."
tags: {}
fontes:
  - 'https://forwardfuture.com'
  - 'https://x.com/matthewberman'
  - 'https://x.com/forwardfuture'
  - 'https://open.spotify.com/show/6dBxDwx'
  - 'https://bit.ly/44TC45V'
  - 'https://www.darkbloom.dev/'
---

## Descrição

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
https://www.darkbloom.dev/

## Transcrição

So I was scrolling X and I came across
this project Dark Bloom. Basically you
install Dark Bloom on your system and
then all of a sudden you're sharing some
of your compute with other people. You
can kind of think of it as a massive
distributed data center and it is
fascinating. There's a lot of people
that don't like data centers and I've
been seeing that opinion around and this
right here might actually be the
solution. a completely distributed data
center run by you. I think a lot of
people got excited about it because you
can make money with it. But that's not
what was exciting to me. I am super
interested in this concept that we have
a distributed data center. Rather than
having these massive data centers which
are highly unpopular, we can basically
break it up and they can live in
everybody's house. Kind of like solar
installations. Everybody's generating
their own electricity. And here's how
you check how much you can make. Here's
the earning estimate. It's dark
bloom.dev. Click right here. Let's say
we have a Mac Studio. We have an M5
Ultra with 96 gigs of memory. We can be
making $37 a month. I know that doesn't
sound like a lot, but it's basically
free money. There is a very small
incremental cost for the electricity,
but otherwise your computer, your Mac is
just sitting there being not used. So,
it serves open-source models. Here are
some of the models it's serving. Quen
3.6, Gemma 4, and GPT OSS. These are
great smaller models that anybody can
run and use. And when you aggregate all
of these nodes together, you get a lot
of inference. And this project has been
blowing up. It's only a few days old.
So, I'm going to tell you all about it.
Then, I'm going to show you how to
install it. This is so cool. Now, Dark
Bloom is currently being served on Open
Router. So, if you want to use one of
the models provided by Dark Bloom, you
can right now. It's really nice and it's
like 50% cheaper than other providers on
Open Router. And they've already served
4.5 billion tokens in the matter of like
a week. So, typically a data center
looks like this. A massive building
placed in usually the middle of nowhere
with hundreds of thousands of GPUs. And
if you've been following the news
lately, they are not that popular in the
United States. This might actually be a
decent alternative to that. So, if
you're one of those people who are super
concerned about data centers, but you
still want to explore artificial
intelligence, this is a great option.
Obviously, local AI has been a thing for
a while. You literally don't need to use
any of these massive data centers or the
power grid. You could just get it up and
running on any machine. But now, imagine
this. Imagine you don't necessarily have
a powerful enough computer sitting on
your desk to run some of these great
models. Well, now you don't need it. You
can connect to somebody else. This is
peer-to-peer
inference, peer-to-peer AI. Super
exciting because now you're not using
those massive data centers. You're just
connecting to somebody else. You are
almost just paying them directly. And
this is a very different paradigm than
what we're used to. This actually might
solve some of at least my concerns about
the concentration of power in AI
open-source openw weights models being
run on distributed GPUs really
distributed compute. And for those of
you who are wondering does this actually
work and how does it work? They actually
put together an entire white paper about
it. So the core problem, a user wants to
run an AI model on someone else's Mac,
but the machine's owner who has root
access and physical custody should not
be able to see the user's prompts or the
model's responses. So if you were
concerned about this model and shipping
your data to somebody else, which you
know people are concerned about giving
the data to the big data centers or the
open AIs and anthropics of the world,
they have actually solved that problem
with this project. Our approach
eliminates every software path through
which inference data could be observed.
The inference engine runs directly
inside a single hardened Swift process.
No subprocesses, no local server, no
interprocess communication using MLX
Swift LM, which is Apple's inference
engine on the Apple silicon GPU. So, I'm
not going to get too far into the
technical details, but they basically
solve the problem of sending your data
to another computer and that other
computer being able to observe it. Now,
they can't. So, just imagine we have a
million of these nodes set up. That is
major inference power that anybody can
tap into at a fraction of the price of
going to an open AI or an anthropic. And
it's completely distributed. It is
completely built by regular people. It's
awesome. Okay, so let me show you how to
install it now. So the first thing
you're going to do is go to dark
bloom.dev. Click up here and click earn.
And it currently has a CLI, although the
Mac app is coming soon. And it's really
just this one command. You will need a
Dark Bloom account. You will need to
install something on your Mac, but after
that, it's pretty much just sitting in
the background making you money. And to
be clear, this is not an ad for Dark
Bloom. I make no money from promoting
Dark Bloom. It is just such a cool
project that I wanted to tell you all
about. So, you know what I actually did?
I just went to codeex and said install
and set up Dark Bloom AI and it's going
to do everything else for me. To finish,
I have to just sign in. So, done. Enter
the code shown in terminal. And again,
this is all being done through Codeex's
own browser. Okay. So, then this thing
pops up in Mac OS and it's under device
management and settings and it's Dark
Bloom provider enrollment. And all you
have to do is click enroll. And that's
pretty much it. And look at that. It's
even testing how many tokens per second
I'm getting. And I'm getting 78 tokens
per second. So now somebody else can use
this these tokens and pay me for it.
It's really awesome. Okay, so it tested
it's running GPT OSS 20B. We passed at
78 tokens per second, but now we have to
do hardware verification. So I'm just
going to say finish it for me. And
here's something you should know. Right
now, they just made this change where
you need at least 48 GB of RAM. And I
know that's a lot, but most Mac minis,
most Mac Studios come with at least that
much. So, you should be good. A lot of
the higherend Mac laptops also come with
that, and they'll probably reduce that
size as it goes, but they raised it just
because they were getting so much
demand, they had to have some kind of
quality bar, and that's what they
decided on. Now, the way that you get
paid is by connecting a Stripe account
to your bank. Now, Stripe is the
intermediary. So, you're not connecting
it directly to Dark Bloom. They have no
access to your bank account. They can
only send you money. So, obviously, use
it at your own risk, but based on what
I've seen about it, nothing screams scam
to me. Now, I made a video and I put it
on Instagram yesterday and a lot of the
comments said this is a scam. like a lot
of them surprisingly but the software is
publicly available. You can go inspect
it. You can go audit it. It is publicly
readable. So I actually had GPT 5.6 Soul
review the entire codebase because I
wanted to know is there anything
malicious in it. So the only thing it
really pointed out as a problem is that
currently you get 100% of the revenue
earned but that can be changed in the
future. Okay, kind of figured that.
There are no hidden malware, no mining,
no credential theft located in there.
MDM and device access concern is low.
And so again, use it at your own risk,
but it seems benign to me. And they also
put together an entire white paper about
how the tech actually works. So it's
quite transparent right now. Now again,
it is in the earliest of early days, so
there's going to be rough edges to it.
There's going to be things that might
not work as expected. They're going to
adjust things pretty rapidly. But why I
wanted to share this is because it seems
like a really cool, interesting project
and more importantly, a very compelling
concept. There's so many powerful
computers out there that are just
sitting idle and we're all dependent on
these massive data centers. Own your own
compute. Now the other criticism from
comments seems to be about electricity
usage and I can tell you Apple computers
are extremely efficient. So the
incremental cost of that electricity is
going to be very low. Now obviously it
depends on where you live, what your
electricity costs are, but you can
probably use AI to do that calculation.
And if you're earning, you know, a
hundred a couple hundred bucks a month,
your electricity bill is not going to be
more than that. And again, this is all
very experimental right now. I just
think it's interesting. And if you want
to stay uptodate on the latest in AI,
check out our newsletter,
forwardfuture.com,
link down below. So, I haven't started
running it. I haven't gotten paid yet. I
have installed it and I am waiting for
it to kick on and start serving, but I'm
looking forward to it. And it's not
about the money to me. It's not a ton of
money, but I do love this idea of
distributed compute. It is very exciting
to me as an alternative to these massive
concentrated data centers. And one last
thing, this is also another reason why
open-source open weights models are so
important. It puts the power into each
individual's hands. You can do whatever
you want with the model, including a
cool project like this. and distributed
compute feels like a very real thing and
something that can be very powerful in
the future. And so I wanted to explore
it. I wanted to test it. We're in the
very earliest of innings, but that's why
we're here. And I know that's why you're
watching this video. You want to be on
the cutting edge, on the frontier with
me. And I made an entire video about
open source. Check that out right
