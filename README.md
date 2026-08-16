# Brand Machine

> by **AI Content Machines**

Aplicação Laravel que centraliza a produção de conteúdo: monitorização de redes
sociais, geradores de peças, rascunhos com agendamento e um agregador de notícias.
Usa uma **vault Obsidian** (pasta de ficheiros Markdown) como memória — o "cérebro"
do sistema — e está **dockerizada** para ser distribuível.

Interface toda em **português europeu**, com o sistema de identidade **Brand Machine** na
variante escura **Nocturna** (ver [`docs/design-nocturna.md`](docs/design-nocturna.md)).

## Funcionalidades

| # | Secção | Estado |
|---|--------|--------|
| 0 | **Painel** — vista geral de tudo | Funcional (dados simulados + vault) |
| 1 | **Monitorização** — YouTube, Instagram, TikTok, LinkedIn; ênfase no último conteúdo de cada género e nos melhores desempenhos | Funcional (driver `fake`) |
| 2 | **Gerador de Clips** | Espaço reservado |
| 3 | **Clips Animados** | Espaço reservado |
| 4 | **Publicações** — posts de página única e carrosséis | Funcional (grava rascunhos no vault) |
| 5 | **Rascunhos e Agendamento** — reúne tudo de 2/3/4 | Funcional (lê/agenda no vault) |
| 6 | **Agregador de Notícias** — YouTube/Reddit/Twitter/TikTok → relatório | Funcional (driver `fake`) |

> Nesta fase **não há integrações reais de API**. Cada serviço tem um driver `fake`
> (dados simulados, sem chaves) e um driver `api` pronto a ligar (Apify / TubeLab /
> Gemini), seleccionável por configuração.

## Arquitectura

- **Laravel 13 + Livewire + Tailwind v4** (design escuro à medida).
- **Camada de domínio** em `app/Services/` com contratos + drivers `fake`/`api`:
  - `Vault/` — leitura/escrita da vault Obsidian (`spatie/yaml-front-matter` + `league/commonmark`).
  - `Monitoring/`, `News/` — recolha de métricas/notícias por driver.
  - `Scoring/` — índice de desempenho ponderado (padrão _head-of-content_).
- **Base de dados** (Postgres/SQLite) guarda apenas metadados; o **conteúdo vive na vault**.

## Correr com Docker (recomendado)

```bash
cp .env.example .env
# Em Docker, use a base de dados/Redis dos contentores:
#   DB_CONNECTION=pgsql · SESSION_DRIVER=redis · QUEUE_CONNECTION=redis · CACHE_STORE=redis
docker compose up --build
```

Aceda a **http://localhost:8080**. Serviços: `web` (Nginx), `app` (PHP-FPM),
`queue`, `scheduler`, `postgres`, `redis`. A vault é montada de `./vault`.

> Ao reconstruir com novos assets, force a recriação do volume `public`:
> `docker compose up --build --force-recreate` (ou `docker compose down -v` antes).

## Correr localmente (sem Docker)

```bash
cp .env.example .env
# manter DB_CONNECTION=sqlite, SESSION/CACHE/QUEUE em database/sync
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

## Testes

```bash
php artisan test
```

Cobrem o _round-trip_ da vault (escrita/leitura de frontmatter), a resposta 200 de
todas as secções e o fluxo de rascunhos (criar peça → vault → agendar).

## Ligar os drivers reais (mais tarde)

1. Preencha as chaves no `.env` (`APIFY_TOKEN`, `TUBELAB_TOKEN`, `GEMINI_API_KEY`, …).
2. Mude `MONITORING_DRIVER=api` e/ou `NEWS_DRIVER=api`.
3. Complete o mapeamento das respostas em `app/Services/Monitoring/ApiMonitoringDriver.php`
   e `app/Services/News/ApiNewsDriver.php` (os pontos de integração estão assinalados).
