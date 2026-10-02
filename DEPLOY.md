# Deployment Guide — SLO WSDC Ranking

Shared hosting deployment: PHP + FTP upload + cron. No SSH or Docker required on the server.

---

## Repository structure

```
slo-wsdc-db/
+-- data/                        ? NOT committed; created & written on the server
¦   +-- raw/                     ? per-dancer raw API JSON (written by scraper)
¦   +-- dancers.json             ? normalised index (written by scraper)
¦   +-- last_updated.txt         ? timestamp of last successful scrape
¦   +-- scraper_live.log         ? live output buffer (written by web UI)
+-- scraper/fetch.php            ? CLI scraper
+-- slo_wsdc_ids.csv             ? list of Slovenian dancer WSDC IDs (editable via /admin)
+-- web/                         ? Laravel application (vendor/ is committed)
¦   +-- app/
¦   +-- vendor/                  ? committed — no composer available on shared hosting; build with `composer install --no-dev --optimize-autoloader`
¦   +-- .env.production.example  ? copy to .env on the server and fill in values
¦   +-- ...
+-- public_html/                 ? maps to the server webroot
¦   +-- index.php                ? entry point; paths point to ../web/
¦   +-- .htaccess
+-- docker-compose.yml           ? local dev only
+-- docker/                      ? local dev only
```

---

## FTP upload map

Upload the repository so it sits in the server's home directory:

| Local path         | Server path          |
|--------------------|----------------------|
| `web/`             | `~/web/`             |
| `scraper/`         | `~/scraper/`         |
| `public_html/`     | `~/public_html/`     |
| `slo_wsdc_ids.csv` | `~/slo_wsdc_ids.csv` |

> Do **not** upload `docker-compose.yml`, `docker/`, `.git/`, or anything in `data/`.

---

## First-time server setup

Do these steps once after the initial upload.

### 1. Environment file

- Copy `web/.env.production.example` ? `web/.env`
- Fill in `APP_KEY` — generate it locally:
  ```
  php web/artisan key:generate --show
  ```
  Paste the output as the value.
- Set `APP_URL` to your domain, e.g. `https://yourdomain.com`
- Set `SCRAPER_SECRET` to a strong password — required to log in to `/admin`

### 2. Create the data directory

Create these on the server (via file manager or FTP — mkdir):

```
~/data/
~/data/raw/
```

### 3. Fix permissions

The PHP process must be able to **read and write** the following:

| Path                      | Why                                               |
|---------------------------|---------------------------------------------------|
| `~/data/`                 | Scraper writes `dancers.json`, `last_updated.txt` |
| `~/data/raw/`             | Scraper writes per-dancer JSON files              |
| `~/data/scraper_live.log` | Web UI writes live scraper output (auto-created)  |
| `~/slo_wsdc_ids.csv`      | `/admin` page can add/remove dancers              |
| `~/web/storage/`          | Laravel sessions, cache, logs                     |
| `~/web/bootstrap/cache/`  | Laravel compiled cache                            |

Set via file manager or SSH:
```bash
chmod 775 ~/data ~/data/raw ~/web/storage ~/web/bootstrap/cache
chmod 664 ~/slo_wsdc_ids.csv
# If data/scraper_live.log already exists:
chmod 664 ~/data/scraper_live.log
```

### 4. Seed initial data

Run the scraper once to populate `data/`:
```
php ~/scraper/fetch.php
```

---

## Admin page

The web UI at `/admin` lets you:
- **Trigger a scraper run** and watch live output
- **Add or remove dancers** from `slo_wsdc_ids.csv`

Login uses the `SCRAPER_SECRET` value from `web/.env`.
After adding/removing dancers, trigger a scraper run from the same page to refresh the rankings.

---

## Cron job

Add via the hosting control panel (Cron Jobs section) to keep data fresh automatically:

```
0 3 * * *   php ~/scraper/fetch.php >> ~/data/scraper.log 2>&1
```

Runs daily at 03:00. Output appends to `~/data/scraper.log`.

---

## Subsequent deploys

FTP-overwrite changed files. `web/` changes most often; `public_html/` and `scraper/` rarely need updates.

After uploading changes that affect routes, config, or views, clear Laravel caches. If the host provides a PHP CLI:
```
php ~/web/artisan config:clear
php ~/web/artisan route:clear
php ~/web/artisan view:clear
```

If there is no CLI access, delete via FTP/file manager:
- `~/web/bootstrap/cache/*.php`
- `~/web/storage/framework/views/*.php`
- `~/web/storage/framework/cache/data/`
