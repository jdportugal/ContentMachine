---
titulo: "Stop Chunking Like It's 2022 — Yuval Belfer, AI21 Labs"
tipo: item_agregado
plataforma: youtube
canal: 'AI Engineer'
data: '2026-09-16'
url: 'https://www.youtube.com/watch?v=r9OwPx_HoV0'
thumbnail: media/monitoring/youtube/r9owpx-hov0.jpg
descricao: "Ask a corpus of Seinfeld transcripts for the name of Jerry's favorite church and a 100 token chunk returns it at rank one, while every larger window buries it below rank fifty. Ask the same corpus who Jerry calls his nemesis and pure evil and the small chunks fail completely, because the answer is spread across a scene rather than sitting in a sentence. Same data, same index, opposite requirements. Yuval Belfer uses that pair to make a claim most retrieval teams have quietly assumed away: there is no correct chunk size, because the correct size is a property of the query, and you pick it at indexing time when you do not yet have any queries. That is the trap in one sentence. At indexing you control the window and know nothing about the questions. At retrieval you have the question and the..."
resumo: ''
tags: {}
fontes:
  - 'https://x.com/yuvalinthedeep'
---

## Descrição

Ask a corpus of Seinfeld transcripts for the name of Jerry's favorite church and a 100 token chunk returns it at rank one, while every larger window buries it below rank fifty. Ask the same corpus who Jerry calls his nemesis and pure evil and the small chunks fail completely, because the answer is spread across a scene rather than sitting in a sentence. Same data, same index, opposite requirements. Yuval Belfer uses that pair to make a claim most retrieval teams have quietly assumed away: there is no correct chunk size, because the correct size is a property of the query, and you pick it at indexing time when you do not yet have any queries. That is the trap in one sentence. At indexing you control the window and know nothing about the questions. At retrieval you have the question and the window is already frozen.

To size the cost, his team duplicated several datasets at six different chunk sizes and ran an oracle experiment, choosing per query the size that happened to work best. The gap between that oracle and any single fixed choice ran 20 to 40 percent of recall, which is what an arbitrary 512 has been quietly costing. Their fix refuses the premise rather than tuning it. Index the corpus at every window size, query all of them, and because chunks of different sizes cannot be compared, return whole documents so the rankings become commensurable, then merge them by reciprocal rank fusion. It is a short script rather than a model. The honest accounting is at the end: two to five times the memory, and almost no added latency.

Speaker info:
https://x.com/yuvalinthedeep
  / yuval-belfer  

Timestamps:
0:00 - A talk about nothing, and why chunking
1:43 - Indexing is boring, retrieval tuning is fun
3:23 - A World Cup directory that cannot answer the query
4:17 - Chunking as lossy compression
6:13 - Six copies of the same dataset
7:07 - Two Seinfeld questions, opposite answers
8:12 - The oracle experiment
9:59 - An information problem at both ends
11:02 - Multiscale indexing and rank fusion
13:50 - Results across four datasets
15:24 - What it costs in memory

## Transcrição

_Sem transcrição disponível._
