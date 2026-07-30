# Vault — o "cérebro" da Máquina de Conteúdo

Esta pasta é a **memória** do sistema: uma _vault_ ao estilo Obsidian, composta por
ficheiros Markdown (`.md`) com _frontmatter_ YAML. A aplicação Laravel lê e escreve
aqui directamente (ver `app/Services/Vault/VaultRepository.php`).

Em Docker, esta pasta é montada como volume (`./vault:/var/www/html/vault`), pelo que
o conhecimento **persiste** independentemente da imagem e pode ser aberto no Obsidian.

## Estrutura

```
vault/
├── monitorizacao/{youtube,instagram,tiktok,linkedin}/   snapshots de métricas
├── rascunhos/                                            peças geradas (2/3/4)
├── noticias/                                             relatórios do agregador
└── publicacoes/                                          peças publicadas
```

## Frontmatter-padrão

```yaml
---
titulo: 'Título da nota'
tipo: post|carrossel|vídeo|short|relatorio|snapshot
plataforma: youtube|instagram|tiktok|linkedin
estado: rascunho|agendado|arquivado
agendado_para: '2026-08-01'
data: '2026-07-20'
tags: [exemplo]
---
```

Os ficheiros de exemplo incluídos servem de demonstração e podem ser apagados.
