---
titulo: 'SOTA Generative Media Panel — Dumitru Erhan, Shane Gu & Nicole Brichtova, Google DeepMind'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-08-30'
url: 'https://www.youtube.com/watch?v=KLDdXOw6jIc'
thumbnail: media/monitoring/youtube/klddxow6jic.jpg
descricao: 'The team took captions from real videos, regenerated the same scenes with their own model, and ran a human eval. People largely preferred the generated version. Dumitru Erhan is quick to deflate that result. The output is not more realistic, it is sharper and more saturated with nicer skin tone, and human preference turns out to be an unreliable thing to optimize against. Nicole Brichtova has a name for the effect, the Instagram filter. The same blind spot surfaces in smaller ways. Their image model quietly began putting wedding rings on hands, and nobody internally caught it until an outside tester asked why, which is reward hacking arriving through the back door. Much of the hour circles one question, whether language is a good enough intermediate representation for any of this. Shane Gu...'
resumo: 'The video features a panel discussion with experts from Google DeepMind discussing advancements in generative media models, including the launch of the NanoBanana 2 light image model and Gemini Omni Flash APIs, highlighting their capabilities and applications in creative processes.'
tags: {}
fontes:
  - 'https://x.com/doomie'
  - 'https://x.com/shaneguML'
  - 'https://x.com/nbrichtova'
---

## Descrição

The team took captions from real videos, regenerated the same scenes with their own model, and ran a human eval. People largely preferred the generated version. Dumitru Erhan is quick to deflate that result. The output is not more realistic, it is sharper and more saturated with nicer skin tone, and human preference turns out to be an unreliable thing to optimize against. Nicole Brichtova has a name for the effect, the Instagram filter. The same blind spot surfaces in smaller ways. Their image model quietly began putting wedding rings on hands, and nobody internally caught it until an outside tester asked why, which is reward hacking arriving through the back door.

Much of the hour circles one question, whether language is a good enough intermediate representation for any of this. Shane Gu argues it thins out exactly where humans are most sensitive. We carry poor vocabulary for audio, taste, smell and skin tone because those sit close to survival, and a professional wine taster he asked had resorted to borrowing the language people use to describe a date. The tell of AI video, swyx notes, is that everything sounds studio recorded, because studio recordings are the training data, and a model with no representation of standing further away cannot get that sound right either. Evaluation stays stubbornly manual underneath all of it. Ten people in a room, two videos side by side, pick one.

Speaker info:
Dumitru Erhan:
https://x.com/doomie
  / dumitruerhan  
Shane Gu:
https://x.com/shaneguML
  / shixiang-shane-gu  
Nicole Brichtova:
https://x.com/nbrichtova
  / nicolebrichtova  

Timestamps:
0:00 - Introductions, and why this session exists
2:00 - Nano Banana 2 Lite and the Omni Flash APIs
4:30 - Beyond demos: storyboards, editing, education
7:51 - Video agents, or one model doing it all
10:26 - Video models as zero shot learners
13:01 - Will everything collapse into one model?
14:20 - Is captioning the right intermediate representation?
18:13 - What people actually mean by world models
19:44 - Vision researchers becoming generation researchers
24:51 - Audio, and why joint generation mattered
26:32 - The things language cannot describe
32:03 - Studio quality as the tell of AI video
34:12 - When humans prefer the generated video
36:07 - Prompt engineering and trained sensitivity
38:27 - Setting a default aesthetic for the world
41:09 - The wedding ring nobody noticed
42:10 - How you actually evaluate video
47:35 - What data they are looking for
53:04 - Forward deployed engineers as an eval channel

## Transcrição

[music]
and welcome back for those on the stream
and those those in person. um we take
tend to basically take these longer
sessions between uh all the sort of
mainstage keynotes to reflect on things
that um you know are particularly
important but like don't have like a
significant like sort of launch moments.
Today we're very lucky to have people
working on Omni and VO Nano Banana like
the you know the world's best generative
models here with us. Uh, Demetrio, I I I
first saw you when you were posting
about your office. [laughter]
Um, I think you're you're probably
number one uh Google Google's number one
office influencer at least in in San
Francisco. I think you like you like to
bike as well. You like to take photos of
>> bike here.
>> Yeah. Um, but you know, but also you
work on video models.
>> That's right.
>> Um, Shane, I I met you I think at like a
dinner.
>> Yeah. Um and uh and uh and I I remember
you were trying to get me invested in
like one of the companies. I forget
forget which one.
>> Forget about that. [laughter]
>> But now but now you're um now you're
working on Omni Thinking. Um and and
just you know a bunch of other
>> Gemini RL.
>> Yeah. Yeah. Uh and Nicole also uh the
rest of the gen media models uh nano
banana and uh all and everything you
just launched actually even this week.
Uh,
>> yeah. We launched some APIs.
>> Yeah. Yeah. Yeah.
>> And I haven't tried to convince you to
invest in anything, but maybe I should.
>> I mean, so I try not to be an investor.
People just convince me anyway. I'm like
just, okay, well, I'm not that rich, but
know like you can't not try to invest in
some of these things. And, you know, for
those of us who are not working at a
Frontier Lab, this is the best this
closest we'll ever get. Um, so yeah,
actually, let's kind of recap since
you're closest to it and we just did it,
like what was launched this week? What
should people go try out?
>> Yeah. Um so yesterday we had two launch
moments. Uh one of them we launched
NanoBanana 2 light uh which is our
fastest, cheapest um image model in the
nano banana model family. Um and it's
better than the original NanoBanana. Um
so really for most people um that model
replaces what you you know used and love
the original Nano Banana for across like
generation and editing and it gets
really close to the frontier quality of
of the kind of mainland bigger models.
So that that's really exciting. I think
if you look at some of the demos or like
things that people have been trying like
getting kind of that like 3 second
latency just unlocks a whole bunch of
things that you can do with like
ideation and iteration and it's just
really fun and the model's getting to a
point where like the quality is really
good um where um it you know you can use
it for iteration but you can also use
some of those outputs as just kind of
like ready um production output. So
that's really exciting. Um and then
second launch we finally um launched the
Gemini Omni Flash APIs um that we
pre-announced at IO. So thank you for
waiting. Um and that you know is the
first time that we're making the APIs
available for developers and it's
basically really exciting kind of video
generation and editing and we're pricing
it the same as Y31 fast. So we're
getting you kind of like really really
good quality for a really awesome price
hopefully. Um
>> yeah, I mean that that's incredible. I'm
actually really So when you guys
launched Omni for the first time, you
also did a podcast uh with Logan who
couldn't be here today uh and you added
like a sloth uh and and Ramen and all
these all these things. I actually
really want to do that to our videos. I
just didn't have an API for it because
obviously I have to automate the whole
thing. So thank you for the API.
>> Uh that is my favorite use case.
Everybody should do that. Um I got a cat
which is probably like the most boring
of the animals. Um if you don't know
what we're talking about, you should
look it up. It's very funny. Feurer. um
Furer who's um you know on on the team
did that.
>> Furer is the number one guy you should
follow for you should follow ideas on
okay what can this thing do?
>> Yes.
>> Right.
>> Yes. He he's he's amazing at that.
>> I've tried to get him for the last two
years to come to AIE. He hasn't made it
yet. He's actually come in person. He
just didn't want to speak because he's
anonymous.
>> I know.
>> I I want to say his real name but I
can't say his real name.
>> No [laughter] no we won't we won't do
that to him. But you should really
follow him. He's amazing.
>> He did all that work. I actually met him
uh in the office uh when we did the
podcast I think and I didn't realize it
was him. So his badge doesn't say
Popers.
>> Yeah,
>> I know.
>> So he used to be part of uh Replicate
and Replicate had this joke where like
everyone was Deep Fates. Deep Fates is
this like kind of mysterious character
and replicate. Replicate is very cool
company and both was part of it. Um, so,
okay, one thing I want to get on there
before I go into like sort of the the
the the sort of omniper is we added
cats, we added sloths, very cool, very
cute, very fun.
>> Uh, what are the, you know, inspire
people as to like what are the more sort
of workhorse use cases that maybe are
not just demos, you know?
>> Yeah. So, so obviously the hero
capability of the model or maybe there's
two like one is the ability to kind of
take in anything as input and then get
video on the other side. Obviously in
the future and and we've kind of talked
about this as a pre-announce like we
want to get the other output modalities
out as well but basically what that
means is you know you can take a set of
images that you have as maybe a
storyboard. You can take like an audio
track as a reference of you know like a
voice that you want a character to speak
and then you can get a video on the
other side. So like that just unlocks a
whole bunch of things that you can do in
like you know short film production or
you know shorts we've launched on
YouTube as well um to help creators kind
of like create um content more easily.
Um and then the other one is obviously
video editing. Like that's another thing
that we're really excited about that
we're just making easier because now you
can use natural language to take a
video, you know, add something, remove
something. Sloth is obviously like fun
example. Um, but there there's obviously
kind of there's consumer use cases that
we kind of had in mind where, you know,
you could take your beach vacation video
that was too noisy and you want to clean
up that noise. Maybe in the past you
wouldn't have because you didn't have
the tools or you didn't know what the
tools were that you needed to go to. So,
that's one use case that you can, you
know, go to. We've seen a lot of folks
use it for kind of marketing ad campaign
creation and I'm excited to see more of
those use cases as we launch the APIs.
um because obviously like we don't we
don't see all of it in the first party
products but I'm really excited for
people to start to explore that um in
the API. So those are just some of the
kind of like high level um things that
have come up. U people also use it to
create like education materials. Yes. Um
and like like that's really exciting. I
think we're all we've all kind of talked
about being excited about the future of
education where like everything can be
kind of customized to you and
personalized to your knowledge level and
the style that you prefer and and so
this is kind of just like a step in that
direction.
>> Yeah. I I I sort of actually used just
none of yesterday, but my my parents are
visiting and there was there was a very
fun sort of use case. They I bought some
gadget off from Amazon that they wanted
and the instructions to use it was were
only in English and there was plenty of
diagrams or whatever and I took a
picture of it and said, you know,
translate this into Romanian. Yes.
>> And keep everything else the same,
right? So it was amazing, right? Like it
was just like, yeah, it looks identical
and it has, you know, it's perfectly
translated. I mean, more or less, right?
But it's it's you know using Gemini
under the hood obviously to kind of do
the translation and so you can you can
see this use case for video as well
right like the the power of text
rendering in in in Omni is is quite next
level. So and you could you could you
could think about plenty of use cases of
like both text rendering translation
internalization all sorts of things that
would be actually genuinely useful to a
lot of different people and sort of
broader access to either you could like
redub a video or whatever it is that you
wanted to do. like there's plenty of
different things that you could you
could think about doing.
>> Yeah. Um one of the most enlightening
conversations I have on my podcast is
with uh just people researchers at the
frontier of these things. Um I had one
with um Ethan from the XAI video team,
the Grock video team who was basically
saying like you know the next trend is
actually not just like single model,
it's more like video agents. Um, and I
don't know if that terminology resonates
uh obviously for for very relevant for
RL. Uh, but it was it was basically kind
of like giving up on like trying to do
everything in in effectively one pass.
Um, do you feel that same way or is it
still an open research question which
way the trends are going? [snorts]
>> Yeah. So um what kind of excite me most
is really when the symbolic kind of
foundational models and this kind of
like video foundational model can
actually kind of really work together
and u in a way the if you look at the
beginning of the generative sort of like
image generation video generation a lot
of it kind of started when the language
model got good enough to provide a very
detailed captioning like from stable
fusion days or kind of dowi 2 days. So
um so basically like language is
extremely u helpful representation uh
one is that it's kind of universal but
the other kind of more um technical
thing like kind of my hypothesis is like
um one very difficult thing about
machine learning is um this sort of like
spirious coordination. So you don't know
you know if the if this kind of feature
right that's kind of predictive is
actually causal factor or not. There are
two ways. One is we can have really
diverse data training data like from
every intervention of the causal graph.
The other is you condition the causal
information and conditioning the
language is kind of like conditioning
like a coal information of the of the
kind of world. So um
>> which is a prompt or a concept what
>> yeah exactly so if you look at like you
know how we going to describe this video
how this kind of image is actually very
close to you know how would describe
this kind of causality you know behind
this like how this is kind of generated.
So one is like that can really allow for
very rich generalization and then uh
very kind of just like a good model. Um
the other is so eight months ago uh we
put the evaluation paper called video
models zero shot learners and reasoners.
>> Yes. So that was a kind of you know it's
it's a confirmed paper and then later on
actually the N banana team followed up
with a vision banana paper that
basically used n banana to do but
essentially the idea is uh video model
is extremely good sort of a foundation
model for space and time kind of
information. So um classic computer
vision tasks a lot of could be kind of
zero shorted and when you like say feed
in some like a visual quiz uh it can you
know there's definitely like a lot to
improve it can kind of solve and it can
um like robotics kind of like seeing it
has really good kind of physical
intuitions like word model uh and I
think the the key is really the kind of
mix of the visual kind of reasoning and
then the text kind of reasoning kind of
all tied together Um obviously you know
like whether doing it you know as kind
of unified model versus like just kind
of agent coation I think that's more
like uh it's going to be more kind of
incremental you know how it's going to I
imagine everything's going to go into
like a single model eventually
>> but right now there's like a lot you can
do if you uh basically take like really
good video understanding image
understanding Gemini agentically with
anomy and that's actually gonna yeah our
team is like exploring a lot
>> yeah okay that there's a there's a lot
in there um I I think uh one question I
I am increasingly starting to wonder is
does it all trend towards one product
for you guys right like now you have
multiple models out the naming of omni
does imply that eventually everything
will go away and it just goes into omnis
um is that the plan
>> is it [laughter] I don't know I I think
I think uh maybe I mean I think
eventually I I think there's sort of
different trade-offs engineering
research product trade-offs in like it's
like for the same reason like the the
sorry how is it called nano banana light
I don't know what the product name
>> nanob banana tite
>> nano banana too light yeah right it's
it's it's it serves a particular niche
right and it probably doesn't
necessarily fit immediately in the same
model literally checkpoint as uh
something that can do 4K you know uh 30
secondond videos right like they're
probably not like trainable in the same
quite way, right? Like, so I I don't
know. It depends on how how far into the
future you look like. Sure, in five
years from now, will they all be the
same model? Probably. Uh but like, you
know, six months from now, we'll we'll
probably still have, you know, multiple
different models doing different things
because kind of from pragmatically the
trade-offs are such that we we should
have multiple different kinds of models.
>> Yeah, I
>> I think that's right. And and just on
that note, I mean, we did call it Gemini
Omni because we wanted to hint at the
future where Gemini just becomes fully
multimodal in and out, right? And so so
it's definitely a move in that
direction. I think we'll probably see a
move in the direction where Omni also
generates images and edits images and
all those kinds of things. But Doo is
right that I think on the way there,
there's a bunch of really really useful
applications of some of these more
specialized models. And so we we will
probably continue to work on those as
well because like that serves a certain
need at this point in time that may not
exist you know a year from now. There's
also like a research question about like
just how much transfer there is between
different kinds of modalities, right? I
think you may believe that there's some
transfer between coding and video
generation and I think most people don't
necessarily believe that but they you
know you could try to think that there
is some some there something there or it
could be a waste right to put them
together to try to learn these both
tasks at the same time right so I think
it's it's it's interesting sort of
question to which extent like image and
video obviously kind of there's some
transfer like kind of not that different
there's value in in learning to output
video and audio at the same time because
joint audio visual is you know that's
how that's how it is. Um and then
there's you know other kind of
intersections of modalities that are not
super obvious right like 3D
representation coding I don't know maybe
uh things like that right so like I
think it's worth sort of exploring the
different corners there and we are
actively doing that um with a focus
towards like what people actually want
to do with these models
>> yeah um what one thing I feel I feel
like uh I'm surprised by but also I feel
like it's insufficiently answered is
what is the correct intermediate
representation Um, so captioning, right?
XI does captioning. Omni does
captioning. Um, and I I I understand how
captioning works for images. Um, and I
understand that you can extend it into
to video and and sort of guide it across
time. It just feels very inefficient. It
there's got to be I feel like there
should be something better. Uh maybe
it's code and maybe we generate you know
and obviously I think a lot of um ffmpeg
and mapplot um what's the three blue one
brown one manm um a lot of like video is
generated through code and maybe that's
like the optimal representation uh any
hypothesis as to like is is it better or
is just English all you need
>> well as so I'm in the Gemini and they
know we do like a lot of RL agent and of
course kind of coding so yeah We we're
definitely exploring the coding
representations.
>> Yeah.
>> As kind of better kind of way to
represent. Yeah.
>> But you know like do you what's your
probability estimate on like [laughter]
if we just output binaries like we just
you know like just it's just ones and
zeros.
>> Um I I guess maybe a kind of similar
discussion was like um basically is the
language the right representation like
right. So uh one kind of question for
example uh professor you know like some
ask is like you know why why does the
channel of thought need to be in the
natural language?
>> Yes.
>> Can it just be the kind of any kind of
like continuous tokens just any amount
of you know additional computations.
>> Um so one is like obviously the test
like adaptive compute is going to give
like you know better results. So it's
that but what really kind of made CH
thought so you know like four years ago
I wrote you know the larger model zero
sort reasoner and then self-improvement.
So I kind of know from the very early
day but the reason like it works really
well is um right now the recipe that
works is the pre-training that scales a
lot and then that basically like learns
a lot of intelligence. there are a lot
of you know scaling RL but those are
still like extremely kind of comput
incent intensive to extract the
information and um you really want to
rely the intelligence on that so
basically by tying the sort of like a
reasoning in the natural language you
basically directly use the intelligence
of the pre-training to it while if you
remove that kind of constraints then
you're not um and these days uh I feel
the a lot of advancements in the texts
but also doing this kind of multimodal
space is very driven by this uh kind of
text as a kind of great uh sort of
representation.
>> Yeah, it's a good backbone.
>> Yeah,
>> I I think to me it's even simpler than
that. It's text is is how we
communicate. So I think fundamentally if
you're building kind of products that
humans will be interfacing with um like
like that we will be using text somehow
if it's a text interface, right? Not not
for everything. So I think it's it's
natural to default to that.
>> Yeah. Obviously there's like a conf
discussion you know some arrow like
arrow maximalists is like oh we don't
care about you know kind of channel
those kind of like stuff it's just just
additional compute
>> sure but I personally yeah
>> ro maximalists I wonder I wonder who who
qualifies in that description David
silver
>> ah okay yeah I mean they they've just
left to to start their thing um
interesting okay so uh I I mean I think
I'm very interested in just like better
representations because I think that's
one of our themes that we're curating
today uh at the world fair is world
models. You mentioned the word world
models but it's not something that's
like super well defined. I think
everyone's like sort of converging on
some version of it that is like the
ideal.
>> Sure. Everything is a world model now.
It's sort of a
>> it's not it's not that useful, right?
>> So I just gave a keynote at the IER
world model workshop. Yeah. And then uh
yeah essentially uh I definitely
encourage to check out the definition by
Jandra Matic. He's like the you know OG
computer vision professor UC Berkeley.
>> Uh he has pretty you know bit of word to
say about world model
>> but also kind of Schmidt Herburver's
kind of how he defined the world model
from 2019 like 1990 sort of uh uh you
know like Wayne was just basically just
that kind of model base. Uh for me the
word model is basically just the model
in the model based RL and I feel that
has sufficient to describe but obviously
you know there are like a lot of uh FE
had a kind of nice blog post about what
about yeah this kind of broken down
>> um but yeah
>> yeah I mean so you know I I'll end this
part of the conversation but like I I do
think that language to me relying on
language as like the sort of like the
narrow pipe through which everything
goes through um still is like a lossy
compression. No, no, no. But we're not
seeing that, right? We're basically
saying the video model and the language
together.
>> So, so I think the language alone is uh
not sufficient. That's why we feel like
the video is a very complement model.
Right? Now the um you know kind of v
omni many people feel as uh you know
generating kind of pretty videos but I
think our vision it's it's much more
than that. It's a missing foundational
model that's absolutely required if you
want to make the AGI that match to
humans not just a jacked one.
>> Yeah. Um okay. So one one other thing
you know you you mentioned on the vision
side um and I'm kind of curious how sort
of uh parallel you know in terms of your
research careers um this development is
like I think basically a lot of vision
people have crossed over into more model
people um a lot of vision people also
become generative video and image people
and is it just as simple as you know
reversing uh image to text and then now
it's text to image like
>> [laughter]
>> is is that if I mean that effectively
was the diffusion process. Um
I I just you know I I just see the
career paths of the people that I talked
to and and see and I I I see this
overall trend of research directions and
I just wanted you to guys to sort of
reflect on on that.
>> I mean I certainly went that way right I
I started long time ago uh doing
computer vision sort of object detection
recognition things like that. Uh I think
just that's just simpler problem right
just generation is just harder like it's
a it's a different kind of mapping right
you map from the the inverse mapping is
not as simple as just inverting the the
kind of network you use right it's it's
a it's it's more ambiguous right to go
from cat to image of a cat and in some
ways it's also a loop because your
vision work creates the synthetic labels
that then continues
>> I mean sure [laughter]
I don't know I don't know I try to
validate my my sort of theories about
how fields develop how how careers has
progressed through this
>> I mean for like the the the better the
understanding side gets like we have
seen that the generation side also gets
better right so like like
>> it's completely bootstrapping yeah it's
>> and so like like like there's definitely
they're there to that thesis and I think
yeah I think a lot of people have kind
of like I I definitely worked with a lot
of um image understanding people who
became image generation people you know
and then some of them have moved on to
video because it's kind of like the next
thing where you have so many more
dimensions to work with so yeah I'm
curious about you spec as your
>> so I definitely like recommend start
with understanding recognition because
that's basically discriminator and then
that's going to lead to better
generation and that's what the bridge is
basically reinforcement learning so my
um my kind of journey is I initially
kind of worked on the algorithmic
research in the gent model against some
like you know amnest kind of generation
and then I worked on like RL and
robotics um and then like six years ago
I was like leading like a moonshot on
the dexterity it was pretty early but I
see now everyone's kind of doing
uh four years ago I basically kind of
figured out that this like symbolic AGI
is going to accelerate much faster than
the kind of physical AGI kind of
counterpart. So uh I decided to kind of
like language models and then those
things. Um and then recently kind of
work with Doomi and then like omni team
I quite enjoy kind of collaboration
there. the what I quite enjoy uh what I
recommend definitely to the researcher
is to uh definitely kind of explore or
at least like get exposure to what the
top people in each of the community are
like looking at how they kind of think
about problems. So when I look at the
video model to me it kind of reminds me
like pretty early on sort of like
language model where like very early
language model was a kind of creative
sort of demo right you kind of like try
to write like a story like mobile and
then like you know GBD2 and then those
kind of days like LTM kind of days right
and then you know uh instruction tuning
you actually kind of make it usable as a
chatbot but then at the chatbot stage it
still had so much hallucinations and
instruction for wasn't good enough so it
couldn't use for reasoning and when I
got good enough um in pre-training and
post- trainining for reasoning then you
know this kind of test time scaling the
RL really took off to like many of the
kind of best performing models and right
now I think the video model is as we
mentioned it's it is a complimentary
foundational model and I can imagine
it's going to follow a similar path it's
going to be very uh it's going to
improve a lot instruction following a
lot of uh this it's going to improve a
lot in reducing coordinations to extend
that it become a very reliable world
model so we can kind of like intermixed
video like space-time simulation was a
text simulation to solve like arbitrary
AGI problems. Also like I think the
difference still is between sort of text
models and like image video models is
that like we haven't quite unified
understanding and generation in in
multimedia I'd say yet like I mean I
think I think without going to the
details of course there's like it
depends on on at which level you're
thinking about this but generally like
there's not that many as far as I know
models sot kind of you know frontier
models that are genuinely
kind of good at both understanding and
generation of of let's videos, right?
Like it's a it's a it's an interesting
challenge. I'm not saying that we should
do this. Uh but but I think uh it kind
of stands to reason that like you know
understanding and generation are two
sides of the same coin. So they they
kind of should be in the same model in
some ways. Uh but we don't necessarily
always do that. So yeah.
>> Uh you mentioned audio as well, right?
Yeah. Uh is that as hard as video or
qualitatively different? If if so, in
what way? Uh, one of the interesting
directions three years ago was people
using um, I guess diffusion to do audio
uh, as in like the the sort of refusion
approach. I don't know if you you guys
saw that. Um, and I just think it's like
very interesting if a modality that we
perceive which is audio is different
than video actually two machines is
exactly the same like there's they see
no difference.
I mean I think on a technical level
there are some differences but I think
they're like relatively minor. I think
from my perspective audio came into into
my life when we shipped V3 which was I
believe the first model that did like a
joint
>> with the slicing of the
>> Yeah. Yeah. the gold bars or whatever.
Um it it was the first model that did
this sort of joint audiovisisual
generation. Yes. uh like in the in the I
mean there are there were other models
that did kind of you know kind of kind
of agentic hacking under the hood but
this one was truly sort of you know
generating everything at once and we the
reason we did that is because we felt
and I think was the right choice we felt
that like uh it only makes sense to
generate them at the same time because
there sort of kind of like from a
machine learning perspective there's one
latent kind of you know causal kind of
you know generative process right like
there's something that generates you
speaking it's not the pixels and then
the the audio or somehow somehow
generated by some other process like the
lips have to move in sync with with the
with the audio, right? So, I think that
that solved a lot of the issues that
previous models had or the way that
people did video generation before where
it was like, okay, we generate the
pixels and then we're going to hack
something on top of it that like moves
the lips with the audio that we
generate. And that's was very bad.
[laughter]
And so, I think I think that was that's
to me that's the the I mean after V3
like you know people were like what do
you mean like there's no audio in your
model? like that makes no sense like
once it's there like you you have to
have it. So I think that was that was
the right choice and doing it to one
single generative model I think was was
the right choice.
>> One thing I kind of want to also can ask
you guys an opinion as well once one
difference I find the audio and then
against the image and video is like the
audio information is less verbalized. I
mean of course the TTS and stuff is
trivial right but the when you get her
outside like how to describe music how
do you describe this like this person's
tone kind of pitch I feel the sort of
the verbalization is insufficient and
the interesting thing is that you kind
of see that in two other things like
taste taste sense and also uh say um
touch
>> like smell and then the another
interesting thing is the skin color so
skin color the the language is pretty
limited to describe the skin color and
the reason is that we're extremely uh
sensitive to the small difference
perturvations or not skin color because
that basically shows us is this person
going to kill me or is can I befriend
this person kind of those kind of
information and then I feel the smell
tastes um skin color and like sound kind
of stuff is very very tied into
primitive a like survival kind of stuff
and so our sort of sensory system is so
sensitive that it's intractable to Um,
so for example, I asked like one the
wine sort of taster and then like
professional and then he basically said
he kind of use like a language from like
a dating, you know, describing like a,
you know, partner as a way to describe
the taste because there's no sufficient
vocab to describe. Um, so I'm kind of
curious. Yeah. Do you guys feel that?
>> I think well to some extent I think the
same is true for visual information,
right? when you think about like a
certain style or a certain aesthetic,
right? Like like there are some people
who just have a much more kind of
developed like whether it's palette or
kind of visual taste and aesthetic,
right? Like I I think language just
tends to be a bit of a limiting factor
when you are trying to describe any of
these things that like we experience
with sensory information. And to your
point earlier, I think that is the kind
of the reason why we are investing in
world models and why we are pushing on
kind of the like perception and like
generation side of things because it it
is such a large part of how we as humans
navigate the world. It's a large part of
how like embodied AI navigates the
world. Um, and and I do I do think
language like does have a lot of it's
it's gotten us very far and it can
probably get us really far, but it it
feels limiting in a lot of these kind of
areas. And yeah, I don't I don't really
know how to describe, you know, like
sense and taste. Um, but yeah, I'm
curious to me.
>> Um, I I yeah, I don't know that I have
thought that deeply about this yet. So,
uh, yeah, I mean yeah, I don't have a
good answer about audio. I mean like I
don't know the limit because I'm
thinking about like well what is what is
Omni bad at in terms of audio but
they're all like solvable problems I
find uh so like with more data or better
data or whatever it is so I don't know
like that we have pushed the frontier so
much that like we are have hit some sort
of limits that are rooted in
evolutionary uh kind of you know limits
imposed by humans. I don't know. He's
feeling the limits of captioning which
is the the thing I was
>> Yeah, exactly. [laughter] There there's
a lot of information in the world and it
connects to basically why we do work
modeling you mentioned. You just need
srefs sref476
and then that's your that's what your
journey does, right? I guess maybe I
can't describe this vibe but
>> well well I think that that's kind of
the point of providing some of these
references, right? Because because like
even just describing how someone talks
and like their tone and and like procity
and all of these things like I think I
think some of these terms even like I
didn't used to know what they mean,
right? Well, now
>> yes. Dispuencuencies ex like like there
there's kind of an entire vocabulary
that even if you're not kind of steeped
in a domain, which is true for actually
like most human domains that like you
don't even know what it means. Um and
sometimes it's also a question of like
if we haven't focused on those things,
you know, with the large language models
that they may also have gaps in those
areas, right? And then we feel them on
the other side with generation because
we're like fundamentally relying on on
the language models understanding of the
world to then be able to like represent
it. Um, so I yeah, it all kind of goes
back to your question about like the the
language as an intermediary. Um, but
yeah, I think to De's point like some of
these might just be like focus areas and
things that we haven't necessarily
pushed on as much as we can and like as
we will we will discover what the actual
ceiling is.
>> Yeah, as a podcaster I think a lot about
sound.
>> Um, and and I I'll just offer a couple
things for discussion in case in case it
triggers anything with you guys. Um I
have three domains of rough audio which
is like a music voice SFX you know is
that rough okay covers everything and
then also even within voice let's just
let's just focus on voice forget the
other two um room sound like the the
echoiness of like big room small room in
person in a car over a phone all these
like are labelable but we experience
them very differently and I I often
think like one of the tells of a AI
video is that it is studio quality
because it was recorded in a studio
video because that's your training data
and like and and to me that's one thing
actually like the most interesting thing
is just uh when I tell this is how I
convince people who are kind of
skeptical about the need for world
models because you need it even for
audio about well I'm further away from
you so I should sound a little bit
softer or more diffused and like the the
video models need to pick that up
because if they're going to do immersive
video and audio you need that
>> I I I love that example of basically
like studio quality or not in a way like
we don't have enough language to really
describe like like this kind of echoing
or like some kind of noise kind of
happening we just like don't have
precise enough and uh if you um you know
basically the reason that I think it's
quite important to have like relatively
information rich like kind of captioning
is that we kind of rely on the natural
language as a representation but if you
basically don't have enough uh
representation that basically means the
condition on the language the generation
is very multimodal and if you anything
can learn from the BAE kind of like you
know very old you know GMBA kind of
research the idea is we really want to
capture most of the stoasticity in the
later representation and then the the X
given the Z should be kind of like
deterministic so yeah
>> yeah yeah um well I hope I hope there's
more uh progress there and I'm sure you
guys are doing
>> I even actually like facial expressions
right and maybe this gets to your point
about like things that we're very
sensitive to right I think you can tell
a lot of AI content also just by from
like people's facial expressions
stressful.
[laughter]
>> Yes.
>> Yes. And we try not to contribute to it,
but you know, um and or or like skin
textures, right? Like like the things
that kind of make things look real in
real life. Like I you know, I can tell
from the way you're nodding or from the
way like your micro expressions are kind
of changing of like how you're reacting
to what I'm saying. Like we haven't
quite crossed that chasm. I think like
we're we're so much better than we were
a year ago.
>> Yeah. Um, but there's so much more
headroom kind of in a lot of those
things that like we as humans are super
sensitive to. And like I think image
arguably probably is there because
there's there's a lot of kind of images
that I will see that like really do look
indistinguishable from reality and I
can't tell if they're generated or not.
>> Better than reality
>> um or well that's a different
>> No, I I think that one of the parad
[laughter] better than what I would take
on my vacation as a photo. Yes. One of
the one of the fun experiments that we
did a while ago in the team is is like
can we generate videos that are better
than than real videos, right? So you
just take the same caption from like oh
yeah some video and then
>> recycle it. Yeah. Just just try to like
describe a real video and then generate
the equivalent version with omni and
then do a human eval. How does how does
it do? And then humans largely prefer AI
generated
>> margin.
>> But because it's because it's the RL
process,
>> that's the RL process working.
>> It's however you want to rationalize it.
It's not necessarily the old process.
It's just like I think it's just I'm not
saying this is a good result. I'm just
saying is we have optimized in a way
that like kind of potentially sort of,
you know, triggers something in the
human brain that like, oh, it looks it
looks all a lot of the videos just look
better. Like I'm not Yeah. Yeah. Yeah.
on on inspection on on deeper inspection
they they would not actually be more
useful or whatever but like if you just
say side by side random YouTube video
versus
>> generated version of it will you will
just have a it will just look better
because it's more it's a sharper more
HDR uh you know the skin tone is is is
better it's not again it's not more
realistic
>> uh it doesn't solve your problem
necessarily but it it looks better
>> I I since also depend on the sensitivity
of the people. Uh I was born raised in
Japan and I think one thing I kind of
know is like they're extremely extremely
like sensitive about like you know
that's why you know like architecture
like food and stuff like they have.
>> Um so I talked to like a mangar like
like artist there and he's like he's
kind of disgusted by like the generation
AI and one kind of thing he mentioned is
like the eye gaze
>> eye gaze that slight difference
>> makes me makes him kind of feel creepy
about like unnatural
>> like if you're looking a little bit off.
>> Yeah. It's just uh Yeah. just like uh it
looks too fake. Yeah. To the point. So
So I think it does depend on the
sensitivity and
>> Yeah. Yeah. All I'm saying is like you
know human preferences are like a not
particularly like uh reliable barometer
of like what you should be optimizing
for like if you just ask people do you
like this or not you not necessarily get
what you wanted.
>> Yeah. Let let me just kind of add one
thing but like four years ago there was
a like debate that if the prompt
engineering is going to disappear and uh
my my like you know some very powerful
people say you know it's going to
disappear but I basically said like it
shouldn't because the prompt engineering
like sort of you know specifying that is
like the the only way you can sort of
control the output sort of you know when
you have like sort of control by the AI
and what allows you to prompt engineer
is really that sensitivity. So sure
maybe like right now the AI can do a lot
of autoprompting and that and it can
generate something that's sufficient but
uh if it's like that never be satisfied
like never be satisfied with the AI's
generated content always fine-tune your
sensitivity and always kind of keep
prompting the differences. I I think to
the there's also a big difference
between like the average human untrained
eye which I I would put myself in that
bucket you know like I have I have some
aesthetic sensibilities and I've done
this long enough that you know like I
have I have a preference um but you know
like your example of a manga artist like
that's somebody who has honed a craft
like over possibly many decades. Um, and
anybody who does that, whether it's like
design, architecture, right? Like you
you you just have a very different level
of like expertise and you see things
that like the average human will not
see. But Doom is right. Like when we
look at if you were to just, you know,
um, poll 10 people on the street, they
would probably prefer the like overly
smooth like very saturated kind of
>> It's called the Instagram filter.
>> It is. It is the Yeah. [laughter] Um,
and you know, and and so there's also a
little bit of a question of like what
does your default aesthetic look like if
you don't specify? But then to Shane's
point, one of the things we always try
to get these models better at is
instruction follow so that like when you
want to get them to a different outcome
like you should be able to whether
that's through language or whether
that's through references because
language is sometimes too limiting. Um,
and so like these models continue to get
better at it but they so much at work.
Do do you feel pressure as a as a
product director to set the default for
the world like I mean [laughter]
>> kind of
>> maybe I should I don't know I haven't
thought about this
>> you know you know it's like someone has
to have a default the default has to
exist
>> actually I will say like we have thought
about this um and I I think one of the
so for example actually like if you look
at nanobanana generations we had like an
explosion of nanobanana infographics
when nanobanana pro came out
>> I tried it yeah
>> um yeah
I think Nurb's papers were like all you
know so so many had like infographics
generated. Can you run your uh
watermarking on it and see how many
>> uh we pro we probably could we have we
haven't done that but I saw so like my
Twitter was maybe this is just also like
the bias of my algorithm but they were
everywhere um and it was actually very
painful because um I think our default
aesthetic was a little bit too it was
too cluttered like I think that the the
model is like a bit of an overeager
student that just like learned you know
it was like oh I know all these like I
know all this information about this
concept let me like shove into the same
image. Japanese infographics 5x that
[laughter]
>> or maybe it was you know um but it just
and and
>> wait so same prompt same content if it's
in Japanese it's
>> density density
>> oh wow
>> because that's the style in Japan
>> yeah some like very you know bureaucrat
and [laughter] there's a famous word for
it yeah
>> no but we do do go through this process
with Omni we did it together right like
where like we had like a bunch of like
we like at the very end okay like this
is we did some tuning and like okay what
kind of style do we prefer right like
you know
>> is it more muted more saturated
>> we had a lot of saturation
>> yeah there was there were I think Nicole
just has PTSD so has forgotten about it
but she was very much involved in this
of like okay which which kind of color
palette do we basically prefer right and
it's you know it's it's it's not
something that like you have to make a a
trade-off there like uh
>> and and and it's because it ends up
being us right like actually it is true
like it it ends up being the modeling
teams and you could ask the question
legitimately of like are we the best
people to do that or should we actually
work with someone who like has a really
creative point of view and is more of
like you know an art director and like
has like and we kind of go back and
forth on this um
>> we have the trusted testers I'm on
>> we do we have trusted testers who give
us a lot of feedback and we take that
serious
>> very well organized by the way to have
these like weekly calls and stuff like
it's it's amazing
>> um Logan's team does a lot of that so
kudo kuda kudos kudos to Logan um who
couldn't be here today um and we have a
lot of people actually internally at
Google like Fulfur who give us like a
ton of No, no, no. Truly like who give
us a ton of feedback on like when we
when we release new checkpoints and like
sometimes it will be stuff that we like
don't see right like we would be like oh
yeah this optimization seems okay and
then they would come back what have you
done like you completely ruined my grass
you know because now the detail is all
blurry.
>> I think he just noticed not not a super
secret at this point but like that our
model tends to put rings wedding rings
on on on hand. That's yeah
>> very strange. I had never noticed that
but he's like he I just saw it and
there's a faux fur channel basically.
>> Uh he posted I was like why is there
wedding ring in every hand? I'm like
that's strange.
>> That sounds very common reward hacking.
>> Yeah. Yeah. Yeah. So but you know
something that we would not have we
would not have noticed necessarily while
while developing this right is an oral
artifact or
>> I I don't know you do have like a lot of
preference based and then you know you
may can prefer that sperious correlation
reward hacking it can happen like in
many weird ways. Yeah
>> it does. It is
>> uh this was related to another topic
that again I I try to use these
mainstage things as introductions or
ties in. Uh we have the eval track we
have character AI and YouTube talking
about how they evaluate videos. Um how
do you evaluate videos
>> apart from furer [laughter]
>> not everyone has a fauxur but also you
know I think there needs to be something
more quantitative
>> well I mean it's you improve Gemini
to improve the evaluation for video. Um
that's that's no no that's that's
definitely one way uh it's actually very
hard.
>> It's very hard. It's very hard um to get
like you know audators to evaluate
things in a video like including
especially things like aesthetics right
like that it's like there are some
things that are a little bit more
objective like especially when we talk
like let's say we talk about images and
we look at like infographics text
rendering that's actually fine right
because like you can kind of OCR things
out and then you can look at like okay
this letter is like messed up and then
the whole thing is actually useless
because if like literally if a letter is
off in render text you just can't use
that asset. Right. So th those things
are like a little bit more auto ratable.
Um from what we found we do rely a lot
on humans looking at things and so we do
do a lot of human evals. We do a lot of
human evals.
>> Do a lot of human ev and every time Jane
is like um and every time we have a new
model we like want to do more things and
we want to like gem in more capabilities
and then we have like more emails that
we have to run. Um, and then at some
point you do get two models that are
like kind of close to each other and
then like we literally make decisions
based on like looking at outputs side by
side. Sometimes like in a room like I've
been in rooms where there's like 10 of
us and we're just like looking at video
side by side and we're like do you
prefer this or do you prefer that? like
oh wow it's
>> I mean but it is it is genuinely very
complicated the more capabilities you
add like you know even just the one
capability but it's like almost AGI
complete capabilities like video editing
right like think about video editing as
a and like editing with audio and
>> my editor will be very happy to hear
this
>> edit the hardest problem in g media
>> I mean I don't know if it's the hardest
but it's definitely there right like uh
in terms of like complexity of of
evaluation like free form video editing
is you can do anything like
>> yes uh and like I I spent a lot of money
on that and it's very hard to tell me
>> like adding those we don't have like add
a sloth eval right like uh that we
>> well now we should
>> now we should yeah yeah yeah but like
things like that like it's it's it's not
that easy to track
>> I think I'm just surprised at the sample
size that you have right like to to test
the entire surface of your models you
still rely on a magnitude of hundreds
>> no no no so we do like yeah well we do
we do a ton of human evals on like on
like you know thousands of things. Um I
I think there's also like an element of
you know we can talk about things like
live experiments right like which which
is also where you get signal on like
like some of these more minute
differences at like much larger scale
then there's auto raers which is
definitely kind of a more it's a very
well defined space I think for LLMs much
more nent for media models and then like
sometimes you still do rely on human
judgment and we do rely on things like
feedback from people who just like have
a very owned like aesthetic and and
people who just like use these models in
their workflows dayto-day, right?
Because we could also like you could
have a model that does really well on
some slice of human evals, but then it
like really breaks a workflow for
somebody. And so this is why we do like
early access programs and we try to get
feedback and then we like try to
incorporate it before we release
something more broadly. I feel like
Shane had a hot take based on his
>> expression always when we were talking
about this
>> every kind of human sort of you know
work should be gradually kind of
amortized and then the interesting thing
is the video understanding especially
like against like AI gener like
detecting air stuff is extremely
interesting uh visual task
>> and then like some of it kind of
aesthetics or this kind of visual
quality but for some of the kind of
cases like semantically doesn't make
sense for example you're taking like
some like a famous scene from a movie
and try to sort of um construct that and
then if you kind of generate it uh it
can generate something there but at some
point some of the semantic information
doesn't make sense like it's actually
inconsistent. So can the AI actually
detect that? So when I evaluate the AI
videos like oh I feel I'm so smart you
know like like AI is still kind of
behind but we should make like a lot of
effort. I think the video understanding
is extremely uh important intelligence
task uh beyond just the pure aesthetics
or the preference. Um and yeah we we
should always try to amatize the human
>> human label. Yeah.
>> Yeah. Um, what data do you need? A lot
of people I talked to wanted to get in
front of you actually. Uh, they I mean
they want to be nice about it. They have
a lot of video data. They have gaming
data. They have real world video data.
They have images. They have labelers.
What do you want?
>> Are you like offering?
>> I'm just like this is your request for
like Okay. Okay. We get I'm sure you get
a lot of pitches, right? You get a lot
of people want to talk to you. what's
like I think actually it's the signal is
this problem this sorting out signal
from noise is the main problem so
creating a nice API of like okay if you
actually do a b and c we are interested
in that
>> um
loaded question there so uh I don't know
that there's like an easy like you know
did you do I think we we do already have
a lot of data I think it's it's
>> hard to talk about this
>> you know you want to talk about the
public I don't want to get you in
trouble Yeah,
>> but like I think
>> No, no. What I just want to say is like
hard to talk about this in a sort of you
know without trying to without I have to
think about the what I am revealing
about our project and what where we're
going. Um generally high quality data I
think maybe maybe let's just put it this
way right it's not not the secret
>> embodied I'm sorry
>> embodied data
>> I mean
>> yeah sure I mean we have sort of
announced I think publicly right that we
we have some sort of robotics
collaboration right like so I think it's
like a like or or but you because we
have a robotics team at GDM so you know
they're always interested in things like
that um I mean for Omni specifically I
think we're just quite interested just
high quality data right like you know it
it's not some sort of not necessarily
like oh random YouTube video but like
you know some a some more professional
shop things like that right the things
that those are those are things that
we're always on the lookout for like uh
and yeah
>> and I think for you know maybe this is
easier to some extent to answer for like
some of the agentic work as well like
like like actual kind of like what are
the tests that people are trying to do
right these things are actually kind of
difficult to manufacture if you're doing
it yourself or if you're like doing it
with a vendor, like what is the actual
like if you're creating a marketing
campaign, like what does that look like,
right? Like do do you start from here's
like a picture of my new product and
then I want to turn that into a video ad
and I want to turn that into a bunch of
assets that like fit fit all these
different ad formats that I need to push
onto various platforms to promote and
then like so you kind of go from this to
that and like what is that kind of
trajectory of tasks that you're that
you're like you know experiencing along
the way like that is really useful and
that is actually kind of difficult to
get right u because like we don't always
have the right firstparty surface where
people are actually doing some of these
things or like you might work with
someone who's a vendor but they don't
also don't have that product surface
right like like a lot of this kind of
information lives in the places where
people are doing these tasks and so
that's kind of difficult to get like if
anyone's figured that out you should
reach out to us
>> every channel of thought yeah every
[laughter] thought
>> every thought yeah and maybe the data
the Chinese lab is using
>> yes yeah uh you know
as a media person myself, right? Like
there's so many podcasters and people in
in marketing departments and all these
like they would happy to be your data
like you know just like put a BCI on my
head
>> and podcast [laughter] watch my things
uh because like you know there's just
endless amount of work to do like
there's so much work and this is all
like this needs to somewhat be commodity
like obviously you can be an art like an
artisan like you can be Hollywood for
like the really high quality stuff but
actually a lot of work is commodity and
like should be modelable and we want you
to do
>> [laughter]
>> And but we we want the high quality to
Demi's point right like we do want we
want the high quality.
>> We want commodity. Yeah. Yes. Yes. You
want on both sides.
>> Um I I just
>> Thank you for the solicitation.
[laughter]
>> Uh I you know we we we also I also added
a data quality track. I I think that uh
people want to understand like what uh
at AI like how to raise the bar, right?
like like the and a lot of it is just
educating the market and educating
researchers and engineers and founders
on like this is where we're going a lot
of this is stop doing that do this do
this instead and I'm like people will
listen
yeah I don't know uh to that extent you
know
>> but I think to that to that point like
there's a lot of again just like craft
that goes into this right and there's a
lot of process like you even to the
marketing campaign example you don't
create that in like five minutes right
you like go you go through a process and
you iterate and you like pick something
over something else because you liked it
for whatever reason like maybe the eye
gaze was correct right like we just we
don't know these things right because
none of us are marketing directors and
like the models don't know these things
>> I even kind of say this for the natural
like a language as well like I I always
kind of say 99% of information is inside
people you can only extract it through
active dialogue and befriending them so
most of the stuff on the internet is
like sort of the outcome the output of
that yes but you know what are what are
all the trajectories you know how did
this person have this inspiration to
write this paper
>> what is the starting point what is the
inspiration what are the dialogue that
sparked it those kind of stuff is kind
of inside people so even you know those
kind of like even the language space is
kind of that I think the creative is
kind of similar as well there's a lot of
dark knowledge
>> yeah it's like when you write a novel
right like a novel speaks to you because
like usually there's some sort of like a
personal connection that you feel to
like the story or the trajectory or the
characters right like if you read most
of the stuff that's written by LLM's
today like it's, you know, it's it's it
starts it falls into these like default
par patterns and like the language
starts to feel really similar and all
the descriptions sound really similar.
You can kind of like quickly read it as
like, oh, this is not that interesting
because like I can't connect to it,
right? Um, and again, that's that's kind
of like a human expertise.
>> One nice thing recently is the Google
Cloud and the Google Deep Mind are kind
of starting to invest a lot more in the
FTEEs for the product engineers. And I
also kind of saw some uh recruiting for
the creative you know gem media kind of
space as well. So I think those are kind
of really the effort because we we kind
of feel that you know what we can kind
of do with a lot of public data there's
limits but really you know partnering
with that we can provide kind of better
models and products and yeah we kind of
feedback
>> uh we have an FD track here for the
first time every lab is announcing it.
It's it's crazy. Um, one thing I'm
actually very keen on doing and I push I
push for this at Cognition as well is to
turn the FDES not just into sales and
solutions but also to EVAL's uh eval
workers.
>> FD is not the sales FD is way way bigger
than that. How do you frame FDs then?
Because [laughter] I do think about it
as sales like you're you know the more
the more you customize the solution for
>> so I define post training as anything
between the pre-training and the final
user experience anything anything is a
post training
>> and to me when I first sort of you know
learned a lot about I mean FD kind of I
guess originally you know came from like
path here and then that so I guess the
kind of history is different but yeah I
think the key is really that um you know
the key is like not only to kind of work
uh with them and ensure that they kind
of know how to
but also to sort of code like derive
kind of insights that can basically kind
of help both parties. They can put the
like a lot of harness how they use the
model. We can improve like very
upstream. So how to get the customer
feedback to the modeling I feel is the
kind of more the the role I I kind of
want for the fds. Yeah.
>> Yeah. Yeah. and and even for sorry just
on that like if you want to talk to us
or at least me um I I'm not going to
offer up your time um but I it's really
helpful for us to actually talk to
people who are using our models and like
understand where they're struggling uh
because again that just like it's it's
the real world task that you're actually
trying to use them for right like I will
talk to people who do kind of interior
inter interior design with some of our
image models um you know and they will
say hey like I really want to take this
pattern pattern, but then I want to
scale it across like 10 different ruck
sizes and sometimes I have like a very
custom ruck size and then the model
fails at like replicating the pattern
the same way or you know I want to do a
try on for these earrings and then the
earrings have a certain size and then
like my head has a certain size like it
has to make sense if you're actually
trying to try things on and like the
models kind of fail at a bunch of these
things that like actually happen in the
real world, right? Um and so that that's
like useful for us because for some of
these things like we don't think about
because we don't you know we don't use
the models for those tasks
>> or like um you know I think to your
point about ad campaigns or whatever
like people have like notions of brand
languages or whatever like which is
>> yes
>> like a a bunch of images or PDFs saying
things you know it's a pretty kind of
you know ambiguous question as well what
is the IKEA brand language you know is
it is it blue and yellow I mean that's
that's not a very like
>> but like what shade of blue you know.
>> Yeah. Yeah. Yeah. So there there's like,
you know, and the brands are pretty
spec, you know, pretty, you know, like
they they do care about the shade of
blue. It's not shouldn't just be a
random blue and a random yellow. That's
not going to be IKEA, right? I'm just
thinking about an example. But like this
is the kind of stuff that, you know,
it's not necessarily part of our like,
you know, developing frontier models
kind of, you know, necessarily mandate,
but it's something that we do want to we
do want to fundamentally like build
products that people will use to solve
concrete tasks, not just not just
research artifacts, right? So I think
it's useful to understand what people do
care about. Uh well, I'm sure a lot of
people are very grateful for your work
and there's a lot more to do that you've
made so much progress over the last like
even just couple years of like Nano
Banana and Theo and Omni and uh I don't
know what else you got cooking but we're
very excited like you this is one of
those things where like I was very
disappointed you know when Sora shut
down and and I think like there needs to
be more general exploration of uh you
know generative models and not just you
know coding. [laughter] I think I think
that is
>> we obviously like this.
>> We love coding. Love coding and and uh
yes uh but thank you so much for your
time. Uh it's been a real pleasure and I
can't wait to see what this looks like
next.
>> Thank you for having us. Great question.
>> Thank you everyone. [applause]
