# Clips Animados — Estúdio de Clips Animados (design)

Data: 2026-07-21 · Branch: `worktree-animated-clips`

## 1. Objectivo

Transformar a página `Clips Animados` (actualmente um *empty state*) num estúdio
com **dois separadores**:

1. **Animação** — a partir de texto **ou** de uma locução (voiceover), gera um
   vídeo **totalmente animado**. Cada segundo da locução tem de ter animação.
2. **Vídeo + Animações** — a partir de um vídeo já pronto, adiciona animações
   **apenas** nos momentos que fazem sentido, sobrepostas ao vídeo original.

Tem de funcionar de ponta a ponta (render real de um MP4), não é protótipo.

## 2. Stack e decisões de arquitectura

- **Laravel + Livewire** — UI, orquestração, estado, filas.
- **Remotion** (subprojecto Node em `remotion/`) — render de pixels. Invocado
  pelo Laravel por CLI (`npx remotion render`) com um ficheiro de *props* JSON.
- **ffmpeg** — extracção de áudio dos vídeos e composição final (overlay).
- **OpenAI Whisper** (`whisper-1`) — transcrição com timestamps ao nível da
  palavra.
- **OpenAI GPT** (`gpt-4o`) — planeador de animações (o *AnimationPlanner*).
- **ElevenLabs** — TTS (gerar locução a partir de texto).

Requisitos de ambiente já verificados: Node 24, ffmpeg 6, PHP 8.4 presentes.
Chaves necessárias no `.env`: `OPENAI_API_KEY`, `ELEVENLABS_API_KEY`.

### Alternativas rejeitadas
- *Servidor de render HTTP separado* — excesso de infra para uma app de uma
  máquina; invocação por CLI encaixa no setup Docker/local existente.
- *"Animação" só com CSS/Livewire* — não exporta MP4; falha "tem de funcionar".

## 3. O "style md" — fonte única de verdade

Ficheiro **`vault/estilo-animacao.md`** (frontmatter YAML + prosa), lido tanto
pelo planeador GPT como pelas composições Remotion. Define:

- **Vocabulário fechado de primitivas de animação:** `fade`, `slide`,
  `scale` (zoom), `kinetic-text` (revelação palavra a palavra), `highlight`,
  `fleuron-draw`, `seal-stamp`, `underline-sweep`, `count-up`, `image-reveal`.
- **Tokens visuais Brand Machine:** paleta papiro/verde-azulado, tipografia
  (Cormorant Garamond, EB Garamond, JetBrains Mono), textura *foxing*,
  ornamentos — para os clips ficarem com a identidade da casa.
- **Regras de tempo:** duração mín./máx. por primitiva, *easing*, sobreposição.

O planeador é **restringido** a este vocabulário → a saída é sempre renderável.

## 4. Separador A — "Animação" (100% coberto)

```
texto ──(ElevenLabs TTS)──┐
                          ├─► audio.mp3 ──(Whisper)──► transcript.json
locução (upload) ─────────┘                                  │
                                                             ▼
                          AnimationPlanner (lê estilo-animacao.md, modo=dense)
                                     │ plan.json: [{start,end,primitive,text,params}]
                                     ▼ validado: cobre 100% da duração, sem lacunas
                          Remotion render (áudio + fundo animado) ──► clip.mp4
```

- **Entrada:** texto (gera locução via TTS) **ou** upload de ficheiro de áudio.
- **Validação de cobertura:** a linha temporal é verificada; qualquer lacuna é
  preenchida automaticamente com uma primitiva ambiente e re-verificada até
  cobrir 100% da duração.
- **Saída:** `clip.mp4` (fundo animado + áudio embutido).

## 5. Separador B — "Vídeo + Animações" (overlay esparso)

```
vídeo (upload) ──(ffmpeg extrai)──► audio.wav ──(Whisper)──► transcript.json
                                                                  │
                                                                  ▼
                              AnimationPlanner (modo=sparse — só momentos-chave)
                                          │ plan.json (lacunas são ESPERADAS)
                                          ▼
                          Remotion render, fundo TRANSPARENTE ──► overlay.mov (alpha)
                                          │
                                          ▼
                          ffmpeg sobrepõe overlay.mov ao vídeo original ──► final.mp4
```

Mesmo planeador e mesmo style md; muda a *flag* de modo (`dense` vs `sparse`),
o render é com fundo transparente e a composição final é feita pelo ffmpeg.

## 6. Modelo de dados e processamento assíncrono

Migração **`clip_projects`**:

| coluna | tipo | notas |
|---|---|---|
| `id` | id | |
| `type` | string | `animation` \| `overlay` |
| `status` | string | `draft`→`transcribing`→`planning`→`rendering`→`done`\|`failed` |
| `input_kind` | string | `text` \| `audio` \| `video` |
| `source_text` | text nullable | texto para TTS |
| `source_path` | string nullable | caminho do áudio/vídeo carregado |
| `audio_path` | string nullable | áudio derivado (TTS ou extraído) |
| `transcript` | json nullable | resultado do Whisper |
| `plan` | json nullable | plano de animações |
| `output_path` | string nullable | MP4 final |
| `error` | text nullable | mensagem em caso de falha |
| `meta` | json nullable | duração, dimensões, voz, etc. |
| timestamps | | |

**Jobs em fila** (`QUEUE_CONNECTION=database` já configurado), encadeados:
`TranscribeJob → PlanAnimationsJob → RenderJob` (o separador B acrescenta
`ComposeOverlayJob`). A UI Livewire faz `wire:poll` nos projectos activos para
mostrar o estado em tempo real e disponibiliza pré-visualização + download.

## 7. Camada de serviços (padrão *driver*, como no repo)

Cada serviço atrás de uma interface, com implementação real e `fake` (testável,
igual à convenção `fake`/`api` existente):

- `TranscriptionService` (OpenAI Whisper)
- `VoiceoverService` (ElevenLabs)
- `AnimationPlanner` (OpenAI GPT) — recebe transcript + modo + style md → plano
- `PlanValidator` — garante cobertura (dense) / coerência (sparse)
- `RemotionRenderer` — wrapper da CLI Remotion
- `VideoCompositor` — ffmpeg (extracção + overlay)

Selecção real vs fake por `CLIPS_DRIVER` no `.env` (default `fake` para testes).

## 8. Localização do código

```
app/Livewire/ClipsAnimados.php          # wizard de 2 separadores
app/Services/Clips/*                     # serviços + interfaces + fakes
app/Jobs/Clips/*                         # jobs em fila
app/Models/ClipProject.php
database/migrations/*_create_clip_projects_table.php
resources/views/livewire/clips-animados.blade.php
remotion/                                # subprojecto Node (composições)
vault/estilo-animacao.md                 # style md
config/contentmachine.php                # secção 'clips' (driver, voz, dimensões)
```

## 9. Formatos e predefinições

- Dimensões de saída: **1080×1920 vertical** (formato shorts/reels).
- Voz TTS: uma voz ElevenLabs por defeito, configurável em Definições depois.
- Todos os textos de interface em Português europeu, dentro do sistema Brand Machine
  (page-header, painéis, fleurões, cotas mono).

## 10. Testes

- Unit: `PlanValidator` (cobertura dense, ausência de lacunas; sparse válido),
  mapeamento transcript→plano com `fake` planner.
- Feature: fluxo Livewire com drivers `fake` (texto→estado `done` sem chaves
  reais), validação de uploads.
- Render real (Remotion/ffmpeg) verificado manualmente com chaves reais.

## 11. Fora de âmbito (YAGNI)

- Edição manual do plano de animações na UI (v2).
- Múltiplas vozes / clonagem de voz.
- Legendas incrustadas configuráveis (o kinetic-text já mostra texto).
- Fila distribuída / render em nuvem.
