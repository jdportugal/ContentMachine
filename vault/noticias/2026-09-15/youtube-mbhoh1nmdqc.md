---
titulo: 'Realtime Voice Agents with Frontier Intelligence — Bohan Li, EliseAI'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-15'
url: 'https://www.youtube.com/watch?v=MBHOH1NmDqc'
thumbnail: media/monitoring/youtube/mbhoh1nmdqc.jpg
descricao: "A background agent quietly makes the tool call, then drops the result into the main model's context so the model believes it made the call itself. That sleight of hand is one of three tricks Bohan Li uses to run a voice agent on a slow, genuinely intelligent model without the caller noticing the wait. Li works on the voice harness at EliseAI and came from self driving, and he maps the cascaded voice stack onto that world: transcription is perception, the language model is planning, and speech synthesis is control. Each layer then gets optimized on its own terms. For transcription he runs two engines at once, a fast streaming one that emits immediately and a slower one with more context that can correct it, where a late correction is thrown away if newer audio has already arrived. The slowe..."
resumo: 'A talk on how EliseAI built a cascaded real-time voice agent pipeline — transcription (perception), LLM planning, and text-to-speech (controls) — borrowing the structure from self-driving car systems. It details optimizations at each layer, such as a "streaming speculative transcriber" that pairs a fast streaming model with a slower, more accurate corrective one to cut latency without losing intel...'
tags: {}
fontes:
  - 'https://x.com/bobowchan'
  - 'https://eliseai.com/'
---

## Descrição

A background agent quietly makes the tool call, then drops the result into the main model's context so the model believes it made the call itself. That sleight of hand is one of three tricks Bohan Li uses to run a voice agent on a slow, genuinely intelligent model without the caller noticing the wait. Li works on the voice harness at EliseAI and came from self driving, and he maps the cascaded voice stack onto that world: transcription is perception, the language model is planning, and speech synthesis is control. Each layer then gets optimized on its own terms. For transcription he runs two engines at once, a fast streaming one that emits immediately and a slower one with more context that can correct it, where a late correction is thrown away if newer audio has already arrived. The slower engine knows from the question being asked which part of the answer is a name and which is a date of birth.

The synthesis trick is the neatest. A prefix cache watches the model's output stream and checks whether audio already exists for that run of words from an earlier turn. Openers repeat constantly in a scripted call, so the cache usually hits, and the agent starts speaking from it while the rest of the sentence is still being written. The synthesis provider is handed the whole sentence and generates it with natural prosody, unaware any of it has already played, and the overlapping audio is suppressed so the two halves join without a seam. Li closes by playing a real clinic booking call, where none of this is audible.

Speaker info:
https://x.com/bobowchan
  / bohan-li-7290b74a  
https://eliseai.com/

Timestamps:
0:00 - Borrowing the self driving stack
1:52 - Perception: a speculative transcriber
4:24 - Eager generation and background tool calls
6:06 - Control: streaming speech synthesis
6:57 - The prefix cache
8:37 - Hiding the seam between cache and provider
10:21 - A full clinic booking call

## Transcrição

[music]
>> My name is Bo. I'm going to be here
presenting real-time voice agents with
Frontier Intelligence. Effectively,
going to be talking a little bit about
how we at Xnor.ai architected our voice
agent harness to get real-time voice
with the Frontier level of intelligence
that we need.
Okay. So, before I start,
I think I wanted to kind of draw some
parallels about
why we decided to go with cascaded voice
agents and especially kind of
comparing that to self-driving cars
which I was working in before. So, to
me, cascaded voice agents makes a lot of
sense when you view it in lens of kind
of breaking it down into perception
which is
for self-driving cars, it's you know,
the bounding boxes, the camera, the
lidar.
For voice, it's going to be the
transcription. Basically, effectively
turning these like signals from the real
world into
elements of data that the language model
or whatever brain you're working on
can process.
Second one is the planning step which is
pretty straightforward.
This is where the language model
will take in the outputs from the
perception stage and produce the outputs
that you want to produce out back and
out into the real world.
And finally, there's the controls layer
where
on self-driving, you'd be taking the
trajectory that the planner would output
and kind of turn it into the real
controls to kind of build like drive the
car. Here, we're turning the text into
audio that we use to express our voice
agent's thoughts.
And um
yeah, so here I'm going to be like going
to diving into each one of these
elements and we've made a few kind of
interesting tricks on each of these
areas to
improve the speed of our voice agents
without sacrificing the intelligence.
So, the first one is going to be uh the
transcriber layer. So, we came up with
this concept called like the streaming
speculative transcriber where
effectively we are layering a fast
streaming transcriber like Flux on top
of or kind of below a
uh scribe V2 or a accurate batch
transcription which kind of takes in
more context. It's a little bit slower,
but it will give you more accurate
detections.
So, we're going to walk through a
scenario. So, in this in this case the
agent just asked, you know, providing
can you provide your name and date of
birth and the user is going to say this
and we'll see how that plays out um
timing-wise. So, first we're going to
get, you know, the short detection. Um
we'll get it from we'll get it from the
streaming layer. The accurate
layer uh the corrective layer is not
going to fire because it's the same
text.
Um we're going to get some more
streaming text detections and in this
case the corrective layer is actually
canceled because we got new um new text.
So, you know, more context, more audio
is going to beat the old accurate one.
And here's where kind of the first
correction comes in. So, because the
scribe V2 layer understands, you know,
the the context of the question, it's
able to understand that this is talking
about name and this is a date of birth.
Then a couple more detections, these are
just punctuation, we don't care.
And so, in the end we kind of release
this text over to the agent.
And moving on um to the language model
layer.
So, here since we're kind of using these
slow but intelligent LLMs, we really
want to reduce the number of round trips
and the thing that causes us to do a lot
of inferences is tool calling. So, one
way to get rid of that is by having
background agents do the tool calling
for you and kind of
um push the tools back into the context
of the main agent so that it thinks it
made the tool call, but
um
but it it it really didn't.
So,
uh so, we remember from like detections
from before.
So, well, what happened is each one of
these detections is going to trigger a
um an early
kind of generation of the agent and we
but we won't actually
emit this out until we're confirming
that the user has finished speaking. So,
in this case, the user says, "Sure." The
agent kind of knows that the user is
about to say something else. Our
background tool calling here, which is
going to be helping us find figure out
the name and the date of birth from the
user detection, is not firing. So,
nothing much there.
Um the next instant detection comes in.
It says that,
you know, still not really a name. Um
our agent kind of plays along and
continues there.
Now, kind of a more more context come
comes back. The agent kind of feels like
there should be a name. It's going to
ask to spell it out because it's
probably thinking there's some
transcription error here. Still no name
or date of birth.
And then finally, this you remember this
is kind of our corrected um final
instant detection from the transcriber
from the Scribe V2.
Um here, our eager kind of agent
generation that was made without any
tool calls is going to get canceled
because the background agent finally is
able to find the name and date of birth
it's looking for. So, it's going to
retrigger and now the the agent actually
has the context it needs.
Um and you see here, it's kind of we're
doing it the tool call here is a little
bit um um
some intelligence there. We're going to
be like, you know, correcting
mis-transcriptions of name, and doing
some like phonetic matching here.
Um and yeah, and then we'll kind of
once we've understood that this is the
end of the user utterance, we'll kind of
emit it out. So, pretty standard.
Okay, and then the next layer here is
going to be text-to-speech. So, with
text-to-speech
the goal is to kind of take what the
agent said, and the agent's going to be
emitting this in a streaming fashion.
So, we're going to need to
um produce audio as quickly as possible.
And ideally, what you can do is before
the agent has even finished generating
the full text you can have the audio
play, so it's kind of hiding the latency
of finishing the generation.
So,
um I'm going to kind of play the
streaming um
the stream the streaming uh agent output
now. So, starts with you.
And yeah, actually before I uh further,
there's this new concept that we're
introducing here called the prefix
cache. So, the prefix cache is going to
be looking at the
um agent stream, and seeing if we
already have generated audio for that
sequence of words um from like a prior
generation, or maybe like the same
generation
um in this
uh in this call as as well.
So, um it sees the word you. Uh we for
this prefix cache, we're going to be,
you know, we don't want to like
immediately hit on every single word.
We're going to be waiting for a little
bit more words.
Um so, after three words, the prefix
cache gets our first hit.
And um over here on the right, this is
kind of our text-to-speech standard
provider, you know, Cartesia is a
text-to-speech engine with web socket
support. So, we're we're piping the
agent through the the cache, and also
piping it through web socket.
Um more tokens come in, more cache, more
sending through web socket. Not much to
say here.
And okay, so now we get our first uh
kind of first unique thing, which is we
found the token that actually causes a
cache miss. And it makes sense. If we're
kind of caching previous generations,
um you said your name is is a pretty
common thing, but once you we add in the
name, suddenly we're that's that's going
to result in the cache miss.
At this point, we're actually going to
yield out our cached audio. So, you said
your name is is going to be
um emitted as the rest of the streaming
text is coming back. So, at this point,
the user hears the agent and user
doesn't really know what's going on.
They just looks like really fast
response times to them.
Um and now the kind of remaining text
flows through.
And at this point, we've already emitted
from the cache. The cache has done its
job. Um the rest we can kind of throw
into Cartesia.
And here's kind of the trick where
Cartesia has seen the entire transcript
up to this point. It
to to Cartesia, like it doesn't know
about the existence of this prefix
cache. It's just going to generate this
full sentence with, you know, standard
natural prosody.
But, what we do is when the generation
comes back, since we've already played
the audio here, we can actually suppress
the audio from Cartesia here and just
play out the remaining stuff. So,
the user, there's might be a tiny bit of
a hiccup. You know, I'll play some audio
later and you'll know that you probably
won't be able to notice.
And effectively, we're kind of taking
this and
or taking this part and just emitting it
directly after this audio has finished
playing. So, it looks seamless to the
user.
And yeah, so I guess at this point kind
of this is a lot of technical details.
I'll I mean the best way is just kind of
play a call and see
see this in action. So here I recorded a
call and let's just hear what happens.
>> This is Bo OBGYN Elise speaking.
>> Hi, I think I might be pregnant and I
want to schedule an ultrasound to
confirm.
>> That's exciting news. Can you provide
your name and date of birth?
>> Sure, it's Elise Trial.
Date of birth
2303.
>> Thank you. Are you a new patient with
us?
>> Yes.
>> Welcome to Bo OBGYN. Can I text you a
link to upload your insurance
information?
>> Sure.
>> Okay, sent. Let me know when you've
received it.
I've received your insurance
information. The earliest appointment I
have is Thursday, July 2nd at 10:00 a.m.
>> Actually, give me a second.
Let me check my calendar.
>> Sure, take your time.
>> Um do you have something for next week?
>> For next week, I can offer an ultrasound
with Dr. Avery Stone at North Clinic on
Tuesday, July 7th at 2:00 p.m. or 3:00
p.m. Do either of those work for you?
>> Yeah, 2:00 p.m. works.
>> Great. Your appointment has been booked.
We look forward to seeing you then.
>> Thanks. Bye-bye.
>> All right. Yeah, that's pretty much it.
Um
Yeah, you can kind of see our
all this like uh
streaming
and you know, a lot of things are
happening in the background and and you
know, this is what really makes like
voice agents interesting. And um there's
a lot of effort that can be done in the
harness to really kind of get a um
natural conversation, which is what
we're after.
Uh okay. Yeah, so I guess briefly, you
know, in the last part, I want to just
talk a little bit about Elise. So I
think Elise, you know, our headquarters
are in New York and kind of we're trying
to expand our presence here in the Bay
Area. Um we I think it's maybe like a
different style of company that I think
people are
like uh think of when they think about
AI startups in San Francisco. Where
we're actually very focused on um
just like helping people and helping
people where they need it, like kind of
the life's most critical areas. We work
on housing, health care and uh we're
doing really well and you know, here's
there's a link here to
um you kind of join our team and there's
going to we're going to be uh posting a
lot on Twitter, so you can follow us at
EliseAI as well.
Um yeah, that's that's it.
>> [applause]
