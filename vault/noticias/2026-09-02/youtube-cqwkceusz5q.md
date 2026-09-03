---
titulo: 'The Robotics Breakthrough Everyone Has Been Waiting For'
tipo: item_agregado
plataforma: youtube
canal: bycloud
data: '2026-09-02'
url: 'https://www.youtube.com/watch?v=cqwKceUSZ5Q'
thumbnail: media/monitoring/youtube/cqwkceusz5q.jpg
descricao: 'In-context learning for robots has been a long-anticipated capability, as it could allow robots to quickly adapt to new tasks without additional training, and Gen-1.5 might have just demonstrated that it is possible for robots at complex tasks. Gen-1.5 [Blog] https://generalistai.com/blog/gen-1.5 my latest project: Intuitive AI Academy We just wrote a new piece on Linear Attention!! https://intuitiveai.academy/ limited time code "BACK2SCHOOL" for 50% off yearly plan My Newsletter (weekly top research papers) https://mail.bycloud.ai/ My Patreon https://www.patreon.com/c/bycloud Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI This video is supported by the kind Patrons & YouTube Members: 🙏Spam Maj, Alex, Chris LeDoux, DX Research Group, Poof N'' Inu, Deagan,...'
resumo: 'The video discusses the advancements in robotics, particularly the emergence of in-context learning, which allows robots to generalize and perform new tasks based on contextual information, similar to language models. It highlights the challenges of applying this capability to robotics and showcases a significant demonstration of this technology by Generalist AI.'
tags: {}
fontes:
  - 'https://generalistai.com/blog/gen-1.5'
  - 'https://intuitiveai.academy/'
  - 'https://mail.bycloud.ai/'
  - 'https://www.patreon.com/c/bycloud'
  - 'https://scrimba.com/?via=bycloudAI'
  - 'https://discord.gg/NhJZGtH'
  - 'https://twitter.com/bycloudai'
  - 'https://www.patreon.com/bycloud'
  - 'https://twitter.com/pygm7'
  - 'https://x.com/miyashita_03'
  - 'https://ko-fi.com/bycloudai'
---

## Descrição

In-context learning for robots has been a long-anticipated capability, as it could allow robots to quickly adapt to new tasks without additional training, and Gen-1.5 might have just demonstrated that it is possible for robots at complex tasks.


Gen-1.5
[Blog] https://generalistai.com/blog/gen-1.5


my latest project: Intuitive AI Academy
We just wrote a new piece on Linear Attention!!
https://intuitiveai.academy/
limited time code "BACK2SCHOOL" for 50% off yearly plan

My Newsletter (weekly top research papers)
https://mail.bycloud.ai/

My Patreon
https://www.patreon.com/c/bycloud


Try out my new fav place to learn how to code https://scrimba.com/?via=bycloudAI

This video is supported by the kind Patrons & YouTube Members: 
🙏Spam Maj, Alex, Chris LeDoux, DX Research Group, Poof N' Inu, Deagan, Robert Zawiasa, Ryszard Warzocha, Tobe2d, Louis Muk, Akkusativ, Kevin Tai, Mark Buckler, NO U, Tony Jimenez, Ângelo Fonseca, jiye, Anushka, Asad Dhamani, Binnie Yiu, Calvin Yan, Clayton Ford, Diego Silva, Etrotta, Gonzalo Fidalgo, Handenon, Hector, Jake Disco very, Michael Brenner, Nilly K, OlegWock, Daddy Wen, Shuhong Chen, Sid_Cipher, Stefan Lorenz, Sup, tantan assawade, Thipok Tham, Thomas Di Martino, Thomas Lin, Richárd Nagyfi, Paperboy, mika, Leo, Berhane-Meskel, Kadhai Pesalam, mayssam, Bill Mangrum, nyaa, Toru Mon, Lame Plane, Matej Macak, Len Mo, saylikhapekar, ZyanSheep, THEVIERAOS, Ricardo Raphael Corona-Moreno, superchordate


[Discord] https://discord.gg/NhJZGtH
[Twitter] https://twitter.com/bycloudai
[Patreon] https://www.patreon.com/bycloud
[Business Inquiries] bycloud@smoothmedia.co
[Other Inquiries] bycloudai@gmail.com
[Profile & Banner Art] https://twitter.com/pygm7
[Video Editor] @Booga04 
Manim Animations created by https://x.com/miyashita_03
[Ko-fi] https://ko-fi.com/bycloudai
[Bitcoin (BTC)] 3JFMJQVGXNA2HJE5V9qCwLiqy6wHY9Vhdx
[Ethereum (ETH)] 0x3d784F55E0bE5f35c1566B2E014598C0f354f190
[Litecoin (LTC)] MGHnqALjyU2W6NuJSSW9fTWV4dcHfwHZd7
[Bitcoin Cash (BCH)] 1LkyGfzHxnSfqMF8tN7ZGDwUTyBB6vcii9
[Solana (SOL)] 6XyMCEdVhtxJQRjMKgUJaySL8cGoBPzzA2NPDMPfVkKN

## Transcrição

One of the biggest milestones for
foundation language models was not
simply that they became better at
chatting or capable of solving high
school level math problems. It was that
after enough pre-training, they started
become capable of generalizing to
entirely new tasks from context alone.
You give them examples of chat dialogue
in context and they can become a
chatbot. You give them some math
questions and then they can solve them
all without any fine-tuning. And if I'm
going to be honest, language is also the
perfect medium for this to happen.
Language is already an incredibly
compressed and informative symbolic
system. A few words can specify a goal,
describe an object, explain a procedure,
or provide an example of an entirely new
task. So, once language models become
sufficiently capable, it made sense that
they could start using the context
window itself as a kind of temporary
learning mechanism. And once this
behavior emerged in language models, the
next question became, "Can this behavior
also emerge in other modalities?" A few
years after the GPT-3 paper demonstrated
this in-context learning behavior,
image, video, and voice generation soon
followed with something similar. But,
none has been more anticipated than
seeing this same pivotal moment emerge
in robotics. Except for the fact that
robotics is a much harder problem. While
in the physical world, information is
not neatly packaged into discrete
tokens, a robot has to continuously
reason about vision, position, contact,
force, motion, and the consequences of
its own actions. Two attempts at exactly
the same task can unfold differently
because an object moved a centimeter, a
grasp slipped, or friction changed. And
especially the data needed would be a
lot more compared to language or video
generation. On top of that, the success
itself is much harder to reduce to
something as clean as predicting the
next token. So, I think most people,
myself included, expected this kind of
generalization to take much longer to
emerge in robotics. And then, on a
random Wednesday afternoon in August
2026, Generalist AI released a Gen 1.5.
In their demo, they genuinely displayed
some of the best-looking robotics
results I have ever seen, specifically
when it comes to generalization, aka
in-context learning. And before we dive
into it, if you're still unfamiliar with
the basics of LLMs like in-context
learning and want to learn in-depth
about how the architecture researchers
have designed led to such a fascinating
behavior, my platform
intuitive.ai.academy might just be the
perfect place for you to start. We cover
LLMs from the ground up, including the
basics of LLM architecture to advanced
topics like mixture of experts and
optimizations. Not only that, our main
focus is to help you understand the key
ideas intuitively, which means we don't
bombard you with crazy-looking maths and
focuses on making sure you understand
the higher-level logic fully. We also
just published a new chapter on breaking
down linear attention intuitively,
tackling one of the latest frontier
concepts that Kimi key three and queen
models are based on. So, if you're
working with LLMs at work, transitioning
into the field, or wanting to get into
LLM research, check it out using the
link down in the description and use our
limited-time code back to school for 50%
off a yearly membership. Anyways, the
big promise of robot foundation models
has always been that instead of training
every new behavior from scratch, you
could eventually just show a robot what
to do and have it copy the action. And
Gen 1.5 might be one of the first
milestones demonstrating the possibility
of that. Because after enough
pre-training on large amounts of
physical interaction data, the robotics
startup Generalist AI found that their
robot started developing something that
looks surprisingly similar to in-context
learning in LLMs. What does in-context
learning look like for robotics? Well,
you basically show the robot a
completely new task for just a few
seconds, put that demonstration into its
context window, and the robot can
immediately attempt it without changing
its weights at all. Their Gen 1.5 has
around 30 seconds of video memory
alongside other sensor, proprioceptive,
and language inputs. For context,
proprioceptive means the body's ability
to sense its own position, movement, and
force through internal receptors. So, a
demonstration or video prompt ranging
from 3 to 12 seconds can be inserted
directly into that context as a sensory
motor sequence. And that context window
will basically include what the robot
saw and what actions were taken all at
100 hertz. The model then continues from
there and tries to infer what physical
behavior it is supposed to reproduce.
And the researchers at Generalist AI
tested this across 10 different
manipulation tasks. Several of them were
replayed on their blog, and the only
instruction is a short physical
demonstration placed inside the context
window. And all the robot has to do is
to replicate the task. This includes
twist lid off a glass jar,
unzip pencil pouch,
brush cube into a bowl,
remove vacuum pad,
pour bolts into a cup,
and zip a zipper.
But after watching all these demos, you
might be like, "I thought robots could
already do this a long time ago." Well,
here's the catch. The training data does
not include any of these actions. These
actions were never explicitly trained
for, which means the robot model has to
replicate it on the fly. And this is
basically the beauty of in-context
learning, and it's magical, and it just
gets progressively better. Because when
you encounter an unexpected situation,
what do you think would happen? In this
putting marker in a cup example, after
the human demonstration, the robot
started copying the action, right? Uh
whoops.
It knocked the cup over. So, now, what
do you think the robot will do? Well, it
dropped the marker, which may not be
ideal, but it went to set the cup back
up straight, then put the marker in. How
cool is that? In their official trailer
at the start, I just love how happy the
researchers are in in background seeing
the robots imitate their actions
flawlessly. To be honest, I am pretty
happy looking at this, too. And what's
even better is that this generalizes
beyond humans demonstrating with
handheld robot claws. In one of the
context learning experiments, they
basically showed that the demonstration
can come from simulation. And the thing
is, Gen1.5 was pre-trained with zero
simulation data, no rendered simulation
footage, and no simulated physics. But
the researchers here take a
demonstration performed entirely inside
a simulator, put that simulator
trajectory into the context window as
the physical prompt, and then have the
real robot perform the task in the real
world. And most importantly, this is not
the usual sim-to-real transfer where you
train the policy inside a simulator and
then deploy that trained policy onto a
real robot. Gen1.5 was not trained on
that task performed in the simulator,
either. The simulation is literally just
the example in the context window. So,
somehow, the model looks at a simulated
robot performing an action and was like,
"Oh, that's close enough." and performed
the physical behavior that maps to its
own real-world body. And it still works
when they change things like which hand
is being used or the position and size
of the objects in the real scene. But
then, they pushed that even further,
because the real future use case of
robotics and context learning wouldn't
have a simulator for people to program
on the go nor a robot arms to
demonstrate an action, right? So, in
another experiment, the researchers
simply performed the task using their
actual hands directly in front of the
robot's cameras. No nothing, just a bare
hands. And in some cases, Gen1.5 can
watch that human demonstration and then
immediately reproduce a task using its
own robot hands, which is mind-blowing.
The impressive part about this, too, is
that a human hand and a robot gripper
physically cannot execute the exact same
motion, which means the model has to
extract something more abstract from the
demonstration, like what is being
accomplished, and then figure out how
that goal translates onto its own body.
This is probably the most impressive
result out of everything they have
shared in their release. It functions
kind of like next token prediction, but
it's instead next action prediction. One
motion and the robot instantly started
replicating the task. As for the last
in-context learning experiment, they
tested whether these physical prompts
can actually be composed together. So,
imagine instead of showing the robot a
one complete long task, you give it two
completely separate demonstrations. In
their example, the first demonstration
shows how to unzip a pencil pouch. The
second shows how to retrieve money from
it. These two demonstrations were
recorded independently and there's no
example showing the transition from one
task into another. They just simply
placed both demonstrations into Gemini
1.5's context window and have the robot
figure out the rest. And of course, it
can smoothly connect these two actions
together into one coherent movement.
However, even though the results are
really impressive to look at, the
success rate of the robots isn't that
great. With no gradient updates to the
model and no task-specific fine-tuning,
across all 10 tasks, it succeeds only
about 59% of the time. Some tasks that
perform better than 59%, but of course
that is the average. So, what if you do
actually fine-tune on these tasks? Would
the performance shoot up? And the answer
is yes. But what is even more
interesting is how little fine-tuning it
takes. Normally, adapting a robot policy
to a genuinely new task can require tens
of thousands of gradient updates,
sometimes even more. But with Gemini
1.5, Generalist took the same tasks,
collected just around 5 minutes of
demonstrations per task, which is
roughly 50 examples, and then fine-tuned
the model for only 10 gradient steps.
That alone increases the average success
rate from 59% to 83%. And on some tasks,
the jump is massive. For sweeping with a
brush, the one-shot model only gets
around 37% success, but after 10
gradient steps, it reaches 99%. Opening
a jar goes from 60% to to
and unzipping a pencil pouch goes from
55.5%
to 86%. According to Generalist, they
did not even heavily optimize this
adaptation procedure. They used hyper
parameters similar to pre-training and
did not perform a dedicated sweep
specifically to squeeze the best
possible performance out of these few
step experiments, which makes this feel
less like the model is learning a
completely new task from scratch and
more like you are just notching it
towards something it almost already
knows how to do. And the evidence
supports this idea, too. After gradients
steps, the model's weights have changed
by less than 0.15% on the held out
tasks. So, basically, nothing has
changed in the underlying model. This
fast adaptation did not randomly appear,
either. Generalist says that as Gen 1.5
continued pre-training for more than 8
months, they watched new task adaptation
go from requiring hundreds of gradient
steps to tens, to one, and eventually to
zero, which is basically in-context
learning. So, the huge pre-training data
was definitely the key to all this. And
another result of training on a large
amount of pre-training data is you would
also be capable of achieving physical
generalization. Because after adapting
Gen 1.5 to a task, the model does not
just become better at replaying the
exact demonstration. It can generalize
to new objects, new environments,
different grasps, different hands,
obstacles, and even completely different
physical strategies for reaching the
same goal. So, in one task-specific
demonstration, which uses one hand to
rotate the jar lid, during some
rollouts, Gen 1.5 would spontaneously
bring in its second hand and open a jar
using a different contact and motion
strategy. On top of that, a model
fine-tuned for 10 gradient steps on 5
minutes of data to twist off a lid jar
can generalize to cups and bottles it
has never seen before. So, even after
fine-tuning, it is not necessarily
locked into the exact behavior it was
shown. And this just shows the ability
to understand the goal while achieving
it in a different approach. But the best
physical generalization example is
probably this one. They fine-tuned Gen
1.5 on 5 minutes of demonstrations where
a brush is used to sweep a cube into a
ball. Then they take the brush away and
instead give the robot a range of
different random things like banana. But
the task is still the same, brush the
cube into the ball. In response, the
robot basically decides that the banana
is capable of being used as a brush and
uses it as a makeshift sweeping tool.
Then in another example, they give it a
dust pan and it does something much more
interesting. Instead of forcing the same
sweeping motion onto a completely
different tool, Gen 1.5 changes
strategies entirely. It scoops the cube
onto the dustpan, lifts it up, and dumps
it into the ball. According to the
researchers, neither the fine-tuning
data nor, to the best of their
knowledge, the pre-training data
contained a tool like a dustpan and were
being used this way for the task. So the
model creates a completely new contact
sequence without language telling it
what the dustpan is or how it should use
it. And there is one more weird emergent
behavior that I also really like. A
model that was only fine-tuned to put
one block into one ball will sometimes
go beyond the actual task and start
sorting multiple blocks by color or
category. The behavior was not what the
task-specific demonstrations were
teaching or what they were prompting. It
just seems to be pulling in some broader
organizing behavior from pre-training
and applying it. They call this a
generalized form of physical common
sense. There would also be emergent
improvisation behaviors due to the large
amount of physical knowledge it has. In
one experiment, the robot is
manipulating LEGO when a brick
accidentally gets stuck on the
fingertips of one gripper. And
obviously, this is not part of the task,
but instead of continuing the original
motion and failing, Gen 1.5 brings in
its other hand, removes the stuck LEGO,
and continues. This is absolutely
jaw-dropping. It realized something that
was not supposed to happen happened and
thought up how to address the problem on
the go, then go back to the original
task, all without any supervision. The
future is just so cool, guys. I am in
tears. Here is also another example
where the model was fine-tuned for only
one gradient step to put a block into a
ball. Then the researchers covered the
ball with a piece of paper. Of course,
there is no paper covering the ball in
those 5 minutes of task-specific data
and also not in the training data,
either. But the model understood that
the paper is on top of the ball. So, it
moves the paper out of the way, puts the
block into the ball, and sometimes even
puts the paper back on top afterward. On
the other hand, the researcher also
shared something really fascinating on X
that is not in their blog. So, what
would happen if you don't prompt the
model anything and just place an object
in front of the model? At the start, you
can see their prompt is just babbling
and the robot was handed a wallet. You
can just see how it's just playing
around with it, flipping it, and then
eventually closing the wallet. Kind of
just like fidgeting. But you can
definitely tell it has some sort of
object understanding of what it is being
handed. Keep in mind, the object wallet
was not shown in the pre-training data.
Then in the end, they just prompted to
take the money out of the pouch where
the wallet was not an item seen during
pre-training and it just gets right to
it. Absolutely fascinating to see. But
it definitely has seen something similar
to a wallet, like a money pouch, and
from the way of how they prompted it,
you can kind of tell that it was only
trained on pouch. So, yeah, the progress
of robotics may have been faster than we
think. And even though we have seen many
robots doing some extremely complicated
tasks, those are not yet truly
generalizable. But this Gen 1.5 model
might actually be the first big
milestone proving that robots are
capable of achieving in-context
learning, and the results are just so
captivating to look at. At this point,
maybe my channel will be pivoting into a
robotics channel in a few years. And if
you want to stay up-to-date with the
latest research papers, you should check
out my newsletter where I cover the
latest and juiciest papers weekly. So,
join the 20,000 people now to have the
papers delivered right into your inbox.
Thank you guys for watching. A big shout
out to Spam Madge, Chris Ladue, Deegan,
Robert Zaviasa, Marcelo Ferreira,
Proof-of-Any-New, DX Research Group,
Alex, Midwest Maker, and many others
that support me through Patreon or
YouTube. Follow me on Twitter if you
haven't, and I'll see you in the next
one.
