---
titulo: '"My name is... my name is...": A Linguistic Map for Voice Agents — Midam Kim, ServiceNow'
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-15'
url: 'https://www.youtube.com/watch?v=IDNfAZVKvPE'
thumbnail: media/monitoring/youtube/idnfazvkvpe.jpg
descricao: 'Midam Kim spells her first name for a voice agent, letter by letter. The bot reads it back with an N on the end. She corrects it. The bot replies thank you for your correction, then says her name wrong anyway, because its pronunciation rules are English and her name is not. Moments later it asks for an account number she has to go looking for, cuts her off while she reads the unfamiliar string aloud, announces it cannot find her record, and asks her to repeat the whole thing without trying anything different. She asks for a human. Kim is an ML engineer at ServiceNow and a longtime researcher of speech in the wild, and she uses that call to argue that voice AI failures are not a scattered list of bugs but a structured one, and that linguistics already has the structure. Her map is a grid. E...'
resumo: 'The talk uses a linguistics lens to diagnose why voice AI agents fail, walking through a real call where a bot misheard a name, mishandled corrections, and cut the speaker off. It frames human-bot conversation as a joint activity and proposes a linguistic framework for building voice agent architecture.'
tags: {}
fontes: {}
---

## Descrição

Midam Kim spells her first name for a voice agent, letter by letter. The bot reads it back with an N on the end. She corrects it. The bot replies thank you for your correction, then says her name wrong anyway, because its pronunciation rules are English and her name is not. Moments later it asks for an account number she has to go looking for, cuts her off while she reads the unfamiliar string aloud, announces it cannot find her record, and asks her to repeat the whole thing without trying anything different. She asks for a human. Kim is an ML engineer at ServiceNow and a longtime researcher of speech in the wild, and she uses that call to argue that voice AI failures are not a scattered list of bugs but a structured one, and that linguistics already has the structure.

Her map is a grid. Each party has a listening channel and a speaking channel, and each channel operates at four levels: sounds, words, interaction, and mental model. Recognition failures sit in one cell, pronunciation in another, turn taking in a third, intent tracking in a fourth. Every familiar problem lands somewhere, which is the point. The cells are interdependent rather than separable, so fixing sounds without words, or words without timing, does not hold. Her sharpest observation concerns what survives a call. In chat the history sits on screen as text. In speech the sounds vanish as they are spoken, and the only thing that accumulates is the user's mental model, which is what you are really designing for.

Speaker info:
  / midamkim  

Timestamps:
0:00 - A call that goes wrong, step by step
3:39 - Conversation as a joint activity
5:25 - Replaying the failure through that lens
7:14 - Eight cells: two channels, four levels
8:56 - Why the layers cannot be fixed separately
9:49 - What vanishes, and what accumulates
10:42 - What to actually work on
12:28 - Diagnosing your own agent
14:13 - Speakers adapt, and languages change

## Transcrição

[music]
>> Okay, hello everyone.
So,
my name is Midam Kim. I am an ML
engineer from ServiceNow and I'll be
talking about a linguistic framework for
voice AI.
So,
quick background of me so you know where
I'm coming from.
Like I said, I'm an ML engineer at
ServiceNow, but I'm also a researcher,
lifelong researcher, of speech
communication in the wild.
So, my motto is doing linguistics and
what I'm going to be doing today is to
hand you that lens of linguistics.
So, have you experienced voice AI
failures?
Yeah, like everyone.
>> [laughter]
>> So, I'm going to introduce an example
that I experienced myself.
So, the bot asked me, "Could you please
spell your first name?"
And then I slowly start to spell my
name.
Yes, it is m i d a m.
And the bot says, "Confirming with you,
is it m i d a n?"
And then I say, "No, it is m i d a m."
Um
and the bot says,
"Thank you for your correction. Happy to
help you today, Madam."
And I then I
get slightly annoyed, more annoyed,
because my name is Midam, not Madam.
And then it asked me about, "Now, what
is your account number?
And then, I start start getting
confused. What is that account number
thing?
And then,
I try to find uh information about that.
So,
which one? Um it must be and I start
uh
slowly start spelling the account
number. So, it is A X 4 5 1.
And then, I take time because I'm not
used to reading this strange number.
And then, the bot cuts me off.
And then, it says, I couldn't find your
record.
And then, without even trying, it asked
me to repeat that again. Can you please
repeat that? And then, I get super
annoyed and then, I can say, can I talk
to a person?
I just don't want to deal with you
anymore.
So, this is a very typical pattern of
voice AI, unfortunately, at this point.
So, I just want to navigate how we can
solve this problem
with linguistics.
So, voice AI is booming.
But users are still often preferring
human agents over voice agents.
How can we mitigate this issue?
But in the first place, what are the
actual problems?
So, I think we can think about a
fundamental frame framework to
understand this into an architecture of
voice AI,
which is called linguistics.
So, as all of us already know,
human communication is a joint activity,
like the thing that we're doing right
now.
So, I give you my sounds and words.
You hear them.
And then, if it is a conversation,
you're going to give me your sounds and
your words.
And then this is going back and forth
through interaction.
And then in this process, we're
continuously
processing and updating our mental
models.
So that's a joint activity
for human communication.
And I would like to say
in the voice AI human communication,
it also has to be a joint activity like
this.
Because that's the only thing that we
know about human communication as a
human being. We have been evolving
thousands of years as communicators, and
this is what we know. So we expect the
same thing to bots.
So let me go over the failure scene of
my call with the voice agent
in this framework.
So you see there's listen
and speak for each party.
So I start spelling my first name.
And then the bot did not hear that the
difference between M and N correctly, so
it's an
SCT failure in the listening level.
And then the TTS applies only
English-centric reading rules to my
name, M I D A M, would read it as Midam
in the
American English version.
So I'm confused, but at this time I'm
kind of generous because that happens a
lot even with human beings. So I'm okay.
But then when it brought
brought up account number thing
because I don't know what that is,
I'm confused again.
But I'm adaptive, I can find I can look
for it.
So I found the number, start reading it,
but
the STT did not recognize the word unit
correctly, so
it cuts me off, and uh
uh finally, it's uh eventually talked
over me.
So, I get
really irritated.
And then, when it asked me for the
repetition of the same information, and
then, it is clear that the spot is not
tracking the mental model with me.
And then, very rudely, it's uh does not
even try interactive clarification,
which is a common strategy by human
beings.
So, I don't want to deal with this
anymore, so I say, "Can I talk to a
person?"
So,
let's go over the uh the framework
again. So, the these are the linguistic
components that are expected and well
maintained in human-to-human voi- uh
uh conversation.
So, there are listening channels, a
listening channel and speaking channel,
and there are different components like
sounds, words, interaction, and mental
model.
So, the first component is, does the bot
recognize the user's speech well?
And all of these technical terms
uh will fall under this.
And then, there was there's going to be
this second component, which is words in
the listening channel. So, does the bot
understand the user's words?
And then, the third one is, does the bot
wait until the right timing to for its
turn? It's about It's going to be about
uh listening channel interaction.
And then, uh the last part is mental
model. So, does the bot understand the
user's intention
in the listening part?
And then, we can also go to the speaking
channel, so it's going to be about
pronunciation for the sound.
And also there's about understand the
the words users are
uh there's about choose the words the
user can understand.
And in the interaction part, there's
about speak with the right timing.
And lastly, there's about speak with the
information the user actually need.
So, there are a lot of engineering or
linguistic or cognitive science terms
that are in here that that are here. Um
you can see now see that all of those
have their right spots in this
linguistic framework.
And importantly, these components are
interdependent,
not separate or uh independent from each
other. They're interdependent and
they're aligned. So, when you want to do
good things about sounds,
you have to think about words level.
And then when you want to do good things
about these sounds and words,
you also have to uh account for
interaction, so turn taking or turn
detection.
And then finally, you want to uh have
good uh task completion, which is the
goal of these mental model uh layer.
Then you have to have all of these.
Without all of those, without any of
those, any of those components, your
voice agent will fail.
And then finally,
uh it has to be well aligned. All of
these have to be well aligned.
And additionally, you have to keep your
mind keep in mind that
this is happening on the timeline.
What I mean by that is it is silently
tracked. Unlike in chat, in chat you see
the history of what was said
uh as text.
But in voice agent experience,
uh, you say something, and the bot says
something, you go back and forth,
and then see, all these waveforms, the
air via the vibration in the, uh, in the
air, they're all gone.
And only the user's mental model is the
thing that's left, and that matters.
So, sounds, words, interactions vanish
the moment they're spoken,
but the mental model proceeds and grows
over the timeline.
So, this is what you have to
target
for user satisfaction.
And then, what can we do
for the bot to meet the standard of the
user?
So, what we can do, uh, would include,
of course, choosing good ASR models or
configurations and do some
post-processing,
uh, choosing good TTS models,
configurations, and pre-processing,
and, uh, carefully curate the vocabulary
that can be shared between the bot and
the user,
and do good job of a turn-to-turn
detection, latency, and turn-taking.
Um, and very importantly, we have to, it
would be great if we can do good emotion
detection and handling, and context
retention, and by context, what I mean
is context about all of these.
And importantly,
uh, it has to be dynamic because things
are always changing, uh, throughout over
the course of the call. So, we would
have to do this management dynamically
along the timeline
for different kinds of people.
So, kids or different kinds of people
like these will have different
expectations that we have to satisfy.
Uh, not just when they're happy, but
also when they're not happy.
So, only then you can pursue a dynamic
and truly scalable orchestration of
voice AI.
So, it's a very difficult job to do.
We always say that voice is the most
natural way of communication, but it is
not actually not easy. Behind the scene,
it is thanks to this linguistic
orchestration.
When your bot is not good at it,
it's a catastrophic failure.
Um, so paying attention to this
linguistic framework would have lots of
business implications because then you
can uh
decrease all of these user frustration,
task failures, live agent escalation, or
abandoned calls, or silent failures.
So, in ServiceNow, we have made a a good
uh benchmark end-to-end benchmark called
Eva bench. So, you can try that to
diagnose your voice agent's uh status.
Um, key takeaways.
So, voice AI is a joint activity between
the bot and the user, not just a
pipeline.
And we must serve users' needs in
multiple layers real time.
It's not that I have given you a fix
today because there's nothing like that.
It just uh the fix is in you and your
system.
But, what I have given you is today is
the linguistic framework you can try to
diagnose your system
and to build your system upon.
You can try Eva, but also you can learn
linguistics and hire linguists.
Um, another thing I want to remind you
of is that business implications are
linguistic implications and vice versa
in this voice AI scene. Because voice is
fundamentally a linguistic and very
human and cognitive experience.
I would like to ask you a longer term
question.
Speakers adapt. So, I
I'm pretty sure that in this talk in my
talk with you guys today, you have
learned something about me, about my
speaking style, what kind of accents I
speak, what kind of words I'm using. So,
next time I see you guys in person, you
would find it more comfortable to talk
to me because you have paid attention to
me.
Right? So, speakers are always adapting.
So, the user will be adapting to your
voice agent throughout the call. So, is
your system ready for them to
use you better, use it your voice agent
better the next time?
And
language is always change. So, is your
voice agent ready for language change in
1 year or 6 months even?
So, thank you.
>> [applause]
