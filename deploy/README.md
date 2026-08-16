# Deploying ContentMachine

One image runs the whole app (web + queue worker + scheduler, plus Node/Remotion +
headless Chrome, ffmpeg, yt-dlp). It's self-contained — SQLite + file/db queue, no
external Postgres/Redis — and serves on **port 8080**.

## 1. Publish the image (automatic)

The dedicated **`production`** branch is the live line — merge into it to release.
A push to `production` runs `.github/workflows/publish.yml`, which builds for
**linux/amd64** (the cloud target) and pushes to GHCR:

```
ghcr.io/jdportugal/contentmachine:latest
```

`GITHUB_TOKEN` is automatic — no secrets to set.

**Releasing:** develop on feature branches; when ready, merge into `production`
(`git checkout production && git merge <branch> && git push`). That build becomes
the new `:latest`; `docker compose pull && up -d` on the host picks it up.

## 2. Make the package public (once)

github.com/jdportugal?tab=packages → **contentmachine** → Package settings →
Change visibility → **Public**. Now anyone can `docker pull` it without auth.

## 3. Deploy on a DigitalOcean Droplet (easiest)

**Zero-SSH, self-deploying.** With the image public, you never touch a terminal:

1. **Create → Droplet** → **Ubuntu 24.04**, **≥ 4 GB RAM** (renders spawn Chrome).
2. Expand **Advanced Options → Add Initialization scripts (user data)** and paste
   the **entire contents of `install.sh`**.
3. Add your SSH key, **Create**. Wait ~2–3 min (Docker install + image pull + cert).
4. Open **`https://<droplet-ip>.sslip.io`** — done.

Prefer SSH instead? `ssh root@<ip>`, get the script onto the box (scp it, or paste
it into a file), then `bash install.sh`. Same result.

Data persists on named volumes (`storage`, `vault`, `db`); the container restarts
on reboot.

**Local:**

```
docker run -d -p 8080:8080 \
  -v cm_storage:/app/storage -v cm_vault:/app/vault -v cm_db:/app/database \
  ghcr.io/jdportugal/contentmachine:latest
```

→ `http://localhost:8080`. (Or `docker compose up` with the root `docker-compose.yml`.)

## 4. Host the installer in a PUBLIC repo (keeps source private)

The source repo is private, so `raw.githubusercontent.com` 404s. Create a small
**public** repo `ContentMachine-deploy`, copy `install.sh` into it as
`install.sh`, and use its raw URL in step 3. The script is self-contained (writes
its own compose + Caddyfile, pulls the public image) — nothing else is needed.

## Configuration

The app boots in the offline **`fake`** driver (no keys, no external calls). For real
generation, add keys to `.env` (in the deploy dir) and `docker compose up -d`:

```
CLIPS_DRIVER=api
OPENAI_API_KEY=…
ELEVENLABS_API_KEY=…   # voiceover + SFX sound generation
ANTHROPIC_API_KEY=…    # clip planner / effect generation
KIE_API_KEY=…          # image generation
```

`APP_KEY` is generated once and persisted on the `storage` volume — nothing to set.

## Gotchas handled here

| Gotcha | Where it's handled |
|--------|--------------------|
| CRLF breaks shell scripts | `.gitattributes` (`*.sh text eol=lf`) |
| Mac arm64 vs cloud amd64 | build `linux/amd64` (the cloud target) in `publish.yml`; Mac runs it via emulation |
| PaaS expects port 8080 | image serves on 8080 |
| Behind a proxy → http:// links (mixed content) | `bootstrap/app.php` `trustProxies(at: '*')` |
| Long renders retried mid-flight | worker `--timeout=1700` < queue `retry_after=1800` |
| Chromium OOM | size the Droplet ≥ 4 GB (renders spawn headless Chrome) |
| No persistent disk | use a Droplet + volumes (not a disk-less PaaS) |
| Private repo → installer 404s | host `install.sh` in a public deploy repo (step 4) |
| `latest` not re-pulling | `docker compose pull` re-resolves it (or pin the `:<sha>` tag) |

**The big lesson:** the Dockerfile is the easy 20%. The other 80% is environment
mismatches — arch, ports, proxy scheme, persistence, memory. This checklist is
already wired in.
