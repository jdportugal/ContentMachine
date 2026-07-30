# PT → EN translation glossary (single source of truth)

Data (NOT translated): vault/noticias/**, vault/clips/**, vault/*/topicos.md content,
generated markdown notes. Only folder NAMES and app-config markdown change.

## Routes (url · name)
- `/` painel → `/` dashboard
- `/monitorizacao` monitorizacao → `/monitoring` monitoring
- `/clips` clips → `/clips` clips (keep)
- `/clips-animados` clips-animados → `/animated-clips` animated-clips
- `/ativos` ativos → `/assets` assets
- `/publicacoes` publicacoes → `/posts` posts
- `/publicacoes/{tipo}` publicacoes.oficina → `/posts/{type}` posts.workshop
- `/rascunhos` rascunhos → `/drafts` drafts
- `/noticias` noticias → `/news` news
- `/design-system` design-system → keep
- `/definicoes` definicoes → `/settings` settings
- `/clips/musica/{name}` clips.musica → `/clips/music/{name}` clips.music

## Livewire classes (App\Livewire)
Painel→Dashboard · Monitorizacao→Monitoring · ClipsAnimados→AnimatedClips ·
Ativos→Assets · Publicacoes\Publicacoes→Posts\Posts · Publicacoes\Oficina→Posts\Workshop ·
Rascunhos→Drafts · Noticias→News · Definicoes→Settings · Clips→keep · DesignSystem→keep

## View files (resources/views/livewire)
painel→dashboard · monitorizacao→monitoring · clips-animados→animated-clips ·
ativos→assets · publicacoes/→posts/ · rascunhos→drafts · noticias→news · definicoes→settings

## Jobs (App\Jobs)
GerarRelatorioJob→GenerateReportJob · GerarImagensJob→GenerateImagesJob ·
RegenerarCartaoJob→RegenerateCardJob · PlanearPublicacaoJob→PlanPostJob ·
AgregarConteudoJob→AggregateContentJob

## Service namespaces (App\Services)
Publicacoes→Posts (PublicacaoPlanner→PostPlanner, PublicacaoKinds→PostKinds).
Aggregation, Shorts, Monitoring, News, Vault, Scoring, Clips, Settings, DesignSystem, Support → keep

## Config keys (config/contentmachine.php)
plataformas→platforms · limite→limit · sem_metricas→without_metrics ·
plataformas_meta→platforms_meta · cor→color · glifo→glyph · fontes→sources ·
limite_por_canal→limit_per_channel · gerar_resumos→generate_summaries ·
proporcoes→ratios · proporcao→ratio · tipos→types · cartoes→cards ·
descricao→description · formato→format · plataforma_padrao→default_platform ·
plano_prompt→plan_prompt · gabarito→template · pesos→weights ·
comentarios→comments · partilhas→shares · guardados→saves
vault.folders keys+values: monitorizacao→monitoring · rascunhos→drafts ·
noticias→news · publicacoes→posts · clips→keep

## Publicacao type keys (config publicacoes.tipos + route {type})
post→post · citacao→quote · dica→tip · carrossel→carousel · lista→list · resumo-semana→week-summary
gabarito values: quadrado→square · citacao→quote · dica→tip · capa-conteudo→cover-content · lista→list

## Events
'erro'→'error' (toast type + checks) · 'abrir-lightbox'→'open-lightbox' ·
'ok', 'toast', 'loader-show', 'loader-hide' → keep

## Vault app-config files (renamed on disk)
vault/estilo-animacao.md→vault/animation-style.md (config clips.style_md) ·
vault/definicoes/definicoes.md→vault/settings/settings.md · vault/design-system.md→keep

## UI labels (nav + headings)
Painel→Dashboard · Vista geral→Overview · Monitorização→Monitoring · Redes sociais→Social networks ·
Gerador de Clips→Clip Generator · Vídeo→Video · Clips Animados→Animated Clips · Animação→Animation ·
Ativos→Assets · Média·Música→Media·Music · Publicações→Posts · Rascunhos→Drafts · Agendamento→Scheduling ·
Notícias→News · Agregador→Aggregator · Sistema de Design→Design System · Marca do conteúdo→Content brand ·
Definições→Settings · Variáveis→Variables · Máquina de Conteúdo→Content Machine · Cérebro→Brain

## Common words
guardar→save · gerar→generate · criar→create · apagar→delete · editar→edit · novo/nova→new ·
tópicos→topics · relatório→report · peça→piece · cartão→card · legenda→caption/subtitle ·
música→music · anexos→attachments · imagem→image · pré-visualização→preview · agora→now ·
oficina→workshop · fonte→source · desempenho→performance · pronto/pronta→ready · a gerar→generating
