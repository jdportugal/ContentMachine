# Rascunhos «pronto» + Vídeo longo → brief + arrastar-e-largar vídeos

**Data:** 2026-07-23
**Estado:** em revisão

## Objetivo

Três mudanças ligadas ao fluxo de produção dos Clips:

1. **Rascunhos** deixa de listar tudo o que está no vault e passa a ser uma
   **fila unificada de conteúdo pronto a agendar** — publicações marcadas como
   prontas, shorts (clips) já renderizados e clips animados concluídos.
2. Ao carregar um **vídeo longo** nos Clips, além de gerar shorts passa a ser
   possível **gerar texto que alimenta o gerador de publicações** (semeia o
   brief da oficina a partir da transcrição).
3. **Arrastar e largar** vídeos longos diretamente no gerador de clips
   (upload até 2 GB), a complementar o campo caminho/URL.

## Feature 1 — Rascunhos: fila unificada de «prontos»

### Fontes (3), normalizadas na renderização

Abordagem escolhida: a `Rascunhos::render()` lê as três fontes e mapeia cada
item para uma forma comum. Sem interface `Publishable`/adaptadores (excesso de
abstração para 3 fontes) e sem copiar itens para o vault (duplicaria estado).

| Fonte | Armazenamento | «Pronto» = | Agendar via |
|---|---|---|---|
| Publicações | vault `rascunhos/` | `estado: pronto` *(novo)* | frontmatter `estado: agendado` + `agendado_para` *(existente)* |
| Clips / shorts | vault `clips/` (`ShortsPipeline::CLIPES`) | `estado: pronto` *(já definido quando `final.mp4` é gerado)* | mesmo padrão de frontmatter |
| Clips animados | BD `clip_projects` | `status: done` *(existente)* | nova coluna nullable `scheduled_for` |

### Forma comum (DTO leve — array associativo)

Cada item é normalizado para:

```
key         string   chave única p/ wire:key (ex.: "post:slug", "db:12")
source      string   'post' | 'clip' | 'animado'
kind        string   rótulo de tipo p/ badge (ex.: "Carrossel", "Short", "Animação")
title       string
cover       ?string  caminho de imagem/thumbnail, se houver
excerpt     string   pré-visualização curta
ref         string   path do vault (post/clip) OU id (animado), p/ as ações
scheduled   ?string  data ISO de agendamento, ou null
```

### Componente `Rascunhos`

- `render()`:
  - posts prontos: `vault->all('rascunhos')->filter(estado ∈ {pronto, agendado})`
  - clips prontos: `vault->all('clips')->filter(estado ∈ {pronto, agendado})`
  - animados concluídos: `ClipProject::where('status','done')`
  - mapeia todos para a forma comum, ordena por data (recentes primeiro),
    aplica o filtro atual.
- Filtros **Todos / Prontos / Agendados** — contagens somadas nas 3 fontes.
  «Agendado» = `scheduled` não-nulo; «Pronto» = pronto e não agendado.
- Ações passam a receber `source` + `ref` e ramificam:
  - `agendar(source, ref, data)`:
    - vault (`post`/`clip`) → `updateFrontmatter(ref, ['estado'=>'agendado','agendado_para'=>data])`
    - `animado` → `ClipProject::find(ref)->update(['scheduled_for'=>data])`
  - `desagendar(source, ref)`:
    - vault → `estado: pronto`, `agendado_para: null`
    - animado → `scheduled_for: null`
  - `remover(source, ref)`:
    - vault → `vault->delete(ref)`
    - animado → `ClipProject::find(ref)->delete()`

### Marcar uma publicação como pronta

Na **index de Publicações** (`livewire/publicacoes/index`), cada cartão de post
composto (`origem === 'publicacoes/oficina'`) ganha um botão-alternância:

- `estado === 'pronto'` → mostra «pronto ✓», botão «Reabrir» → volta a `rascunho`
- caso contrário → botão «Marcar pronto» → `estado: pronto`

Novo método em `Publicacoes` (index): `alternarPronto(string $path)`.

Consequência intencional: posts em `rascunho` (a trabalhar) deixam de aparecer
em Rascunhos. Clips que já estão `pronto` passam a aparecer (correto).

## Feature 2 — Vídeo longo → semear o brief da oficina

### Clips (detalhe do vídeo longo)

Novo botão **«Gerar publicação»**, ativo quando a fonte está transcrita
(`$temTranscricao`). Método `gerarPublicacao(string $fontePath)` em `Clips`:

1. Lê os segmentos via `ShortsPipeline::transcricao($fonte)` e junta os `text`
   num só bloco de texto simples.
2. Trunca a um limite razoável (**~6000 caracteres** — `ponytail:` corte
   simples, subir se a IA precisar de mais contexto).
3. `session(['oficina_brief' => $texto])`.
4. `return redirect()->route('publicacoes')` com um aviso de sessão:
   «Texto do vídeo carregado — escolha o formato».

### Oficina (`mount`)

No fim de `mount()`, se **não** há `nota` na query e existe
`session('oficina_brief')`:

- `$this->brief = session('oficina_brief')`
- `session()->forget('oficina_brief')`

A partir daqui o pipeline de redação IA existente (`redigirComIa`) trata do
resto — sem código de IA novo.

O aviso de sessão é mostrado na index de Publicações (banner simples).

## Feature 3 — Arrastar e largar vídeos longos no gerador de clips

Complementa (não substitui) o campo caminho/URL do formulário «Novo vídeo
longo». Cap de upload: **2 GB**.

### Componente `Clips`

- `use WithFileUploads;` + `public $novoVideo = null;`.
- `adicionarFonte()` passa a aceitar upload OU caminho/URL:
  - validação: `novoVideo` **ou** `novaFonte` obrigatório (um dos dois);
    `novoVideo` → `file|mimetypes:video/mp4,video/quicktime|max:2097152` (2 GB em KB).
  - se `novoVideo` presente:
    `$ref = Storage::disk('local')->path($this->novoVideo->store('clips/uploads'))`
    (caminho **absoluto** — `LocalVideoEngine::resolveSource` faz `is_file($ref)`).
    Título por defeito = nome original do ficheiro se `novaFonteTitulo` vazio.
  - senão: `$ref = trim($this->novaFonte)` (comportamento atual).
  - `reset('novaFonte','novaFonteTitulo','novoVideo')` no fim.

### Vista (dropzone Alpine + `$wire.upload`)

Zona de largar por cima do campo caminho/URL:

```
<div x-data="{ over:false }"
     x-on:dragover.prevent="over=true"
     x-on:dragleave.prevent="over=false"
     x-on:drop.prevent="over=false; if ($event.dataTransfer.files.length)
        $wire.upload('novoVideo', $event.dataTransfer.files[0])"
     :class="over ? 'border-teal' : 'border-ink-soft/25'"
     class="border border-dashed rounded-sm p-6 text-center ...">
   Arrasta o vídeo para aqui, ou <label>escolhe<input type="file"
      wire:model="novoVideo" accept="video/mp4,video/quicktime" class="hidden"></label>
</div>
<div wire:loading wire:target="novoVideo">a carregar…</div>  {{-- barra de progresso Livewire --}}
@error('novoVideo') ... @enderror
```

Reusa o `window.CMLoader.busy()` no submit, como os outros botões pesados.

### Limites de upload (2 GB) — todas as camadas

- **PHP (Docker):** novo `docker/php/uploads.ini` com
  `upload_max_filesize=2G`, `post_max_size=2G`, `memory_limit=512M`,
  `max_execution_time=0`, `max_input_time=0`; copiado no Dockerfile para
  `/usr/local/etc/php/conf.d/uploads.ini`.
- **Nginx (Docker):** `docker/nginx/default.conf` → `client_max_body_size 2G`.
- **Livewire:** `config/livewire.php` → `temporary_file_upload.rules`
  `max:2097152`.
- **Dev local (`php artisan serve`):** o `php.ini` do PHP CLI do utilizador
  também precisa de `upload_max_filesize`/`post_max_size` elevados —
  `ponytail:` documentar no spec; o servidor embutido usa o php.ini do sistema.

## Migração / dados

- Uma migração: adicionar `clip_projects.scheduled_for` (`date` nullable).
- Sem backfill. Posts antigos em `rascunho` ficam simplesmente ocultos de
  Rascunhos até serem marcados prontos.

## Verificação (teste de feature)

- Post `pronto` aparece em Rascunhos; post `rascunho` não.
- `alternarPronto` alterna `rascunho` ⇄ `pronto`.
- Agendar um clip (vault) grava `estado: agendado` + `agendado_para`.
- Agendar um clip animado (BD) grava `scheduled_for`; desagendar limpa-o.
- `gerarPublicacao` põe o texto da transcrição em `session('oficina_brief')`;
  a `Oficina::mount` pré-preenche `$this->brief` e limpa a sessão.
- `adicionarFonte` com `novoVideo` carregado grava a fonte com o caminho
  absoluto do ficheiro; com só `novaFonte` mantém o comportamento atual; sem
  nenhum dos dois → erro de validação.

## Fora de âmbito

- Publicação real nas redes (continua a ser só agendamento no vault/BD).
- Alterar o formato de um post depois de escolhido (a oficina fixa o tipo pela
  rota, como hoje).
